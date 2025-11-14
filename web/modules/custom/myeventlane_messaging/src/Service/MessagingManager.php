<?php

namespace Drupal\myeventlane_messaging\Service;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Psr\Log\LoggerInterface;

final class MessagingManager {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $lang,
    private readonly QueueFactory $queueFactory,
    private readonly LoggerInterface $logger,
    private readonly MessageRenderer $messageRenderer,
  ) {}

  public function queue(string $type, string $to, array $context = [], array $opts = []) : void {
    $payload = ['type' => $type, 'to' => $to, 'context' => $context, 'opts' => $opts];
    $this->queueFactory->get('myeventlane_messaging')->createItem($payload);
  }

  public function sendNow(array $payload) : void {
    $type = $payload['type'] ?? 'generic';
    $to   = $payload['to'] ?? '';
    $ctx  = $payload['context'] ?? [];
    $opts = $payload['opts'] ?? [];

    if (!$to) {
      $this->logger->warning('Skipping message with empty recipient.');
      return;
    }

    $conf = $this->configFactory->get("myeventlane_messaging.template.$type");
    if (!$conf || !$conf->get('enabled')) {
      $this->logger->notice('Template @type disabled or missing.', ['@type' => $type]);
      return;
    }

    // SUBJECT: render as Twig string (no theme), then strip & truncate.
    $subjectTpl = (string) ($conf->get('subject') ?? '');
    $subjectRaw = $this->messageRenderer->renderString($subjectTpl, $ctx);
    $subject    = Html::decodeEntities(strip_tags($subjectRaw));
    // Keep well under common varchar limits (e.g., 255) and Easy Email’s field.
    $subject    = Unicode::truncate($subject, 150, TRUE, TRUE);

    // BODY: render inner HTML (Twig string) and wrap with theme shell.
    $body = $this->messageRenderer->renderHtmlBody($conf, $ctx);

    $langcode = $opts['langcode'] ?? $this->lang->getDefaultLanguage()->getId();

    $params = [
      'subject' => $subject,
      'body'    => $body, // plain fallback (ok if HTML too)
      'html'    => $body, // used by symfony_mailer_lite / Easy Email
      'headers' => ['Content-Type' => 'text/html; charset=UTF-8'],
    ];

    $result = $this->mailManager->mail(
      'myeventlane_messaging',
      'generic',
      $to,
      $langcode,
      $params
    );

    if (!empty($result['result'])) {
      $this->logger->info('Sent message @type to @to.', ['@type' => $type, '@to' => $to]);
    } else {
      $this->logger->error('Failed sending message @type to @to.', ['@type' => $type, '@to' => $to]);
    }
  }
}
