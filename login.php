<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';

if (current_user()) {
    redirect('dashboard.php');
}
$next  = safe_next((string)($_GET['next'] ?? $_POST['next'] ?? ''));
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');
    $pdo   = db();

    $st = $pdo->prepare('SELECT account_id, password_hash, failed_attempts, locked_until FROM user_account WHERE email = ?');
    $st->execute([$email]);
    $acc = $st->fetch();

    if ($acc && $acc['locked_until'] && $acc['locked_until'] > now_str()) {
        $mins = max(1, (int)ceil((strtotime($acc['locked_until']) - time()) / 60));
        $error = "Too many failed attempts. Please try again in $mins minute(s).";
    } else {
        // Always run a password check so "no such email" and "wrong password" take the same time.
        $hash = $acc['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
        $ok   = password_verify($pass, $hash);
        if ($acc && $ok) {
            $pdo->prepare('UPDATE user_account SET failed_attempts = 0, locked_until = NULL, last_login = ? WHERE account_id = ?')
                ->execute([now_str(), $acc['account_id']]);
            session_regenerate_id(true);
            $_SESSION['account_id'] = (int)$acc['account_id'];
            redirect($next);
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
        $error = 'Invalid email or password.';
    }
}

public_start('Log in');
?>
<div class="wrap"><div class="authshell">
  <aside class="authside">
    <h2>Every peso, accounted for.</h2>
    <p>Log in to see the fund balance, project budgets and, if you are on the council, record collections and request funding for events.</p>
    <ul class="checks">
      <li><?= icon('wallet') ?>Live balance from the actual records</li>
      <li><?= icon('inbox') ?>Officers submit requests to their College Mayor</li>
      <li><?= icon('shield') ?>Role-based access for every account</li>
    </ul>
  </aside>
  <div class="card authcard">
    <h1>Welcome back</h1>
    <p class="muted">Log in with your school email, or the administrator email.</p>
    <?php show_flash(); ?>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <label><span>Email</span>
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
      <label><span>Password</span>
        <span class="password-field"><input type="password" name="password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span></label>
      <button class="btn block" type="submit">Log in</button>
    </form>
    <p class="muted small">New here? <a href="signup.php">Create an account</a> with your <code><?= e(ALLOWED_EMAIL_DOMAIN) ?></code> email.</p>
  </div>
</div></div>
<?php public_end();
