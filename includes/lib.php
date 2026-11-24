<?php
declare(strict_types=1);

session_name('CF_SESSID');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/migrate.php';

const AVATAR_DIR       = __DIR__ . '/../uploads/avatars';
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;    
const SCHEMA_VERSION   = 5;                  

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/** Split a verified payment into the college (70%) and university (30%) shares. */
function fee_distribution(float $amount): array
{
    $college = round($amount * 0.70, 2);
    return ['college' => $college, 'university' => round($amount - $college, 2)];
}


function audit_event(int $accountId, ?int $collegeId, string $action, string $entityType, ?int $entityId = null, array $oldValues = [], array $newValues = []): void
{
    $st = db()->prepare('INSERT INTO audit_log (account_id,college_id,action,entity_type,entity_id,old_values,new_values,ip_address) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([
        $accountId ?: null, $collegeId ?: null, mb_substr($action, 0, 80), mb_substr($entityType, 0, 80), $entityId,
        $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
        $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
    ]);
}

function money($v): string
{
    $n = (float)$v;
    return ($n < 0 ? '-' : '') . CURRENCY . number_format(abs($n), 2);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = [$type, $msg];
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $expected = (string)($_SESSION['csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Please go back, refresh the page and try again.');
    }
}

function db(bool $showError = true): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        if ((int)($_SESSION['schema_v'] ?? 0) < SCHEMA_VERSION) {
            migrate_schema($pdo);
            $_SESSION['schema_v'] = SCHEMA_VERSION;
        }
    } catch (PDOException $ex) {
        $pdo = null;
        if (!$showError) {
            throw $ex;
        }
        public_start('Database not ready');
        echo '<div class="flash err"><strong>Cannot connect to the database.</strong><br>'
           . e($ex->getMessage()) . '</div>'
           . '<p>Check the settings in <code>config.php</code>, then run the '
           . '<a href="setup.php">setup page</a> to create the tables.</p>';
        public_end();
        exit;
    }
    return $pdo;
}

function friendly_error(PDOException $ex): string
{
    $code = (int)($ex->errorInfo[1] ?? 0);
    return match (true) {
        $code === 1451 => 'This record is still used by other records (for example payments, projects or expenses), so it cannot be deleted. Remove or reassign those first.',
        $code === 1452 => 'The related record you picked does not exist.',
        $code === 1062 => 'That value already exists. Receipt numbers, voucher numbers, student numbers, names and school years must be unique.',
        $code === 3819 || $code === 4025 => 'One of the values breaks a database rule (for example an amount must be greater than zero, or an end date cannot be before the start date).',
        $code === 1264 || $code === 1406 => 'A value is too large or too long for its column.',
        default => 'Database error: ' . $ex->getMessage(),
    };
}

const NAME_PATTERN = '/^[\p{L}][\p{L}\p{M}\s.\'’\-]*$/u';

function colleges_list(): array
{
    $fallback = [
        'College of Agriculture', 'College of Architecture', 'College of Asian and Islamic Studies',
        'College of Computing Studies', 'College of Criminal Justice Education', 'College of Engineering',
        'College of Forestry and Environmental Studies', 'College of Home Economics', 'College of Law',
        'College of Liberal Arts', 'College of Medicine', 'College of Nursing',
        'College of Sports Science and Physical Education', 'College of Public Administration and Development Studies',
        'College of Science and Mathematics', 'College of Social Work and Community Development',
        'College of Teacher Education',
    ];
    try {
        $rows = db(false)->query('SELECT college_name FROM college WHERE is_active=1 ORDER BY college_name')->fetchAll(PDO::FETCH_COLUMN);
        if ($rows) return array_values(array_map('strval', $rows));
    } catch (Throwable $e) { /* Use the initial list if the catalog is not available yet. */ }
    return $fallback;
}

