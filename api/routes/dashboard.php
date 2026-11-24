<?php
declare(strict_types=1);

$user = need_login();
$pdo = db();
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$first = $user['level'] === 'admin' ? ($user['display_name'] ?: 'Administrator') : ($user['first_name'] ?: 'Student');

$res = ['greeting' => $greet, 'first' => $first];

if ($user['level'] === 'student') {
    $st = $pdo->prepare('SELECT school_year,program,college,year_level FROM student WHERE student_id=?');
    $st->execute([$user['student_id']]);
    $student = $st->fetch();
    $en = $pdo->prepare('SELECT e.school_year,e.program_name_snapshot program,e.year_level,c.college_name college FROM student_enrollment e JOIN college c ON c.college_id=e.college_id WHERE e.student_id=? ORDER BY e.school_year DESC,e.enrollment_id DESC LIMIT 1');
    $en->execute([$user['student_id']]);
    $snapshot = $en->fetch();
    if ($snapshot) $student = array_merge($student, $snapshot);
    $st = $pdo->prepare('SELECT pr.fee_name,sp.amount_due,sp.status,sp.school_year,sp.college_share,sp.university_share FROM student_payment sp JOIN payment_requirement pr ON pr.requirement_id=sp.requirement_id WHERE sp.student_id=? ORDER BY sp.school_year DESC,pr.fee_name');
    $st->execute([$user['student_id']]);
    $payments = $st->fetchAll();
    $st = $pdo->prepare("SELECT title,body,announcement_type,publish_at,event_date,deadline FROM announcement WHERE status='Published' AND (college_id IS NULL OR college_id=(SELECT college_id FROM college WHERE college_name=?)) ORDER BY publish_at DESC LIMIT 8");
    $st->execute([$student['college']]);
    $news = $st->fetchAll();
    $res['student'] = $student;
    $res['payments'] = $payments;
    $res['news'] = $news;
} else {
    $cid = (int)($user['college_id'] ?? 0);
    $isAdmin = $user['level'] === 'admin';
    $st = $pdo->prepare('SELECT status,COUNT(*) n,COALESCE(SUM(amount_requested),0) amount FROM fund_request' . ($isAdmin ? '' : ' WHERE college_id=?') . ' GROUP BY status');
    $st->execute($isAdmin ? [] : [$cid]);
    $req = [];
    foreach ($st->fetchAll() as $r) $req[$r['status']] = $r;
    $st = $pdo->prepare("SELECT COUNT(*) FROM student_payment WHERE status IN ('Proof Submitted','Under Verification')" . ($isAdmin ? '' : ' AND college_id=?'));
    $st->execute($isAdmin ? [] : [$cid]);
    $waiting = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM student' . ($isAdmin ? '' : ' WHERE college=?'));
    $st->execute($isAdmin ? [] : [$user['college']]);
    $students = (int)$st->fetchColumn();
    $res['stats'] = ['students' => $students, 'pending' => (int)($req['Pending']['n'] ?? 0), 'waiting' => $waiting];
}

out($res);
