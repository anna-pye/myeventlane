(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.melTickets = {
    attach(context) {
      once('melTickets-card', '.mel-ticket-card', context).forEach(() => {});
    }
  };
})(Drupal, once);
