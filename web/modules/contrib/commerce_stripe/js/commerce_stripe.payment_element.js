/**
 * @file
 * Defines behaviors for the Stripe Payment Element payment method form.
 */

((Drupal, drupalSettings, once, Stripe) => {
  /**
   * Attaches the commerceStripePaymentElement behavior.
   */
  Drupal.behaviors.commerceStripePaymentElement = {
    attach(context) {
      if (
        !drupalSettings.commerceStripePaymentElement ||
        !drupalSettings.commerceStripePaymentElement.publishableKey
      ) {
        return;
      }

      const settings = drupalSettings.commerceStripePaymentElement;
      async function processStripeForm(item) {
        const stripeForm = item.closest('form');
        const primaryButton = stripeForm.querySelector(
          `input[data-drupal-selector="${settings.buttonId}"],button[data-drupal-selector="${settings.buttonId}"]`,
        );

        const stripeOptions = {
          apiVersion: settings.apiVersion,
        };
        // Create a Stripe client.
        const stripe = Stripe(settings.publishableKey, stripeOptions);

        // Show Stripe Payment Element form.
        if (settings.showPaymentForm) {
          // Create an instance of Stripe Elements.
          const elements = stripe.elements(settings.createElementsOptions);
          const paymentElement = elements.create(
            'payment',
            settings.paymentElementOptions,
          );
          paymentElement.mount(`#${settings.elementId}`);
          paymentElement.on('ready', () => {
            primaryButton.disabled = false;
            primaryButton.classList.remove('is-disabled');
          });

          stripeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            primaryButton.disabled = true;
            let stripeConfirm = stripe.confirmPayment;
            if (settings.intentType === 'setup') {
              stripeConfirm = stripe.confirmSetup;
            }
            try {
              const result = await stripeConfirm({
                elements,
                confirmParams: {
                  return_url: settings.returnUrl,
                },
                redirect: 'always',
              });
              if (result.error) {
                // Inform the user if there was an error.
                // Display the message error in the payment form.
                Drupal.commerceStripe.displayError(result.error.message);
                // Allow the customer to re-submit the form.
                primaryButton.disabled = false;
              }
            } catch (error) {
              // Inform the user if there was an error.
              // Display the message error in the payment form.
              Drupal.commerceStripe.displayError(error.message);
              // Allow the customer to re-submit the form.
              primaryButton.disabled = false;
            }
          });
        }
        // Confirm a payment by payment method.
        else {
          primaryButton.disabled = false;
          primaryButton.classList.remove('is-disabled');
          stripeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            primaryButton.disabled = true;
            let stripeConfirm = stripe.confirmPayment;
            if (settings.intentType === 'setup') {
              stripeConfirm = stripe.confirmSetup;
            }
            try {
              const result = await stripeConfirm({
                clientSecret: settings.clientSecret,
                confirmParams: {
                  return_url: settings.returnUrl,
                },
                redirect: 'always',
              });
              if (result.error) {
                // Inform the user if there was an error.
                // Display the message error in the payment form.
                Drupal.commerceStripe.displayError(result.error.message);
                // Allow the customer to re-submit the form.
                primaryButton.disabled = false;
              }
            } catch (error) {
              // Inform the user if there was an error.
              // Display the message error in the payment form.
              Drupal.commerceStripe.displayError(error.message);
              // Allow the customer to re-submit the form.
              primaryButton.disabled = false;
            }
          });
        }
      }
      once('stripe-processed', `#${settings.elementId}`, context).forEach(
        processStripeForm,
      );
    },
  };
})(Drupal, drupalSettings, once, window.Stripe);
