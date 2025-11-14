(function (Drupal) {
  Drupal.behaviors.melBoostSelect = {
    attach(context) {
      const radios = context.querySelectorAll('.mel-boost-radios input[type="radio"]');
      if (!radios.length) return;

      const setSelected = (vid) => {
        // Set the actual radio value and aria states.
        radios.forEach(r => {
          r.checked = (r.value === String(vid));
        });
        context.querySelectorAll('.boost-row').forEach(row => {
          const on = row.getAttribute('data-variation-id') === String(vid);
          row.setAttribute('aria-checked', on ? 'true' : 'false');
          row.classList.toggle('is-selected', on);
        });
      };

      // Default: first radio is selected by Drupal.
      const checked = context.querySelector('.mel-boost-radios input[type="radio"]:checked');
      if (checked) setSelected(checked.value);

      // Make each row clickable to select.
      context.querySelectorAll('.boost-row').forEach(row => {
        const vid = row.getAttribute('data-variation-id');
        const handle = () => setSelected(vid);
        row.addEventListener('click', handle);
        row.addEventListener('keypress', (e) => {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            handle();
          }
        });
      });
    }
  };
})(Drupal);
