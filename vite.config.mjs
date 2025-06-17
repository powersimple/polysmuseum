import { defineConfig } from 'vite';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
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
            .filter(file => file.endsWith('.js'))
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

      // Initial processing
      processLegacyJS();

      // Watch for changes
      const watcher = server.watcher;
      
      watcher.on('change', (filePath) => {
        console.log(`Detected change in file: ${filePath}`);

        if (filePath.endsWith('.php')) {
          console.log('PHP file changed. Reloading browser...');
          server.ws.send({ type: 'full-reload' });
        } else if (filePath.endsWith('.css')) {
          console.log('CSS file changed. Reloading browser...');
          server.ws.send({ type: 'full-reload' });
        } else if (filePath.includes('app/js/custom/') || filePath.includes('app/js/vendor/')) {
          console.log('Legacy JavaScript file changed. Reprocessing...');
          processLegacyJS();
          server.ws.send({ type: 'full-reload' });
        } else if (ENABLE_CESIUM && filePath.includes('cesium/')) {
          console.log('Cesium file changed. Reloading...');
          server.ws.send({ type: 'full-reload' });
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
    sourcemap: true
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
      },
    },
    hmr: {
      protocol: 'wss',
      host: 'localhost',
      overlay: true,
      clientPort: 3000,
    },
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
