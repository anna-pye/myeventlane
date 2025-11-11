(function (Drupal, once) {
  'use strict';

  function coerceInt(v, fallback) {
    var n = parseInt(v, 10);
    return isNaN(n) ? fallback : n;
  }

  function stepSelect(select, delta) {
    var idx = select.selectedIndex;
    var next = Math.max(0, Math.min(select.options.length - 1, idx + delta));
    if (next !== idx) {
      select.selectedIndex = next;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function stepNumber(input, delta) {
    var cur = coerceInt(input.value, 0);
    var min = input.min !== '' ? coerceInt(input.min, -Infinity) : -Infinity;
    var max = input.max !== '' ? coerceInt(input.max,  Infinity) :  Infinity;
    var step = input.step !== '' ? coerceInt(input.step, 1) : 1;

    var next = cur + (delta * step);
    if (next < min) next = min;
    if (next > max) next = max;

    if (next !== cur) {
      input.value = String(next);
      // Commerce listens to input/change to fire AJAX.
      input.dispatchEvent(new Event('input',  { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function updateDisabledState(wrap, input) {
    var minus = wrap.querySelector('.mel-step[data-step="-1"]');
    var plus  = wrap.querySelector('.mel-step[data-step="1"]');

    if (!minus || !plus || !input) return;

    if (input.tagName === 'SELECT') {
      minus.disabled = (input.selectedIndex <= 0);
      plus.disabled  = (input.selectedIndex >= input.options.length - 1);
      return;
    }

    var v   = coerceInt(input.value, 0);
    var min = input.min !== '' ? coerceInt(input.min, -Infinity) : -Infinity;
    var max = input.max !== '' ? coerceInt(input.max,  Infinity) :  Infinity;

    minus.disabled = (v <= min);
    plus.disabled  = (v >= max);
  }

  Drupal.behaviors.myeventlaneQty = {
    attach: function (context) {
      once('mel-qty', '[data-mel-qty]', context).forEach(function (wrap) {
        var minus = wrap.querySelector('.mel-step[data-step="-1"]');
        var plus  = wrap.querySelector('.mel-step[data-step="1"]');
        var input = wrap.querySelector('input[type="number"], select');

        if (!input) return;

        // Initial disabled state
        updateDisabledState(wrap, input);

        // Sync disabled state when user types/changes
        input.addEventListener('input', function () { updateDisabledState(wrap, input); }, { passive: true });
        input.addEventListener('change', function () { updateDisabledState(wrap, input); }, { passive: true });

        function handle(delta) {
          if (input.tagName === 'SELECT') {
            stepSelect(input, delta);
          } else {
            stepNumber(input, delta);
          }
          updateDisabledState(wrap, input);
        }

        minus && minus.addEventListener('click', function (e) { e.preventDefault(); handle(-1); });
        plus  && plus.addEventListener('click',  function (e) { e.preventDefault(); handle(1);  });
      });
    }
  };
})(Drupal, once);