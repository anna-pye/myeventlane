<?php

namespace Drupal\myeventlane_rsvp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Database\Connection;

/**
 * Controller to render RSVP edit form inside profile layout.
 */
class RSVPEditController extends ControllerBase {

  protected $formBuilder;
  protected $database;

  public function __construct(FormBuilderInterface $formBuilder, Connection $database) {
    $this->formBuilder = $formBuilder;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('database')
    );
  }

  public function render($rsvp_id) {
    // Load RSVP row
    $record = $this->database->select('myeventlane_rsvp', 'r')
      ->fields('r')
      ->condition('id', $rsvp_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new AccessDeniedHttpException('RSVP not found.');
    }

    // Only allow owner to edit
    if ((int) $record['uid'] !== (int) $this->currentUser()->id()) {
      throw new AccessDeniedHttpException('Not allowed to edit this RSVP.');
    }

    return [
      '#theme' => 'my_profile_page',
      '#rsvp_form' => $this->formBuilder->getForm('Drupal\myeventlane_rsvp\Form\RSVPEditForm', $rsvp_id),
      '#attached' => [
        'library' => ['myeventlane_theme/my-profile'],
      ],
    ];
  }

}

