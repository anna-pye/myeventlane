(function (Drupal, once) {
  Drupal.behaviors.melMatrix = {
    attach(context) {
      once('melMatrix', '.mel-ticket-matrix__form', context).forEach(root => {
        // Example: make number inputs behave like steppers visually.
        root.querySelectorAll('input[type="number"].mel-ticket-qty-input').forEach(inp => {
          inp.min = '0';
          inp.step = '1';
          if (!inp.value) inp.value = '0';
        });
      });
    }
  };
})(Drupal, once);
