<?php
declare(strict_types=1);

if (!is_post()) fail('Use POST to log out.', 405);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $c = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $c['path'], $c['domain'], (bool)$c['secure'], (bool)$c['httponly']);
}
session_destroy();
session_start();
out(['ok' => true, 'csrf' => csrf(), 'flash' => ['type' => 'ok', 'msg' => 'You have been logged out.'], 'redirect' => '/login']);
