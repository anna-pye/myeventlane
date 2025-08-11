document.addEventListener('DOMContentLoaded', () => {
  const ticketTypeSelect = document.querySelector('[name="field_ticket_type[0][value]"]');
  const rsvpPanel = document.querySelector('.ticket-type-wrapper[data-ticket-type="rsvp"]');
  const paidPanel = document.querySelector('.ticket-type-wrapper[data-ticket-type="paid"]');

  function updatePanels() {
    const selected = ticketTypeSelect?.value;
    if (!selected) return;

    if (rsvpPanel) {
      rsvpPanel.style.display = selected === 'rsvp' ? 'block' : 'none';
      rsvpPanel.classList.toggle('fade-in', selected === 'rsvp');
    }
    if (paidPanel) {
      paidPanel.style.display = selected === 'paid' ? 'block' : 'none';
      paidPanel.classList.toggle('fade-in', selected === 'paid');
    }
  }

  if (ticketTypeSelect) {
    ticketTypeSelect.addEventListener('change', updatePanels);
    updatePanels(); // Set initial state
  }
});
