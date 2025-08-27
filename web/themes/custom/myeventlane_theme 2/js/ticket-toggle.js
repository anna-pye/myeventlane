(function ($, Drupal) {
  Drupal.behaviors.ticketToggle = {
    attach: function (context, settings) {
      const ticketType = $('select[name="field_ticket_type[0][value]"]', context);
      const ticketPanel = $('#ticket-inline-wrapper', context);
      const rsvpPanel = $('#edit-field-rsvp-target-wrapper', context);

      function togglePanels() {
        const val = ticketType.val();
        ticketPanel.toggle(val === 'paid');
        rsvpPanel.toggle(val === 'rsvp');
      }

      togglePanels();
      ticketType.once('ticketToggle').on('change', togglePanels);
    }
  };
})(jQuery, Drupal);

