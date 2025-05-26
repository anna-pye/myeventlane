<?php

namespace Drupal\mailchimp\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for processing access token from OAuth middleware server.
 */
class MailchimpOAuthController extends ControllerBase {

  const OAUTH_MIDDLEWARE_URL = 'https://drupal-oauth.mailchimp.com';

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfService;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->configFactory = $container->get('config.factory');
    $instance->stateService = $container->get('state');
    $instance->csrfService = $container->get('csrf_token');
    return $instance;
  }

  /**
   * Request access token from middleware server.
   *
   * @param string $temp_token
   *   The temporary token that was issued from the initial request.
   * @param string $csrf_token
   *   CSRF token.
   */
  public function getAccessToken(string $temp_token, string $csrf_token) {
    $post_params = [
      'form_params' => [
        'type' => 'access_token',
        'temp_token' => $temp_token,
      ],
    ];
    $client = $this->client();
    $url = Url::fromRoute('mailchimp.admin.oauth');

    if (!$this->csrfService->validate($csrf_token, "mailchimp_admin_oauth_settings")) {
      $this->messenger()->addError("Could not validate CSRF token.");
      return new RedirectResponse($url->toString());
    }

    try {
      $middleware_url = self::OAUTH_MIDDLEWARE_URL;
      $response = $client->request('POST', $middleware_url . '/access-token', $post_params);
      // Check for response with access_token, and store in database.
      if ($response->getStatusCode() == '200') {
        // Check for a token in the response.
        $json = $response->getBody()->getContents();
        $message = json_decode($json, TRUE);

        if (isset($message['access_token'])) {
          $access_token = $message['access_token'];
          $data_center = $message['data_center'];
          // Save authentication values to state.
          $this->stateService->set('mailchimp_access_token', $access_token);
          $this->stateService->set('mailchimp_data_center', $data_center);
          return new RedirectResponse($url->toString());
        }
      }
    }
    catch (GuzzleException $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return new RedirectResponse($url->toString());
  }

  /**
   * Initialize a new Guzzle client.
   *
   * @return \GuzzleHttp\Client
   *   Guzzle client.
   */
  protected function client() {
    // Intialize Guzzle Client.
    return new Client([
      'base_uri' => '',
      'timeout'  => 100.0,
    ]);
  }

}
