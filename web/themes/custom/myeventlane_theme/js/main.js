// js/main.js
// main.js — entry point for Vite
import '../scss/main.scss';

// Example: import your site scripts below
import './global.js';
import './theme.js';

// Attach ready hook to ensure DOM scripts execute safely
document.addEventListener('DOMContentLoaded', () => {
  console.log('✅ MyEventLane theme JS loaded');
});