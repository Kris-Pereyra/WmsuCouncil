<?php
declare(strict_types=1);

$user = need_admin();
$pdo = db();

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$sy = trim((string)($_GET['school_year'] ?? ''));
if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';
if ($sy !== '' && !preg_match('/^\d{4}-\d{4}$/', $sy)) $sy = '';

if (is_post()) {
    $errors = [];
    $date = trim((string)($_POST['entry_date'] ?? ''));
    $year = trim((string)($_POST['school_year'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $reference = trim((string)($_POST['reference_no'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $amount = trim((string)($_POST['amount'] ?? ''));
    if ($date === '' || !valid_ymd($date)) $errors[] = 'Enter a valid transaction date.';
    if (!preg_match('/^\d{4}-\d{4}$/', $year)) $errors[] = 'School year must look like 2026-2027.';
    if ($category === '' || mb_strlen($category) > 80) $errors[] = 'Enter a category (maximum 80 characters).';
    if (mb_strlen($reference) > 80) $errors[] = 'Reference number is too long.';
    if ($description === '' || mb_strlen($description) > 255) $errors[] = 'Description is required (maximum 255 characters).';
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount) || (float)$amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if ($errors) errors_out($errors);
    $st = $pdo->prepare("INSERT INTO university_fund_ledger (entry_date,school_year,direction,category,reference_no,description,amount,created_by) VALUES (?,?,'Out',?,?,?,?,?)");
    $st->execute([$date, $year, $category, $reference ?: null, $description, $amount, $user['account_id']]);
    audit_event((int)$user['account_id'], null, 'Recorded university fund outflow', 'university_fund_ledger', (int)$pdo->lastInsertId(), [], ['date' => $date, 'school_year' => $year, 'category' => $category, 'amount' => $amount]);
    done('University fund outflow recorded.');
}

$where = ['1=1'];
$params = [];
if ($from !== '') { $where[] = 'entry_date >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'entry_date <= ?'; $params[] = $to; }
if ($sy !== '') { $where[] = 'school_year = ?'; $params[] = $sy; }
$st = $pdo->prepare('SELECT * FROM university_fund_ledger WHERE ' . implode(' AND ', $where) . ' ORDER BY entry_date DESC,entry_id DESC');
$st->execute($params);
$rows = $st->fetchAll();
$in = 0.0;
$out = 0.0;
foreach ($rows as $r) { if ($r['direction'] === 'In') $in += (float)$r['amount']; else $out += (float)$r['amount']; }

out([
    'rows'  => $rows,
    'in'    => $in,
    'out'   => $out,
    'years' => $pdo->query('SELECT DISTINCT school_year FROM university_fund_ledger ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN),
    'today' => date('Y-m-d'),
    'filters' => ['from' => $from, 'to' => $to, 'school_year' => $sy],
]);
