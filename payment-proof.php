<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user=require_login();$id=(int)($_GET['id']??0);$pdo=db();
$st=$pdo->prepare('SELECT pp.file_name,pp.original_name,pp.mime_type,sp.student_id,sp.college_id FROM payment_proof pp JOIN student_payment sp ON sp.payment_id=pp.payment_id WHERE pp.proof_id=?');$st->execute([$id]);$r=$st->fetch();
if(!$r) {http_response_code(404);exit('Proof not found.');}
$allowed=$user['level']==='admin'||((int)($user['student_id']??0)===(int)$r['student_id'])||(in_array($user['level'],['mayor','officer'],true)&&(int)($user['college_id']??0)===(int)$r['college_id']);
if(!$allowed)forbidden('You cannot view this payment proof.');
$path=__DIR__.'/uploads/payment-proofs/'.$r['file_name'];if(!is_file($path)){http_response_code(404);exit('The uploaded file is missing.');}
header('X-Content-Type-Options: nosniff');header('Content-Type: '.$r['mime_type']);$safeName=preg_replace('/[^A-Za-z0-9._ -]/','_',basename((string)$r['original_name']));header('Content-Disposition: attachment; filename="'.$safeName.'"');header('Content-Length: '.filesize($path));readfile($path);exit;
