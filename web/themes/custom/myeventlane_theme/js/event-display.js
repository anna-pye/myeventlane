/**
 * MyEventLane - event display helpers
 * NOTE: Requires libraries.yml to depend on core/once.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.melEventDisplay = {
    attach(context) {
      // Example: add a class once to the book page wrapper.
      once('melEventDisplayInit', '.mel-event-card', context).forEach((el) => {
        el.classList.add('mel-event-card--js');
      });
    }
  };
})(Drupal, once);
