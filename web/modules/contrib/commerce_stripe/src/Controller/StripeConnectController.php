<?php

namespace Drupal\commerce_stripe\Controller;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_stripe\StripeHelper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides an OAUth return callback.
 */
class StripeConnectController extends ControllerBase {

  /**
   * The client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $clientFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->clientFactory = $container->get('http_client_factory');
    $instance->messenger = $container->get('messenger');
    $instance->logger = $container->get('logger.channel.commerce_stripe');
    return $instance;
  }

  /**
   * Handles returning from Stripe after a successful OAuth authentication.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   *   The payment gateway.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function oauthReturn(PaymentGatewayInterface $commerce_payment_gateway, Request $request): RedirectResponse {
    $plugin = $commerce_payment_gateway->getPlugin();
    // @todo move this to an access handler?
    if (!$request->query->has('code') ||
      !StripeHelper::isStripeGateway($plugin)) {
      throw new AccessDeniedHttpException();
    }
    $client = $this->clientFactory->fromOptions();
    try {
      $response = $client->post(StripeHelper::BASE_CONNECT_URL . '/oauth/token', [
        'form_params' => [
          'code' => $request->query->get('code'),
        ],
      ]);
      $response = Json::decode($response->getBody()->getContents());
      if (isset($response['access_token'], $response['stripe_publishable_key'])) {
        $html = Markup::create(sprintf('<br/><strong>Publishable key:</strong> %s<br/><strong>Access token:</strong> %s<br/><strong>Stripe User ID:</strong> %s', $response['stripe_publishable_key'], $response['access_token'], $response['stripe_user_id'] ?? ''));
        $this->messenger->addWarning($this->t('Stripe credentials generated. This is the only time they will be displayed. Please copy and store them securely.') . $html);
        $configuration = $commerce_payment_gateway->getPluginConfiguration();
        $configuration['publishable_key'] = $response['stripe_publishable_key'];
        $configuration['access_token'] = $response['access_token'];
        if (isset($response['stripe_user_id'])) {
          $configuration['stripe_user_id'] = $response['stripe_user_id'];
        }
        unset($configuration['secret_key']);
        $commerce_payment_gateway->setPluginConfiguration($configuration);
        $commerce_payment_gateway->save();

        return new RedirectResponse($commerce_payment_gateway->toUrl('collection')->toString());
      }
    }
    catch (RequestException $exception) {
      $this->logger->error($exception->getMessage());
    }

    $this->messenger->addError($this->t('There was an error connecting to Stripe.'));
    return new RedirectResponse($commerce_payment_gateway->toUrl('collection')->toString());
  }

}
