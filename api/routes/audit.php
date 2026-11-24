<?php
declare(strict_types=1);

$user = need_login();
if (!in_array($user['level'], ['admin', 'mayor'], true)) deny('Only the administrator and College Mayor can view the audit trail.');
$pdo = db();
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$where = [];
$params = [];
if ($user['level'] === 'mayor') { $where[] = 'al.college_id=?'; $params[] = (int)$user['college_id']; }
foreach (['created_at >= ?' => $from . ' 00:00:00', 'created_at <= ?' => $to . ' 23:59:59'] as $clause => $value) {
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2} /', $value)) { $where[] = 'al.' . $clause; $params[] = $value; }
}
$sql = 'SELECT al.*,ua.email,c.college_name FROM audit_log al LEFT JOIN user_account ua ON ua.account_id=al.account_id LEFT JOIN college c ON c.college_id=al.college_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY al.created_at DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($params);

out(['rows' => $st->fetchAll(), 'filters' => ['from' => $from, 'to' => $to]]);
