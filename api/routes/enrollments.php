<?php
declare(strict_types=1);

$user = need_admin();
$pdo = db();
$catalog = programs_by_college();

if (is_post()) {
    $errors = [];
    $studentId = (int)($_POST['student_id'] ?? 0);
    $collegeName = trim((string)($_POST['college'] ?? ''));
    $program = trim((string)($_POST['program'] ?? ''));
    $yearLevel = (int)($_POST['year_level'] ?? 0);
    $schoolYear = trim((string)($_POST['school_year'] ?? ''));
    $status = (string)($_POST['enrollment_status'] ?? 'Verified');
    if ($studentId < 1) $errors[] = 'Choose a student.';
    if (!isset($catalog[$collegeName]) || !in_array($program, $catalog[$collegeName] ?? [], true)) $errors[] = 'Choose a valid college and a program belonging to it.';
    if ($yearLevel < 1 || $yearLevel > 6) $errors[] = 'Year level must be from 1 to 6.';
    if (!preg_match('/^(\d{4})-(\d{4})$/', $schoolYear, $m) || (int)$m[2] !== (int)$m[1] + 1) $errors[] = 'School year must look like 2026-2027.';
    if (!in_array($status, ['Pending', 'Verified', 'Not Enrolled', 'Archived'], true)) $errors[] = 'Choose a valid enrollment status.';
    $studentCheck = $pdo->prepare('SELECT student_id FROM student WHERE student_id=?');
    $studentCheck->execute([$studentId]);
    if (!$studentCheck->fetchColumn()) $errors[] = 'The selected student does not exist.';
    if ($errors) errors_out($errors);

    $pdo->beginTransaction();
    try {
        $collegeIdStmt = $pdo->prepare('SELECT college_id FROM college WHERE college_name=?');
        $collegeIdStmt->execute([$collegeName]);
        $collegeId = (int)$collegeIdStmt->fetchColumn();
        $programIdStmt = $pdo->prepare('SELECT program_id FROM program_catalog WHERE college_id=? AND program_name=?');
        $programIdStmt->execute([$collegeId, $program]);
        $programId = $programIdStmt->fetchColumn();
        $pdo->prepare('UPDATE student SET college=?,program=?,year_level=?,school_year=? WHERE student_id=?')->execute([$collegeName, $program, $yearLevel, $schoolYear, $studentId]);
        $pdo->prepare("INSERT INTO student_enrollment (student_id,college_id,program_id,program_name_snapshot,year_level,school_year,enrollment_status,verified_by,verified_at) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE college_id=VALUES(college_id),program_id=VALUES(program_id),program_name_snapshot=VALUES(program_name_snapshot),year_level=VALUES(year_level),enrollment_status=VALUES(enrollment_status),verified_by=VALUES(verified_by),verified_at=VALUES(verified_at)")
            ->execute([$studentId, $collegeId, $programId ?: null, $program, $yearLevel, $schoolYear, $status, $user['account_id'], $status === 'Verified' ? now_str() : null]);
        audit_event((int)$user['account_id'], null, 'Recorded student enrollment', 'student_enrollment', $studentId, [], ['college' => $collegeName, 'program' => $program, 'year_level' => $yearLevel, 'school_year' => $schoolYear, 'status' => $status]);
        $pdo->commit();
        done('Enrollment record saved. Previous school-year records remain available.', ['redirect' => '/enrollments?school_year=' . urlencode($schoolYear)]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$colleges = $pdo->query('SELECT college_id,college_name FROM college WHERE is_active=1 ORDER BY college_name')->fetchAll();
$students = $pdo->query('SELECT student_id,student_no,first_name,last_name FROM student ORDER BY last_name,first_name')->fetchAll();
$filterYear = trim((string)($_GET['school_year'] ?? ''));
$filterCollege = (int)($_GET['college_id'] ?? 0);
$sql = 'SELECT e.*,s.student_no,s.first_name,s.last_name,c.college_name FROM student_enrollment e JOIN student s ON s.student_id=e.student_id JOIN college c ON c.college_id=e.college_id WHERE (?=\'\' OR e.school_year=?) AND (?=0 OR e.college_id=?) ORDER BY e.school_year DESC,c.college_name,s.last_name,s.first_name';
$st = $pdo->prepare($sql);
$st->execute([$filterYear, $filterYear, $filterCollege, $filterCollege]);
$studentOptions = [];
foreach ($students as $s) $studentOptions[] = ['id' => (int)$s['student_id'], 'label' => $s['last_name'] . ', ' . $s['first_name'] . ' · ' . $s['student_no']];

out([
    'rows'           => $st->fetchAll(),
    'years'          => $pdo->query('SELECT DISTINCT school_year FROM student_enrollment ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN),
    'colleges'       => $colleges,
    'catalog'        => (object)$catalog,
    'studentOptions' => $studentOptions,
    'defaultYear'    => school_year_options()[0],
]);
