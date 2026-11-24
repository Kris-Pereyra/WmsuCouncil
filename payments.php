<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user=require_login(); $pdo=db();
$isStudent=$user['level']==='student'; $isVerifier=in_array($user['level'],['admin','mayor','officer'],true);
$collegeId=(int)($user['college_id']??0); $errors=[];
if($isVerifier && $user['level']!=='admin' && $collegeId<1)forbidden('Your account is not linked to a college. Ask the administrator to check your college assignment.');
if($isStudent){
  if(empty($user['student_id'])||$collegeId<1) forbidden('Your student profile must have a valid college before payments can be displayed.');
  $sy=(string)($user['school_year']?:school_year_options()[0]);
  $pdo->prepare('INSERT IGNORE INTO payment_requirement (fee_code,fee_name,amount,school_year) VALUES (?,?,?,?)')->execute(['INSURANCE','Student Insurance',70.00,$sy]);
  $pdo->prepare('INSERT IGNORE INTO payment_requirement (fee_code,fee_name,amount,school_year) VALUES (?,?,?,?)')->execute(['CSC','College Student Council Payment',200.00,$sy]);
  $req=$pdo->prepare('SELECT requirement_id,amount FROM payment_requirement WHERE school_year=? AND is_active=1');$req->execute([$sy]);
  foreach($req->fetchAll() as $r){$pdo->prepare("INSERT IGNORE INTO student_payment (student_id,college_id,requirement_id,school_year,amount_due) VALUES (?,?,?,?,?)")->execute([$user['student_id'],$collegeId,$r['requirement_id'],$sy,$r['amount']]);}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf(); $do=(string)($_POST['do']??''); $paymentId=(int)($_POST['payment_id']??0);
  $st=$pdo->prepare('SELECT * FROM student_payment WHERE payment_id=?');$st->execute([$paymentId]);$payment=$st->fetch();
  if(!$payment) forbidden('Payment record not found.');
  if($do==='upload'){
    if(!$isStudent || (int)$payment['student_id']!==(int)$user['student_id']) forbidden('You can upload proof only for your own payment.');
    if(!in_array($payment['status'],['Unpaid','Rejected','Proof Submitted'],true)) forbidden('This payment cannot accept another proof at this stage.');
    $f=$_FILES['proof']??null;
    if(!$f || (int)$f['error']!==UPLOAD_ERR_OK) $errors[]='Choose a proof-of-payment file to upload.';
    elseif((int)$f['size']>5*1024*1024) $errors[]='File must be 5 MB or smaller.';
    else{
      $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$exts=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
      if(!isset($exts[$mime]))$errors[]='Only JPG, PNG, or PDF files are accepted.';
      else{
        $filename=bin2hex(random_bytes(18)).'.'.$exts[$mime];$dest=__DIR__.'/uploads/payment-proofs/'.$filename;
        if(!move_uploaded_file($f['tmp_name'],$dest))$errors[]='The file could not be saved. Check the uploads folder permissions.';
        else{
          $pdo->beginTransaction();
          try{$pdo->prepare('INSERT INTO payment_proof (payment_id,file_name,original_name,mime_type) VALUES (?,?,?,?)')->execute([$paymentId,$filename,mb_substr(basename((string)$f['name']),0,180),$mime]);
            $pdo->prepare("UPDATE student_payment SET status='Proof Submitted' WHERE payment_id=? AND student_id=?")->execute([$paymentId,$user['student_id']]);
            $pdo->prepare("INSERT INTO payment_verification_log (payment_id,account_id,action,remarks) VALUES (?,?,'Proof submitted','Student uploaded payment proof')")->execute([$paymentId,$user['account_id']]);audit_event((int)$user['account_id'],(int)$payment['college_id'],'Uploaded payment proof','student_payment',$paymentId,[],['status'=>'Proof Submitted']);$pdo->commit();flash('ok','Proof uploaded. Your College Council Officer can now verify it against the physical record.');redirect('payments.php');
          }catch(Throwable $e){$pdo->rollBack();@unlink($dest);throw $e;}
        }
      }
    }
  }elseif($do==='verify'){
    if(!$isVerifier)forbidden('Only council staff can verify payments.');
    if($user['level']!=='admin' && (int)$payment['college_id']!==$collegeId)forbidden('You may verify payments only for your assigned college.');
    if(!in_array($payment['status'],['Proof Submitted','Under Verification'],true))$errors[]='Only submitted proofs can be verified or rejected.';
    $decision=(string)($_POST['decision']??'');$remarks=trim((string)($_POST['remarks']??''));$receipt=trim((string)($_POST['receipt_no']??''));
    if(!in_array($decision,['Verified','Rejected','Under Verification'],true))$errors[]='Invalid verification decision.';
    if(mb_strlen($remarks)>255||mb_strlen($receipt)>60)$errors[]='Remarks or receipt number is too long.';
    if($decision==='Rejected'&&$remarks==='')$errors[]='Enter a reason when rejecting proof.';
    if($decision==='Verified'&&$receipt==='')$errors[]='Enter the physical receipt or reference number before marking a payment as paid.';
    if(!$errors){
      $pdo->beginTransaction();
      try{
        $lock=$pdo->prepare('SELECT status FROM student_payment WHERE payment_id=? FOR UPDATE');
        $lock->execute([$paymentId]);
        $lockedStatus=(string)$lock->fetchColumn();
        if(!in_array($lockedStatus,['Proof Submitted','Under Verification'],true)) throw new RuntimeException('This payment was already processed by another verifier. Refresh the page.');
        if($decision==='Verified'){
          $split=fee_distribution((float)$payment['amount_due']);
          $pdo->prepare("UPDATE student_payment SET status='Verified',paid_at=NOW(),verified_by=?,verified_at=NOW(),receipt_no=?,college_share=?,university_share=? WHERE payment_id=?")->execute([$user['account_id'],$receipt?:null,$split['college'],$split['university'],$paymentId]);
          $pdo->prepare('INSERT IGNORE INTO fund_distribution (payment_id,college_id,school_year,gross_amount,college_amount,university_amount) VALUES (?,?,?,?,?,?)')->execute([$paymentId,$payment['college_id'],$payment['school_year'],$payment['amount_due'],$split['college'],$split['university']]);
          $pdo->prepare("INSERT IGNORE INTO university_fund_ledger (entry_date,school_year,direction,category,reference_no,description,amount,source_payment_id,created_by) VALUES (DATE(NOW()),?,'In','Student fee 30% share',?,'30% university share from verified student fee',?,?,?)")->execute([$payment['school_year'],$receipt?:('PAY-'.$paymentId),$split['university'],$paymentId,$user['account_id']]);
        }else{
          $pdo->prepare('UPDATE student_payment SET status=?,verified_by=?,verified_at=NOW() WHERE payment_id=?')->execute([$decision,$user['account_id'],$paymentId]);
        }
        $pdo->prepare('INSERT INTO payment_verification_log (payment_id,account_id,action,remarks,receipt_no) VALUES (?,?,?,?,?)')->execute([$paymentId,$user['account_id'],$decision,$remarks?:null,$receipt?:null]);
        audit_event((int)$user['account_id'],(int)$payment['college_id'],'Payment '.$decision,'student_payment',$paymentId,['status'=>$payment['status']],['status'=>$decision,'receipt_no'=>$receipt]);
        $pdo->commit();flash('ok',$decision==='Verified'?'Payment verified and split into the 70% college / 30% university ledger.':'Payment status updated.');redirect('payments.php');
      }catch(RuntimeException $e){$pdo->rollBack();flash('err',$e->getMessage());redirect('payments.php');}catch(Throwable $e){$pdo->rollBack();throw $e;}
    }
  }
}

