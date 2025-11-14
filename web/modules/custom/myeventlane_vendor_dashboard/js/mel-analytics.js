(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.analyticsToggle = {
    attach(context, settings) {
      once('chart-toggle', '.button--analytics', context).forEach(function (button) {
        $(button).on('click', function () {
          const $button = $(this);
          const eventNid = $button.data('event-nid');
          const chartId = `analytics-chart-${eventNid}`;
          const container = document.getElementById(chartId);

          $(container).toggle();

          if (!container.dataset.loaded) {
            const url = drupalSettings.path.baseUrl + 'vendor-dashboard/analytics/' + eventNid;
            fetch(url)
              .then(response => response.json())
              .then(json => {
                new Chart(container, {
                  type: 'bar',
                  data: {
                    labels: json.labels,
                    datasets: [{
                      label: 'RSVP / Sales',
                      data: json.values,
                      backgroundColor: '#f26d5b',
                    }]
                  },
                  options: {
                    responsive: true,
                    scales: {
                      y: { beginAtZero: true }
                    },
                    plugins: {
                      legend: { display: false }
                    }
                  }
                });
                container.dataset.loaded = 'true';
              })
              .catch(err => {
                console.error('Analytics fetch error:', err);
              });
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);