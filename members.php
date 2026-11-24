<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user=require_login();if(!in_array($user['level'],['admin','mayor'],true))forbidden('Only the university administrator or College Mayor can view member records.');
$pdo=db();$isAdmin=$user['level']==='admin';$cid=(int)($user['college_id']??0);$errors=[];
if(!$isAdmin && $cid<1)forbidden('Your Mayor assignment does not contain a valid college.');
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();if(!$isAdmin&&$user['level']!=='mayor')forbidden();$do=(string)($_POST['do']??'');
 if($do==='assign_officer'){
  if($isAdmin)forbidden('The university administrator assigns College Mayors. The College Mayor assigns ordinary officers.');
  $studentId=(int)($_POST['student_id']??0);$position=trim((string)($_POST['position']??''));$start=trim((string)($_POST['term_start']??date('Y-m-d')));
  $d=DateTime::createFromFormat('Y-m-d',$start);
  if($position===''||mb_strlen($position)>40||!$d||$d->format('Y-m-d')!==$start)$errors[]='Enter a valid position and term start date.';
  $q=$pdo->prepare('SELECT student_id FROM student WHERE student_id=? AND college=?');$q->execute([$studentId,$user['college']]);
  if(!$q->fetchColumn())$errors[]='Select a student from your own college.';
  if(!$errors){$pdo->prepare('INSERT INTO officer (student_id,position,term_start) VALUES (?,?,?)')->execute([$studentId,$position,$start]);audit_event((int)$user['account_id'],$cid,'Assigned officer','officer',(int)$pdo->lastInsertId(),[],['student_id'=>$studentId,'position'=>$position,'term_start'=>$start]);flash('ok','Officer assignment added.');redirect('members.php');}
 }elseif($do==='end_officer'){
  if($isAdmin)forbidden('Only the assigned College Mayor manages ordinary officer assignments.');$oid=(int)($_POST['officer_id']??0);
  $st=$pdo->prepare("UPDATE officer o JOIN student s ON s.student_id=o.student_id SET o.term_end=CURDATE() WHERE o.officer_id=? AND s.college=? AND (o.term_end IS NULL OR o.term_end>=CURDATE())");$st->execute([$oid,$user['college']]);if($st->rowCount())audit_event((int)$user['account_id'],$cid,'Ended officer term','officer',$oid,[],['term_end'=>date('Y-m-d')]);flash($st->rowCount()?'ok':'err',$st->rowCount()?'Officer term ended.':'Officer not found in your college.');redirect('members.php');
 }
}
$params=[];$where='';if(!$isAdmin){$where='WHERE s.college=?';$params[]=$user['college'];}
$st=$pdo->prepare("SELECT s.student_id,s.student_no,s.first_name,s.middle_name,s.last_name,s.suffix,s.program,s.college,s.year_level,s.school_year,s.contact_no,a.account_id,a.email,a.profile_photo FROM student s LEFT JOIN user_account a ON a.student_id=s.student_id $where ORDER BY s.last_name,s.first_name");$st->execute($params);$students=$st->fetchAll();
$st=$pdo->prepare("SELECT o.officer_id,o.student_id,o.position,o.term_start,o.term_end FROM officer o JOIN student s ON s.student_id=o.student_id ".(!$isAdmin?'WHERE s.college=?':'').' ORDER BY o.term_start DESC');$st->execute($isAdmin?[]:[$user['college']]);$allOfficers=$st->fetchAll();$current=[];foreach($allOfficers as $o)if($o['term_start']<=date('Y-m-d')&&(!$o['term_end']||$o['term_end']>=date('Y-m-d')))$current[(int)$o['student_id']][]=$o;
$q=trim((string)($_GET['q']??''));$rows=[];foreach($students as $s){$pos=$current[(int)$s['student_id']]??[];$hay=implode(' ',[$s['student_no'],$s['first_name'],$s['last_name'],$s['program'],$s['college'],implode(' ',array_column($pos,'position'))]);if($q!==''&&stripos($hay,$q)===false)continue;$rows[]=[$s,$pos];}
page_start('Members','members'); ?>
<div class="head"><div><h1><?= $isAdmin?'University student directory':'College members' ?> <span class="count"><?=count($rows)?></span></h1><p class="muted"><?= $isAdmin?'View student and officer records across WMSU. Assignments of ordinary officers are managed by each College Mayor.':'Manage officer positions only within your assigned college.' ?></p></div></div>
<?php if($errors): ?><div class="flash err"><ul><?php foreach($errors as $er)echo'<li>'.e($er).'</li>';?></ul></div><?php endif;?>
<?php if(!$isAdmin): ?><section class="card"><h2>Assign an officer</h2><form method="post" class="search"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="assign_officer"><select name="student_id" required><option value="">Choose a student from your college</option><?php foreach($students as $s): ?><option value="<?=(int)$s['student_id']?>"><?=e($s['last_name'].', '.$s['first_name'].' · '.$s['student_no'])?></option><?php endforeach;?></select><input name="position" maxlength="40" required placeholder="Position (e.g. Treasurer)"><input type="date" name="term_start" value="<?=e(date('Y-m-d'))?>" required><button class="btn">Assign officer</button></form></section><?php endif;?>
<form method="get" class="search"><input type="search" name="q" value="<?=e($q)?>" placeholder="Search student, program or position"><button class="btn ghost">Search</button></form>
<div class="card tablewrap"><table><thead><tr><th>Student</th><th>College / program</th><th>Account</th><th>Current position</th><?php if(!$isAdmin): ?><th>Action</th><?php endif;?></tr></thead><tbody><?php foreach($rows as [$s,$pos]): ?><tr><td><strong><?=e(full_name($s,true))?></strong><br><small class="muted"><?=e($s['student_no'])?></small></td><td><?=e($s['college'])?><br><small class="muted"><?=e($s['program'])?> · Year <?= (int)$s['year_level']?></small></td><td><?=$s['account_id']?'<span class="pill approved">Registered</span>':'<span class="muted">No account</span>'?></td><td><?php foreach($pos as $o): ?><span class="pill officer"><?=e($o['position'])?></span> <small class="muted">since <?=e($o['term_start'])?></small><?php endforeach;?><?php if(!$pos): ?><span class="muted">—</span><?php endif;?></td><?php if(!$isAdmin): ?><td><?php foreach($pos as $o): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="end_officer"><input type="hidden" name="officer_id" value="<?=(int)$o['officer_id']?>"><button class="btn ghost small">End <?=e($o['position'])?></button></form><?php endforeach;?></td><?php endif;?></tr><?php endforeach;?><?php if(!$rows): ?><tr><td colspan="<?= $isAdmin?4:5 ?>" class="empty">No students found.</td></tr><?php endif;?></tbody></table></div>
<?php page_end(); ?>
