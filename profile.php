<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_login();
$pdo  = db();
$isAdmin = $user['level'] === 'admin';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'profile') {
        if ($isAdmin) {
            $name = trim((string)($_POST['display_name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) {
                $errors[] = 'Name is required (max 80 characters).';
            } else {
                $pdo->prepare('UPDATE user_account SET display_name = ? WHERE account_id = ?')->execute([$name, $user['account_id']]);
            }
        } else {
            $program = trim((string)($_POST['program'] ?? ''));
            $year    = (string)($_POST['year_level'] ?? '');
            $contact = trim((string)($_POST['contact_no'] ?? ''));
            $allowedPrograms = programs_by_college()[$user['college'] ?? ''] ?? [];
            if (!in_array($program, $allowedPrograms, true)) {
                $errors[] = 'Choose a program listed under your registered college.';
            }
            if (!preg_match('/^[1-6]$/', $year)) {
                $errors[] = 'Year level must be from 1 to 6.';
            }
            $normalizedContact = $contact === '' ? null : normalize_phone($contact);
            if ($contact !== '' && $normalizedContact === null) $errors[] = 'Contact number must be a Philippine mobile number such as 0917 123 4567.';
            if (!$errors) {
                $pdo->prepare('UPDATE student SET program = ?, year_level = ?, contact_no = ? WHERE student_id = ?')
                    ->execute([$program, (int)$year, $normalizedContact, $user['student_id']]);
            }
        }
        if (!$errors) {
            flash('ok', 'Profile updated.');
            redirect('profile.php');
        }
    }

    if ($do === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $new2 = (string)($_POST['new2'] ?? '');
        $st = $pdo->prepare('SELECT password_hash FROM user_account WHERE account_id = ?');
        $st->execute([$user['account_id']]);
        if (!password_verify($cur, (string)$st->fetchColumn())) {
            $errors[] = 'Your current password is not correct.';
        } elseif (strlen($new) < 8 || strlen($new) > 72) {
            $errors[] = 'New password must be 8 to 72 characters long.';
        } elseif (!hash_equals($new, $new2)) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            $pdo->prepare('UPDATE user_account SET password_hash = ? WHERE account_id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['account_id']]);
            session_regenerate_id(true);
            flash('ok', 'Password changed.');
            redirect('profile.php');
        }
    }
}

// Council history + this student's own payments
$terms = $payments = [];
if (!$isAdmin) {
    $st = $pdo->prepare('SELECT position, term_start, term_end FROM officer WHERE student_id = ? ORDER BY term_start DESC');
    $st->execute([$user['student_id']]);
    $terms = $st->fetchAll();
    $st = $pdo->prepare(
        'SELECT c.receipt_no, c.amount, c.collection_date, fs.source_name
           FROM collection c JOIN fund_source fs ON fs.source_id = c.source_id
          WHERE c.student_id = ? ORDER BY c.collection_date DESC, c.collection_id DESC LIMIT 50'
    );
    $st->execute([$user['student_id']]);
    $payments = $st->fetchAll();
}
$today = date('Y-m-d');

page_start('My profile', 'profile');
?>
<div class="head"><h1>My profile</h1></div>

<?php if ($errors): ?>
  <div class="flash err"><ul><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="two">
  <section class="card">
    <h2>Account</h2>
    <dl class="detail">
      <dt>Name</dt><dd><?= e($user['name']) ?></dd>
      <dt>Email</dt><dd><?= e($user['email']) ?></dd>
      <?php if (!$isAdmin): ?>
        <dt>Student No.</dt><dd><?= e($user['student_no']) ?></dd>
        <dt>Program</dt><dd><?= e($user['program']) ?></dd>
        <dt>Year level</dt><dd>Year <?= (int)$user['year_level'] ?></dd>
      <?php endif; ?>
      <dt>Access</dt><dd><span class="pill <?= e($user['level']) ?>"><?= $isAdmin ? 'Administrator' : ($user['level'] === 'mayor' ? 'College Mayor' : ($user['level'] === 'officer' ? 'Council officer' : 'Student')) ?></span></dd>
    </dl>
  </section>

  <section class="card">
    <h2>Student council</h2>
    <?php if ($isAdmin): ?>
      <p>You are the <strong>administrator</strong>. You assign College Mayors; each Mayor manages ordinary officer positions within their college.</p>
    <?php elseif ($user['positions']): ?>
      <p class="council yes">✔ You are part of the council.</p>
      <ul class="plain">
        <?php foreach ($user['positions'] as $pos): ?>
          <li><strong><?= e($pos['position']) ?></strong> <span class="muted">since <?= e($pos['term_start']) ?><?= $pos['term_end'] ? ', until ' . e($pos['term_end']) : '' ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="council no">You are not currently part of the council.</p>
    <?php endif; ?>
    <?php if (!$isAdmin): ?>
      <p class="muted small">Only the assigned College Mayor can manage ordinary officer positions.</p>
    <?php endif; ?>
    <?php if (count($terms) > ($user['positions'] ? count($user['positions']) : 0)): ?>
      <h3>Past terms</h3>
      <ul class="plain">
        <?php foreach ($terms as $t): if ($t['term_start'] <= $today && ($t['term_end'] === null || $t['term_end'] >= $today)) continue; ?>
          <li><?= e($t['position']) ?> <span class="muted"><?= e($t['term_start']) ?> → <?= e((string)$t['term_end']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<div class="two">
  <section class="card">
    <h2>Edit details</h2>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="do" value="profile">
      <?php if ($isAdmin): ?>
        <label><span>Display name</span><input name="display_name" maxlength="80" required value="<?= e($user['display_name'] ?? '') ?>"></label>
      <?php else: ?>
        <label><span>Program</span><select name="program" required><?= options_html($allowedPrograms ?? (programs_by_college()[$user['college'] ?? ''] ?? []), (string)$user['program']) ?></select></label>
        <label><span>Year level</span>
          <select name="year_level"><?php for ($y = 1; $y <= 6; $y++): ?><option value="<?= $y ?>" <?= (int)$user['year_level'] === $y ? 'selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?></select></label>
        <label><span>Contact number</span><input type="tel" name="contact_no" maxlength="20" value="<?= e($user['contact_no'] ?? '') ?>" placeholder="0917 123 4567"></label>
        <p class="muted small">Name, student number, college and email cannot be changed here. Program choices are limited to your registered college.</p>
      <?php endif; ?>
      <div><button class="btn" type="submit">Save</button></div>
    </form>
  </section>

  <section class="card">
    <h2>Change password</h2>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="do" value="password">
      <label><span>Current password</span><span class="password-field"><input type="password" name="current" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show current password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span></label>
      <label><span>New password</span><span class="password-field"><input type="password" name="new" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show new password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span></label>
      <label><span>Repeat new password</span><span class="password-field"><input type="password" name="new2" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show repeated new password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span></label>
      <div><button class="btn" type="submit">Change password</button></div>
    </form>
  </section>
</div>

<?php if (!$isAdmin): ?>
<section class="card">
  <h2>My payments</h2>
  <div class="tablewrap"><table>
    <thead><tr><th>Date</th><th>Receipt</th><th>Source</th><th class="num">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $r): ?>
      <tr><td class="nw"><?= e($r['collection_date']) ?></td><td class="nw"><?= e($r['receipt_no']) ?></td>
          <td><?= e($r['source_name']) ?></td><td class="num"><?= money($r['amount']) ?></td></tr>
    <?php endforeach; if (!$payments) echo '<tr><td colspan="4" class="empty">No payments recorded under your student number yet.</td></tr>'; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
<?php page_end();
