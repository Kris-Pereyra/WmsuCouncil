<?php
declare(strict_types=1);

$user = need_login();
if (!in_array($user['level'], ['admin', 'mayor', 'officer'], true)) deny('Only Admin, Mayor, and Officer accounts can view the college ledger.');
$pdo = db();
$collegeId = (int)($user['college_id'] ?? 0);
$collegeFilter = (int)($_GET['college_id'] ?? 0);
if ($user['level'] !== 'admin' && $collegeId < 1) deny('Your account is not linked to a college. Ask the administrator to check your college assignment.');
if ($user['level'] !== 'admin') $collegeFilter = $collegeId;
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$sy = trim((string)($_GET['school_year'] ?? ''));
if (!($from === '' || valid_ymd($from))) $from = '';
if (!($to === '' || valid_ymd($to))) $to = '';
if ($sy !== '' && !preg_match('/^\d{4}-\d{4}$/', $sy)) $sy = '';

$base = "SELECT c.collection_date tx_date,'Collection' kind,c.receipt_no reference_no,CONCAT(s.last_name,', ',s.first_name,' · ',fs.source_name) detail,c.amount inflow,0 outflow,cl.college_name college_name,c.school_year,0 university_share FROM collection c JOIN student s ON s.student_id=c.student_id JOIN fund_source fs ON fs.source_id=c.source_id JOIN college cl ON cl.college_id=c.college_id UNION ALL SELECT e.expense_date,'Expense',e.voucher_no,CONCAT(p.title,' · ',ec.category_name),0,e.amount,cl.college_name,e.school_year,0 FROM expense e JOIN project p ON p.project_id=e.project_id JOIN budget b ON b.budget_id=p.budget_id JOIN officer o ON o.officer_id=p.officer_id JOIN student s ON s.student_id=o.student_id JOIN college cl ON cl.college_id=e.college_id JOIN expense_category ec ON ec.category_id=e.category_id UNION ALL SELECT DATE(COALESCE(sp.verified_at,sp.paid_at,sp.created_at)),'Student fee',COALESCE(sp.receipt_no,CONCAT('PAY-',sp.payment_id)),CONCAT(s.last_name,', ',s.first_name,' · ',pr.fee_name),sp.college_share,0,cl.college_name,sp.school_year,sp.university_share FROM student_payment sp JOIN student s ON s.student_id=sp.student_id JOIN college cl ON cl.college_id=sp.college_id JOIN payment_requirement pr ON pr.requirement_id=sp.requirement_id WHERE sp.status='Verified'";
$sql = 'SELECT * FROM (' . $base . ') x WHERE 1=1';
$params = [];
if ($collegeFilter) { $sql .= ' AND college_name=(SELECT college_name FROM college WHERE college_id=?)'; $params[] = $collegeFilter; }
if ($from !== '') { $sql .= ' AND tx_date>=?'; $params[] = $from; }
if ($to !== '') { $sql .= ' AND tx_date<=?'; $params[] = $to; }
if ($sy !== '') { $sql .= ' AND school_year=?'; $params[] = $sy; }
$sql .= ' ORDER BY tx_date DESC,reference_no DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$in = $out = $university = 0.0;
foreach ($rows as $r) { $in += (float)$r['inflow']; $out += (float)$r['outflow']; $university += (float)$r['university_share']; }
$colleges = $user['level'] === 'admin' ? $pdo->query('SELECT college_id,college_name FROM college ORDER BY college_name')->fetchAll() : [];
$years = $pdo->query('SELECT school_year FROM budget WHERE school_year IS NOT NULL UNION SELECT school_year FROM collection WHERE school_year IS NOT NULL UNION SELECT school_year FROM expense WHERE school_year IS NOT NULL UNION SELECT school_year FROM student_payment WHERE school_year IS NOT NULL ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN);

out([
    'rows' => $rows, 'in' => $in, 'out' => $out, 'university' => $university,
    'colleges' => $colleges, 'years' => $years, 'isAdmin' => $user['level'] === 'admin',
    'filters' => ['college_id' => $collegeFilter, 'from' => $from, 'to' => $to, 'school_year' => $sy],
]);
