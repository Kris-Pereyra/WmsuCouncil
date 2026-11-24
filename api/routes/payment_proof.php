<?php
declare(strict_types=1);

/** Streams a proof file after the same permission check the old payment-proof.php did. */
function proof_text_error(int $code, string $msg): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($msg);
}

if ($DB_ERROR !== null) proof_text_error(503, 'Cannot connect to the database.');
$user = current_user();
if (!$user) proof_text_error(401, 'Please log in to view this payment proof.');
$id = (int)($_GET['id'] ?? 0);
$pdo = db();
$st = $pdo->prepare('SELECT pp.file_name,pp.original_name,pp.mime_type,sp.student_id,sp.college_id FROM payment_proof pp JOIN student_payment sp ON sp.payment_id=pp.payment_id WHERE pp.proof_id=?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) proof_text_error(404, 'Proof not found.');
$allowed = $user['level'] === 'admin'
    || ((int)($user['student_id'] ?? 0) === (int)$r['student_id'])
    || (in_array($user['level'], ['mayor', 'officer'], true) && (int)($user['college_id'] ?? 0) === (int)$r['college_id']);
if (!$allowed) proof_text_error(403, 'You cannot view this payment proof.');
$path = __DIR__ . '/../../uploads/payment-proofs/' . basename((string)$r['file_name']);
if (!is_file($path)) proof_text_error(404, 'The uploaded file is missing.');
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $r['mime_type']);
$safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string)$r['original_name']));
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
