<?php

namespace Drupal\commerce;

use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\commerce\Event\CommerceEvents;
use Drupal\commerce\Event\PostMailSendEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MailHandler implements MailHandlerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new MailHandler object.
   *
   * @param \Drupal\Core\Language\LanguageDefault $languageDefault
   *   The language default.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected LanguageDefault $languageDefault,
    protected LanguageManagerInterface $languageManager,
    protected MailManagerInterface $mailManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function sendMail($to, $subject, array $body, array $params = []) {
    if (empty($to)) {
      return FALSE;
    }

    $default_params = [
      'headers' => [
        'Content-Type' => 'text/html; charset=UTF-8;',
        'Content-Transfer-Encoding' => '8Bit',
      ],
      'id' => 'mail',
      // For all user triggered emails, The 'from' address will be set by
      // commerce_store_mail_alter(). In case an email could also be triggered
      // externally e.g. by an administrator, the mailer should specify the
      // 'from' address of the right store.
      'from' => '',
      'reply-to' => NULL,
      'subject' => $subject,
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      // The body will be rendered in commerce_mail(), because that's what
      // MailManager expects. The correct theme and render context aren't
      // setup until then.
      'body' => $body,
    ];
    if (!empty($params['cc'])) {
      $default_params['headers']['Cc'] = $params['cc'];
    }
    if (!empty($params['bcc'])) {
      $default_params['headers']['Bcc'] = $params['bcc'];
    }
    $params = array_replace($default_params, $params);

    // Change the active language to ensure the email is properly translated.
    if ($params['langcode'] != $default_params['langcode']) {
      $this->changeActiveLanguage($params['langcode']);
    }

    $message = $this->mailManager->mail('commerce', $params['id'], $to, $params['langcode'], $params, $params['reply-to']);
    // When Symfony mailer is used for sending emails, the "to" key isn't
    // present in the message array returned.
    $message += ['to' => $to];

    // Revert back to the original active language.
    if ($params['langcode'] != $default_params['langcode']) {
      $this->changeActiveLanguage($default_params['langcode']);
    }
    // Allow modules to react after an email has been sent.
    $event = new PostMailSendEvent($params, $message);
    $this->eventDispatcher->dispatch($event, CommerceEvents::POST_MAIL_SEND);

    return (bool) $message['result'];
  }

  /**
   * Changes the active language for translations.
   *
   * @param string $langcode
   *   The langcode.
   */
  protected function changeActiveLanguage($langcode) {
    if (!$this->languageManager->isMultilingual()) {
      return;
    }
    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      return;
    }
    // The language manager has no method for overriding the default
    // language, like it does for config overrides. We have to change the
    // default language service's current language.
    // @see https://www.drupal.org/project/drupal/issues/3029010
    $this->languageDefault->set($language);
    $this->languageManager->setConfigOverrideLanguage($language);
    $this->languageManager->reset();

    // The default string_translation service, TranslationManager, has a
    // setDefaultLangcode method. However, this method is not present on
    // either of its interfaces. Therefore we check for the concrete class
    // here so that any swapped service does not break the application.
    // @see https://www.drupal.org/project/drupal/issues/3029003
    $string_translation = $this->getStringTranslation();
    if ($string_translation instanceof TranslationManager) {
      $string_translation->setDefaultLangcode($language->getId());
      $string_translation->reset();
    }
  }

}
