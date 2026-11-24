<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user=require_login();if(!in_array($user['level'],['admin','mayor'],true))forbidden('Only the administrator and College Mayor can view the audit trail.');
$pdo=db();$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));$where=[];$params=[];
if($user['level']==='mayor'){$where[]='al.college_id=?';$params[]=(int)$user['college_id'];}
foreach(['created_at >= ?'=>$from.' 00:00:00','created_at <= ?'=>$to.' 23:59:59'] as $clause=>$value){if($value!==''&&preg_match('/^\d{4}-\d{2}-\d{2} /',$value)){$where[]='al.'.$clause;$params[]=$value;}}
$sql='SELECT al.*,ua.email,c.college_name FROM audit_log al LEFT JOIN user_account ua ON ua.account_id=al.account_id LEFT JOIN college c ON c.college_id=al.college_id';if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY al.created_at DESC LIMIT 500';$st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
page_start('Audit trail','audit'); ?>
<div class="head"><div><h1>Audit trail</h1><p class="muted">Recent recorded actions. Mayor view is restricted to the assigned college.</p></div></div>
<form method="get" class="search"><label class="inline">From<input type="date" name="from" value="<?=e($from)?>"></label><label class="inline">To<input type="date" name="to" value="<?=e($to)?>"></label><button class="btn ghost">Filter</button></form>
<div class="card tablewrap"><table><thead><tr><th>Date / time</th><th>College</th><th>Account</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td class="nw"><?=e($r['created_at'])?></td><td><?=e($r['college_name']??'University-wide')?></td><td><?=e($r['email']??'System')?></td><td><?=e($r['action'])?></td><td><?=e($r['entity_type'])?> #<?=e($r['entity_id']??'—')?></td><td><small><?=e($r['new_values']??$r['old_values']??$r['ip_address']??'—')?></small></td></tr><?php endforeach;?><?php if(!$rows): ?><tr><td colspan="6" class="empty">No audit entries found.</td></tr><?php endif;?></tbody></table></div>
<?php page_end(); ?>
