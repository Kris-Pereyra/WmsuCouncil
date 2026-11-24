<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_staff();
$pdo = db();
$isAdmin = $user['level'] === 'admin';
$isMayor = $user['level'] === 'mayor';
$me = (int)$user['account_id'];
$collegeId = $isAdmin ? 0 : (int)($user['college_id'] ?? 0);
if (!$isAdmin && $collegeId < 1) forbidden('Your account is not linked to a college. Ask the administrator to check your student or Mayor assignment.');
$statuses = ['Pending','Approved','Declined'];
$errors = [];
$form = ['event_title'=>'','event_date'=>'','venue'=>'','purpose'=>'','amount_requested'=>'','request_type'=>'Event','school_year'=>(string)($user['school_year'] ?? school_year_options()[0])];

function request_values(): array {
    $v=[];
    foreach (['event_title','event_date','venue','purpose','amount_requested','request_type','school_year'] as $k) $v[$k]=trim((string)($_POST[$k]??''));
    $err=[];
    if ($v['event_title']==='' || mb_strlen($v['event_title'])>120) $err[]='Request title is required (maximum 120 characters).';
    $d=DateTime::createFromFormat('Y-m-d',$v['event_date']);
    if (!$d || $d->format('Y-m-d')!==$v['event_date']) $err[]='Choose a valid event or activity date.';
    if (mb_strlen($v['venue'])>120) $err[]='Venue must be at most 120 characters.';
    if (mb_strlen($v['purpose'])<10 || mb_strlen($v['purpose'])>500) $err[]='Purpose must be 10 to 500 characters.';
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/',$v['amount_requested']) || (float)$v['amount_requested']<=0) $err[]='Amount must be greater than zero.';
    if (!in_array($v['request_type'],['Event','Project','Donation','Funding'],true)) $err[]='Choose a valid request type.';
    if (!preg_match('/^\d{4}-\d{4}$/',$v['school_year'])) $err[]='Choose a valid school year.';
    return [$v,$err];
}

$filter=(string)($_GET['status']??'all');
if (!in_array($filter,array_merge(['all'],$statuses),true)) $filter='all';
$view=(string)($_GET['view']??'list'); $editId=(int)($_GET['id']??0);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf(); $do=(string)($_POST['do']??''); $id=(int)($_POST['id']??0);
    if ($do==='decide') {
        if (!$isMayor && !$isAdmin) forbidden('Only the assigned College Mayor or university administrator can decide requests.');
        $decision=(string)($_POST['decision']??''); $remarks=trim((string)($_POST['remarks']??''));
        $st=$pdo->prepare("UPDATE fund_request SET status=?, decided_by=?, decided_at=?, admin_remarks=?, updated_at=? WHERE request_id=? AND status='Pending' AND (?=0 OR college_id=?)");
        if (!in_array($decision,['Approved','Declined'],true) || mb_strlen($remarks)>255 || ($decision==='Declined' && $remarks==='')) {
            flash('err','Choose Approve or Decline. A reason is required when declining.');
        } else {
            $st->execute([$decision,$me,now_str(),$remarks?:null,now_str(),$id,$isAdmin?0:$collegeId,$isAdmin?0:$collegeId]);
            if($st->rowCount())audit_event($me,$isAdmin?null:$collegeId,'Decided request','fund_request',$id,[],['status'=>$decision,'remarks'=>$remarks]);
            flash($st->rowCount()?'ok':'err',$st->rowCount()?'Request decision saved.':'Request is outside your college or has already been decided.');
        }
        redirect('requests.php?status='.urlencode($filter));
    }
    if ($do==='withdraw') {
        $st=$pdo->prepare("DELETE FROM fund_request WHERE request_id=? AND requested_by=? AND status='Pending'");
        $st->execute([$id,$me]); flash($st->rowCount()?'ok':'err',$st->rowCount()?'Your pending request was withdrawn.':'Only your own pending requests can be withdrawn.'); redirect('requests.php');
    }
    if ($do==='save') {
        if ($user['level']!=='officer') forbidden('Only a College Officer can submit a request to the Mayor.');
        [$form,$errors]=request_values(); $view='new';
        if (!$errors) {
            $st=$pdo->prepare("INSERT INTO fund_request (requested_by,event_title,event_date,venue,purpose,amount_requested,status,created_at,college_id,school_year,request_type) VALUES (?,?,?,?,?,?,'Pending',?,?,?,?)");
            $st->execute([$me,$form['event_title'],$form['event_date'],$form['venue']?:null,$form['purpose'],$form['amount_requested'],now_str(),$collegeId,$form['school_year'],$form['request_type']]);
            audit_event($me,$collegeId,'Submitted request','fund_request',(int)$pdo->lastInsertId(),[],['title'=>$form['event_title'],'amount'=>$form['amount_requested'],'school_year'=>$form['school_year']]);
            flash('ok','Request submitted to the College Mayor for review.'); redirect('requests.php');
        }
    }
}

