// dashboard-analytics.js
(function ($, Drupal) {
  Drupal.behaviors.vendorDashboardAnalytics = {
    attach: function (context, settings) {
      $('[class^=js-analytics-btn-]', context).once('vendor-analytics').on('click', function () {
        var nid = $(this).data('event-nid');
        $('#analytics-chart-' + nid).toggle();
        // TODO: Replace with AJAX chart/data fetching if needed.
        $('#analytics-chart-' + nid).html('<div style="padding:1em;">[Chart Placeholder for event ' + nid + ']</div>');
      });
    }
  };
})(jQuery, Drupal);