/** Active program choices are loaded from the Admin-maintained catalog. */
function programs_by_college(): array
{
    $fallback = [
        'College of Agriculture' => ['BS Agribusiness', 'BS Agriculture major in Animal Science', 'BS Agriculture major in Crop Science', 'BS Food Technology'],
        'College of Architecture' => ['BS Architecture'],
        'College of Asian and Islamic Studies' => ['BA Asian Studies', 'BA Islamic Studies', 'Diploma in Arabic Language'],
        'College of Computing Studies' => ['BS Computer Science', 'BS Information Technology', 'Associate in Computer Technology'],
        'College of Criminal Justice Education' => ['BS Criminology'],
        'College of Engineering' => ['BS Civil Engineering', 'BS Computer Engineering', 'BS Electrical Engineering', 'BS Mechanical Engineering'],
        'College of Forestry and Environmental Studies' => ['BS Forestry'],
        'College of Home Economics' => ['BS Home Economics'],
        'College of Law' => ['Juris Doctor'],
        'College of Liberal Arts' => ['AB English', 'AB History', 'AB Political Science', 'BS Economics', 'BS Psychology'],
        'College of Medicine' => ['Doctor of Medicine'],
        'College of Nursing' => ['BS Nursing'],
        'College of Sports Science and Physical Education' => ['Bachelor of Physical Education', 'BS Exercise and Sports Sciences'],
        'College of Public Administration and Development Studies' => ['BS Public Administration'],
        'College of Science and Mathematics' => ['BS Biology', 'BS Chemistry', 'BS Mathematics'],
        'College of Social Work and Community Development' => ['BS Social Work'],
        'College of Teacher Education' => ['Bachelor of Elementary Education', 'Bachelor of Secondary Education', 'Bachelor of Early Childhood Education'],
    ];
    try {
        $rows = db(false)->query('SELECT c.college_name,p.program_name FROM program_catalog p JOIN college c ON c.college_id=p.college_id WHERE c.is_active=1 AND p.is_active=1 ORDER BY c.college_name,p.program_name')->fetchAll();
        if ($rows) {
            $map = [];
            foreach ($rows as $row) $map[$row['college_name']][] = $row['program_name'];
            return $map;
        }
    } catch (Throwable $e) { /* Use the initial list if the catalog is not available yet. */ }
    return $fallback;
}

function program_options(): array
{
    $groups = programs_by_college();
    return $groups ? array_merge(...array_values($groups)) : [];
}

function college_of(string $program): ?string
{
    foreach (programs_by_college() as $college => $list) {
        if (in_array($program, $list, true)) return $college;
    }
    return null;
}

/** Current school year first, then the three before it (e.g. 2026-2027, 2025-2026, ...). */
function school_year_options(): array
{
    $start = (int)date('n') >= 6 ? (int)date('Y') : (int)date('Y') - 1;
    $out = [];
    for ($y = $start; $y >= $start - 3; $y--) {
        $out[] = $y . '-' . ($y + 1);
    }
    return $out;
}

function valid_person_name(string $v, int $max = 50): bool
{
    return $v !== '' && mb_strlen($v) <= $max && (bool)preg_match(NAME_PATTERN, $v);
}

/** "Ana Marie Reyes", or "Ana M. Reyes" when $short is true. */
function full_name(array $r, bool $short = false): string
{
    $mid = trim((string)($r['middle_name'] ?? ''));
    if ($mid !== '' && $short) {
        $mid = mb_substr($mid, 0, 1) . '.';
    }
    return trim(($r['first_name'] ?? '') . ' ' . $mid . ' ' . ($r['last_name'] ?? '') . ' ' . ($r['suffix'] ?? ''));
}

/** Philippine mobile number → 09XXXXXXXXX, or null when it does not look right. */
function normalize_phone(string $v): ?string
{
    $d = preg_replace('/[\s\-().]/', '', $v);
    if (preg_match('/^\+?63(9\d{9})$/', $d, $m)) {
        return '0' . $m[1];
    }
    return preg_match('/^09\d{9}$/', $d) ? $d : null;
}

function pretty_phone(?string $v): string
{
    return $v && preg_match('/^(\d{4})(\d{3})(\d{4})$/', $v, $m) ? "$m[1] $m[2] $m[3]" : (string)$v;
}

/** <option> tags for a dropdown. $groups (college => programs) makes grouped options. */
function options_html(array $options, string $val, bool $optional = false, array $groups = []): string
{
    $h = '<option value="">' . ($optional ? '—' : '— choose —') . '</option>';
    if ($val !== '' && !in_array($val, $options, true)) {
        $h .= '<option value="' . e($val) . '" selected>' . e($val) . ' (current)</option>';
    }
    $opt = fn(string $o) => '<option value="' . e($o) . '"' . ($o === $val ? ' selected' : '') . '>' . e($o) . '</option>';
    if ($groups) {
        foreach ($groups as $label => $list) {
            $h .= '<optgroup label="' . e($label) . '">' . implode('', array_map($opt, $list)) . '</optgroup>';
        }
    } else {
        $h .= implode('', array_map($opt, $options));
    }
    return $h;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $a = mb_substr($parts[0] ?? '?', 0, 1);
    $b = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($a . $b);
}

