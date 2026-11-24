<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';

if (current_user()) {
    redirect('dashboard.php');
}
$email = (string)($_SESSION['pending_email'] ?? '');
if ($email === '') {
    flash('err', 'Start by filling in the sign-up form.');
    redirect('signup.php');
}
$pdo = db();
$st = $pdo->prepare('SELECT * FROM pending_signup WHERE email = ?');
$st->execute([$email]);
$p = $st->fetch();
if (!$p) {
    unset($_SESSION['pending_email'], $_SESSION['dev_code']);
    flash('err', 'Your sign-up expired. Please fill in the form again.');
    redirect('signup.php');
}

$errors = [];
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'resend') {
        if (strtotime($p['last_sent_at']) > time() - 60) {
            $errors[] = 'Please wait a minute before asking for a new code.';
        } else {
            $code = new_code();
            $pdo->prepare('UPDATE pending_signup SET code_hash = ?, attempts = 0, last_sent_at = ?, expires_at = ? WHERE pending_id = ?')
                ->execute([password_hash($code, PASSWORD_DEFAULT), now_str(),
                           date('Y-m-d H:i:s', time() + CODE_TTL_MINUTES * 60), $p['pending_id']]);
            [$ok, $err] = send_code_email($email, $code);
            if ($ok) {
                $info = 'A new code was sent.';
                if (MAIL_MODE !== 'smtp') {
                    $_SESSION['dev_code'] = $code;
                }
            } else {
                $errors[] = 'We could not send the email. ' . $err;
            }
        }
    }

    if ($do === 'verify') {
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        if ($p['expires_at'] < now_str()) {
            $errors[] = 'That code has expired. Ask for a new one below.';
        } elseif ((int)$p['attempts'] >= 5) {
            $pdo->prepare('DELETE FROM pending_signup WHERE pending_id = ?')->execute([$p['pending_id']]);
            unset($_SESSION['pending_email'], $_SESSION['dev_code']);
            flash('err', 'Too many wrong codes. Please sign up again.');
            redirect('signup.php');
        } elseif (!password_verify($code, $p['code_hash'])) {
            $pdo->prepare('UPDATE pending_signup SET attempts = attempts + 1 WHERE pending_id = ?')->execute([$p['pending_id']]);
            $left = 4 - (int)$p['attempts'];
            $errors[] = 'That code is not correct. ' . ($left > 0 ? "$left tries left." : 'No tries left.');
        } else {
            // Code is right: now (and only now) create the student + login account.
            $pdo->beginTransaction();
            try {
                $s = $pdo->prepare('SELECT student_id, first_name, last_name FROM student WHERE student_no = ?');
                $s->execute([$p['student_no']]);
                if ($row = $s->fetch()) {
                    $has = $pdo->prepare('SELECT 1 FROM user_account WHERE student_id = ?');
                    $has->execute([$row['student_id']]);
                    if ($has->fetchColumn()
                        || mb_strtolower($row['first_name']) !== mb_strtolower($p['first_name'])
                        || mb_strtolower($row['last_name']) !== mb_strtolower($p['last_name'])) {
                        throw new RuntimeException('That student number is no longer available. Ask the administrator.');
                    }
                    $sid = (int)$row['student_id'];
                } else {
                    $pdo->prepare('INSERT INTO student (student_no, first_name, middle_name, last_name, suffix, program, college, year_level, school_year, contact_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$p['student_no'], $p['first_name'], $p['middle_name'], $p['last_name'], $p['suffix'], $p['program'], $p['college'], $p['year_level'], $p['school_year'], $p['contact_no']]);
                    $sid = (int)$pdo->lastInsertId();
                }
                $pdo->prepare('UPDATE student SET middle_name = ?, suffix = ?, program = ?, college = ?, year_level = ?, school_year = ?, contact_no = ? WHERE student_id = ?')
                    ->execute([$p['middle_name'], $p['suffix'], $p['program'], $p['college'], $p['year_level'], $p['school_year'], $p['contact_no'], $sid]);

                $collegeQ = $pdo->prepare('SELECT college_id FROM college WHERE college_name = ?');
                $collegeQ->execute([$p['college']]);
                $collegeId = (int)$collegeQ->fetchColumn();
                if (!$collegeId) throw new RuntimeException('The selected college is not configured. Please contact the administrator.');
                $programQ = $pdo->prepare('SELECT program_id FROM program_catalog WHERE college_id = ? AND program_name = ?');
                $programQ->execute([$collegeId, $p['program']]);
                $programId = $programQ->fetchColumn();
                $enroll = $pdo->prepare("INSERT INTO student_enrollment (student_id, college_id, program_id, program_name_snapshot, year_level, school_year, enrollment_status, verified_at) VALUES (?, ?, ?, ?, ?, ?, 'Verified', ?) ON DUPLICATE KEY UPDATE college_id=VALUES(college_id), program_id=VALUES(program_id), program_name_snapshot=VALUES(program_name_snapshot), year_level=VALUES(year_level), enrollment_status='Verified', verified_at=VALUES(verified_at)");
                $enroll->execute([$sid, $collegeId, $programId ?: null, $p['program'], $p['year_level'], $p['school_year'], now_str()]);
                $pdo->prepare("INSERT INTO user_account (student_id, email, password_hash, role, verified_at) VALUES (?, ?, ?, 'student', ?)")
                    ->execute([$sid, $p['email'], $p['password_hash'], now_str()]);
                $pdo->prepare('INSERT IGNORE INTO payment_requirement (fee_code,fee_name,amount,school_year) VALUES (?,?,?,?)')->execute(['INSURANCE','Student Insurance',70.00,$p['school_year']]);
                $pdo->prepare('INSERT IGNORE INTO payment_requirement (fee_code,fee_name,amount,school_year) VALUES (?,?,?,?)')->execute(['CSC','College Student Council Payment',200.00,$p['school_year']]);
                $feeQ = $pdo->prepare('SELECT requirement_id,amount FROM payment_requirement WHERE school_year=? AND is_active=1');
                $feeQ->execute([$p['school_year']]);
                $feePay = $pdo->prepare('INSERT IGNORE INTO student_payment (student_id,college_id,requirement_id,school_year,amount_due) VALUES (?,?,?,?,?)');
                foreach ($feeQ->fetchAll() as $fee) $feePay->execute([$sid,$collegeId,$fee['requirement_id'],$p['school_year'],$fee['amount']]);
                $pdo->prepare('DELETE FROM pending_signup WHERE pending_id = ?')->execute([$p['pending_id']]);
                $pdo->commit();
                unset($_SESSION['pending_email'], $_SESSION['dev_code']);
                flash('ok', 'Email verified — your account is ready. You can log in now.');
                redirect('login.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $errors[] = friendly_error($ex);
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                $errors[] = $ex->getMessage();
            }
        }
    }
}

// Mask the address a little: ab***@wmsu.edu.ph
[$local, $domain] = explode('@', $email, 2);
$masked = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;

public_start('Verify email');
?>
<div class="wrap"><div class="authwrap"><div class="card authcard">
  <h1>Check your email</h1>
  <p class="muted">We sent a 6-digit code to <strong><?= e($masked) ?></strong>. It works for <?= (int)CODE_TTL_MINUTES ?> minutes.</p>

  <?php if (MAIL_MODE !== 'smtp' && !empty($_SESSION['dev_code'])): ?>
    <div class="devnote"><strong>Test mode:</strong> email sending is turned off, so your code is shown here:
      <span class="devcode"><?= e($_SESSION['dev_code']) ?></span>
      <small>Set <code>MAIL_MODE</code> to <code>smtp</code> in <code>config.php</code> to send real emails.</small></div>
  <?php endif; ?>

  <?php if ($info): ?><div class="flash ok"><?= e($info) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="flash err"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div><?php endif; ?>

  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="do" value="verify">
    <label><span>Verification code</span>
      <input class="codein" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus autocomplete="one-time-code" placeholder="••••••"></label>
    <button class="btn" type="submit">Verify and create account</button>
  </form>

  <form method="post" class="inline-form">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="do" value="resend">
    <span class="muted small">Didn't get it?</span>
    <button class="linkbtn" type="submit">Send a new code</button>
    <span class="muted small">·</span>
    <a class="small" href="signup.php">Use a different email</a>
  </form>
</div></div></div>
<?php public_end();
