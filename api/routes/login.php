<?php
declare(strict_types=1);

if (!is_post()) fail('Use POST to log in.', 405);
need_db();
if (current_user()) {
    out(['ok' => true]);
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$pass  = (string)($_POST['password'] ?? '');
$pdo   = db();

$st = $pdo->prepare('SELECT account_id, password_hash, failed_attempts, locked_until FROM user_account WHERE email = ?');
$st->execute([$email]);
$acc = $st->fetch();

if ($acc && $acc['locked_until'] && $acc['locked_until'] > now_str()) {
    $mins = max(1, (int)ceil((strtotime($acc['locked_until']) - time()) / 60));
    fail("Too many failed attempts. Please try again in $mins minute(s).", 422);
}

// Always run a password check so "no such email" and "wrong password" take the same time.
$hash = $acc['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
$ok   = password_verify($pass, $hash);
if ($acc && $ok) {
    $pdo->prepare('UPDATE user_account SET failed_attempts = 0, locked_until = NULL, last_login = ? WHERE account_id = ?')
        ->execute([now_str(), $acc['account_id']]);
    session_regenerate_id(true);
    $_SESSION['account_id'] = (int)$acc['account_id'];
    out(['ok' => true, 'csrf' => csrf()]);
}
if ($acc) {
    $fails = (int)$acc['failed_attempts'] + 1;
    if ($fails >= 5) {
        $pdo->prepare('UPDATE user_account SET failed_attempts = 0, locked_until = ? WHERE account_id = ?')
            ->execute([date('Y-m-d H:i:s', time() + 600), $acc['account_id']]);
    } else {
        $pdo->prepare('UPDATE user_account SET failed_attempts = ? WHERE account_id = ?')
            ->execute([$fails, $acc['account_id']]);
    }
}
fail('Invalid email or password.', 422);
