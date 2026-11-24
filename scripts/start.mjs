// npm start  ->  build the React app if needed, then serve everything from PHP on one port.
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { startPhp } from './php.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);

function build() {
  return new Promise((resolve, reject) => {
    let viteBin;
    try {
      viteBin = path.join(path.dirname(require.resolve('vite/package.json')), 'bin', 'vite.js');
    } catch {
      return reject(new Error('Vite is not installed yet. Run:  npm install'));
    }
    const p = spawn(process.execPath, [viteBin, 'build'], { cwd: root, stdio: 'inherit' });
    p.on('exit', (code) => (code === 0 ? resolve() : reject(new Error('Build failed.'))));
  });
}

try {
  if (!existsSync(path.join(root, 'dist', 'index.html'))) {
    console.log('[build] dist/ not found — building the front end first…');
    await build();
  }
} catch (err) {
  console.error(`\n${err.message}\n`);
  process.exit(1);
}

const port = process.env.API_PORT || 8000;
console.log(`\nCouncil Funds is running on  http://127.0.0.1:${port}\n`);
startPhp().on('exit', (code) => process.exit(code ?? 0));
