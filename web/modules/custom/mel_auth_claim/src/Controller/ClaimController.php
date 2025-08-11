<?php

namespace Drupal\mel_auth_claim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\user\Entity\User;
use Drupal\Component\Serialization\Json;

class ClaimController extends ControllerBase {

  protected $service;

  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->service = $container->get('mel_auth_claim.service');
    return $instance;
  }

  public function redeem(string $token) {
    $row = $this->service->loadValidToken($token);
    if (!$row) {
      $this->messenger()->addError($this->t('This claim link is invalid or has expired.'));
      return new RedirectResponse('/user/login');
    }

    $email = $row['email'];
    $context = [];
    if (!empty($row['context'])) {
      $context = Json::decode($row['context']) ?: [];
    }

    // If user exists, use it; otherwise create a minimal account (blocked=false).
    $accounts = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
    $account = $accounts ? reset($accounts) : NULL;

    if (!$account) {
      $account = User::create([
        'name' => $email,
        'mail' => $email,
        'status' => 1,
      ]);
      $account->enforceIsNew();
      $account->save();
    }

    // Attach historical data.
    $this->service->attachHistoryToUser((int) $account->id(), $context + ['email' => $email]);

    // One-time sign-in.
    user_login_finalize($account);
    $this->service->redeemToken((int) $row['tid']);

    $this->messenger()->addStatus($this->t('Welcome! Your tickets and RSVPs have been saved to your account.'));
    // Encourage password (or passkey) set if they don’t have one.
    return new RedirectResponse('/my-events');
  }
}
