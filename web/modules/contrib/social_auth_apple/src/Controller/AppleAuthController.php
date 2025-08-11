<?php

namespace Drupal\social_auth_apple\Controller;

use Drupal\Core\Url;
use Drupal\social_auth\Controller\OAuth2ControllerBase;
use Drupal\social_auth\Plugin\Network\NetworkInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handle responses for Social Auth Apple.
 */
class AppleAuthController extends OAuth2ControllerBase {

  /**
   * {@inheritdoc}
   *
   * @see \League\OAuth2\Client\Provider\Apple::getAuthorizationParameters()
   * @see \League\OAuth2\Client\Provider\Apple::fetchResourceOwnerDetails()
   */
  public function callback(NetworkInterface $network): RedirectResponse {
    // Authentication through Apple always sends a POST
    // if email and name are requested,
    // but Social Auth is based on a GET request,
    // so we need to redirect to the same URL with the POST data.
    // @see \League\OAuth2\Client\Provider\Apple::getAuthorizationParameters()
    $request = $this->request->getCurrentRequest();
    if ($request->isMethod(Request::METHOD_POST)) {
      $url = Url::createFromRequest($request)->mergeOptions(['query' => $request->request->all()]);
      return new RedirectResponse($url->toString());
    }
    return parent::callback($network);
  }

}
