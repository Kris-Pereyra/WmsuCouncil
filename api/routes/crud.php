<?php
declare(strict_types=1);

/**
 * Generic table editor (Students, Fund Sources, Collections, Budgets, Projects, Expense Categories,
 * Expenses). Same entities, scoping, validation and business rules as the old crud.php.
 *   GET  ?r=crud&t=expense                 -> rows + field definitions
 *   GET  ?r=crud&t=expense&view=form[&id=] -> field definitions + dropdown options (+ one record)
 *   POST ?r=crud&t=expense  {do:save|delete,id,...fields}
 */

$user = need_login();
if (!in_array($user['level'], ['admin', 'mayor'], true)) deny('Only the university administrator or an assigned College Mayor can access financial records.');

$ents = entities();
$t = (string)($_GET['t'] ?? '');
if (!isset($ents[$t])) fail('Unknown table.', 404);
if ($t === 'officer') deny('Ordinary College Officer assignments are managed by the assigned College Mayor through Members. The university administrator manages Mayor assignments only.');
[$scopeSql, $scopeParams] = entity_scope_sql($t, $user);
$E   = $ents[$t];
$pk  = $E['pk'];
$pdo = db();
$canWrite = can_write($t, $user);
$myOfficerId = $user['officer_ids'][0] ?? 0;

function collect(array $E, array $orig = []): array
{
    $vals = $raw = $errors = [];
    foreach ($E['fields'] as $name => $f) {
        if ($f['type'] === 'derived') {
            continue;
        }
        $v = trim((string)($_POST[$name] ?? ''));
        $raw[$name] = $v;
        $label = $f['label'];

        if ($v === '') {
            if (!empty($f['optional'])) {
                $vals[$name] = null;
            } else {
                $errors[] = "$label is required.";
            }
            continue;
        }

        switch ($f['type']) {
            case 'text':
            case 'textarea':
                if (mb_strlen($v) > $f['max']) {
                    $errors[] = "$label must be at most {$f['max']} characters.";
                }
                if (isset($f['pattern'])) {
                    if (!preg_match($f['pattern'], $v, $m)) {
                        $errors[] = "$label has the wrong format. " . ($f['hint'] ?? '');
                    } elseif ($name === 'school_year' && (int)$m[2] !== (int)$m[1] + 1) {
                        $errors[] = 'School year must cover consecutive years, e.g. 2026-2027.';
                    }
                }
                break;
            case 'number':
                if (!preg_match('/^\d+$/', $v) || (int)$v < $f['min'] || (int)$v > $f['max']) {
                    $errors[] = "$label must be a whole number from {$f['min']} to {$f['max']}.";
                }
                break;
            case 'money':
                if (!preg_match('/^\d{1,' . $f['digits'] . '}(\.\d{1,2})?$/', $v)) {
                    $errors[] = "$label must be a valid amount (up to 2 decimals).";
                } elseif (!empty($f['positive']) && (float)$v <= 0) {
                    $errors[] = "$label must be greater than zero.";
                }
                break;
            case 'date':
                if (!valid_ymd($v)) {
                    $errors[] = "$label is not a valid date.";
                }
                break;
            case 'select':
                if (!in_array($v, $f['options'], true) && $v !== (string)($orig[$name] ?? null)) {
                    $errors[] = "$label has an invalid choice.";
                }
                break;
            case 'phone':
                $n = normalize_phone($v);
                if ($n === null) {
                    $errors[] = "$label must be a Philippine mobile number such as 0917 123 4567.";
                } else {
                    $v = $n;
                }
                break;
            case 'fk':
                if (!array_key_exists((int)$v, fk_options($f['fk']))) {
                    $errors[] = "$label: the selected record does not exist.";
                }
                break;
        }
        $vals[$name] = $v;
    }
    foreach ($E['fields'] as $name => $f) {
        if (isset($f['not_before']) && !empty($vals[$name]) && !empty($vals[$f['not_before']])
            && $vals[$name] < $vals[$f['not_before']]) {
            $errors[] = $f['label'] . ' cannot be before ' . $E['fields'][$f['not_before']]['label'] . '.';
        }
    }
    return [$vals, $errors, $raw];
}

