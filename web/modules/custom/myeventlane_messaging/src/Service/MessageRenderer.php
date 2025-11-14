<?php

namespace Drupal\myeventlane_messaging\Service;

use Drupal\Core\Render\RendererInterface;
use Twig\Environment;

/**
 * Renders messaging templates.
 * - Subjects: render as Twig *strings* (no theme layer, no debug wrappers).
 * - Bodies:   render inner HTML via Twig string, then wrap with theme template.
 */
final class MessageRenderer {

  public function __construct(
    private readonly Environment $twig,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * Render a small Twig string with context (for subjects, tokens, etc.).
   * No theme layer involved; avoids Twig debug wrappers.
   */
  public function renderString(string $template, array $context = []): string {
    try {
      $tpl = $this->twig->createTemplate($template);
      return trim((string) $tpl->render($context));
    }
    catch (\Throwable $e) {
      \Drupal::logger('myeventlane_messaging')->error('Twig string render failed: @msg', ['@msg' => $e->getMessage()]);
      return $template;
    }
  }

  /**
   * Render the HTML email body inside our wrapper template.
   * $conf should contain 'body_html' (Twig string).
   */
  public function renderHtmlBody(\Drupal\Core\Config\Config $conf, array $context = []): string {
    $inner = '';
    $body_tpl = (string) ($conf->get('body_html') ?? '');
    if ($body_tpl !== '') {
      try {
        $inner = $this->twig->createTemplate($body_tpl)->render($context);
      }
      catch (\Throwable $e) {
        \Drupal::logger('myeventlane_messaging')->error('Twig body render failed: @msg', ['@msg' => $e->getMessage()]);
        $inner = $body_tpl;
      }
    }
    // Wrap with our themed email shell.
    $build = [
      '#theme' => 'myeventlane_email',
      '#body' => $inner,
      '#context' => $context,
    ];
    return (string) $this->renderer->renderPlain($build);
  }

}
