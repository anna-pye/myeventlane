import React from "react";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend } from "recharts";

const data = window.drupalSettings.myeventlane_analytics.chartData;

const VendorEventAnalyticsChart = () => (
  <div className="vendor-event-analytics-chart">
    <h2>Your Event Performance</h2>
    <ResponsiveContainer width="100%" height={300}>
      <BarChart data={data} margin={{ top: 16, right: 24, left: 0, bottom: 16 }}>
        <XAxis dataKey="event" />
        <YAxis />
        <Tooltip />
        <Legend />
        <Bar dataKey="tickets" fill="#f26d5b" name="Tickets Sold" />
        <Bar dataKey="rsvps" fill="#6e7ef2" name="RSVPs" />
      </BarChart>
    </ResponsiveContainer>
  </div>
);

export default VendorEventAnalyticsChart;

// Mount for Drupal
if (document.getElementById('vendor-event-analytics-chart-root')) {
  import("react").then(React => {
    import("react-dom").then(ReactDOM => {
      ReactDOM.render(<VendorEventAnalyticsChart />, document.getElementById('vendor-event-analytics-chart-root'));
    });
  });
}
