<?php

namespace Drupal\mel_auth_claim\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\TimeInterface;

class ClaimService {

  public function __construct(
    protected Connection $db,
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $langManager,
    protected RendererInterface $renderer,
    protected LoggerInterface $logger,
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $etm,
    protected TimeInterface $time,
  ) {}

  /**
   * Create a one-time token and email it.
   *
   * @param string $email
   * @param array $context
   *   e.g. ['rsvp_ids' => [1,2], 'order_ids' => [33], 'source' => 'rsvp_success']
   * @param int $ttl
   *   Seconds until expiry (default 24h).
   */
  public function issue(string $email, array $context = [], int $ttl = 86400): void {
    $token = bin2hex(random_bytes(32));
    $now = $this->time->getRequestTime();

    $this->db->insert('mel_claim_token')->fields([
      'email' => mb_strtolower($email),
      'token' => $token,
      'context' => Json::encode($context),
      'created' => $now,
      'expires' => $now + $ttl,
      'ip' => \Drupal::request()->getClientIp(),
      'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ])->execute();

    $link = \Drupal::url('mel_auth_claim.redeem', ['token' => $token], ['absolute' => TRUE]);
    $langcode = $this->langManager->getDefaultLanguage()->getId();

    $this->mailManager->mail(
      'mel_auth_claim',
      'claim_link',
      $email,
      $langcode,
      ['link' => $link],
      NULL,
      TRUE
    );

    $this->logger->notice('Claim email issued to %email with token %token', ['%email' => $email, '%token' => substr($token, 0, 8) . '…']);
  }

  /**
   * Validate token; returns assoc array or NULL.
   */
  public function loadValidToken(string $token): ?array {
    $now = $this->time->getRequestTime();
    $row = $this->db->select('mel_claim_token', 't')
      ->fields('t')
      ->condition('token', $token)
      ->condition('redeemed', 0)
      ->condition('expires', $now, '>')
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /**
   * Mark token redeemed.
   */
  public function redeemToken(int $tid): void {
    $this->db->update('mel_claim_token')
      ->fields(['redeemed' => $this->time->getRequestTime()])
      ->condition('tid', $tid)
      ->execute();
  }

  /**
   * Attach historical RSVPs/orders by email (stubs you can fill).
   */
  public function attachHistoryToUser(int $uid, array $context): void {
    // TODO: If you store RSVP emails in myeventlane_rsvp, update those rows to uid=$uid.
    // Example:
    // $ids = $context['rsvp_ids'] ?? [];
    // if ($ids) {
    //   $this->db->update('myeventlane_rsvp')->fields(['uid' => $uid])->condition('id', $ids, 'IN')->execute();
    // }
    //
    // For Commerce orders created as guest (email-only), find orders by mail field and set uid.
    // $order_storage = $this->etm->getStorage('commerce_order');
    // $order_ids = $order_storage->getQuery()->condition('mail', $context['email'])->condition('uid', 0)->execute();
    // Load & setOwnerId($uid) then save.
  }
}
