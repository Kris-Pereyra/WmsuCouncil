<?php
declare(strict_types=1);

$user = need_admin();
$pdo = db();

if (is_post()) {
    $errors = [];
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'assign') {
        $cid = (int)($_POST['college_id'] ?? 0);
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $start = trim((string)($_POST['start_date'] ?? date('Y-m-d')));
        if ($cid < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !valid_ymd($start)) $errors[] = 'Select a college, enter a valid WMSU account email, and choose a valid start date.';
        if (!$errors) {
            $q = $pdo->prepare('SELECT a.account_id,a.student_id,s.college,c.college_id FROM user_account a JOIN student s ON s.student_id=a.student_id JOIN college c ON c.college_name=s.college WHERE a.email=?');
            $q->execute([$email]);
            $target = $q->fetch();
            if (!$target) {
                $errors[] = 'No registered student account was found for that email.';
            } elseif ((int)$target['college_id'] !== $cid) {
                $errors[] = 'The selected account belongs to a different college. A Mayor must be assigned from the same college.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE college_mayor SET is_active=0,end_date=CURDATE() WHERE is_active=1 AND (college_id=? OR account_id=?)')->execute([$cid, $target['account_id']]);
                    $pdo->prepare('INSERT INTO college_mayor (college_id,account_id,assigned_by,start_date,is_active) VALUES (?,?,?,?,1)')->execute([$cid, $target['account_id'], $user['account_id'], $start]);
                    audit_event((int)$user['account_id'], $cid, 'Assigned Mayor', 'college_mayor', (int)$pdo->lastInsertId(), [], ['account_id' => (int)$target['account_id'], 'start_date' => $start]);
                    $pdo->commit();
                    done('College Mayor assignment saved.');
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
        errors_out($errors);
    } elseif ($do === 'remove') {
        $id = (int)($_POST['assignment_id'] ?? 0);
        $q = $pdo->prepare('SELECT college_id,account_id FROM college_mayor WHERE assignment_id=? AND is_active=1');
        $q->execute([$id]);
        $old = $q->fetch();
        $pdo->prepare('UPDATE college_mayor SET is_active=0,end_date=CURDATE() WHERE assignment_id=? AND is_active=1')->execute([$id]);
        if ($old) audit_event((int)$user['account_id'], (int)$old['college_id'], 'Removed Mayor', 'college_mayor', $id, $old, ['is_active' => 0]);
        done('Mayor assignment ended.');
    }
    fail('Unknown action.', 400);
}

out([
    'colleges' => $pdo->query('SELECT college_id,college_name FROM college ORDER BY college_name')->fetchAll(),
    'rows'     => $pdo->query('SELECT cm.*,c.college_name,a.email, s.first_name,s.last_name FROM college_mayor cm JOIN college c ON c.college_id=cm.college_id JOIN user_account a ON a.account_id=cm.account_id LEFT JOIN student s ON s.student_id=a.student_id ORDER BY cm.is_active DESC,c.college_name,cm.start_date DESC')->fetchAll(),
    'today'    => date('Y-m-d'),
]);
