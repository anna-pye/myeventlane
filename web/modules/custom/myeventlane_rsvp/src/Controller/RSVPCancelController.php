<?php

declare(strict_types=1);

namespace Drupal\myeventlane_rsvp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Back-compat shim: redirect legacy paths to confirm form.
 */
final class RSVPCancelController extends ControllerBase {

  /** @var \Symfony\Component\HttpFoundation\RequestStack */
  protected $requestStack;

  public static function create(ContainerInterface $container): self {
    $instance = new self();
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  public function cancel(?int $id = NULL): RedirectResponse {
    if (empty($id)) {
      $this->messenger()->addError($this->t('Invalid cancel link. Please try again from your RSVPs list.'));
      return $this->redirect('<front>');
    }

    $request = $this->requestStack->getCurrentRequest();
    $token = $request?->query->get('token');

    $url = Url::fromRoute('myeventlane_rsvp.cancel_form', ['rsvp' => $id], [
      'query' => $token ? ['token' => $token] : [],
      'absolute' => TRUE,
    ]);

    return new RedirectResponse($url->toString());
  }

}
