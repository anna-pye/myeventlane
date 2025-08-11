<?php

namespace Drupal\social_auth_apple;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Error;
use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\social_auth\User\SocialAuthUser;
use Drupal\social_auth\User\SocialAuthUserInterface;
use Drupal\social_auth_apple\Settings\AppleAuthSettingsInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains all the logic for Apple OAuth2 authentication.
 */
class AppleAuthManager extends OAuth2Manager {

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    ConfigFactory $configFactory,
    LoggerChannelFactoryInterface $loggerFactory,
    RequestStack $requestStack,
  ) {
    parent::__construct($configFactory->get(AppleAuthSettingsInterface::CONFIG_NAME), $loggerFactory, $requestStack->getCurrentRequest());
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(): void {
    if ($code = $this->request->query->get('code')) {
      try {
        $this->setAccessToken($this->client->getAccessToken('authorization_code', ['code' => $code]));
      }
      catch (\Throwable $e) {
        $this->loggerFactory->get('social_auth_apple')->error('There was an error during authentication. ' . Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string', Error::decodeException($e));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInfo(): ?SocialAuthUserInterface {
    if (!$this->user && $access_token = $this->getAccessToken()) {
      /** @var \League\OAuth2\Client\Provider\AppleResourceOwner $owner */
      $owner = $this->client->getResourceOwner($access_token);
      $first_name = $owner->getFirstName();
      $last_name = $owner->getLastName();
      if ($first_name) {
        $name = $first_name;
        if ($last_name) {
          $name .= ' ' . $last_name;
        }
      }
      $this->user = new SocialAuthUser(
        $name ?? $owner->getEmail(),
        $owner->getId(),
        $this->getAccessToken(),
        $owner->getEmail(),
        NULL,
        $this->getExtraDetails()
      );
      $this->user->setFirstName($first_name);
      $this->user->setLastName($last_name);
    }
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUrl(): string {
    $scopes = [
      'name',
      'email',
    ];
    if ($extra_scopes = $this->getScopes()) {
      $scopes = array_merge($scopes, explode(',', $extra_scopes));
    }
    return $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function requestEndPoint(string $method, string $path, ?string $domain = NULL, array $options = []): mixed {
    if (!$domain) {
      $domain = 'https://appleid.apple.com';
    }
    $url = $domain . $path;
    $request = $this->client->getAuthenticatedRequest($method, $url, $this->getAccessToken(), $options);
    try {
      return $this->client->getParsedResponse($request);
    }
    catch (IdentityProviderException $e) {
      $this->loggerFactory->get('social_auth_apple')->error("There was an error when requesting $url. Exception: {$e->getMessage()}");
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): string {
    return $this->client->getState();
  }

}
