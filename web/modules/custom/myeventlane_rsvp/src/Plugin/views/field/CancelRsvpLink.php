<?php

declare(strict_types=1);

namespace Drupal\myeventlane_rsvp\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\myeventlane_rsvp\Service\RsvpLinkBuilder;

/**
 * @ViewsField("myeventlane_cancel_rsvp_link")
 *
 * Adds a "Cancel" link for each RSVP row.
 */
final class CancelRsvpLink extends FieldPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly RsvpLinkBuilder $linkBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('myeventlane_rsvp.link_builder'),
    );
  }

  /**
   * Add a simple option to choose which field contains the RSVP id.
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['id_source'] = ['default' => 'id']; // Machine name of the field/column containing the id.
    $options['link_text'] = ['default' => 'Cancel'];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['id_source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RSVP ID source field'),
      '#description' => $this->t('Views field/column that contains the RSVP id (e.g. "id", "rsvp_id", or a base table id).'),
      '#default_value' => $this->options['id_source'] ?? 'id',
      '#required' => TRUE,
    ];

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->options['link_text'] ?? 'Cancel',
      '#required' => TRUE,
    ];
  }

  public function query(): void {
    // No-op; we only transform existing data to a link.
  }

  public function render(ResultRow $row): array {
    $rsvp_id = $this->extractId($row);
    if (!$rsvp_id) {
      return ['#markup' => ''];
    }

    $url = $this->linkBuilder->cancelUrl($rsvp_id, FALSE);
    $link = Link::fromTextAndUrl($this->options['link_text'] ?? 'Cancel', $url)->toRenderable();
    $link['#attributes']['class'][] = 'button';
    $link['#attributes']['class'][] = 'button--secondary';

    // Make it look/feel like your theme CTA on mobile.
    $link['#attributes']['data-myel-mobile-cta'] = 'true';

    return $link;
  }

  /**
   * Try a few ways to get the RSVP id out of the row.
   */
  private function extractId(ResultRow $row): ?int {
    $key = (string) ($this->options['id_source'] ?? 'id');

    // 1) If the row has the field directly (e.g. computed column).
    if (isset($row->{$key}) && is_scalar($row->{$key})) {
      $val = (string) $row->{$key};
      return ctype_digit($val) ? (int) $val : NULL;
    }

    // 2) If the base entity is available (e.g. custom rsvp_submission entity).
    if (isset($row->_entity) && method_exists($row->_entity, 'id')) {
      $id = (int) $row->_entity->id();
      return $id > 0 ? $id : NULL;
    }

    // 3) If the result array has it.
    if (!empty($row->index) && isset($this->view->result[$row->index])) {
      $candidate = $this->view->result[$row->index];
      if (is_array($candidate) && isset($candidate[$key]) && is_scalar($candidate[$key])) {
        $val = (string) $candidate[$key];
        return ctype_digit($val) ? (int) $val : NULL;
      }
    }

    return NULL;
  }

}
