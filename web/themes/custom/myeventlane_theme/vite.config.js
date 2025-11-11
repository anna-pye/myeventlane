// vite.config.js
import { defineConfig } from 'vite';
import path from 'path';
import fs from 'fs';

const themePath = '/var/www/html/web/themes/custom/myeventlane_theme';

// Detect if we’re running in DDEV
const inDdev = fs.existsSync('/var/www/html/.ddev/ssl/myeventlane.ddev.site.key');
const host = inDdev ? 'myeventlane.ddev.site' : 'localhost';

// SSL paths for DDEV (falls back to localhost)
const keyPath = inDdev
  ? '/var/www/html/.ddev/ssl/myeventlane.ddev.site.key'
  : path.resolve(process.env.HOME, '.ddev/ssl/myeventlane.ddev.site.key');
const certPath = inDdev
  ? '/var/www/html/.ddev/ssl/myeventlane.ddev.site.crt'
  : path.resolve(process.env.HOME, '.ddev/ssl/myeventlane.ddev.site.crt');

// Read HTTPS certs if available
const httpsConfig =
  fs.existsSync(keyPath) && fs.existsSync(certPath)
    ? { key: fs.readFileSync(keyPath), cert: fs.readFileSync(certPath) }
    : false;

export default defineConfig({
  root: themePath,
  base: '/themes/custom/myeventlane_theme/',

  build: {
    outDir: path.resolve(themePath, 'dist'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        // 🔥 Ensure Vite always processes this entry point
        main: path.resolve(themePath, 'js/main.js'),
      },
      output: {
        entryFileNames: 'js/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
  },

  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    https: httpsConfig,
    origin: `https://${host}:5173`,
    hmr: {
      host,
      protocol: 'wss',
    },
    fs: {
      allow: [themePath, '/var/www/html'],
    },
  },

  css: {
    preprocessorOptions: {
      scss: {
        additionalData: `@use "sass:color"; @use "settings/variables" as vars;`,
      },
    },
  },
});