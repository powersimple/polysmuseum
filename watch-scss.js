const nodemon = require('nodemon');
const { execSync } = require('child_process');
const path = require('path');
const WebSocket = require('ws');

const themeDir = path.resolve(__dirname);
const scssFile = path.join(themeDir, 'app/scss/style.scss');
const cssFile = path.join(themeDir, 'style.css');
const minCssFile = path.join(themeDir, 'style.min.css');

let compileTimeout;
let ws;

// Connect to Vite's WebSocket server
function connectWebSocket() {
  ws = new WebSocket('wss://localhost:3000', {
    rejectUnauthorized: false
  });

  ws.on('open', () => {
    console.log('Connected to Vite WebSocket server');
  });

  ws.on('error', (error) => {
    console.error('WebSocket error:', error);
    // Try to reconnect in 5 seconds
    setTimeout(connectWebSocket, 5000);
  });
}

connectWebSocket();

function compileSCSS() {
  try {
    console.log('Compiling SCSS...');
    execSync(`npx sass ${scssFile} ${cssFile} --source-map`, { stdio: 'inherit' });
    execSync(`npx sass ${scssFile} ${minCssFile} --style=compressed --source-map`, { stdio: 'inherit' });
    console.log('SCSS compilation completed.');
    
    // Send reload command through WebSocket
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ type: 'full-reload' }));
      console.log('Sent reload command to browser');
    }
  } catch (error) {
    console.error(`Error compiling SCSS: ${error.message}`);
  }
}

// Initial compilation
compileSCSS();

// Start nodemon to watch SCSS files
nodemon({
  watch: path.join(themeDir, 'app/scss'),
  ext: 'scss',
  exec: 'echo "Change detected"',
  ignore: ['*.css', '*.css.map'],
}).on('restart', () => {
  console.log('SCSS changes detected');
  compileSCSS();
});

// Handle graceful shutdown
process.once('SIGTERM', function () {
  nodemon.emit('quit');
  process.exit(0);
});

process.once('SIGINT', function () {
  nodemon.emit('quit');
  process.exit(0);
}); 