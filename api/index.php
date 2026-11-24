<?php
declare(strict_types=1);

/**
 * Front controller for the JSON API.
 *   GET  api/index.php?r=dashboard
 *   POST api/index.php?r=requests            (JSON body, X-CSRF-Token header)
 * One file per area lives in api/routes/. Route "payment-proof" maps to routes/payment_proof.php.
 */

require __DIR__ . '/bootstrap.php';

$route = (string)($_GET['r'] ?? 'session');
if (!preg_match('/^[a-z][a-z_\-]*$/', $route)) {
    fail('Unknown API route.', 404);
}
$file = __DIR__ . '/routes/' . str_replace('-', '_', $route) . '.php';
if (!is_file($file)) {
    fail('Unknown API route.', 404);
}
require $file;
// Every route ends with out()/fail()/done(); reaching here means a route forgot to answer.
fail('The server did not return a result.', 500);
