<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$f = ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'suffix' => '', 'student_no' => '', 'college' => '', 'program' => '',
      'year_level' => '', 'school_year' => '', 'contact_no' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($f as $k => $_) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['email'] = strtolower($f['email']);
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if (!valid_school_email($f['email'])) {
        $errors[] = 'Use your school email ending in ' . ALLOWED_EMAIL_DOMAIN . '.';
    }
    if (!preg_match('/^[A-Za-z0-9\-]{4,20}$/', $f['student_no'])) {
        $errors[] = 'Student number must be 4–20 letters, numbers or dashes.';
    }
    if (!valid_person_name($f['first_name'])) {
        $errors[] = 'First name is required (letters, spaces, hyphens and apostrophes; max 50 characters).';
    }
    if ($f['middle_name'] !== '' && !valid_person_name($f['middle_name'])) {
        $errors[] = 'Middle name may only use letters, spaces, hyphens and apostrophes (max 50 characters). Leave it blank if you have none.';
    }
    if (!valid_person_name($f['last_name'])) {
        $errors[] = 'Last name is required (letters, spaces, hyphens and apostrophes; max 50 characters).';
    }
    if (!in_array($f['college'], colleges_list(), true)) {
        $errors[] = 'Please choose your college from the list.';
    }
    if (!isset(programs_by_college()[$f['college']]) || !in_array($f['program'], programs_by_college()[$f['college']], true)) {
        $errors[] = 'Please choose a program belonging to your selected college.';
    }
    if ($f['suffix'] !== '' && !in_array($f['suffix'], ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'Other'], true)) {
        $errors[] = 'Please choose a valid suffix.';
    }
    if (!preg_match('/^[1-6]$/', $f['year_level'])) {
        $errors[] = 'Year level must be from 1 to 6.';
    }
    if (!in_array($f['school_year'], school_year_options(), true)) {
        $errors[] = 'Please choose your school year.';
    }
    $phone = normalize_phone($f['contact_no']);
    if ($phone === null) {
        $errors[] = 'Enter a Philippine mobile number such as 0917 123 4567.';
    }
    if (strlen($pass) < 8 || strlen($pass) > 72) {
        $errors[] = 'Password must be 8 to 72 characters long.';
    } elseif (!hash_equals($pass, $pass2)) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->prepare('DELETE FROM pending_signup WHERE expires_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);

        $st = $pdo->prepare('SELECT 1 FROM user_account WHERE email = ?');
        $st->execute([$f['email']]);
        if ($st->fetchColumn()) {
            $errors[] = 'That email already has an account. Please log in instead.';
        }

        $st = $pdo->prepare('SELECT student_id, first_name, last_name FROM student WHERE student_no = ?');
        $st->execute([$f['student_no']]);
        if ($s = $st->fetch()) {
            $has = $pdo->prepare('SELECT 1 FROM user_account WHERE student_id = ?');
            $has->execute([$s['student_id']]);
            if ($has->fetchColumn()) {
                $errors[] = 'That student number already has an account.';
            } elseif (mb_strtolower($s['first_name']) !== mb_strtolower($f['first_name'])
                   || mb_strtolower($s['last_name']) !== mb_strtolower($f['last_name'])) {
                $errors[] = 'That student number is already on file under a different name. Check it, or ask the administrator.';
            }
        }

        $st = $pdo->prepare('SELECT last_sent_at FROM pending_signup WHERE email = ?');
        $st->execute([$f['email']]);
        $last = $st->fetchColumn();
        if (!$errors && $last && strtotime((string)$last) > time() - 60) {
            $errors[] = 'A code was just sent. Please wait a minute before trying again.';
        }
    }

    if (!$errors) {
        $code = new_code();
        $pdo->prepare('DELETE FROM pending_signup WHERE email = ?')->execute([$f['email']]);
        $pdo->prepare(
            'INSERT INTO pending_signup (email, student_no, first_name, middle_name, last_name, suffix, program, college,
                                         year_level, school_year, contact_no,
                                         password_hash, code_hash, attempts, last_sent_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        )->execute([
            $f['email'], $f['student_no'], $f['first_name'], $f['middle_name'] !== '' ? $f['middle_name'] : null,
            $f['last_name'], $f['suffix'] !== '' ? $f['suffix'] : null, $f['program'], $f['college'],
            (int)$f['year_level'], $f['school_year'], $phone,
            password_hash($pass, PASSWORD_DEFAULT), password_hash($code, PASSWORD_DEFAULT),
            now_str(), date('Y-m-d H:i:s', time() + CODE_TTL_MINUTES * 60),
        ]);
        [$ok, $err] = send_code_email($f['email'], $code);
        if (!$ok) {
            $pdo->prepare('DELETE FROM pending_signup WHERE email = ?')->execute([$f['email']]);
            $errors[] = 'We could not send the verification email. ' . $err;
        } else {
            $_SESSION['pending_email'] = $f['email'];
            unset($_SESSION['dev_code']);
            if (MAIL_MODE !== 'smtp') {
                $_SESSION['dev_code'] = $code;
            }
            redirect('verify.php');
        }
    }
}

