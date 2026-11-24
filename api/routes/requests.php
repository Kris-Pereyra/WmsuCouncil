<?php
declare(strict_types=1);

$user = need_staff();
$pdo = db();
$isAdmin = $user['level'] === 'admin';
$isMayor = $user['level'] === 'mayor';
$me = (int)$user['account_id'];
$collegeId = $isAdmin ? 0 : (int)($user['college_id'] ?? 0);
if (!$isAdmin && $collegeId < 1) deny('Your account is not linked to a college. Ask the administrator to check your student or Mayor assignment.');
$statuses = ['Pending', 'Approved', 'Declined'];

function request_values(): array
{
    $v = [];
    foreach (['event_title', 'event_date', 'venue', 'purpose', 'amount_requested', 'request_type', 'school_year'] as $k) $v[$k] = trim((string)($_POST[$k] ?? ''));
    $err = [];
    if ($v['event_title'] === '' || mb_strlen($v['event_title']) > 120) $err[] = 'Request title is required (maximum 120 characters).';
    if (!valid_ymd($v['event_date'])) $err[] = 'Choose a valid event or activity date.';
    if (mb_strlen($v['venue']) > 120) $err[] = 'Venue must be at most 120 characters.';
    if (mb_strlen($v['purpose']) < 10 || mb_strlen($v['purpose']) > 500) $err[] = 'Purpose must be 10 to 500 characters.';
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v['amount_requested']) || (float)$v['amount_requested'] <= 0) $err[] = 'Amount must be greater than zero.';
    if (!in_array($v['request_type'], ['Event', 'Project', 'Donation', 'Funding'], true)) $err[] = 'Choose a valid request type.';
    if (!preg_match('/^\d{4}-\d{4}$/', $v['school_year'])) $err[] = 'Choose a valid school year.';
    return [$v, $err];
}

$filter = (string)($_GET['status'] ?? 'all');
if (!in_array($filter, array_merge(['all'], $statuses), true)) $filter = 'all';

if (is_post()) {
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($do === 'decide') {
        if (!$isMayor && !$isAdmin) deny('Only the assigned College Mayor or university administrator can decide requests.');
        $decision = (string)($_POST['decision'] ?? '');
        $remarks = trim((string)($_POST['remarks'] ?? ''));
        $st = $pdo->prepare("UPDATE fund_request SET status=?, decided_by=?, decided_at=?, admin_remarks=?, updated_at=? WHERE request_id=? AND status='Pending' AND (?=0 OR college_id=?)");
        if (!in_array($decision, ['Approved', 'Declined'], true) || mb_strlen($remarks) > 255 || ($decision === 'Declined' && $remarks === '')) {
            notice('err', 'Choose Approve or Decline. A reason is required when declining.');
        }
        $st->execute([$decision, $me, now_str(), $remarks ?: null, now_str(), $id, $isAdmin ? 0 : $collegeId, $isAdmin ? 0 : $collegeId]);
        if ($st->rowCount()) audit_event($me, $isAdmin ? null : $collegeId, 'Decided request', 'fund_request', $id, [], ['status' => $decision, 'remarks' => $remarks]);
        notice($st->rowCount() ? 'ok' : 'err', $st->rowCount() ? 'Request decision saved.' : 'Request is outside your college or has already been decided.');
    }
    if ($do === 'withdraw') {
        $st = $pdo->prepare("DELETE FROM fund_request WHERE request_id=? AND requested_by=? AND status='Pending'");
        $st->execute([$id, $me]);
        notice($st->rowCount() ? 'ok' : 'err', $st->rowCount() ? 'Your pending request was withdrawn.' : 'Only your own pending requests can be withdrawn.');
    }
    if ($do === 'save') {
        if ($user['level'] !== 'officer') deny('Only a College Officer can submit a request to the Mayor.');
        [$form, $errors] = request_values();
        if ($errors) errors_out($errors);
        $st = $pdo->prepare("INSERT INTO fund_request (requested_by,event_title,event_date,venue,purpose,amount_requested,status,created_at,college_id,school_year,request_type) VALUES (?,?,?,?,?,?,'Pending',?,?,?,?)");
        $st->execute([$me, $form['event_title'], $form['event_date'], $form['venue'] ?: null, $form['purpose'], $form['amount_requested'], now_str(), $collegeId, $form['school_year'], $form['request_type']]);
        audit_event($me, $collegeId, 'Submitted request', 'fund_request', (int)$pdo->lastInsertId(), [], ['title' => $form['event_title'], 'amount' => $form['amount_requested'], 'school_year' => $form['school_year']]);
        done('Request submitted to the College Mayor for review.', ['redirect' => '/requests']);
    }
    fail('Unknown action.', 400);
}

$where = [];
$params = [];
if (!$isAdmin) { $where[] = 'fr.college_id=?'; $params[] = $collegeId; }
if ($filter !== 'all') { $where[] = 'fr.status=?'; $params[] = $filter; }
$sql = "SELECT fr.*, c.college_name, ua.email, COALESCE(s.first_name,ua.display_name) first_name, s.last_name FROM fund_request fr JOIN user_account ua ON ua.account_id=fr.requested_by LEFT JOIN student s ON s.student_id=ua.student_id LEFT JOIN college c ON c.college_id=fr.college_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY FIELD(fr.status,\'Pending\',\'Approved\',\'Declined\'), fr.created_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);

out([
    'rows'          => $st->fetchAll(),
    'status'        => $filter,
    'canSubmit'     => $user['level'] === 'officer',
    'canDecide'     => $isMayor || $isAdmin,
    'defaultYear'   => (string)($user['school_year'] ?? school_year_options()[0]),
]);
