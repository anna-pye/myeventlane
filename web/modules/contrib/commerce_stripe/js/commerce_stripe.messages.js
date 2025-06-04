(($, Drupal) => {
  Drupal.commerceStripe = {
    displayError(errorMessage) {
      $('#payment-errors').html(
        Drupal.theme('commerceStripeError', errorMessage),
      );
    },
  };
  Drupal.theme.commerceStripeError = (message) =>
    $('<div class="payment-messages payment-messages--error"></div>').html(
      message,
    );
})(jQuery, Drupal);
