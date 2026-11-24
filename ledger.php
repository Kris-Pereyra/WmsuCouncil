<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_admin();
$pdo = db();

$valid = fn(string $d) => ($x = DateTime::createFromFormat('Y-m-d', $d)) && $x->format('Y-m-d') === $d;
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));
$kind = (string)($_GET['kind'] ?? 'all');
if ($from !== '' && !$valid($from)) $from = '';
if ($to !== '' && !$valid($to)) $to = '';
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

page_start('Ledger', 'ledger');
?>
<div class="head"><h1>Ledger</h1></div>

<form method="get" class="search">
  <label class="inline">From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="inline">To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label class="inline">Show
    <select name="kind">
      <option value="all" <?= $kind === 'all' ? 'selected' : '' ?>>All</option>
      <option value="Collection" <?= $kind === 'Collection' ? 'selected' : '' ?>>Collections only</option>
      <option value="Expense" <?= $kind === 'Expense' ? 'selected' : '' ?>>Expenses only</option>
    </select></label>
  <button class="btn" type="submit">Filter</button>
  <a class="btn ghost" href="ledger.php">Reset</a>
</form>

<section class="cards">
  <div class="stat in"><span>Money in</span><strong><?= money($in) ?></strong></div>
  <div class="stat out"><span>Money out</span><strong><?= money($out) ?></strong></div>
  <div class="stat bal <?= $in - $out < 0 ? 'neg' : '' ?>"><span>Net for this period</span><strong><?= money($in - $out) ?></strong></div>
</section>

<div class="card tablewrap"><table>
  <thead><tr><th>Date</th><th>Type</th><th>Receipt / Voucher</th><th>Details</th><th class="num">In</th><th class="num">Out</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td class="nw"><?= e($r['d']) ?></td>
      <td><span class="pill <?= $r['kind'] === 'Collection' ? 'approved' : 'draft' ?>"><?= e($r['kind']) ?></span></td>
      <td class="nw"><?= e($r['ref']) ?></td><td><?= e($r['detail']) ?></td>
      <td class="num pos"><?= (float)$r['inflow'] > 0 ? money($r['inflow']) : '' ?></td>
      <td class="num neg"><?= (float)$r['outflow'] > 0 ? money($r['outflow']) : '' ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="empty">No transactions match these filters.</td></tr>'; ?>
  </tbody>
</table></div>
<?php page_end();
