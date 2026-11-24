<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/includes/migrate.php';
session_name('CF_SESSID');
session_start();

function run_sql_file(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    $sql = preg_replace('/--[^\r\n]*/', '', $sql);                                 
    $sql = preg_replace('/CREATE TABLE (?!IF NOT EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $sql);
    $sql = preg_replace('/CREATE VIEW /i', 'CREATE OR REPLACE VIEW ', $sql);       
    foreach (explode(';', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(16));
}

$errors = [];
$log = [];
$done = false;
$locked = false;
$v = ['email' => '', 'name' => 'Administrator'];

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $has = fn(string $table): bool => (bool)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$table'"
    )->fetchColumn();

    if ($has('user_account')) {
        $locked = (bool)$pdo->query('SELECT COUNT(*) FROM `' . DB_NAME . "`.user_account WHERE role = 'admin'")->fetchColumn();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
        if (!hash_equals($_SESSION['setup_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Invalid form token. Refresh the page and try again.');
        }
        $v['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
        $v['name']  = trim((string)($_POST['name'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');

        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 120) $errors[] = 'Enter a valid administrator email.';
        if ($v['name'] === '' || mb_strlen($v['name']) > 80) $errors[] = 'Enter the administrator name (max 80 characters).';
        if (strlen($pass) < 8 || strlen($pass) > 72) $errors[] = 'Password must be 8 to 72 characters long.';
        elseif (!hash_equals($pass, $pass2)) $errors[] = 'The two passwords do not match.';

        if (!$errors) {
            run_sql_file($pdo, __DIR__ . '/sql/schema.sql');
            $log[] = 'Database, tables and views are ready.';
            $pdo->exec('USE `' . DB_NAME . '`');
            run_sql_file($pdo, __DIR__ . '/sql/auth.sql');
            migrate_schema($pdo); 
            $log[] = 'Login and funding-request tables are ready.';

            $pdo->prepare("INSERT INTO user_account (student_id, email, display_name, password_hash, role, verified_at)
                           VALUES (NULL, ?, ?, ?, 'admin', ?)")
                ->execute([$v['email'], $v['name'], password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            $log[] = 'Administrator account created.';

            if (!empty($_POST['sample']) && is_file(__DIR__ . '/sql/sample_data.sql')) {
                if ((int)$pdo->query('SELECT COUNT(*) FROM student')->fetchColumn() === 0) {
                    run_sql_file($pdo, __DIR__ . '/sql/sample_data.sql');
                    $log[] = 'Demo data loaded.';
                } else {
                    $log[] = 'Demo data skipped — students already exist.';
                }
            }
            $done = true;
            $locked = true;
        }
    }
} catch (Throwable $ex) {
    $errors[] = 'Error: ' . $ex->getMessage();
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup · Council Funds</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Public+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/style.css?v=2"></head>
<body class="pub"><main class="wrap"><div class="authwrap"><div class="card authcard wide-card">
<h1>Council Funds setup</h1>
<?php foreach ($errors as $er): ?><div class="flash err"><?= htmlspecialchars($er) ?></div><?php endforeach; ?>
<?php foreach ($log as $l): ?><div class="flash ok"><?= htmlspecialchars($l) ?></div><?php endforeach; ?>

<?php if ($done): ?>
  <p>All set. <strong>For safety, delete <code>setup.php</code></strong> from the folder when you are done.</p>
  <p><a class="btn" href="login.php">Go to login</a></p>
<?php elseif ($locked): ?>
  <p>Setup has already been completed (an administrator exists), so it is locked.</p>
  <p>To protect your data, please delete <code>setup.php</code> from the folder.</p>
  <p><a class="btn" href="login.php">Go to login</a></p>
<?php else: ?>
  <p class="muted">This creates the <code><?= DB_NAME ?></code> database on <code><?= DB_HOST ?></code> and the first administrator account. It is safe to run again if something went wrong.</p>
  <form method="post" class="form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['setup_csrf']) ?>">
    <label class="wide"><span>Administrator email <b>*</b></span><input type="email" name="email" required value="<?= htmlspecialchars($v['email']) ?>"><small>Any email works for the administrator.</small></label>
    <label class="wide"><span>Administrator name <b>*</b></span><input name="name" maxlength="80" required value="<?= htmlspecialchars($v['name']) ?>"></label>
    <label><span>Password <b>*</b></span><span class="password-field"><input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span><small>At least 8 characters</small></label>
    <label><span>Confirm password <b>*</b></span><span class="password-field"><input type="password" name="password2" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show confirm password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span></label>
    <label class="inline wide"><input type="checkbox" name="sample" value="1"> Also load demo data (students, budget, projects, collections, expenses)</label>
    <div class="actions wide"><button class="btn" type="submit">Run setup</button></div>
  </form>
<?php endif; ?>
</div></div></main></body></html>
