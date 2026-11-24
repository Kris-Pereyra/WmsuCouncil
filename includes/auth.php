<?php

declare(strict_types=1);

function now_str(): string
{
    return date('Y-m-d H:i:s');
}

function valid_school_email(string $email): bool
{
    $d = preg_quote(ALLOWED_EMAIL_DOMAIN, '/');
    return strlen($email) <= 120 && (bool)preg_match('/^[a-z0-9._%+\-]+' . $d . '\z/i', $email);
}

function new_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function send_code_email(string $email, string $code): array
{
    $subject = 'Verify Your Email | WMSU Student Council Funds';

    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeTtl = (int) CODE_TTL_MINUTES;
    
    $textBody =
        "WMSU STUDENT COUNCIL FUNDS MANAGEMENT SYSTEM\n\n" .
        "One last step to get started.\n\n" .
        "Your verification code is: {$code}\n\n" .
        "This code expires in " . CODE_TTL_MINUTES . " minutes.\n\n" .
        "Do not share this code with anyone.\n\n" .
        "If you did not request this code, please ignore this email.\n";

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Your Email</title>
</head>

<body style="margin:0;padding:0;background-color:#F7F3F3;font-family:Arial,Helvetica,sans-serif;color:#14171F;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="background-color:#F7F3F3;padding:35px 12px;">
<tr>
<td align="center">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="max-width:600px;background-color:#FFFFFF;border:1px solid #F3C9CD;border-radius:12px;overflow:hidden;">

    <!-- Header -->
    <tr>
        <td style="padding:25px 35px;background-color:#FDECEE;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td align="left">
                        <div style="font-size:25px;font-weight:800;letter-spacing:1px;color:#D3202F;">
                            WMSU
                        </div>
                        <div style="font-size:10px;letter-spacing:1.5px;color:#5B6272;margin-top:5px;">
                            STUDENT COUNCIL FUNDS
                        </div>
                    </td>
                    <td align="right">
                        <span style="font-size:10px;font-weight:bold;letter-spacing:1px;color:#D3202F;background-color:#FDE7E7;padding:9px 12px;border-radius:20px;">
                            ACCOUNT SECURITY
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="height:4px;background-color:#D3202F;font-size:0;line-height:0;">
            &nbsp;
        </td>
    </tr>

    <tr>
        <td style="padding:42px 35px 35px;">

            <div style="font-size:11px;font-weight:bold;letter-spacing:2px;color:#D3202F;margin-bottom:14px;">
                EMAIL VERIFICATION
            </div>

            <h1 style="font-size:30px;line-height:1.25;color:#14171F;margin:0 0 20px;font-weight:700;">
                One last step to get started.
            </h1>

            <p style="font-size:15px;line-height:1.8;color:#5B6272;margin:0 0 12px;">
                Hello, Student!
            </p>

            <p style="font-size:14px;line-height:1.8;color:#5B6272;margin:0 0 28px;">
                Thank you for registering with the WMSU Student Council Funds Management System.
                To complete your registration, please use the verification code below.
            </p>

            <!-- Verification code box -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                   style="background-color:#FBF7F7;border:1px solid #EBDDDF;border-radius:10px;">
                <tr>
                    <td align="center" style="padding:27px 15px;">

                        <div style="font-size:11px;font-weight:bold;letter-spacing:1.5px;color:#5B6272;margin-bottom:15px;">
                            YOUR ONE-TIME VERIFICATION CODE
                        </div>

                        <div style="font-size:38px;line-height:1.3;font-weight:800;letter-spacing:9px;color:#D3202F;">
                            {$safeCode}
                        </div>

                        <div style="font-size:12px;color:#5B6272;margin-top:15px;">
                             This code expires in <strong style="color:#3A404C;">{$safeTtl} minutes</strong>.
                        </div>

                    </td>
                </tr>
            </table>

            <p style="font-size:13px;line-height:1.8;color:#5B6272;margin:25px 0;">
                Enter this code on the registration page to verify your email address.
                For your protection, never share this code with anyone.
            </p>

            <!-- Security notice -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                   style="background-color:#FDE7E7;border-left:4px solid #B3121F;">
                <tr>
                    <td style="padding:16px 18px;">
                        <div style="font-size:13px;font-weight:bold;color:#B3121F;margin-bottom:7px;">
                            Security notice
                        </div>
                        <div style="font-size:12px;line-height:1.7;color:#3A404C;">
                            If you did not request this verification code, you can safely ignore this email.
                            No action is required.
                        </div>
                    </td>
                </tr>
            </table>

        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="padding:25px 35px;background-color:#F7F3F3;border-top:1px solid #F3C9CD;">

            <div style="font-size:14px;font-weight:bold;color:#14171F;margin-bottom:7px;">
                WMSU Student Council Funds
            </div>

            <div style="font-size:11px;line-height:1.7;color:#5B6272;">
                Student Council Funds Management System
            </div>

            <div style="height:1px;background-color:#F3C9CD;margin:18px 0;"></div>

            <div style="font-size:10px;line-height:1.7;color:#5B6272;">
                This is an automated account verification email. Please do not reply.
                <br>
                &copy; WMSU Student Council Funds Management System.
            </div>

        </td>
    </tr>

</table>

</td>
</tr>
</table>

</body>
</html>
HTML;

    // Send both plain-text and HTML versions through your existing SMTP mailer.
    $result = send_mail($email, $subject, $textBody, $htmlBody);

    // Return the format expected by signup.php.
    if (isset($result['ok'])) {
        return [
            (bool)$result['ok'],
            (string)($result['error'] ?? $result['message'] ?? '')
        ];
    }

    return [
        (bool)($result[0] ?? false),
        (string)($result[1] ?? '')
    ];
}

