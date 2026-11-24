// npm run dev  ->  PHP back end + Vite dev server together. Ctrl+C stops both.
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { startPhp } from './php.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(import.meta.url);

let viteBin;
try {
  viteBin = path.join(path.dirname(require.resolve('vite/package.json')), 'bin', 'vite.js');
} catch {
  console.error('\nVite is not installed yet. Run:  npm install\n');
  process.exit(1);
}

console.log(`[php]  back end  -> http://127.0.0.1:${process.env.API_PORT || 8000}`);
const php = startPhp();
const vite = spawn(process.execPath, [viteBin], { cwd: root, stdio: 'inherit' });

const stop = () => { php.kill(); vite.kill(); };
process.on('SIGINT', () => { stop(); process.exit(0); });
process.on('SIGTERM', () => { stop(); process.exit(0); });
vite.on('exit', (code) => { php.kill(); process.exit(code ?? 0); });
php.on('exit', (code) => { if (code) { vite.kill(); process.exit(code); } });
