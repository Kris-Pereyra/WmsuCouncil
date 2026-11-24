<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in web server:   php -S 127.0.0.1:8000 router.php
 * (npm run dev / npm start use it for you.)
 *
 *   /api/...            -> api/index.php            (JSON API)
 *   /setup.php          -> setup.php                (one-time installer, unchanged)
 *   /uploads/avatars/.. -> profile pictures
 *   /assets/..          -> style.css + logo (used by setup.php)
 *   everything else     -> the built React app in dist/
 * The Apache .htaccess rules do not apply to the built-in server, so the private folders are blocked here.
 */

$root = __DIR__;
$path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
if ($path === '' || $path[0] !== '/' || str_contains($path, "\0") || str_contains($path, '..')) {
    http_response_code(400);
    exit('Bad request.');
}

function not_found(): bool
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    return true;
}

function send_file(string $file): bool
{
    static $types = [
        'html' => 'text/html; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8', 'json' => 'application/json; charset=utf-8', 'map' => 'application/json',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'txt' => 'text/plain; charset=utf-8',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!isset($types[$ext]) || !is_file($file)) {
        return not_found();
    }
    header('Content-Type: ' . $types[$ext]);
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

// Never serve source, config, SQL, tooling or private uploads.
if (preg_match('#^/(sql|includes|docs|src|scripts|node_modules|api/routes|\.git)(/|$)#i', $path)
    || preg_match('#^/(config\.php|package(-lock)?\.json|vite\.config\.js|router\.php|\.gitignore|\.htaccess|README\.md)$#i', $path)
    || preg_match('#^/uploads/payment-proofs(/|$)#i', $path)) {
    return not_found();
}

if ($path === '/api' || str_starts_with($path, '/api/')) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require $root . '/api/index.php';
    return true;
}

if ($path === '/setup.php') {
    require $root . '/setup.php';
    return true;
}

if (str_starts_with($path, '/uploads/avatars/') || str_starts_with($path, '/assets/')) {
    return send_file($root . $path);
}

// The built React app. "/" and "/dist/" both open it, so ../api/ links work from either.
$dist = $root . '/dist';
$rel = $path;
if (str_starts_with($rel, '/dist')) {
    $rel = substr($rel, 5) ?: '/';
}
if ($rel === '/' || $rel === '') {
    $rel = '/index.html';
}
if (!is_dir($dist)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Council Funds</title>'
       . '<body style="font:16px system-ui;max-width:40rem;margin:4rem auto;padding:0 1rem">'
       . '<h1>The front end is not built yet</h1>'
       . '<p>Run <code>npm run build</code> and reload, or use <code>npm run dev</code> for development.</p></body>';
    return true;
}
return send_file($dist . $rel);
