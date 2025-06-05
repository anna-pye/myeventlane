// web/themes/custom/myeventlane/js/theme.js
(function ($, Drupal) {
  Drupal.behaviors.myeventlaneUI = {
    attach: function (context, settings) {
      // Add a subtle hover animation to buttons.
      $('.button', context).once('animate').on('mouseenter', function () {
        $(this).css('transform', 'scale(1.03)');
      }).on('mouseleave', function () {
        $(this).css('transform', 'scale(1)');
      });
    }
  };
})(jQuery, Drupal);
