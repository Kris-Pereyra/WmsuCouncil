<?php
declare(strict_types=1);

if (!is_post()) fail('Use POST to sign up.', 405);
need_db();
if (current_user()) {
    out(['ok' => true, 'redirect' => '/dashboard']);
}

$errors = [];
$f = ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'suffix' => '', 'student_no' => '', 'college' => '', 'program' => '',
      'year_level' => '', 'school_year' => '', 'contact_no' => '', 'email' => ''];

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

if ($errors) errors_out($errors);

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
    errors_out(['We could not send the verification email. ' . $err]);
}
$_SESSION['pending_email'] = $f['email'];
unset($_SESSION['dev_code']);
if (MAIL_MODE !== 'smtp') {
    $_SESSION['dev_code'] = $code;
}
out(['ok' => true, 'redirect' => '/verify']);