$where=[]; $params=[];
if (!$isAdmin) { $where[]='fr.college_id=?'; $params[]=$collegeId; }
if ($filter!=='all') { $where[]='fr.status=?'; $params[]=$filter; }
$sql="SELECT fr.*, c.college_name, ua.email, COALESCE(s.first_name,ua.display_name) first_name, s.last_name FROM fund_request fr JOIN user_account ua ON ua.account_id=fr.requested_by LEFT JOIN student s ON s.student_id=ua.student_id LEFT JOIN college c ON c.college_id=fr.college_id";
if ($where) $sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY FIELD(fr.status,\'Pending\',\'Approved\',\'Declined\'), fr.created_at DESC';
$st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
page_start('Requests','requests');
?>
<div class="head"><div><h1>College requests</h1><p class="muted">Officers submit proposals; the assigned College Mayor reviews requests for their college.</p></div><?php if ($user['level']==='officer'): ?><a class="btn" href="requests.php?view=new">New request</a><?php endif; ?></div>
<?php if ($view==='new' && $user['level']==='officer'): ?>
<section class="card"><h2>Submit request</h2><?php if($errors): ?><div class="flash err"><ul><?php foreach($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="save">
<label>Request type<select name="request_type"><option>Event</option><option>Project</option><option>Donation</option><option>Funding</option></select></label>
<label>Title<input name="event_title" maxlength="120" required value="<?=e($form['event_title'])?>"></label>
<label>Activity date<input type="date" name="event_date" required value="<?=e($form['event_date'])?>"></label>
<label>School year<input name="school_year" maxlength="9" required value="<?=e($form['school_year'])?>" placeholder="2026-2027"></label>
<label>Venue (optional)<input name="venue" maxlength="120" value="<?=e($form['venue'])?>"></label>
<label>Purpose and details<textarea name="purpose" minlength="10" maxlength="500" required><?=e($form['purpose'])?></textarea></label>
<label>Amount requested (₱)<input type="number" name="amount_requested" min="0.01" step="0.01" required value="<?=e($form['amount_requested'])?>"></label>
<div><button class="btn" type="submit">Send to Mayor</button> <a class="btn ghost" href="requests.php">Cancel</a></div></form></section>
<?php endif; ?>
<form method="get" class="search"><label class="inline">Status<select name="status"><?php foreach(array_merge(['all'],$statuses) as $x): ?><option value="<?=e($x)?>" <?=$filter===$x?'selected':''?>><?=e($x==='all'?'All statuses':$x)?></option><?php endforeach; ?></select></label><button class="btn ghost">Filter</button></form>
<div class="card tablewrap"><table><thead><tr><th>Request</th><?php if($isAdmin): ?><th>College</th><?php endif; ?><th>Requested by</th><th>School year</th><th>Date</th><th class="num">Amount</th><th>Status</th><?php if($isMayor||$isAdmin): ?><th>Decision</th><?php endif; ?></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><strong><?=e($r['event_title'])?></strong><br><small class="muted"><?=e($r['request_type'])?> · <?=e($r['purpose'])?></small></td><?php if($isAdmin): ?><td><?=e($r['college_name']??'Unassigned')?></td><?php endif; ?><td><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')) ?: $r['email'])?></td><td><?=e($r['school_year']??'—')?></td><td><?=e($r['event_date'])?></td><td class="num"><?=money($r['amount_requested'])?></td><td><span class="pill <?=strtolower($r['status'])?>"><?=e($r['status'])?></span><?php if($r['admin_remarks']): ?><br><small class="muted"><?=e($r['admin_remarks'])?></small><?php endif; ?></td><?php if($isMayor||$isAdmin): ?><td><?php if($r['status']==='Pending'): ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="decide"><input type="hidden" name="id" value="<?=(int)$r['request_id']?>"><input name="remarks" maxlength="255" placeholder="Reason if declining"><button class="btn small" name="decision" value="Approved">Approve</button><button class="btn ghost small" name="decision" value="Declined">Decline</button></form><?php else: ?>—<?php endif; ?></td><?php endif; ?></tr><?php endforeach; if(!$rows): ?><tr><td colspan="<?=($isAdmin?8:7)?>" class="empty">No requests found.</td></tr><?php endif; ?></tbody></table></div>
<?php page_end(); ?>
