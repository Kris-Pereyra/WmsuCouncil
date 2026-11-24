// Starts only the PHP back end (API + uploads + setup page) with router.php.
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const port = process.env.API_PORT || 8000;
const bin = process.env.PHP_BIN || 'php';

export function startPhp({ quiet = false } = {}) {
  const child = spawn(bin, ['-S', `127.0.0.1:${port}`, '-t', root, path.join(root, 'router.php')], {
    cwd: root,
    stdio: quiet ? ['ignore', 'ignore', 'inherit'] : 'inherit',
  });
  child.on('error', (err) => {
    if (err.code === 'ENOENT') {
      console.error(`\n[php] Could not run "${bin}". Install PHP 8.1+ (or XAMPP) and either add it to your PATH or set PHP_BIN,\n` +
        `      e.g.  PHP_BIN="C:\\xampp\\php\\php.exe" npm run dev\n`);
    } else {
      console.error('[php]', err.message);
    }
    process.exit(1);
  });
  return child;
}

// Run directly (npm run dev:api)
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  console.log(`[php] back end on http://127.0.0.1:${port}  (API at /api/index.php)`);
  startPhp().on('exit', (code) => process.exit(code ?? 0));
}
