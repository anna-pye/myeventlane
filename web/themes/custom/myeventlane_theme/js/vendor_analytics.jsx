import React, { useEffect, useState } from 'react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
import ReactDOM from 'react-dom';

const VendorAnalytics = () => {
  const [data, setData] = useState([]);

  useEffect(() => {
    fetch('/jsonapi/custom/vendor-analytics')
      .then(res => res.json())
      .then(json => setData(json.data || []));
  }, []);

  return (
    <div className="vendor-analytics">
      <h2>Event Performance</h2>
      <ResponsiveContainer width="100%" height={300}>
        <BarChart data={data}>
          <XAxis dataKey="event" />
          <YAxis />
          <Tooltip />
          <Bar dataKey="rsvp" fill="#f26d5b" />
          <Bar dataKey="sales" fill="#6e7ef2" />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
};

const container = document.getElementById('vendor-analytics-app');
if (container) {
  ReactDOM.render(<VendorAnalytics />, container);
}