function current_user(): ?array
{
    static $loaded = false, $user = null;
    if ($loaded) return $user;
    $loaded = true;
    $id = (int)($_SESSION['account_id'] ?? 0);
    if (!$id) return null;
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT a.account_id, a.email, a.role, a.display_name, a.student_id, a.profile_photo,
                s.student_no, s.first_name, s.middle_name, s.last_name, s.suffix, s.program, s.college,
                s.year_level, s.school_year, s.contact_no, c.college_id, c.college_name
           FROM user_account a LEFT JOIN student s ON s.student_id = a.student_id
           LEFT JOIN college c ON c.college_name = s.college
          WHERE a.account_id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        unset($_SESSION['account_id']);
        return null;
    }
    $row['name'] = $row['role'] === 'admin' ? ($row['display_name'] ?: 'Administrator') : full_name($row);
    $row['positions'] = [];
    $row['officer_ids'] = [];
    if ($row['student_id']) {
        $today = date('Y-m-d');
        $o = $pdo->prepare('SELECT officer_id, position, term_start, term_end FROM officer WHERE student_id = ? AND term_start <= ? AND (term_end IS NULL OR term_end >= ?) ORDER BY term_start');
        $o->execute([$row['student_id'], $today, $today]);
        $row['positions'] = $o->fetchAll();
        $row['officer_ids'] = array_map('intval', array_column($row['positions'], 'officer_id'));
    }
    $row['mayor_assignments'] = [];
    if ($row['role'] !== 'admin') {
        $m = $pdo->prepare('SELECT cm.assignment_id, cm.college_id, c.college_name, cm.start_date, cm.end_date FROM college_mayor cm JOIN college c ON c.college_id=cm.college_id WHERE cm.account_id=? AND cm.is_active=1 AND cm.start_date<=? AND (cm.end_date IS NULL OR cm.end_date>=?) ORDER BY cm.start_date DESC');
        $m->execute([$id, date('Y-m-d'), date('Y-m-d')]);
        $row['mayor_assignments'] = $m->fetchAll();
    }
    $row['level'] = $row['role'] === 'admin' ? 'admin' : ($row['mayor_assignments'] ? 'mayor' : ($row['positions'] ? 'officer' : 'student'));
    if ($row['level'] === 'mayor') {
        $row['college_id'] = (int)$row['mayor_assignments'][0]['college_id'];
        $row['college'] = $row['mayor_assignments'][0]['college_name'];
    }
    return $user = $row;
}

