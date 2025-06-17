import { defineConfig } from 'vite';
import { resolve } from 'path';
import cesium from 'vite-plugin-cesium';

export default defineConfig({
  plugins: [cesium()],
  root: './',
  base: './',
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html'),
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    hmr: {
      host: 'localhost',
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
}); 