public_start('Sign up');
?>
<div class="wrap"><div class="authshell">
  <aside class="authside">
    <h2>Join your student council’s books.</h2>
    <p>See where collections go, follow each project’s spending and keep your own details up to date.</p>
    <ul class="checks">
      <li><?= icon('shield') ?><span>Only <strong><?= e(ALLOWED_EMAIL_DOMAIN) ?></strong> emails are accepted</span></li>
      <li><?= icon('check') ?>A 6-digit code confirms your mailbox</li>
      <li><?= icon('user') ?>Add a profile picture after you log in</li>
    </ul>
  </aside>
  <div class="card authcard">
    <h1>Create your account</h1>
    <?php show_flash(); ?>
    <?php if ($errors): ?>
      <div class="flash err"><strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

      <div class="fgroup wide">Your name</div>
      <label><span>First name <b>*</b></span><input name="first_name" maxlength="50" required autocomplete="given-name" value="<?= e($f['first_name']) ?>"></label>
      <label><span>Middle name <i>(optional)</i></span><input name="middle_name" maxlength="50" autocomplete="additional-name" value="<?= e($f['middle_name']) ?>"></label>
      <label><span>Last name <b>*</b></span><input name="last_name" maxlength="50" required autocomplete="family-name" value="<?= e($f['last_name']) ?>"></label>
      <label><span>Suffix <i>(optional)</i></span><select name="suffix"><option value="">— none —</option><?php foreach (['Jr.','Sr.','II','III','IV','V','Other'] as $suffix): ?><option value="<?= e($suffix) ?>" <?= $f['suffix'] === $suffix ? 'selected' : '' ?>><?= e($suffix) ?></option><?php endforeach; ?></select></label>

      <div class="fgroup wide">School details</div>
      <label><span>Student number <b>*</b></span><input name="student_no" maxlength="20" required value="<?= e($f['student_no']) ?>"></label>
      <label><span>School year enrolled <b>*</b></span>
        <select name="school_year" required><?= options_html(school_year_options(), $f['school_year']) ?></select></label>
      <label class="wide"><span>College <b>*</b></span>
        <select name="college" id="collegeSelect" required><option value="">— choose your college first —</option><?php foreach (colleges_list() as $college): ?><option value="<?= e($college) ?>" <?= $f['college'] === $college ? 'selected' : '' ?>><?= e($college) ?></option><?php endforeach; ?></select>
      </label>
      <label class="wide"><span>Current program <b>*</b></span>
        <select name="program" id="programSelect" required <?= $f['college'] === '' ? 'disabled' : '' ?>><option value="">— choose your college first —</option><?php if ($f['college'] !== ''): ?><?php foreach ((programs_by_college()[$f['college']] ?? []) as $program): ?><option value="<?= e($program) ?>" <?= $f['program'] === $program ? 'selected' : '' ?>><?= e($program) ?></option><?php endforeach; ?><?php endif; ?></select>
        <small>Select your college before choosing a program.</small></label>
      <label><span>Year level <b>*</b></span>
        <select name="year_level" required>
          <option value="">— choose —</option>
          <?php for ($y = 1; $y <= 6; $y++): ?><option value="<?= $y ?>" <?= $f['year_level'] === (string)$y ? 'selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?>
        </select></label>
      <label><span>Contact number <b>*</b></span><input type="tel" name="contact_no" maxlength="20" required inputmode="tel" placeholder="0917 123 4567" value="<?= e($f['contact_no']) ?>"></label>

      <div class="fgroup wide">Sign-in details</div>
      <label class="wide"><span>School email <b>*</b></span>
        <input type="email" name="email" required placeholder="yourname<?= e(ALLOWED_EMAIL_DOMAIN) ?>" value="<?= e($f['email']) ?>">
        <small>Must end with <?= e(ALLOWED_EMAIL_DOMAIN) ?></small></label>
      <label><span>Password <b>*</b></span><span class="password-field"><input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span><small>At least 8 characters</small></label>
      <label><span>Confirm password <b>*</b></span><span class="password-field"><input type="password" name="password2" minlength="8" maxlength="72" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle aria-label="Show confirm password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 4 16 16"/></svg></button></span></label>
      <div class="actions wide">
        <button class="btn" type="submit">Send verification code</button>
        <a class="btn ghost" href="login.php">I already have an account</a>
      </div>
    </form>
  </div>
</div></div>
<script>
const collegePrograms = <?= json_encode(programs_by_college(), JSON_UNESCAPED_UNICODE) ?>;
const collegeSelect = document.getElementById('collegeSelect');
const programSelect = document.getElementById('programSelect');
function refreshPrograms(selected = '') {
  const list = collegePrograms[collegeSelect.value] || [];
  programSelect.innerHTML = '<option value="">— choose your program —</option>';
  list.forEach(name => { const option = document.createElement('option'); option.value = name; option.textContent = name; if (name === selected) option.selected = true; programSelect.appendChild(option); });
  programSelect.disabled = list.length === 0;
  if (list.length === 0) { const option = document.createElement('option'); option.value = ''; option.textContent = 'Program list not yet configured — contact the administrator'; programSelect.appendChild(option); }
}
collegeSelect.addEventListener('change', () => refreshPrograms());
</script>
<?php public_end();
