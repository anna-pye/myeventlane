import React from "react";
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend } from "recharts";

const data = window.drupalSettings.myeventlane_analytics.chartData;

const Chart = () => (
  <div className="event-analytics-chart">
    <h2>Event Sales & RSVPs</h2>
    <ResponsiveContainer width="100%" height={320}>
      <BarChart data={data} margin={{top: 12, right: 30, left: 0, bottom: 12}}>
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

export default Chart;

// Mounting
if (document.getElementById('event-analytics-chart-root')) {
  import("react").then(React => {
    import("react-dom").then(ReactDOM => {
      ReactDOM.render(<Chart />, document.getElementById('event-analytics-chart-root'));
    });
  });
}
