<?php
declare(strict_types=1);

$user = need_login();
if (!in_array($user['level'], ['admin', 'mayor'], true)) deny('Only the university administrator or College Mayor can view member records.');
$pdo = db();
$isAdmin = $user['level'] === 'admin';
$cid = (int)($user['college_id'] ?? 0);
if (!$isAdmin && $cid < 1) deny('Your Mayor assignment does not contain a valid college.');

if (is_post()) {
    $errors = [];
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'assign_officer') {
        if ($isAdmin) deny('The university administrator assigns College Mayors. The College Mayor assigns ordinary officers.');
        $studentId = (int)($_POST['student_id'] ?? 0);
        $position = trim((string)($_POST['position'] ?? ''));
        $start = trim((string)($_POST['term_start'] ?? date('Y-m-d')));
        if ($position === '' || mb_strlen($position) > 40 || !valid_ymd($start)) $errors[] = 'Enter a valid position and term start date.';
        $q = $pdo->prepare('SELECT student_id FROM student WHERE student_id=? AND college=?');
        $q->execute([$studentId, $user['college']]);
        if (!$q->fetchColumn()) $errors[] = 'Select a student from your own college.';
        if ($errors) errors_out($errors);
        $pdo->prepare('INSERT INTO officer (student_id,position,term_start) VALUES (?,?,?)')->execute([$studentId, $position, $start]);
        audit_event((int)$user['account_id'], $cid, 'Assigned officer', 'officer', (int)$pdo->lastInsertId(), [], ['student_id' => $studentId, 'position' => $position, 'term_start' => $start]);
        done('Officer assignment added.');
    } elseif ($do === 'end_officer') {
        if ($isAdmin) deny('Only the assigned College Mayor manages ordinary officer assignments.');
        $oid = (int)($_POST['officer_id'] ?? 0);
        $st = $pdo->prepare('UPDATE officer o JOIN student s ON s.student_id=o.student_id SET o.term_end=CURDATE() WHERE o.officer_id=? AND s.college=? AND (o.term_end IS NULL OR o.term_end>=CURDATE())');
        $st->execute([$oid, $user['college']]);
        if ($st->rowCount()) audit_event((int)$user['account_id'], $cid, 'Ended officer term', 'officer', $oid, [], ['term_end' => date('Y-m-d')]);
        notice($st->rowCount() ? 'ok' : 'err', $st->rowCount() ? 'Officer term ended.' : 'Officer not found in your college.');
    }
    fail('Unknown action.', 400);
}

$params = [];
$where = '';
if (!$isAdmin) { $where = 'WHERE s.college=?'; $params[] = $user['college']; }
$st = $pdo->prepare("SELECT s.student_id,s.student_no,s.first_name,s.middle_name,s.last_name,s.suffix,s.program,s.college,s.year_level,s.school_year,s.contact_no,a.account_id,a.email,a.profile_photo FROM student s LEFT JOIN user_account a ON a.student_id=s.student_id $where ORDER BY s.last_name,s.first_name");
$st->execute($params);
$students = $st->fetchAll();
$st = $pdo->prepare('SELECT o.officer_id,o.student_id,o.position,o.term_start,o.term_end FROM officer o JOIN student s ON s.student_id=o.student_id ' . (!$isAdmin ? 'WHERE s.college=?' : '') . ' ORDER BY o.term_start DESC');
$st->execute($isAdmin ? [] : [$user['college']]);
$allOfficers = $st->fetchAll();
$current = [];
foreach ($allOfficers as $o) {
    if ($o['term_start'] <= date('Y-m-d') && (!$o['term_end'] || $o['term_end'] >= date('Y-m-d'))) $current[(int)$o['student_id']][] = $o;
}
$q = trim((string)($_GET['q'] ?? ''));
$rows = [];
foreach ($students as $s) {
    $pos = $current[(int)$s['student_id']] ?? [];
    $hay = implode(' ', [$s['student_no'], $s['first_name'], $s['last_name'], $s['program'], $s['college'], implode(' ', array_column($pos, 'position'))]);
    if ($q !== '' && stripos($hay, $q) === false) continue;
    $rows[] = [
        'student_id' => (int)$s['student_id'], 'student_no' => $s['student_no'], 'name' => full_name($s, true),
        'college' => $s['college'], 'program' => $s['program'], 'year_level' => (int)$s['year_level'],
        'has_account' => (bool)$s['account_id'], 'positions' => $pos,
    ];
}
$options = [];
foreach ($students as $s) {
    $options[] = ['id' => (int)$s['student_id'], 'label' => $s['last_name'] . ', ' . $s['first_name'] . ' · ' . $s['student_no']];
}

out(['rows' => $rows, 'q' => $q, 'isAdmin' => $isAdmin, 'studentOptions' => $isAdmin ? [] : $options, 'today' => date('Y-m-d')]);
