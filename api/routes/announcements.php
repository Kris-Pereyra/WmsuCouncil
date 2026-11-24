<?php
declare(strict_types=1);

$user = need_login();
$pdo = db();
$staff = in_array($user['level'], ['admin', 'mayor', 'officer'], true);
$errors = [];

if (is_post()) {
    if (!$staff) deny('Students can only view announcements.');
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $type = (string)($_POST['announcement_type'] ?? 'General');
    $event = trim((string)($_POST['event_date'] ?? ''));
    $deadline = trim((string)($_POST['deadline'] ?? ''));
    $sy = trim((string)($_POST['school_year'] ?? ''));
    $allowed = ['General', 'Event', 'Payment', 'Meeting', 'Project', 'Donation'];
    if ($title === '' || mb_strlen($title) > 180) $errors[] = 'Title is required (maximum 180 characters).';
    if ($body === '' || mb_strlen($body) > 10000) $errors[] = 'Announcement text is required (maximum 10,000 characters).';
    if (!in_array($type, $allowed, true)) $errors[] = 'Choose a valid announcement type.';
    foreach (['Event date' => $event, 'Deadline' => $deadline] as $label => $date) {
        if ($date !== '' && !valid_ymd($date)) $errors[] = "$label is invalid.";
    }
    if ($sy !== '' && !preg_match('/^\d{4}-\d{4}$/', $sy)) $errors[] = 'School year must look like 2026-2027.';
    if ($errors) errors_out($errors);

    $cid = $user['level'] === 'admin' ? 0 : (int)($user['college_id'] ?? 0);
    if ($user['level'] !== 'admin' && $cid < 1) deny('Your account has no assigned college.');
    $pdo->prepare("INSERT INTO announcement (college_id,created_by,title,body,announcement_type,publish_at,event_date,deadline,school_year,status) VALUES (?,?,?,?,?,NOW(),?,?,?,'Published')")
        ->execute([$cid ?: null, $user['account_id'], $title, $body, $type, $event ?: null, $deadline ?: null, $sy ?: null]);
    audit_event((int)$user['account_id'], $cid ?: null, 'Published announcement', 'announcement', (int)$pdo->lastInsertId(), [], ['title' => $title, 'type' => $type, 'school_year' => $sy]);
    done('Announcement published.');
}

$cid = (int)($user['college_id'] ?? 0);
$sql = "SELECT a.*,c.college_name,ua.display_name,s.first_name,s.last_name FROM announcement a LEFT JOIN college c ON c.college_id=a.college_id JOIN user_account ua ON ua.account_id=a.created_by LEFT JOIN student s ON s.student_id=ua.student_id WHERE a.status='Published' AND (a.college_id IS NULL OR a.college_id=?) ORDER BY a.publish_at DESC";
if ($user['level'] === 'admin') {
    $sql = "SELECT a.*,c.college_name,ua.display_name,s.first_name,s.last_name FROM announcement a LEFT JOIN college c ON c.college_id=a.college_id JOIN user_account ua ON ua.account_id=a.created_by LEFT JOIN student s ON s.student_id=ua.student_id WHERE a.status='Published' ORDER BY a.publish_at DESC";
    $rows = $pdo->query($sql)->fetchAll();
} else {
    $st = $pdo->prepare($sql);
    $st->execute([$cid]);
    $rows = $st->fetchAll();
}

out(['rows' => $rows, 'canPublish' => $staff]);
