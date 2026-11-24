<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user=require_login(); $pdo=db(); $staff=in_array($user['level'],['admin','mayor','officer'],true); $errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf(); if(!$staff) forbidden('Students can only view announcements.');
  $title=trim((string)($_POST['title']??'')); $body=trim((string)($_POST['body']??'')); $type=(string)($_POST['announcement_type']??'General');
  $event=trim((string)($_POST['event_date']??'')); $deadline=trim((string)($_POST['deadline']??'')); $sy=trim((string)($_POST['school_year']??''));
  $allowed=['General','Event','Payment','Meeting','Project','Donation'];
  if($title===''||mb_strlen($title)>180)$errors[]='Title is required (maximum 180 characters).';
  if($body===''||mb_strlen($body)>10000)$errors[]='Announcement text is required (maximum 10,000 characters).';
  if(!in_array($type,$allowed,true))$errors[]='Choose a valid announcement type.';
  foreach(['Event date'=>$event,'Deadline'=>$deadline] as $label=>$date){if($date!==''&&(!DateTime::createFromFormat('Y-m-d',$date)||DateTime::createFromFormat('Y-m-d',$date)->format('Y-m-d')!==$date))$errors[]="$label is invalid.";}
  if($sy!==''&&!preg_match('/^\d{4}-\d{4}$/',$sy))$errors[]='School year must look like 2026-2027.';
  if(!$errors){$cid=$user['level']==='admin'?0:(int)($user['college_id']??0); if($user['level']!=='admin'&&$cid<1)forbidden('Your account has no assigned college.');
    $pdo->prepare("INSERT INTO announcement (college_id,created_by,title,body,announcement_type,publish_at,event_date,deadline,school_year,status) VALUES (?,?,?,?,?,NOW(),?,?,?,'Published')")->execute([$cid?:null,$user['account_id'],$title,$body,$type,$event?:null,$deadline?:null,$sy?:null]);
    audit_event((int)$user['account_id'],$cid?:null,'Published announcement','announcement',(int)$pdo->lastInsertId(),[],['title'=>$title,'type'=>$type,'school_year'=>$sy]);
    flash('ok','Announcement published.'); redirect('announcements.php');
  }
}
$cid=(int)($user['college_id']??0);
$sql="SELECT a.*,c.college_name,ua.display_name,s.first_name,s.last_name FROM announcement a LEFT JOIN college c ON c.college_id=a.college_id JOIN user_account ua ON ua.account_id=a.created_by LEFT JOIN student s ON s.student_id=ua.student_id WHERE a.status='Published' AND (a.college_id IS NULL OR a.college_id=?) ORDER BY a.publish_at DESC";
if($user['level']==='admin'){$sql="SELECT a.*,c.college_name,ua.display_name,s.first_name,s.last_name FROM announcement a LEFT JOIN college c ON c.college_id=a.college_id JOIN user_account ua ON ua.account_id=a.created_by LEFT JOIN student s ON s.student_id=ua.student_id WHERE a.status='Published' ORDER BY a.publish_at DESC";$rows=$pdo->query($sql)->fetchAll();}else{$st=$pdo->prepare($sql);$st->execute([$cid]);$rows=$st->fetchAll();}
$colleges=$user['level']==='admin'?$pdo->query('SELECT college_id,college_name FROM college WHERE is_active=1 ORDER BY college_name')->fetchAll():[];
page_start('Announcements','announcements'); ?>
<div class="head"><div><h1>Announcements</h1><p class="muted">University-wide and your college’s published notices.</p></div></div>
<?php if($staff): ?><section class="card"><h2>Publish announcement</h2><?php if($errors): ?><div class="flash err"><ul><?php foreach($errors as $er)echo'<li>'.e($er).'</li>';?></ul></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Title<input name="title" maxlength="180" required></label><label>Type<select name="announcement_type"><?php foreach(['General','Event','Payment','Meeting','Project','Donation'] as $t): ?><option><?=e($t)?></option><?php endforeach;?></select></label><?php if($user['level']==='admin'): ?><p class="muted">Audience: All WMSU students (university-wide)</p><?php else: ?><p class="muted">Audience: <?=e($user['college']??'Your college')?></p><?php endif; ?><label>Announcement<textarea name="body" rows="5" maxlength="10000" required></textarea></label><div class="two"><label>Event date (optional)<input type="date" name="event_date"></label><label>Deadline (optional)<input type="date" name="deadline"></label></div><label>School year (optional)<input name="school_year" placeholder="2026-2027" maxlength="9"></label><button class="btn">Publish</button></form></section><?php endif; ?>
<div class="stack"><?php foreach($rows as $a): ?><article class="card"><div class="head"><div><span class="pill"><?=e($a['announcement_type'])?></span> <small class="muted"><?=e($a['college_name']??'WMSU University-wide')?></small><h2><?=e($a['title'])?></h2></div><small class="muted"><?=e($a['publish_at'])?></small></div><p><?=nl2br(e($a['body']))?></p><small class="muted"><?php if($a['event_date']): ?>Event: <?=e($a['event_date'])?> · <?php endif;?><?php if($a['deadline']): ?>Deadline: <?=e($a['deadline'])?> · <?php endif;?><?=e($a['school_year']??'')?></small></article><?php endforeach;?><?php if(!$rows): ?><div class="card empty">No published announcements yet.</div><?php endif;?></div>
<?php page_end(); ?>
