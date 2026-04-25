import { defineConfig } from 'vite';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { createServer as createHttpsServer } from 'https';
import { WebSocketServer } from 'ws';
import cesium from 'vite-plugin-cesium';

const proxyTarget = 'https://polys';
const serverName = 'obi-wan-v';
const themeDir = path.resolve(__dirname);

// Enable/disable Cesium integration
const ENABLE_CESIUM = true;

// Function to process legacy JavaScript files
function processLegacyJS() {
    try {
        // Process custom JS files
        const customDir = path.join(themeDir, 'app/js/custom');
        const vendorDir = path.join(themeDir, 'app/js/vendor');
        
        // Process custom files
        console.log('Processing custom JavaScript...');
        const customFiles = fs.readdirSync(customDir)
            .filter(file => file.endsWith('.js') && file !== 'hmr-client.js') // Exclude HMR client
            .map(file => path.join(customDir, file));
        
        const customContent = customFiles
            .map(file => fs.readFileSync(file, 'utf8'))
            .join('\n');
        
        fs.writeFileSync(path.join(themeDir, 'main.js'), customContent, 'utf8');
        execSync(`npx terser ${path.join(themeDir, 'main.js')} -o ${path.join(themeDir, 'main.min.js')} --compress --mangle --source-map "root='${themeDir}',url='main.min.js.map'"`, { stdio: 'inherit' });
        
        // Process vendor files
        console.log('Processing vendor JavaScript...');
        const vendorFiles = fs.readdirSync(vendorDir)
            .filter(file => file.endsWith('.js'))
            .map(file => path.join(vendorDir, file));
        
        const vendorContent = vendorFiles
            .map(file => fs.readFileSync(file, 'utf8'))
            .join('\n');
        
        fs.writeFileSync(path.join(themeDir, 'vendor.js'), vendorContent, 'utf8');
        execSync(`npx terser ${path.join(themeDir, 'vendor.js')} -o ${path.join(themeDir, 'vendor.min.js')} --compress --mangle --source-map "root='${themeDir}',url='vendor.min.js.map'"`, { stdio: 'inherit' });
        
        console.log('JavaScript processing completed.');
    } catch (error) {
        console.error(`Error processing JavaScript: ${error.message}`);
    }
}

// Configure plugins based on enabled features
const plugins = [
  {
    name: 'watch-and-compile',
    configureServer(server) {
      console.log('Vite server started. Watching files...');
      const phpGlob = path.join(themeDir, '**/*.php');
      const jsGlob = path.join(themeDir, 'app/js/**/*.js');
      const cssFile = path.join(themeDir, 'style.css');
      const cesiumGlob = ENABLE_CESIUM ? path.join(themeDir, 'cesium/**/*') : null;

      console.log(`Watching JS files at: ${jsGlob}`);
      console.log(`Watching PHP files at: ${phpGlob}`);
      console.log(`Watching compiled CSS at: ${cssFile}`);
      if (ENABLE_CESIUM) {
        console.log(`Watching Cesium files at: ${cesiumGlob}`);
      }

      // ── Livereload WebSocket server on port 3001 ──────────────────────
      // Separate from Vite's proxy so the browser can always reach it.
      // Uses the same SSL certs as Vite for wss:// from the browser.
      const lrHttps = createHttpsServer({
        key: fs.readFileSync(path.join(themeDir, 'localhost.key')),
        cert: fs.readFileSync(path.join(themeDir, 'localhost.crt')),
      });
      const lrWss = new WebSocketServer({ server: lrHttps });
      lrWss.on('connection', () => {
        console.log('[livereload] Browser connected on :3001');
      });
      lrHttps.listen(3001, '0.0.0.0', () => {
        console.log('[livereload] WS server ready on wss://*:3001');
      });

      let reloadTimer = null;
      function notifyBrowsers() {
        // Debounce: SCSS compilation triggers style.css, style.min.css, .map files
        // in quick succession. Wait 500ms for them to settle, then reload once.
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
          const msg = JSON.stringify({ type: 'full-reload' });
          lrWss.clients.forEach((client) => {
            if (client.readyState === 1) client.send(msg);
          });
          console.log('[livereload] Reload sent to', lrWss.clients.size, 'browser(s)');
        }, 500);
      }
      // ── End livereload server ─────────────────────────────────────────

      // Initial processing
      processLegacyJS();

      // Watch for changes
      const watcher = server.watcher;

      watcher.on('change', (filePath) => {
        console.log(`Detected change in file: ${filePath}`);

        if (filePath.endsWith('.php')) {
          console.log('PHP file changed. Reloading browser...');
          notifyBrowsers();
        } else if (filePath.endsWith('.css')) {
          console.log('CSS file changed. Reloading browser...');
          notifyBrowsers();
        } else if (filePath.includes('app/js/custom/') || filePath.includes('app/js/vendor/')) {
          console.log('Legacy JavaScript file changed. Reprocessing...');
          processLegacyJS();
          notifyBrowsers();
        } else if (ENABLE_CESIUM && filePath.includes('cesium/')) {
          console.log('Cesium file changed. Reloading...');
          notifyBrowsers();
        }
      });

      watcher.on('error', (error) => {
        console.error('Watcher error:', error);
      });
    },
  },
];

// Add Cesium plugin if enabled
if (ENABLE_CESIUM) {
  plugins.unshift(cesium());
}

export default defineConfig({
  base: '/',
  build: {
    outDir: 'build',
    rollupOptions: {
      input: {
        cesium: 'cesium/cesium.js',
        modern: 'app/js/modern/main.js'
      }
    },
    target: 'esnext',
    sourcemap: false
  },
  css: {
    devSourcemap: true,
  },
  server: {
    host: '0.0.0.0',
    port: 3000,
    cors: true,
    https: {
      key: fs.readFileSync('./localhost.key'),
      cert: fs.readFileSync('./localhost.crt'),
    },
   proxy: {
  '/': {
    target: proxyTarget,
    changeOrigin: true,
    secure: false,
    rewrite: (path) => path.replace(`/${serverName}`, ''),
        headers: {
          'X-Forwarded-Proto': 'https',
          'X-Forwarded-Host': `${serverName}:3000`,
        }
      },
    },
    hmr: false,
    watch: {
      include: [
        'style.css',
        '**/*.php',
        '**/*.js',
        ...(ENABLE_CESIUM ? ['cesium/**/*'] : [])
      ],
      fs: {
        allow: ['..']
      }
    }
  },
  optimizeDeps: {
    include: ['cesium']
  },
  resolve: {
    alias: {
      '@': path.resolve(themeDir, 'cesium')
    }
  },
  plugins,
});
