(($, Drupal, drupalSettings, Stripe) => {
  Drupal.behaviors.commerceStripeReview = {
    attach: function attach(context) {
      if (
        !drupalSettings.commerceStripe ||
        !drupalSettings.commerceStripe.publishableKey
      ) {
        return;
      }
      $(
        once(
          'stripe-processed',
          `[id^=${drupalSettings.commerceStripe.buttonId}]`,
          context,
        ),
      ).each((k, el) => {
        const $form = $(el).closest('form');
        const $primaryButton = $form.find(':input.button--primary');
        const stripe = Stripe(drupalSettings.commerceStripe.publishableKey, {
          betas: ['payment_intent_beta_3'],
        });

        let allowSubmit = false;
        $form.on('submit.stripe_3ds', () => {
          $form.find(':input.button--primary').prop('disabled', true);
          if (!allowSubmit) {
            $form.find(':input.button--primary').prop('disabled', true);
            const data = {
              payment_method: drupalSettings.commerceStripe.paymentMethod,
            };
            if (drupalSettings.commerceStripe.shipping) {
              data.shipping = drupalSettings.commerceStripe.shipping;
            }

            stripe
              .handleCardPayment(
                drupalSettings.commerceStripe.clientSecret,
                data,
              )
              .then((result) => {
                if (result.error) {
                  Drupal.commerceStripe.displayError(result.error.message);
                } else {
                  allowSubmit = true;
                  $primaryButton.prop('disabled', false);
                  $form.get(0).requestSubmit($primaryButton.get(0));
                }
              });
            return false;
          }
          return true;
        });

        if (drupalSettings.commerceStripe.autoSubmitReviewForm) {
          $primaryButton.prop('disabled', false);
          $form.get(0).requestSubmit($primaryButton.get(0));
        }
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      const $form = $(
        `[id^=${drupalSettings.commerceStripe.buttonId}]`,
        context,
      ).closest('form');
      if ($form.length === 0) {
        return;
      }
      $form.off('submit.stripe_3ds');
    },
  };
})(jQuery, Drupal, drupalSettings, window.Stripe);
