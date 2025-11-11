/**
 * Ticket type toggle (RSVP vs Paid) – Drupal 11 style.
 * Looks for an element with class .js-ticket-type (radio group or select)
 * and shows/hides .js-rsvp-section / .js-paid-section.
 */
(function (Drupal, once) {
  'use strict';

  function getValue(el) {
    // Works if el is a <select> or a container wrapping radios.
    if (el.matches('select')) {
      return (el.value || '').toLowerCase();
    }
    const checked = el.querySelector('input[type=radio]:checked');
    return checked ? (checked.value || '').toLowerCase() : '';
  }

  function wire(el) {
    const form = el.closest('form') || document;
    const rsvp = form.querySelector('.js-rsvp-section');
    const paid = form.querySelector('.js-paid-section');

    function apply() {
      const v = getValue(el);
      if (rsvp) rsvp.style.display = (v === 'rsvp') ? '' : 'none';
      if (paid) paid.style.display = (v === 'paid') ? '' : 'none';
    }

    // Listen on the radios/select inside el.
    const inputs = el.matches('select')
      ? [el]
      : el.querySelectorAll('input[type=radio]');
    inputs.forEach((i) => i.addEventListener('change', apply));

    // Initial state.
    apply();
  }

  Drupal.behaviors.melTicketToggle = {
    attach(context) {
      // Convert any old uses of element.once(...) to the new once() API.
      once('melTicketToggle', '.js-ticket-type', context).forEach(wire);
    }
  };
})(Drupal, once);
