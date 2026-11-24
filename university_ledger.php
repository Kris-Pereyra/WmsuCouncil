<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_admin();
$pdo = db();
$errors = [];
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$sy = trim((string)($_GET['school_year'] ?? ''));
$validDate = static function(string $v): bool {
    if ($v === '') return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
};
if (!$validDate($from)) $from = '';
if (!$validDate($to)) $to = '';
if ($sy !== '' && !preg_match('/^\d{4}-\d{4}$/', $sy)) $sy = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $date = trim((string)($_POST['entry_date'] ?? ''));
    $year = trim((string)($_POST['school_year'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $reference = trim((string)($_POST['reference_no'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $amount = trim((string)($_POST['amount'] ?? ''));
    if (!$validDate($date) || $date === '') $errors[] = 'Enter a valid transaction date.';
    if (!preg_match('/^\d{4}-\d{4}$/', $year)) $errors[] = 'School year must look like 2026-2027.';
    if ($category === '' || mb_strlen($category) > 80) $errors[] = 'Enter a category (maximum 80 characters).';
    if (mb_strlen($reference) > 80) $errors[] = 'Reference number is too long.';
    if ($description === '' || mb_strlen($description) > 255) $errors[] = 'Description is required (maximum 255 characters).';
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount) || (float)$amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if (!$errors) {
        $st = $pdo->prepare("INSERT INTO university_fund_ledger (entry_date,school_year,direction,category,reference_no,description,amount,created_by) VALUES (?,?,'Out',?,?,?,?,?)");
        $st->execute([$date,$year,$category,$reference ?: null,$description,$amount,$user['account_id']]);
        audit_event((int)$user['account_id'], null, 'Recorded university fund outflow', 'university_fund_ledger', (int)$pdo->lastInsertId(), [], ['date'=>$date,'school_year'=>$year,'category'=>$category,'amount'=>$amount]);
        flash('ok', 'University fund outflow recorded.');
        redirect('university_ledger.php');
    }
}
$where = ['1=1']; $params = [];
if ($from !== '') { $where[] = 'entry_date >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'entry_date <= ?'; $params[] = $to; }
if ($sy !== '') { $where[] = 'school_year = ?'; $params[] = $sy; }
$st = $pdo->prepare('SELECT * FROM university_fund_ledger WHERE '.implode(' AND ',$where).' ORDER BY entry_date DESC,entry_id DESC');
$st->execute($params); $rows = $st->fetchAll();
$in = 0.0; $out = 0.0;
foreach ($rows as $r) { if ($r['direction'] === 'In') $in += (float)$r['amount']; else $out += (float)$r['amount']; }
$years = $pdo->query('SELECT DISTINCT school_year FROM university_fund_ledger ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN);
page_start('University fund','university_ledger'); ?>
<div class="head"><div><h1>University central fund</h1><p class="muted">The university’s 30% share of verified student fees is posted here automatically. Only Admin can record central-fund outflows.</p></div></div>
<?php if ($errors): ?><div class="flash err"><ul><?php foreach ($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div><?php endif; ?>
<section class="cards"><div class="stat in"><span>Central fund inflow</span><strong><?=money($in)?></strong></div><div class="stat out"><span>Central fund outflow</span><strong><?=money($out)?></strong></div><div class="stat bal"><span>Net in selected period</span><strong><?=money($in-$out)?></strong></div></section>
<section class="card"><h2>Record central-fund expense</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><div class="two"><label>Date<input type="date" name="entry_date" value="<?=e(date('Y-m-d'))?>" required></label><label>School year<input name="school_year" maxlength="9" placeholder="2026-2027" required></label></div><div class="two"><label>Category<input name="category" maxlength="80" placeholder="University program / operations" required></label><label>Reference no. (optional)<input name="reference_no" maxlength="80"></label></div><label>Description<input name="description" maxlength="255" required></label><label>Amount (₱)<input type="number" name="amount" min="0.01" step="0.01" required></label><button class="btn">Record outflow</button></form></section>
<form method="get" class="search"><label class="inline">School year<select name="school_year"><option value="">All years</option><?php foreach($years as $y): ?><option value="<?=e($y)?>" <?=$sy===$y?'selected':''?>><?=e($y)?></option><?php endforeach;?></select></label><label class="inline">From<input type="date" name="from" value="<?=e($from)?>"></label><label class="inline">To<input type="date" name="to" value="<?=e($to)?>"></label><button class="btn ghost">Filter</button><a class="btn ghost" href="university_ledger.php">Reset</a></form>
<div class="card tablewrap"><table><thead><tr><th>Date</th><th>School year</th><th>Direction</th><th>Category</th><th>Reference</th><th>Description</th><th class="num">Amount</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['entry_date'])?></td><td><?=e($r['school_year'])?></td><td><span class="pill <?= $r['direction']==='In'?'approved':'closed' ?>"><?=e($r['direction'])?></span></td><td><?=e($r['category'])?></td><td><?=e($r['reference_no']??'—')?></td><td><?=e($r['description'])?></td><td class="num"><?=money($r['amount'])?></td></tr><?php endforeach;?><?php if(!$rows): ?><tr><td colspan="7" class="empty">No central-fund transactions match these filters.</td></tr><?php endif;?></tbody></table></div>
<?php page_end(); ?>
