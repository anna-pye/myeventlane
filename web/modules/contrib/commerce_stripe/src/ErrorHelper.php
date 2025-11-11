<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\AuthenticationException;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException as StripeAuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Exception\RateLimitException;

/**
 * Translates Stripe exceptions and errors into Commerce exceptions.
 */
class ErrorHelper {

  /**
   * Translates Stripe exceptions into Commerce exceptions.
   *
   * @param \Stripe\Exception\ApiErrorException $exception
   *   The Stripe exception.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|\Drupal\commerce_payment\Entity\PaymentMethodInterface|null $payment
   *   The payment or payment method responsible for the exception.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   The Commerce exception.
   */
  public static function handleException(ApiErrorException $exception, PaymentInterface|PaymentMethodInterface|null $payment = NULL): void {
    $message = "Stripe error '" . $exception->getStripeCode() . "': " . $exception->getMessage();
    if ($exception instanceof CardException) {
      \Drupal::logger('commerce_stripe')->warning($message . ' ' . $exception->getDeclineCode() . '.');
      if ($exception->getStripeCode() === 'card_declined' && $exception->getDeclineCode() === 'card_not_supported') {
        // Stripe only supports Visa/MasterCard/Amex for non-USD transactions.
        // @todo Find a better way to communicate this to the customer.
        $message = t('Your card is not supported. Please use a Visa, MasterCard, or American Express card.');
        \Drupal::messenger()->addWarning($message);
        throw HardDeclineException::createForPayment($payment, $message, $exception->getCode(), $exception);
      }
      throw DeclineException::createForPayment($payment, 'We encountered an error processing your card details. Please verify your details and try again.', $exception->getCode(), $exception);
    }
    if ($exception instanceof RateLimitException) {
      \Drupal::logger('commerce_stripe')->warning($message);
      throw InvalidRequestException::createForPayment($payment, 'Too many requests.', $exception->getCode(), $exception);
    }
    if ($exception instanceof StripeInvalidRequestException) {
      \Drupal::logger('commerce_stripe')->warning($message);
      throw InvalidRequestException::createForPayment($payment, 'Invalid parameters were supplied to Stripe\'s API.', $exception->getCode(), $exception);
    }
    if ($exception instanceof StripeAuthenticationException) {
      \Drupal::logger('commerce_stripe')->warning($message);
      throw AuthenticationException::createForPayment($payment, 'Stripe authentication failed.', $exception->getCode(), $exception);
    }
    if ($exception instanceof ApiConnectionException) {
      \Drupal::logger('commerce_stripe')->warning($message);
      throw InvalidResponseException::createForPayment($payment, 'Network communication with Stripe failed.', $exception->getCode(), $exception);
    }
    \Drupal::logger('commerce_stripe')->warning($message);
    throw InvalidResponseException::createForPayment($payment, 'There was an error with Stripe request.', $exception->getCode(), $exception);
  }

  /**
   * Translates Stripe errors into Commerce exceptions.
   *
   * @todo Make sure this is really needed or handleException cover all
   *   possible errors.
   *
   * @param object $result
   *   The Stripe result object.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|\Drupal\commerce_payment\Entity\PaymentMethodInterface|null $payment
   *   The payment or payment method responsible for the exception.
   *
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   The Commerce exception.
   */
  public static function handleErrors(object $result, PaymentInterface|PaymentMethodInterface|null $payment = NULL): void {
    $result_data = $result->toArray();
    if ($result_data['status'] === 'succeeded') {
      return;
    }

    if (!empty($result_data['failure_code'])) {
      $failure_code = $result_data['failure_code'];
      // https://stripe.com/docs/api?lang=php#errors
      // Validation errors can be due to a module error (mapped to
      // InvalidRequestException) or due to a user input error (mapped to
      // a HardDeclineException).
      $hard_decline_codes = ['processing_error', 'missing', 'card_declined'];
      if (in_array($failure_code, $hard_decline_codes, TRUE)) {
        throw HardDeclineException::createForPayment($payment, $result_data['failure_message'], $failure_code);
      }

      throw InvalidRequestException::createForPayment($payment, $result_data['failure_message'], $failure_code);
    }
  }

}
