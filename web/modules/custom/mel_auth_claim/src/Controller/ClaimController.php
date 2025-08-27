<?php

namespace Drupal\mel_auth_claim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\Component\Serialization\Json;
use Drupal\mel_auth_claim\Service\ClaimService;

class ClaimController extends ControllerBase {

  /** @var \Drupal\mel_auth_claim\Service\ClaimService */
  protected ClaimService $claim;

  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->claim = $container->get('mel_auth_claim.service');
    return $instance;
  }

  /**
   * Redeem a one-time claim token.
   */
  public function redeem(string $token) {
    // Validate token.
    $row = $this->claim->loadValidToken($token);
    if (!$row) {
      $this->messenger()->addError($this->t('This claim link is invalid or has expired.'));
      return $this->redirect('user.login');
    }

    $email = $row['email'];
    $context = [];
    if (!empty($row['context'])) {
      $context = Json::decode($row['context']) ?: [];
    }

    // Find or create user by email.
    $accounts = $this->entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
    /** @var \Drupal\user\UserInterface|null $account */
    $account = $accounts ? reset($accounts) : NULL;

    if (!$account) {
      // Base the username on email local part; ensure uniqueness.
      $base_name = preg_replace('/[^a-z0-9_.-]+/i', '', strtok($email, '@')) ?: 'user';
      $name = $base_name;
      $i = 1;
      while ($this->entityTypeManager()->getStorage('user')->loadByProperties(['name' => $name])) {
        $name = $base_name . '_' . $i++;
      }

      $account = User::create([
        'name' => $name,
        'mail' => $email,
        'status' => 1,
      ]);
      $account->enforceIsNew();
      $account->save();
    }

    // Attach historical data (RSVP IDs, orders, etc.).
    $this->claim->attachHistoryToUser((int) $account->id(), $context + ['email' => $email]);

    // Mark token redeemed and log the user in.
    $this->claim->redeemToken((int) $row['tid']);
    user_login_finalize($account);

    $this->messenger()->addStatus($this->t('Welcome! Your RSVPs and purchases have been linked to your account.'));

    // Redirect to your dashboard if you have a route, else front.
    // return $this->redirect('myeventlane.my_events'); // if you’ve defined this route
    return $this->redirect('<front>');
  }

}
