(function (Drupal) {
  Drupal.behaviors.melMessages = {
    attach: function (context) {
      const wrap = context.querySelector('#mel-messages-anchor .messages');
      if (!wrap) return;
      // Animate in
      requestAnimationFrame(() => wrap.classList.add('mel-toast--show'));

      // If using the “Status messages” block with close button:
      wrap.addEventListener('click', (e) => {
        if (e.target.closest('.messages__close')) {
          wrap.classList.remove('mel-toast--show');
        }
      });
    }
  };
})(Drupal);