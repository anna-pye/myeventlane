(function ($, Drupal) {
  Drupal.behaviors.scrollToContent = {
    attach: function (context, settings) {
      $('.search-form', context).once('scroll-setup').each(function () {
        $(this).on('submit', function () {
          setTimeout(() => {
            const target = document.querySelector('.event-listings');
            if (target) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }, 100); // Let the results load
        });
      });
    }
  };
})(jQuery, Drupal);