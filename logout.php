<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $c['path'], $c['domain'], (bool)$c['secure'], (bool)$c['httponly']);
    }
    session_destroy();
    session_start();
    flash('ok', 'You have been logged out.');
}
redirect('login.php');
