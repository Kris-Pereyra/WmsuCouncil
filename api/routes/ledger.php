<?php
declare(strict_types=1);

$user = need_admin();
$pdo = db();

$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$kind = (string)($_GET['kind'] ?? 'all');
if ($from !== '' && !valid_ymd($from)) $from = '';
if ($to !== '' && !valid_ymd($to)) $to = '';
if (!in_array($kind, ['all', 'Collection', 'Expense'], true)) $kind = 'all';

$sql = "SELECT * FROM (
          SELECT c.collection_date d, 'Collection' kind, c.receipt_no ref,
                 CONCAT(s.last_name, ', ', s.first_name, ' · ', fs.source_name) detail,
                 c.amount inflow, 0 outflow
            FROM collection c JOIN student s ON s.student_id = c.student_id
            JOIN fund_source fs ON fs.source_id = c.source_id
          UNION ALL
          SELECT e.expense_date, 'Expense', e.voucher_no,
                 CONCAT(p.title, ' · ', ec.category_name), 0, e.amount
            FROM expense e JOIN project p ON p.project_id = e.project_id
            JOIN expense_category ec ON ec.category_id = e.category_id
        ) x WHERE 1=1";
$params = [];
if ($from !== '') { $sql .= ' AND d >= ?'; $params[] = $from; }
if ($to   !== '') { $sql .= ' AND d <= ?'; $params[] = $to; }
if ($kind !== 'all') { $sql .= ' AND kind = ?'; $params[] = $kind; }
$sql .= ' ORDER BY d, kind, ref';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$in = $out = 0.0;
foreach ($rows as $r) { $in += (float)$r['inflow']; $out += (float)$r['outflow']; }

out(['rows' => $rows, 'in' => $in, 'out' => $out, 'filters' => ['from' => $from, 'to' => $to, 'kind' => $kind]]);