/** How a stored value is shown in the table (same as the old cell()). */
function crud_cell(array $f, $v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return match ($f['type']) {
        'money' => money($v),
        'fk'    => fk_options($f['fk'])[(int)$v] ?? ('#' . $v),
        'phone' => pretty_phone((string)$v),
        default => (string)$v,
    };
}

if (is_post()) {
    if (!$canWrite) {
        deny('Your role can view these records but not change them.');
    }
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($do === 'delete') {
        try {
            $del = $pdo->prepare("DELETE FROM `$t` WHERE `$pk` = ? AND ($scopeSql)");
            $del->execute([$id, ...$scopeParams]);
            audit_event((int)$user['account_id'], $user['level'] === 'mayor' ? (int)$user['college_id'] : null, 'Deleted', $t, $id);
            notice('ok', $E['single'] . ' deleted.', ['redirect' => "/crud/$t"]);
        } catch (PDOException $ex) {
            notice('err', friendly_error($ex), ['redirect' => "/crud/$t"]);
        }
    }

    if ($do === 'save') {
        $orig = [];
        if ($id) {
            $o = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk` = ? AND ($scopeSql)");
            $o->execute([$id, ...$scopeParams]);
            $orig = $o->fetch() ?: [];
        }
        [$vals, $errors, $form] = collect($E, $orig);
        if ($user['level'] === 'mayor') unset($vals['college_id']);
        if ($t === 'student' && !$errors) {
            $vals['college'] = college_of((string)$vals['program']) ?? ($orig['college'] ?? null);
        }
        if (!$errors && !$id && $user['level'] === 'mayor' && in_array($t, ['budget', 'fund_source'], true)) {
            $vals['college_id'] = (int)$user['college_id'];
        }
        if (!$errors && $t === 'collection') {
            $same = $pdo->prepare('SELECT sc.college_id student_college,oc.college_id officer_college FROM student s JOIN college sc ON sc.college_name=s.college LEFT JOIN officer o ON o.officer_id=? LEFT JOIN student os ON os.student_id=o.student_id LEFT JOIN college oc ON oc.college_name=os.college WHERE s.student_id=?');
            $same->execute([(int)$vals['officer_id'], (int)$vals['student_id']]);
            $sameRow = $same->fetch();
            if (!$sameRow || !$sameRow['officer_college'] || (int)$sameRow['student_college'] !== (int)$sameRow['officer_college']) $errors[] = 'The receiving officer and paying student must belong to the same college.';
            if (!$errors) {
                $sourceCheck = $pdo->prepare('SELECT college_id FROM fund_source WHERE source_id=?');
                $sourceCheck->execute([(int)$vals['source_id']]);
                $sourceCollege = $sourceCheck->fetchColumn();
                if ($sourceCollege !== false && $sourceCollege !== null && (int)$sourceCollege !== (int)$sameRow['student_college']) $errors[] = 'The selected fund source belongs to a different college.';
                if ($user['level'] === 'mayor' && (int)$sameRow['student_college'] !== (int)$user['college_id']) $errors[] = 'You can record collections only for your own college.';
            }
        }
        if (!$errors && !$id && $t === 'collection') {
            $scope = $pdo->prepare('SELECT c.college_id,s.school_year FROM student s LEFT JOIN college c ON c.college_name=s.college WHERE s.student_id=?');
            $scope->execute([(int)$vals['student_id']]);
            $snapshot = $scope->fetch();
            if ($snapshot) { $vals['college_id'] = $snapshot['college_id']; $vals['school_year'] = $snapshot['school_year'] ?: school_year_options()[0]; }
        }
        if (!$errors && !$id && $t === 'expense') {
            $scope = $pdo->prepare('SELECT COALESCE(b.college_id,c.college_id) college_id,b.school_year FROM project p JOIN budget b ON b.budget_id=p.budget_id JOIN officer o ON o.officer_id=p.officer_id JOIN student s ON s.student_id=o.student_id LEFT JOIN college c ON c.college_name=s.college WHERE p.project_id=?');
            $scope->execute([(int)$vals['project_id']]);
            $snapshot = $scope->fetch();
            if ($snapshot) { $vals['college_id'] = $snapshot['college_id']; $vals['school_year'] = $snapshot['school_year']; }
        }
        if (!$errors && $t === 'project') {
            if (!$id && empty($vals['request_id'])) $errors[] = 'A project must be linked to an approved request.';
            if (!empty($vals['request_id'])) {
                $check = $pdo->prepare('SELECT fr.status,fr.college_id request_college,fr.school_year request_year,fr.amount_requested,b.college_id budget_college,b.school_year budget_year,oc.college_id officer_college FROM fund_request fr JOIN budget b ON b.budget_id=? JOIN officer o ON o.officer_id=? JOIN student os ON os.student_id=o.student_id JOIN college oc ON oc.college_name=os.college WHERE fr.request_id=?');
                $check->execute([(int)$vals['budget_id'], (int)$vals['officer_id'], (int)$vals['request_id']]);
                $approved = $check->fetch();
                if (!$approved || $approved['status'] !== 'Approved') $errors[] = 'Select an approved request.';
                elseif ((int)$approved['request_college'] !== (int)$approved['budget_college'] || (int)$approved['officer_college'] !== (int)$approved['budget_college'] || $approved['request_year'] !== $approved['budget_year']) $errors[] = 'The approved request, project officer, and budget must belong to the same college and school year.';
                elseif ((float)$vals['allocated_amount'] > (float)$approved['amount_requested']) $errors[] = 'Project allocation cannot exceed the amount approved in the request.';
                elseif ($user['level'] === 'mayor' && (int)$approved['request_college'] !== (int)$user['college_id']) $errors[] = 'You can create projects only for approved requests in your college.';
            }
        }
        if (!$errors && $t === 'project') {
            $budgetCheck = $pdo->prepare('SELECT b.total_amount - COALESCE(SUM(p.allocated_amount),0) remaining FROM budget b LEFT JOIN project p ON p.budget_id=b.budget_id AND (?=0 OR p.project_id<>?) WHERE b.budget_id=? GROUP BY b.budget_id');
            $budgetCheck->execute([$id, $id, (int)$vals['budget_id']]);
            $remaining = $budgetCheck->fetchColumn();
            if ($remaining === false || (float)$vals['allocated_amount'] > (float)$remaining) $errors[] = 'The project allocation exceeds the remaining budget.';
        }
        if (!$errors && $t === 'expense') {
            $officerCheck = $pdo->prepare('SELECT b.college_id budget_college,oc.college_id officer_college FROM project p JOIN budget b ON b.budget_id=p.budget_id JOIN officer o ON o.officer_id=? JOIN student os ON os.student_id=o.student_id JOIN college oc ON oc.college_name=os.college WHERE p.project_id=?');
            $officerCheck->execute([(int)$vals['officer_id'], (int)$vals['project_id']]);
            $officerRow = $officerCheck->fetch();
            if (!$officerRow || ($officerRow['budget_college'] !== null && (int)$officerRow['budget_college'] !== (int)$officerRow['officer_college'])) $errors[] = 'The responsible officer must belong to the project budget college.';
        }
        if (!$errors && $t === 'expense') {
            $expenseCheck = $pdo->prepare('SELECT p.allocated_amount - COALESCE(SUM(e.amount),0) remaining FROM project p LEFT JOIN expense e ON e.project_id=p.project_id AND (?=0 OR e.expense_id<>?) WHERE p.project_id=? GROUP BY p.project_id');
            $expenseCheck->execute([$id, $id, (int)$vals['project_id']]);
            $remaining = $expenseCheck->fetchColumn();
            if ($remaining === false || (float)$vals['amount'] > (float)$remaining) $errors[] = 'This expense exceeds the remaining project allocation.';
        }
        if ($errors) errors_out($errors);
        try {
            if ($id) {
                $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($vals)));
                $stmt = $pdo->prepare("UPDATE `$t` SET $set WHERE `$pk` = ? AND ($scopeSql)");
                $stmt->execute([...array_values($vals), $id, ...$scopeParams]);
                audit_event((int)$user['account_id'], $user['level'] === 'mayor' ? (int)$user['college_id'] : null, 'Updated', $t, $id, $orig, $vals);
                done($E['single'] . ' updated.', ['redirect' => "/crud/$t"]);
            } else {
                $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($vals)));
                $marks = implode(', ', array_fill(0, count($vals), '?'));
                $pdo->prepare("INSERT INTO `$t` ($cols) VALUES ($marks)")->execute(array_values($vals));
                audit_event((int)$user['account_id'], $user['level'] === 'mayor' ? (int)$user['college_id'] : null, 'Created', $t, (int)$pdo->lastInsertId(), [], $vals);
                done($E['single'] . ' added.', ['redirect' => "/crud/$t"]);
            }
        } catch (PDOException $ex) {
            errors_out([friendly_error($ex)]);
        }
    }
    fail('Unknown action.', 400);
}

/* ---------------------------------------------------------------- GET */

$view = (string)($_GET['view'] ?? 'list');
$id = (int)($_GET['id'] ?? 0);

$fields = [];
$fkOptions = [];
foreach ($E['fields'] as $name => $f) {
    $default = $f['default'] ?? null;
    if ($default === 'today') $default = date('Y-m-d');
    $fields[] = [
        'name' => $name, 'label' => $f['label'], 'type' => $f['type'], 'optional' => !empty($f['optional']),
        'max' => $f['max'] ?? null, 'min' => $f['min'] ?? null, 'hint' => $f['hint'] ?? null,
        'suggest' => $f['suggest'] ?? null, 'options' => $f['options'] ?? null,
        'groups' => isset($f['groups']) ? (object)$f['groups'] : null,
        'fk' => $f['fk'] ?? null, 'positive' => !empty($f['positive']), 'default' => $default,
    ];
    if (($f['type'] ?? '') === 'fk' && !isset($fkOptions[$f['fk']])) {
        $list = [];
        foreach (fk_options($f['fk']) as $oid => $olabel) $list[] = ['id' => (int)$oid, 'label' => $olabel];
        $fkOptions[$f['fk']] = $list;
    }
}
$meta = [
    'key' => $t, 'title' => $E['title'], 'single' => $E['single'], 'fields' => $fields, 'canWrite' => $canWrite,
    'fk' => (object)$fkOptions, 'myOfficerId' => (int)$myOfficerId,
    'hidden' => $user['level'] === 'mayor' ? ['college_id'] : [],
];

if ($view === 'form') {
    $record = null;
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk` = ? AND ($scopeSql)");
        $stmt->execute([$id, ...$scopeParams]);
        $row = $stmt->fetch();
        if ($row) {
            $raw = [];
            $cells = [];
            foreach ($E['fields'] as $name => $f) {
                $raw[$name] = (string)($row[$name] ?? '');
                $cells[$name] = crud_cell($f, $row[$name] ?? null);
            }
            $record = ['id' => $id, 'raw' => $raw, 'cells' => $cells];
        }
    }
    out($meta + ['record' => $record]);
}

$q = trim((string)($_GET['q'] ?? ''));
$list = $pdo->prepare("SELECT * FROM `$t` WHERE ($scopeSql) ORDER BY {$E['order']} LIMIT 2000");
$list->execute($scopeParams);
$display = [];
foreach ($list->fetchAll() as $r) {
    $cells = [];
    foreach ($E['fields'] as $name => $f) {
        $cells[$name] = crud_cell($f, $r[$name] ?? null);
    }
    if ($q !== '' && stripos(implode(' | ', $cells), $q) === false) {
        continue;
    }
    $display[] = ['id' => (int)$r[$pk], 'cells' => $cells];
}
out($meta + ['rows' => $display, 'q' => $q]);