function avatar(string $name, ?string $photo, string $size = ''): string
{
    if ($photo && preg_match('/^[A-Za-z0-9_.\-]+$/', $photo)) {
        return '<img class="avatar ' . e($size) . '" src="uploads/avatars/' . e($photo) . '" alt="">';
    }
    return '<span class="avatar ini ' . e($size) . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function delete_avatar(?string $file): void
{
    if ($file && preg_match('/^[A-Za-z0-9_.\-]+$/', $file)) {
        $path = AVATAR_DIR . '/' . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function save_avatar(array $file, int $accountId): ?string
{
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('That picture is too large. Please use one under 2 MB.');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        throw new RuntimeException('The picture could not be uploaded. Please try again.');
    }
    if ((int)$file['size'] > AVATAR_MAX_BYTES) {
        throw new RuntimeException('That picture is too large. Please use one under 2 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Please upload a JPG, PNG or WebP picture.');
    }
    if ($info[0] * $info[1] > 25_000_000) {
        throw new RuntimeException('That picture has too many pixels. Please use a smaller one.');
    }
    if (!is_dir(AVATAR_DIR) && !@mkdir(AVATAR_DIR, 0755, true)) {
        throw new RuntimeException('The uploads folder could not be created. Check folder permissions.');
    }
    $base = 'u' . $accountId . '_' . bin2hex(random_bytes(6));

    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if (!$src) {
            throw new RuntimeException('That picture could not be read. Try a different file.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(360, 360);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 234, 247, 241));   // palette mist behind transparent PNGs
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 360, 360, $side, $side);
        $name = $base . '.jpg';
        $ok = imagejpeg($dst, AVATAR_DIR . '/' . $name, 86);
        imagedestroy($src);
        imagedestroy($dst);
    } else {
        $name = $base . '.' . $types[$info[2]];
        $ok = move_uploaded_file($file['tmp_name'], AVATAR_DIR . '/' . $name);
    }
    if (!$ok) {
        throw new RuntimeException('The picture could not be saved. Check folder permissions.');
    }
    return $name;
}

function pending_requests(): int
{
    static $n = null;
    if ($n === null) {
        $n = (int)db()->query("SELECT COUNT(*) FROM fund_request WHERE status = 'Pending'")->fetchColumn();
    }
    return $n;
}

function icon(string $name): string
{
    $p = [
        'home'     => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5h13V10"/><path d="M10 19.5v-5h4v5"/>',
        'user'     => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c.8-3.6 3.8-5.4 7.5-5.4s6.7 1.8 7.5 5.4"/>',
        'inbox'    => '<path d="M4 13.5 6.5 5h11L20 13.5"/><path d="M4 13.5V19h16v-5.5h-4.5a3.5 3.5 0 0 1-7 0z"/>',
        'book'     => '<path d="M5 4.5h11.5a2 2 0 0 1 2 2V20H7a2 2 0 0 1-2-2z"/><path d="M5 18a2 2 0 0 1 2-2h11.5"/>',
        'users'    => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3 19.5c.6-3.2 3-4.8 6-4.8s5.4 1.6 6 4.8"/><path d="M16 5.6a3.2 3.2 0 0 1 0 5.8"/><path d="M18 15c1.8.5 2.8 2 3 4.5"/>',
        'student'  => '<path d="m3 9 9-4.5L21 9l-9 4.5z"/><path d="M7 11.5V16c0 1.2 2.2 2.5 5 2.5s5-1.3 5-2.5v-4.5"/>',
        'badge'    => '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="12" cy="10" r="2.4"/><path d="M7.5 17c.6-2 2.2-3 4.5-3s3.9 1 4.5 3"/>',
        'coins'    => '<ellipse cx="12" cy="7" rx="7" ry="3"/><path d="M5 7v5c0 1.7 3.1 3 7 3s7-1.3 7-3V7"/><path d="M5 12v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5"/>',
        'wallet'   => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><rect x="4" y="8" width="16" height="11" rx="2.5"/><circle cx="15.5" cy="13.5" r="1.1"/>',
        'receipt'  => '<path d="M6 3.5h12V21l-3-2-3 2-3-2-3 2z"/><path d="M9 8.5h6M9 12h6"/>',
        'folder'   => '<path d="M3.5 7.5a2 2 0 0 1 2-2H10l2 2.5h6.5a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',
        'tag'      => '<path d="M3.5 12.2V5.5a2 2 0 0 1 2-2h6.7l8.3 8.3a2 2 0 0 1 0 2.8l-5.6 5.6a2 2 0 0 1-2.8 0z"/><circle cx="8.5" cy="8.5" r="1.2"/>',
        'chart'    => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-4M12.5 16V8M17 16v-6"/>',
        'logout'   => '<path d="M14 4.5H6.5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2H14"/><path d="M10 12h10M17 8.5 20.5 12 17 15.5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'camera'   => '<path d="M4 8.5a2 2 0 0 1 2-2h2l1.2-1.8h5.6L16 6.5h2a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><circle cx="12" cy="12.5" r="3.2"/>',
        'shield'   => '<path d="M12 3.5 5 6v5.5c0 4.2 2.8 7.4 7 9 4.2-1.6 7-4.8 7-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ($p[$name] ?? '') . '</svg>';
}

function entities(): array
{
    return [
        'student' => [
            'title' => 'Students', 'single' => 'Student', 'group' => 'people',
            'pk' => 'student_id', 'order' => 'last_name, first_name',
            'fields' => [
                'student_no'  => ['label' => 'Student No.', 'type' => 'text', 'max' => 20],
                'first_name'  => ['label' => 'First name', 'type' => 'text', 'max' => 50,
                                  'pattern' => NAME_PATTERN, 'hint' => 'Letters, spaces, hyphens and apostrophes only.'],
                'middle_name' => ['label' => 'Middle name', 'type' => 'text', 'max' => 50, 'optional' => true,
                                  'pattern' => NAME_PATTERN],
                'last_name'   => ['label' => 'Last name', 'type' => 'text', 'max' => 50,
                                  'pattern' => NAME_PATTERN],
                'suffix'      => ['label' => 'Suffix', 'type' => 'select', 'options' => ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'Other'], 'optional' => true],
                'program'     => ['label' => 'Program', 'type' => 'select', 'options' => program_options(),
                                  'groups' => programs_by_college(), 'hint' => 'The college is filled in from the program.'],
                'college'     => ['label' => 'College', 'type' => 'derived'],
                'year_level'  => ['label' => 'Year level', 'type' => 'number', 'min' => 1, 'max' => 6],
                'school_year' => ['label' => 'School year', 'type' => 'select', 'options' => school_year_options(), 'optional' => true],
                'contact_no'  => ['label' => 'Contact number', 'type' => 'phone', 'max' => 20, 'optional' => true,
                                  'hint' => 'e.g. 0917 123 4567'],
            ],
        ],
        'officer' => [
            'title' => 'Officers', 'single' => 'Officer', 'group' => 'people',
            'pk' => 'officer_id', 'order' => 'term_start DESC, officer_id DESC',
            'fields' => [
                'student_id' => ['label' => 'Student', 'type' => 'fk', 'fk' => 'student'],
                'position'   => ['label' => 'Position', 'type' => 'text', 'max' => 40,
                                 'suggest' => ['President', 'Vice President', 'Secretary', 'Treasurer', 'Auditor', 'PIO']],
                'term_start' => ['label' => 'Term start', 'type' => 'date'],
                'term_end'   => ['label' => 'Term end', 'type' => 'date', 'optional' => true,
                                 'not_before' => 'term_start', 'hint' => 'Leave blank for the current term.'],
            ],
        ],
        'fund_source' => [
            'title' => 'Fund Sources', 'single' => 'Fund source', 'group' => 'in', 'write' => 'officer',
            'pk' => 'source_id', 'order' => 'source_name',
            'fields' => [
                'source_name' => ['label' => 'Source name', 'type' => 'text', 'max' => 60,
                                  'hint' => 'e.g. Org Fee, Fundraiser, Donation'],
                'source_type' => ['label' => 'Source type', 'type' => 'text', 'max' => 30,
                                  'suggest' => ['Mandatory', 'Voluntary', 'Grant']],
            ],
        ],
        'collection' => [
            'title' => 'Collections', 'single' => 'Collection', 'group' => 'in', 'write' => 'officer',
            'pk' => 'collection_id', 'order' => 'collection_date DESC, collection_id DESC',
            'fields' => [
                'student_id'      => ['label' => 'Paid by (student)', 'type' => 'fk', 'fk' => 'student'],
                'source_id'       => ['label' => 'Fund source', 'type' => 'fk', 'fk' => 'fund_source'],
                'officer_id'      => ['label' => 'Received by (officer)', 'type' => 'fk', 'fk' => 'officer'],
                'receipt_no'      => ['label' => 'Receipt No.', 'type' => 'text', 'max' => 20],
                'amount'          => ['label' => 'Amount', 'type' => 'money', 'digits' => 8, 'positive' => true],
                'collection_date' => ['label' => 'Date collected', 'type' => 'date', 'default' => 'today'],
            ],
        ],
        'budget' => [
            'title' => 'Budgets', 'single' => 'Budget', 'group' => 'plan', 'write' => 'officer',
            'pk' => 'budget_id', 'order' => 'school_year DESC',
            'fields' => [
                'college_id'   => ['label' => 'College (leave blank for university-level budget)', 'type' => 'fk', 'fk' => 'college', 'optional' => true],
                'school_year'  => ['label' => 'School year', 'type' => 'text', 'max' => 9,
                                   'pattern' => '/^(\d{4})-(\d{4})$/', 'hint' => 'Format: 2026-2027'],
                'total_amount' => ['label' => 'Total amount', 'type' => 'money', 'digits' => 10],
                'status'       => ['label' => 'Status', 'type' => 'select',
                                   'options' => ['Draft', 'Approved', 'Closed'], 'default' => 'Draft'],
            ],
        ],
        'project' => [
            'title' => 'Projects', 'single' => 'Project', 'group' => 'plan', 'write' => 'officer',
            'pk' => 'project_id', 'order' => 'start_date DESC, project_id DESC',
            'fields' => [
                'budget_id'        => ['label' => 'College budget (school year)', 'type' => 'fk', 'fk' => 'budget'],
                'request_id'       => ['label' => 'Approved request', 'type' => 'fk', 'fk' => 'fund_request', 'optional' => true],
                'officer_id'       => ['label' => 'Project head (officer)', 'type' => 'fk', 'fk' => 'officer'],
                'title'            => ['label' => 'Title', 'type' => 'text', 'max' => 120],
                'allocated_amount' => ['label' => 'Allocated amount', 'type' => 'money', 'digits' => 10],
                'start_date'       => ['label' => 'Start date', 'type' => 'date', 'default' => 'today'],
                'end_date'         => ['label' => 'End date', 'type' => 'date', 'optional' => true,
                                       'not_before' => 'start_date'],
            ],
        ],
        'expense_category' => [
            'title' => 'Expense Categories', 'single' => 'Category', 'group' => 'plan',
            'pk' => 'category_id', 'order' => 'category_name',
            'fields' => [
                'category_name' => ['label' => 'Category name', 'type' => 'text', 'max' => 60,
                                    'hint' => 'e.g. Food, Printing, Venue'],
            ],
        ],
        'expense' => [
            'title' => 'Expenses', 'single' => 'Expense', 'group' => 'plan', 'write' => 'officer',
            'pk' => 'expense_id', 'order' => 'expense_date DESC, expense_id DESC',
            'fields' => [
                'project_id'   => ['label' => 'Project', 'type' => 'fk', 'fk' => 'project'],
                'category_id'  => ['label' => 'Category', 'type' => 'fk', 'fk' => 'expense_category'],
                'officer_id'   => ['label' => 'Approved by (officer)', 'type' => 'fk', 'fk' => 'officer'],
                'voucher_no'   => ['label' => 'Voucher No.', 'type' => 'text', 'max' => 20],
                'amount'       => ['label' => 'Amount', 'type' => 'money', 'digits' => 8, 'positive' => true],
                'expense_date' => ['label' => 'Date spent', 'type' => 'date', 'default' => 'today'],
                'description'  => ['label' => 'Description', 'type' => 'textarea', 'max' => 255, 'optional' => true],
            ],
        ],
    ];
}

function fk_options(string $entity): array
{
    static $cache = [];
    $u = current_user();
    $scopeKey = ($u['level'] ?? 'guest') . ':' . (string)($u['college_id'] ?? 0);
    $key = $entity . ':' . $scopeKey;
    if (isset($cache[$key])) return $cache[$key];
    $mayor = $u && $u['level'] === 'mayor';
    $sql = [
        'college' => "SELECT college_id id, college_name label FROM college WHERE is_active=1 ORDER BY college_name",
        'fund_request' => "SELECT request_id id, CONCAT(event_title, ' (', school_year, ')') label FROM fund_request WHERE status='Approved'" . ($mayor ? ' AND college_id = ?' : '') . " ORDER BY school_year DESC,event_title",
        'student' => "SELECT student_id id, CONCAT(student_no, ' – ', last_name, ', ', first_name) label FROM student" . ($mayor ? ' WHERE college = ?' : '') . " ORDER BY last_name, first_name",
        'officer' => "SELECT o.officer_id id, CONCAT(s.last_name, ', ', s.first_name, ' (', o.position, ' ', YEAR(o.term_start), ')') label FROM officer o JOIN student s ON s.student_id=o.student_id" . ($mayor ? ' WHERE s.college = ?' : '') . " ORDER BY s.last_name, s.first_name, o.term_start",
        'fund_source' => "SELECT source_id id, source_name label FROM fund_source" . ($mayor ? ' WHERE college_id = ? OR college_id IS NULL' : '') . " ORDER BY source_name",
        'budget' => "SELECT budget_id id, school_year label FROM budget" . ($mayor ? ' WHERE college_id = ?' : '') . " ORDER BY school_year DESC",
        'project' => "SELECT p.project_id id, CONCAT(p.title, ' (', b.school_year, ')') label FROM project p JOIN budget b ON b.budget_id=p.budget_id" . ($mayor ? " WHERE b.college_id = ? AND p.officer_id IN (SELECT o.officer_id FROM officer o JOIN student s ON s.student_id=o.student_id WHERE s.college = ?) AND p.request_id IN (SELECT request_id FROM fund_request WHERE status='Approved' AND college_id = ?)" : '') . " ORDER BY b.school_year DESC,p.title",
        'expense_category' => "SELECT category_id id, category_name label FROM expense_category ORDER BY category_name",
    ];
    if (!isset($sql[$entity])) return [];
    $st = db()->prepare($sql[$entity]);
    $params = match ($entity) {
        'fund_request','budget','fund_source' => $mayor ? [(int)$u['college_id']] : [],
        'student','officer' => $mayor ? [(string)$u['college']] : [],
        'project' => $mayor ? [(int)$u['college_id'], (string)$u['college'], (int)$u['college_id']] : [],
        default => [],
    };
    $st->execute($params);
    return $cache[$key] = $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        [$type, $msg] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="flash ' . e($type) . '">' . e($msg) . '</div>';
    }
}

const FONT_LINK = 'https://fonts.googleapis.com/css2?family=Newsreader:wght@500;600&family=Public+Sans:wght@400;500;600;700&display=swap';

function html_head(string $title): void
{
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#BFE7D2">
<title><?= e($title) ?> · Council Funds</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= FONT_LINK ?>">
<link rel="stylesheet" href="assets/style.css?v=3">
<script src="assets/app.js?v=2" defer></script>
</head>
<?php
}

function public_start(string $title): void
{
    $in = !empty($_SESSION['account_id']);
    html_head($title);
    ?>
<body class="pub">
<header class="pubhead"><div class="wrap pubbar">
  <a class="brand" href="index.php"><span class="logo">₱</span><span>Council Funds</span></a>
  <nav class="pubnav" aria-label="Main">
    <a href="index.php#features">Features</a>
    <a href="index.php#how">How it works</a>
    <a href="index.php#roles">Roles</a>
    <?php if ($in): ?>
      <a class="btn" href="dashboard.php">Open dashboard</a>
    <?php else: ?>
      <a class="keep" href="login.php">Log in</a>
      <a class="btn" href="signup.php">Sign up</a>
    <?php endif; ?>
  </nav>
</div></header>
<main class="pubmain">
<?php
}

function public_end(): void
{
    ?></main>
<footer class="pubfoot"><div class="wrap">
  <span><strong>Council Funds</strong> &middot; Student council finance system</span>
  <span>Sign-ups are limited to <code><?= e(ALLOWED_EMAIL_DOMAIN) ?></code> email addresses.</span>
</div></footer>
</body>
</html>
<?php
}

function page_start(string $title, string $active = ''): void
{
    $u = current_user();
    $staff = $u && in_array($u['level'], ['admin', 'officer', 'mayor'], true);
    $groups = [
        'people' => ['People', 'users'],
        'in'     => ['Money in', 'coins'],
        'plan'   => ['Planning and spending', 'folder'],
    ];
    $icons = [
        'student' => 'student', 'officer' => 'badge', 'fund_source' => 'wallet', 'collection' => 'receipt',
        'budget' => 'chart', 'project' => 'folder', 'expense_category' => 'tag', 'expense' => 'receipt',
    ];
    $pending = ($u && $u['level'] === 'admin') ? pending_requests() : 0;
    html_head($title);
    ?>
<body>
<aside class="side">
  <a class="brand" href="dashboard.php"><span class="logo">₱</span><span>Council Funds<small>Student council finance</small></span></a>
  <nav aria-label="Main">
    <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'on' : '' ?>"><?= icon('home') ?>Dashboard</a>
    <?php if ($u): ?>
      <a href="profile.php" class="<?= $active === 'profile' ? 'on' : '' ?>"><?= icon('user') ?>My profile</a>
      <a href="announcements.php" class="<?= $active === 'announcements' ? 'on' : '' ?>"><?= icon('bell') ?>Announcements</a>
      <a href="payments.php" class="<?= $active === 'payments' ? 'on' : '' ?>"><?= icon('receipt') ?><?= $staff ? 'Payments' : 'My payments' ?></a>
    <?php endif; ?>
    <?php if ($staff): ?>
      <a href="requests.php" class="<?= $active === 'requests' ? 'on' : '' ?>"><?= icon('inbox') ?>Requests<?php if ($pending): ?><span class="badge"><?= (int)$pending ?></span><?php endif; ?></a>
      <a href="college_ledger.php" class="<?= $active === 'college_ledger' ? 'on' : '' ?>"><?= icon('book') ?>College history</a>
      <?php if ($u['level'] === 'admin'): ?><a href="university_ledger.php" class="<?= $active === 'university_ledger' ? 'on' : '' ?>"><?= icon('wallet') ?>University fund</a><?php endif; ?>
      <?php if ($u['level'] === 'admin' || $u['level'] === 'mayor'): ?><a href="audit.php" class="<?= $active === 'audit' ? 'on' : '' ?>"><?= icon('shield') ?>Audit trail</a><?php endif; ?>
      <?php if ($u['level'] === 'admin' || $u['level'] === 'mayor'): ?>
      <a href="members.php" class="<?= $active === 'members' ? 'on' : '' ?>"><?= icon('users') ?>Members</a>
      <?php endif; ?>
      <?php if ($u['level'] === 'admin'): ?><a href="mayors.php" class="<?= $active === 'mayors' ? 'on' : '' ?>"><?= icon('shield') ?>Mayor assignments</a><?php endif; ?>
      <?php if ($u['level'] === 'admin'): ?><a href="enrollments.php" class="<?= $active === 'enrollments' ? 'on' : '' ?>"><?= icon('users') ?>Enrollment records</a><?php endif; ?>
      <?php if ($u['level'] === 'admin'): ?><a href="catalog.php" class="<?= $active === 'catalog' ? 'on' : '' ?>"><?= icon('book') ?>College/program catalog</a><?php endif; ?>
      <?php if ($u['level'] === 'admin' || $u['level'] === 'mayor'): ?>
      <?php foreach ($groups as $g => [$gname, $gicon]): ?>
        <div class="grp"><?= e($gname) ?></div>
        <?php foreach (entities() as $key => $en): if ($en['group'] !== $g || $key === 'officer' || ($u['level'] === 'mayor' && $key === 'student')) continue; ?>
          <a href="crud.php?t=<?= e($key) ?>" class="<?= $active === $key ? 'on' : '' ?>"><?= icon($icons[$key] ?? $gicon) ?><?= e($en['title']) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </nav>
  <?php if ($u): ?>
  <div class="me">
    <a class="me-who" href="profile.php">
      <?= avatar($u['name'], $u['profile_photo'] ?? null) ?>
      <span><strong><?= e($u['name']) ?></strong>
      <span class="pill <?= e($u['level']) ?>"><?= e(level_label($u)) ?></span></span>
    </a>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <button class="linkbtn" type="submit"><?= icon('logout') ?>Log out</button>
    </form>
  </div>
  <?php endif; ?>
</aside>
<main class="main">
<?php
    show_flash();
}

function page_end(): void
{
    echo "</main>\n</body>\n</html>";
}
