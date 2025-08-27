<?php

namespace Drupal\mel_auth_claim\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ClaimRedeemController extends ControllerBase {

  public function __construct(private $claimService) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('mel_auth_claim.service'));
  }

  public function redeem(string $token) {
    $row = $this->claimService->loadValidToken($token);
    if (!$row) {
      $this->messenger()->addError($this->t('This link is invalid or expired.'));
      return $this->redirect('<front>');
    }

    // TODO: If user logs in/creates account, attach history:
    // $this->claimService->attachHistoryToUser($this->currentUser()->id(), json_decode($row['context'], TRUE) ?? []);

    // Mark redeemed and send them somewhere useful.
    $this->claimService->redeemToken((int) $row['tid']);
    $this->messenger()->addStatus($this->t('Your RSVPs have been linked. Welcome!'));
    return new RedirectResponse('/my-events');
  }
}