$filterSy=trim((string)($_GET['school_year']??''));
if($isStudent){$sql='SELECT sp.*,pr.fee_name,s.first_name,s.last_name,s.student_no,c.college_name FROM student_payment sp JOIN payment_requirement pr ON pr.requirement_id=sp.requirement_id JOIN student s ON s.student_id=sp.student_id JOIN college c ON c.college_id=sp.college_id WHERE sp.student_id=? ORDER BY sp.school_year DESC,pr.fee_name';$st=$pdo->prepare($sql);$st->execute([$user['student_id']]);}
else{$sql='SELECT sp.*,pr.fee_name,s.first_name,s.last_name,s.student_no,c.college_name FROM student_payment sp JOIN payment_requirement pr ON pr.requirement_id=sp.requirement_id JOIN student s ON s.student_id=sp.student_id JOIN college c ON c.college_id=sp.college_id WHERE (?=0 OR sp.college_id=?) AND (?=\'\' OR sp.school_year=?) ORDER BY sp.school_year DESC,c.college_name,s.last_name,pr.fee_name';$scope=$user['level']==='admin'?0:$collegeId;$st=$pdo->prepare($sql);$st->execute([$scope,$scope,$filterSy,$filterSy]);}
$rows=$st->fetchAll();
$proofs=[];if($rows){$ids=array_column($rows,'payment_id');$marks=implode(',',array_fill(0,count($ids),'?'));$ps=$pdo->prepare("SELECT pp.* FROM payment_proof pp JOIN (SELECT payment_id,MAX(proof_id) proof_id FROM payment_proof WHERE payment_id IN ($marks) GROUP BY payment_id) x ON x.proof_id=pp.proof_id");$ps->execute($ids);foreach($ps->fetchAll() as $pr)$proofs[(int)$pr['payment_id']]=$pr;}
$years=$pdo->query('SELECT DISTINCT school_year FROM student_payment ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN);
page_start('Payments','payments'); ?>
<div class="head"><div><h1><?= $isStudent?'My annual fees':'Payment verification' ?></h1><p class="muted">Insurance ₱70 + CSC ₱200 = ₱270 per school year. Verified payments are split 70% to the college and 30% to the university.</p></div></div>
<?php if($errors): ?><div class="flash err"><ul><?php foreach($errors as $er)echo'<li>'.e($er).'</li>';?></ul></div><?php endif; ?>
<?php if(!$isStudent): ?><form method="get" class="search"><label class="inline">School year<select name="school_year"><option value="">All years</option><?php foreach($years as $y): ?><option value="<?=e($y)?>" <?=$filterSy===$y?'selected':''?>><?=e($y)?></option><?php endforeach;?></select></label><button class="btn ghost">Filter</button></form><?php endif; ?>
<div class="card tablewrap"><table><thead><tr><?php if(!$isStudent): ?><th>Student</th><th>College</th><?php endif; ?><th>Fee</th><th>School year</th><th class="num">Amount</th><th>Status</th><th>Proof / action</th></tr></thead><tbody>
<?php foreach($rows as $r): $pid=(int)$r['payment_id'];$proof=$proofs[$pid]??null; ?><tr><?php if(!$isStudent): ?><td><?=e($r['last_name'].', '.$r['first_name'])?><br><small class="muted"><?=e($r['student_no'])?></small></td><td><?=e($r['college_name'])?></td><?php endif; ?><td><?=e($r['fee_name'])?></td><td><?=e($r['school_year'])?></td><td class="num"><?=money($r['amount_due'])?></td><td><span class="pill <?=strtolower(str_replace(' ','-',$r['status']))?>"><?=e($r['status'])?></span><?php if($r['status']==='Verified'): ?><br><small class="muted">College <?=money($r['college_share'])?> · University <?=money($r['university_share'])?></small><?php endif;?></td><td><?php if($proof): ?><a href="payment-proof.php?id=<?=(int)$proof['proof_id']?>">View latest proof</a><?php else: ?><span class="muted">No proof</span><?php endif; ?>
<?php if($isStudent && in_array($r['status'],['Unpaid','Rejected','Proof Submitted'],true)): ?><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="upload"><input type="hidden" name="payment_id" value="<?=$pid?>"><input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required><button class="btn small">Upload proof</button></form><?php elseif($isVerifier && in_array($r['status'],['Proof Submitted','Under Verification'],true)): ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="verify"><input type="hidden" name="payment_id" value="<?=$pid?>"><input name="receipt_no" maxlength="60" placeholder="Physical receipt no."><input name="remarks" maxlength="255" placeholder="Reason if rejecting"><button class="btn small" name="decision" value="Verified">Verify / paid</button><button class="btn ghost small" name="decision" value="Under Verification">Check physical record</button><button class="btn ghost small" name="decision" value="Rejected">Reject</button></form><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows): ?><tr><td colspan="<?= $isStudent?6:8 ?>" class="empty">No payment records found for this school year.</td></tr><?php endif;?></tbody></table></div>
<section class="card"><h2>How the ₱270 is distributed</h2><div class="tablewrap"><table><thead><tr><th>Fee</th><th class="num">Gross</th><th class="num">College 70%</th><th class="num">University 30%</th></tr></thead><tbody><tr><td>Insurance</td><td class="num">₱70.00</td><td class="num">₱49.00</td><td class="num">₱21.00</td></tr><tr><td>CSC</td><td class="num">₱200.00</td><td class="num">₱140.00</td><td class="num">₱60.00</td></tr><tr><th>Total</th><th class="num">₱270.00</th><th class="num">₱189.00</th><th class="num">₱81.00</th></tr></tbody></table></div></section>
<?php page_end(); ?>
