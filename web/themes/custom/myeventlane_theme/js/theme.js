// theme.js — MyEventLane Theme Behaviors
(function ($, Drupal) {
  Drupal.behaviors.myeventlaneTheme = {
    attach: function (context, settings) {
      // Filter button behavior
      document.querySelectorAll('.filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const status = btn.dataset.status;
          document
            .querySelectorAll('.filter-btn')
            .forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');

          document.querySelectorAll('.event-card').forEach((card) => {
            if (status === 'all' || card.classList.contains(`status-${status}`)) {
              card.style.display = 'block';
            } else {
              card.style.display = 'none';
            }
          });
        });
      });

      // Toast message animation
      const wrap = document.getElementById('mel-messages-anchor');
      if (wrap) {
        const blocks = wrap.querySelectorAll('[data-drupal-messages] .messages');
        blocks.forEach((b) =>
          requestAnimationFrame(() => b.classList.add('mel-toast--show'))
        );
      }
    },
  };
})(jQuery, Drupal);