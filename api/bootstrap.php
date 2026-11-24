<?php
declare(strict_types=1);

/**
 * JSON API bootstrap.
 *
 * The React front end talks to the same PHP code the old pages used (includes/lib.php,
 * includes/auth.php, the same SQL, the same validation rules and messages). This file only
 * replaces the "page" layer: instead of HTML + redirects, every endpoint answers with JSON.
 */

ini_set('display_errors', '0');          // never let a PHP notice corrupt the JSON answer
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* ------------------------------------------------------------------ output helpers */

function out(array $data = [], int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/** One error message (shown in a red box). */
function fail(string $msg, int $code = 400, array $extra = []): never
{
    out(['error' => $msg] + $extra, $code);
}

/** A list of validation messages (shown as a red bullet list). */
function errors_out(array $errors): never
{
    out(['errors' => array_values($errors)], 422);
}

/** Success with a one-time notice, like flash('ok', ...) + redirect in the old pages. */
function done(string $msg, array $extra = []): never
{
    out(['ok' => true, 'flash' => ['type' => 'ok', 'msg' => $msg]] + $extra);
}

/** A notice that is not a success, like flash('err', ...) + redirect in the old pages. */
function notice(string $type, string $msg, array $extra = []): never
{
    out(['ok' => $type === 'ok', 'flash' => ['type' => $type, 'msg' => $msg]] + $extra);
}

/** Replacement for forbidden(): 403 + message. */
function deny(string $msg = 'Your account does not have access to this page.'): never
{
    fail($msg, 403, ['code' => 'forbidden']);
}

/* ------------------------------------------------------------------ request helpers */

// JSON bodies are exposed through $_POST so the ported page code can keep reading $_POST[...].
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    $_POST = [];
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            if (is_bool($v)) {
                $_POST[$k] = $v ? '1' : '';
            } elseif (is_scalar($v)) {
                $_POST[$k] = (string)$v;
            } elseif ($v === null) {
                $_POST[$k] = '';
            }
        }
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Same protection as check_csrf(), but the token travels in a header and failures are JSON. */
function api_check_csrf(): void
{
    $expected = (string)($_SESSION['csrf'] ?? '');
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? ''));
    if ($expected === '' || !hash_equals($expected, $sent)) {
        fail('Invalid form token. Please refresh the page and try again.', 400, ['code' => 'csrf']);
    }
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
    api_check_csrf();
}

/* ------------------------------------------------------------------ database + user guards */

$DB_ERROR = null;
try {
    db(false);
} catch (PDOException $ex) {
    $DB_ERROR = $ex->getMessage();
}

function need_db(): PDO
{
    global $DB_ERROR;
    if ($DB_ERROR !== null) {
        fail('Cannot connect to the database. Check config.php, then run setup.php. (' . $DB_ERROR . ')', 503, ['code' => 'db']);
    }
    return db();
}

function need_login(): array
{
    need_db();
    $u = current_user();
    if (!$u) {
        fail('Please log in to continue.', 401, ['code' => 'auth']);
    }
    return $u;
}

function need_staff(): array
{
    $u = need_login();
    if (!in_array($u['level'], ['admin', 'mayor', 'officer'], true)) {
        deny('This page is for council officers and the administrator.');
    }
    return $u;
}

function need_admin(): array
{
    $u = need_login();
    if ($u['level'] !== 'admin') {
        deny('Only the university administrator can access this page.');
    }
    return $u;
}

/* ------------------------------------------------------------------ shared payload builders */

/** The logged-in user as the front end needs it (no password hash is ever selected). */
function user_payload(array $u): array
{
    return [
        'account_id'   => (int)$u['account_id'],
        'email'        => $u['email'],
        'role'         => $u['role'],
        'level'        => $u['level'],
        'label'        => level_label($u),
        'name'         => $u['name'],
        'display_name' => $u['display_name'],
        'first_name'   => $u['first_name'],
        'student_id'   => $u['student_id'] !== null ? (int)$u['student_id'] : null,
        'profile_photo' => $u['profile_photo'],
        'student_no'   => $u['student_no'],
        'program'      => $u['program'],
        'college'      => $u['college'],
        'college_id'   => $u['college_id'] !== null ? (int)$u['college_id'] : null,
        'year_level'   => $u['year_level'] !== null ? (int)$u['year_level'] : null,
        'school_year'  => $u['school_year'],
        'contact_no'   => $u['contact_no'],
        'positions'    => $u['positions'],
    ];
}

/** True when $d is a real Y-m-d date. */
function valid_ymd(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}

/* ------------------------------------------------------------------ last-resort error handler */

set_exception_handler(function (Throwable $ex): void {
    if ($ex instanceof PDOException) {
        $msg = friendly_error($ex);
    } else {
        $msg = 'Something went wrong on the server: ' . $ex->getMessage();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});
