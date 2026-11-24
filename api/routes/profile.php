<?php
declare(strict_types=1);

$user = need_login();
$pdo  = db();
$isAdmin = $user['level'] === 'admin';
$errors = [];

if (is_post()) {
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
        if (!$errors) done('Profile updated.');
        errors_out($errors);
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
            done('Password changed.', ['csrf' => csrf()]);
        }
        errors_out($errors);
    }

    if ($do === 'photo' || $do === 'photo_remove') {
        $oldPhoto = $user['profile_photo'] ?? null;
        try {
            if ($do === 'photo_remove') {
                $newPhoto = null;
            } else {
                $newPhoto = save_avatar($_FILES['photo'] ?? [], (int)$user['account_id']);
                if ($newPhoto === null) {
                    throw new RuntimeException('Choose a picture first.');
                }
            }
            $pdo->prepare('UPDATE user_account SET profile_photo = ? WHERE account_id = ?')
                ->execute([$newPhoto, $user['account_id']]);
            delete_avatar($oldPhoto);
            done($newPhoto ? 'Profile picture updated.' : 'Profile picture removed.');
        } catch (RuntimeException $ex) {
            errors_out([$ex->getMessage()]);
        }
    }

    fail('Unknown action.', 400);
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

out([
    'terms'           => $terms,
    'payments'        => $payments,
    'today'           => date('Y-m-d'),
    'allowedPrograms' => $isAdmin ? [] : (programs_by_college()[$user['college'] ?? ''] ?? []),
]);
