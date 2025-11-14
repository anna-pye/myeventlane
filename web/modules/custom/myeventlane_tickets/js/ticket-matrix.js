(function (Drupal, once) {
  Drupal.behaviors.melTicketMatrix = {
    attach: function (context) {
      // Quantity stepper click
     once('melTicketStepper', '.mel-ticket-row', context).forEach((row) => {
      row.querySelectorAll('[data-mel-step]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          const input = row.querySelector('.mel-qty-input');
          if (!input) return;

          const cur = parseInt(input.value || '0', 10) || 0;
          const isInc = btn.dataset.melStep === '+';
          input.value = String(Math.max(0, cur + (isInc ? 1 : -1)));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });
    });

      // Subtotal updates
      function updateSubtotal() {
        const rows = document.querySelectorAll('.mel-qty-input');
        let total = 0;
        let count = 0;

        rows.forEach(row => {
          const qty = parseInt(row.value || '0', 10);
          const label = row.closest('.mel-ticket-row')?.querySelector('.mel-ticket-label');
          if (!label || qty === 0) return;

          const priceMatch = label.textContent.match(/\$([0-9.,]+)/);
          if (!priceMatch) return;

          const unit = parseFloat(priceMatch[1].replace(',', ''));
          total += qty * unit;
          count += qty;
        });

        const el = document.querySelector('#mel-subtotal');
        if (!el) return;

        if (count === 0) {
          el.classList.remove('mel-subtotal-visible');
          el.textContent = '';
        } else {
          el.textContent = `${count} ticket${count > 1 ? 's' : ''} — $${total.toFixed(2)}`;
          el.classList.add('mel-subtotal-visible');
        }
      }

      function updateGroupSubtotals() {
        const groups = document.querySelectorAll('.mel-ticket-matrix-card');
        groups.forEach(group => {
          let total = 0;
          let count = 0;
          const inputs = group.querySelectorAll('.mel-qty-input');

          inputs.forEach(input => {
            const qty = parseInt(input.value || '0', 10);
            const label = input.closest('.mel-ticket-row')?.querySelector('.mel-ticket-label');
            if (!label || qty === 0) return;

            const match = label.textContent.match(/\$([0-9.,]+)/);
            if (!match) return;

            const unit = parseFloat(match[1].replace(',', ''));
            total += qty * unit;
            count += qty;
          });

          let target = group.querySelector('.mel-group-subtotal');
          if (!target) {
            target = document.createElement('div');
            target.className = 'mel-group-subtotal';
            group.appendChild(target);
          }

          if (count === 0) {
            target.textContent = '';
            target.style.display = 'none';
          } else {
            target.textContent = `${count} × tickets — $${total.toFixed(2)}`;
            target.style.display = 'block';
          }
        });
      }

      // Update subtotals on quantity change
      document.addEventListener('change', function (e) {
        if (e.target.classList.contains('mel-qty-input')) {
          updateSubtotal();
          updateGroupSubtotals();
        }
      });

      // Hide/show error message on submit
      document.addEventListener('submit', function (e) {
        const form = e.target.closest('form#myeventlane-ticket-matrix-form, form[data-drupal-selector="myeventlane-ticket-matrix-form"]') || e.target;
        if (!form) return;

        const err = form.querySelector('.mel-error');
        if (err) err.classList.add('visually-hidden');

        const qtys = form.querySelectorAll('.mel-qty-input');
        let sum = 0;
        qtys.forEach(i => sum += (parseInt(i.value || '0', 10) || 0));
        if (sum === 0 && err) {
          err.classList.remove('visually-hidden');
        }
      });

      // Initial update on attach
      updateSubtotal();
      updateGroupSubtotals();
    }
  };
})(Drupal, once);