(function (Drupal, once) {
  Drupal.behaviors.melCartActions = {
    attach: function (context) {
      // Collect ALL continue links rendered anywhere
      const all = Array.from(once('mel-continue-any', '[data-mel-continue]', context));
      if (!all.length) return;

      // First one becomes the primary
      const primary = all[0];

      // Move primary into the Commerce actions container
      const actions = context.querySelector('#edit-actions');
      if (actions && !actions.querySelector('[data-mel-continue]')) {
        actions.insertBefore(primary, actions.firstChild);
      }

      // Remove any duplicates that might have been printed elsewhere
      for (let i = 1; i < all.length; i++) {
        all[i].remove();
      }
    }
  };
})(Drupal, once);