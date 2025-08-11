// Empty shell for future custom JS.
(function ($, Drupal) {
  Drupal.behaviors.myeventlaneTheme = {
    attach: function (context, settings) {
    document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
    const status = btn.dataset.status;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.event-card').forEach(card => {
      if (status === 'all' || card.classList.contains(`status-${status}`)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    });
  });
});(jQuery, Drupal);

