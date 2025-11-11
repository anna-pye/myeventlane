(function () {
  'use strict';

  // Let Drupal behaviors do their thing first; our code is idempotent.
  document.addEventListener('click', function (e) {
    const minus = e.target.closest('.mel-qty__btn--minus');
    const plus  = e.target.closest('.mel-qty__btn--plus');

    if (!minus && !plus) return;

    const wrapper = e.target.closest('[data-mel-qty]');
    if (!wrapper) return;

    // The Commerce quantity input is inside the wrapper.
    const input = wrapper.querySelector('input[type="number"].quantity-edit-input, input[type="number"][name^="edit_quantity"]');
    if (!input) return;

    const step = parseFloat(input.step || '1');
    const min  = input.min !== '' ? parseFloat(input.min) : 0;
    const max  = input.max !== '' ? parseFloat(input.max) : Number.MAX_SAFE_INTEGER;
    let value  = parseFloat(input.value || '0');

    if (minus) value = Math.max(min, value - step);
    if (plus)  value = Math.min(max, value + step);

    input.value = value;

    // Trigger the same events Commerce listens for.
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.dispatchEvent(new Event('input',  { bubbles: true }));
  });

  // Convert the default number input into our stepped UI:
  // wrap input with - and + buttons if not already done.
  function decorateQtyInputs(root) {
    root.querySelectorAll('[data-mel-qty]').forEach((wrap) => {
      if (wrap.dataset.melBound === '1') return; // once
      const input = wrap.querySelector('input[type="number"]');
      if (!input) return;

      // Keep Commerce classes & attributes. We only add buttons around it.
      const minus = document.createElement('button');
      minus.type = 'button';
      minus.className = 'mel-qty__btn mel-qty__btn--minus';
      minus.textContent = '−';

      const plus = document.createElement('button');
      plus.type = 'button';
      plus.className = 'mel-qty__btn mel-qty__btn--plus';
      plus.textContent = '+';

      input.classList.add('mel-qty__input');

      wrap.insertBefore(minus, input);
      wrap.insertBefore(input, input.nextSibling);
      wrap.insertBefore(plus, input.nextSibling);

      wrap.dataset.melBound = '1';
    });
  }

  // On load & on ajax refreshes (Views/Ajax submit).
  decorateQtyInputs(document);
  document.addEventListener('ajaxComplete', () => decorateQtyInputs(document));
})();