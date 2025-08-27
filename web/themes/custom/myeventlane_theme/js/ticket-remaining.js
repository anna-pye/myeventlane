(function (Drupal) {
  Drupal.behaviors.melRemainingToggle = {
    attach: function (context) {
      const btn = context.querySelector('.mel-remaining-toggle');
      const panel = context.querySelector('#mel-remaining');
      if (!btn || !panel) return;
      btn.addEventListener('click', function () {
        const open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        panel.hidden = open;
      });
    }
  };
})(Drupal);
