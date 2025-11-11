(function (Drupal) {
  Drupal.behaviors.melCartStepper = {
    attach: function (context) {
      // Avoid rebinding multiple times on same context.
      if (context.__melStepperBound) return;
      context.__melStepperBound = true;

      document.addEventListener('click', function (e) {
        const btn = e.target.closest('.mel-step');
        if (!btn || !document.contains(btn)) return;

        const wrap = btn.closest('[data-mel-stepper]');
        if (!wrap) return;

        let control =
          wrap.querySelector('input[type="number"]') ||
          wrap.querySelector('select');

        if (!control) return;

        const step = parseInt(btn.getAttribute('data-step'), 10) || 0;
        const getInt = (v, d) => {
          const n = parseInt(String(v), 10);
          return Number.isFinite(n) ? n : d;
        };

        const isSelect = control.tagName === 'SELECT';
        const cur = getInt(control.value, 0);
        const min = getInt(control.getAttribute('min'), 0);
        const max = getInt(control.getAttribute('max'), 9999);
        const s   = getInt(control.getAttribute('step'), 1);

        let next = cur + (step * s);
        if (next < min) next = min;
        if (next > max) next = max;

        if (isSelect) {
          let best = null, bestDiff = Infinity;
          control.querySelectorAll('option').forEach(opt => {
            const val = getInt(opt.value, NaN);
            const diff = Math.abs(val - next);
            if (Number.isFinite(val) && diff < bestDiff) { best = val; bestDiff = diff; }
          });
          if (best !== null) control.value = best;
        } else {
          control.value = String(next);
        }

        // Kick Drupal Commerce AJAX to recompute totals.
        control.dispatchEvent(new Event('change', { bubbles: true }));
      }, { passive: true });
    }
  };
})(Drupal);