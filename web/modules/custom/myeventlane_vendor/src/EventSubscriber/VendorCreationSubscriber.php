<?php

namespace Drupal\myeventlane_vendor\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityInsertEvent;
use Drupal\Core\Entity\EntityEvents;

class VendorCreationSubscriber implements EventSubscriberInterface {

  protected $entityTypeManager;
  protected $currentUser;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  public static function getSubscribedEvents() {
    return [
      EntityEvents::insert => 'onEventCreate',
    ];
  }
  
	public function onEventCreate(EntityInsertEvent $event) {
	  $entity = $event->getEntity();
	  if ($entity->getEntityTypeId() == 'node' && $entity->bundle() == 'event') {
	    $store_storage = $this->entityTypeManager->getStorage('commerce_store');
	    $profile_storage = $this->entityTypeManager->getStorage('profile');

	    $existing_store = $store_storage->loadByProperties(['uid' => $this->currentUser->id()]);
	    if (!$existing_store) {
	      // Create Commerce Store.
	      $store = $store_storage->create([
	        'type' => 'vendor',
	        'name' => $this->currentUser->getDisplayName() . "'s Store",
	        'uid' => $this->currentUser->id(),
	        'mail' => $this->currentUser->getEmail(),
	        'address' => [],
	      ]);
	      $store->save();
	    }

	    // Ensure vendor profile exists.
	    $existing_profile = $profile_storage->loadByUser($this->currentUser, 'vendor_profile');
	    if (!$existing_profile) {
	      $profile = $profile_storage->create([
	        'type' => 'vendor_profile',
	        'uid' => $this->currentUser->id(),
	        'status' => TRUE,
	      ]);
	      $profile->save();
	    }
	  }
	}
}