function is_admin(array $u): bool
{
    return $u['level'] === 'admin';
}

function level_label(array $u): string
{
    return match ($u['level']) {
        'admin'   => 'Administrator',
        'officer' => implode(', ', array_column($u['positions'], 'position')),
        'mayor' => 'College Mayor',
        default   => 'Student',
    };
}

function forbidden(string $msg = 'Your account does not have access to this page.'): never
{
    http_response_code(403);
    page_start('No access');
    echo '<div class="card"><h1>No access</h1><p>' . e($msg) . '</p>'
        . '<p><a class="btn" href="dashboard.php">Back to dashboard</a></p></div>';
    page_end();
    exit;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $next = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
        if (!empty($_SERVER['QUERY_STRING'])) {
            $next .= '?' . $_SERVER['QUERY_STRING'];
        }
        redirect('login.php?next=' . urlencode($next));
    }
    return $u;
}

function require_staff(): array
{
    $u = require_login();
    if (!in_array($u['level'], ['admin', 'mayor', 'officer'], true)) {
        forbidden('This page is for council officers and the administrator.');
    }
    return $u;
}

function can_write(string $table, array $u): bool
{
    $rule = entities()[$table]['write'] ?? 'admin';
    if ($u['level'] === 'admin') return true;
    return $u['level'] === 'mayor' && $rule === 'officer' && !in_array($table, ['student', 'officer', 'expense_category'], true);
}

function entity_scope_sql(string $table, array $u): array
{
    if ($u['level'] === 'admin') return ['1=1', []];
    if ($u['level'] !== 'mayor') return ['1=0', []];
    $college = (string)($u['college'] ?? '');
    return match ($table) {
        'student' => ['college = ?', [$college]],
        'officer' => ['student_id IN (SELECT student_id FROM student WHERE college = ?)', [$college]],
        'fund_source' => ['college_id = ?', [(int)$u['college_id']]],
        'collection' => ['college_id = ?', [(int)$u['college_id']]],
        'budget' => ['college_id = ?', [(int)$u['college_id']]],
        'project' => ["budget_id IN (SELECT budget_id FROM budget WHERE college_id = ?) AND officer_id IN (SELECT o.officer_id FROM officer o JOIN student s ON s.student_id=o.student_id WHERE s.college = ?) AND request_id IN (SELECT request_id FROM fund_request WHERE status='Approved' AND college_id = ?)", [(int)$u['college_id'], $college, (int)$u['college_id']]],
        'expense' => ["college_id = ? AND project_id IN (SELECT p.project_id FROM project p JOIN budget b ON b.budget_id=p.budget_id WHERE b.college_id=? AND p.request_id IN (SELECT request_id FROM fund_request WHERE status='Approved' AND college_id=?))", [(int)$u['college_id'], (int)$u['college_id'], (int)$u['college_id']]],
        'expense_category' => ['1=1', []],
        default => ['1=0', []],
    };
}

function require_admin(): array
{
    $u = require_login();
    if ($u['level'] !== 'admin') forbidden('Only the university administrator can access this page.');
    return $u;
}

function user_college_id(array $u): int
{
    return (int)($u['college_id'] ?? 0);
}

function can_manage_college(array $u, int $collegeId): bool
{
    return $u['level'] === 'admin' || ($u['level'] === 'mayor' && user_college_id($u) === $collegeId);
}

function safe_next(string $next): string
{
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $next) ? $next : 'dashboard.php';
}
