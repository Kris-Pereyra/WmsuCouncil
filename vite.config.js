import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// The PHP back end (api/, uploads/, setup.php) runs on its own port; Vite forwards those URLs to it,
// so the browser only ever talks to one address and the PHP session cookie just works.
const apiPort = process.env.API_PORT || 8000;
const php = `http://127.0.0.1:${apiPort}`;

export default defineConfig({
  base: './',                      // relative asset URLs: the build works from any folder (XAMPP, php -S, ...)
  plugins: [react()],
  server: {
    port: Number(process.env.WEB_PORT) || 5173,
    proxy: {
      '/api': php,
      '/uploads': php,
      '/setup.php': php,
    },
  },
  build: {
    outDir: 'dist',
    assetsDir: 'static',            // not "assets": that folder name is already used by style.css + the logo
    emptyOutDir: true,
  },
});
