<?php
declare(strict_types=1);

$user = need_admin();
$pdo = db();

if (is_post()) {
    $errors = [];
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'add_college') {
        $name = trim((string)($_POST['college_name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['college_code'] ?? '')));
        if ($name === '' || mb_strlen($name) > 150) $errors[] = 'College name is required (maximum 150 characters).';
        if (mb_strlen($code) > 30 || ($code !== '' && !preg_match('/^[A-Z0-9-]+$/', $code))) $errors[] = 'College code may contain only letters, numbers, and dashes.';
        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO college (college_name,college_code,is_active) VALUES (?,?,1)')->execute([$name, $code ?: null]);
                audit_event((int)$user['account_id'], null, 'Added college', 'college', (int)$pdo->lastInsertId(), [], ['college_name' => $name, 'college_code' => $code]);
                done('College added to the active catalog.');
            } catch (PDOException $e) {
                $errors[] = friendly_error($e);
            }
        }
    } elseif ($do === 'add_program') {
        $collegeId = (int)($_POST['college_id'] ?? 0);
        $program = trim((string)($_POST['program_name'] ?? ''));
        if ($collegeId < 1) $errors[] = 'Choose a college.';
        if ($program === '' || mb_strlen($program) > 80) $errors[] = 'Program name is required (maximum 80 characters).';
        $check = $pdo->prepare('SELECT college_name FROM college WHERE college_id=? AND is_active=1');
        $check->execute([$collegeId]);
        $collegeName = $check->fetchColumn();
        if (!$collegeName) $errors[] = 'The selected college is not active.';
        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO program_catalog (college_id,program_name,is_active) VALUES (?,?,1)')->execute([$collegeId, $program]);
                audit_event((int)$user['account_id'], null, 'Added program to catalog', 'program_catalog', (int)$pdo->lastInsertId(), [], ['college' => $collegeName, 'program' => $program]);
                done('Program added. It will now appear in the college-first registration dropdown.');
            } catch (PDOException $e) {
                $errors[] = friendly_error($e);
            }
        }
    } elseif ($do === 'toggle_college') {
        $id = (int)($_POST['college_id'] ?? 0);
        $pdo->prepare('UPDATE college SET is_active=1-is_active WHERE college_id=?')->execute([$id]);
        audit_event((int)$user['account_id'], null, 'Changed college catalog status', 'college', $id);
        done('College catalog status updated.');
    } elseif ($do === 'toggle_program') {
        $id = (int)($_POST['program_id'] ?? 0);
        $pdo->prepare('UPDATE program_catalog SET is_active=1-is_active WHERE program_id=?')->execute([$id]);
        audit_event((int)$user['account_id'], null, 'Changed program catalog status', 'program_catalog', $id);
        done('Program catalog status updated.');
    } else {
        fail('Unknown action.', 400);
    }
    errors_out($errors);
}

out([
    'colleges' => $pdo->query('SELECT c.*,COUNT(p.program_id) program_count FROM college c LEFT JOIN program_catalog p ON p.college_id=c.college_id GROUP BY c.college_id ORDER BY c.college_name')->fetchAll(),
    'programs' => $pdo->query('SELECT p.*,c.college_name FROM program_catalog p JOIN college c ON c.college_id=p.college_id ORDER BY c.college_name,p.program_name')->fetchAll(),
]);
