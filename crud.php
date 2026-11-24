<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_login();
if (!in_array($user['level'], ['admin','mayor'], true)) forbidden('Only the university administrator or an assigned College Mayor can access financial records.');

$ents = entities();
$t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
if (!isset($ents[$t])) {
    http_response_code(404);
    page_start('Not found');
    echo '<p>Unknown table. <a href="dashboard.php">Back to dashboard</a></p>';
    page_end();
    exit;
}
if ($t === 'officer') forbidden('Ordinary College Officer assignments are managed by the assigned College Mayor through Members. The university administrator manages Mayor assignments only.');
if ($user['level'] === 'mayor' && $t === 'student') { /* read-only college directory */ }
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
                $d = DateTime::createFromFormat('Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) {
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

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$form   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (!$canWrite) {
        forbidden('Your role can view these records but not change them.');
    }
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($do === 'delete') {
        try {
            $del = $pdo->prepare("DELETE FROM `$t` WHERE `$pk` = ? AND ($scopeSql)");
            $del->execute([$id, ...$scopeParams]);
            audit_event((int)$user['account_id'], $user['level']==='mayor'?(int)$user['college_id']:null, 'Deleted', $t, $id);
            flash('ok', $E['single'] . ' deleted.');
        } catch (PDOException $ex) {
            flash('err', friendly_error($ex));
        }
        redirect("crud.php?t=$t");
    }

    if ($do === 'save') {
        $orig = [];
        if ($id) {
            $o = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk` = ? AND ($scopeSql)");
            $o->execute([$id, ...$scopeParams]);
            $orig = $o->fetch() ?: [];
        }
        [$vals, $errors, $form] = collect($E, $orig);
        $action = $id ? 'edit' : 'new';
        if ($user['level'] === 'mayor') unset($vals['college_id']);
        if ($t === 'student' && !$errors) {
            $vals['college'] = college_of((string)$vals['program']) ?? ($orig['college'] ?? null);
        }
        if (!$errors && !$id && $user['level'] === 'mayor' && in_array($t, ['budget','fund_source'], true)) {
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
            $budgetCheck->execute([$id,$id,(int)$vals['budget_id']]);
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
            $expenseCheck->execute([$id,$id,(int)$vals['project_id']]);
            $remaining = $expenseCheck->fetchColumn();
            if ($remaining === false || (float)$vals['amount'] > (float)$remaining) $errors[] = 'This expense exceeds the remaining project allocation.';
        }
        if (!$errors) {
            try {
                if ($id) {
                    $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($vals)));
                    $stmt = $pdo->prepare("UPDATE `$t` SET $set WHERE `$pk` = ? AND ($scopeSql)");
                    $stmt->execute([...array_values($vals), $id, ...$scopeParams]);
                    audit_event((int)$user['account_id'], $user['level']==='mayor'?(int)$user['college_id']:null, 'Updated', $t, $id, $orig, $vals);
                    flash('ok', $E['single'] . ' updated.');
                } else {
                    $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($vals)));
                    $marks = implode(', ', array_fill(0, count($vals), '?'));
                    $pdo->prepare("INSERT INTO `$t` ($cols) VALUES ($marks)")->execute(array_values($vals));
                    audit_event((int)$user['account_id'], $user['level']==='mayor'?(int)$user['college_id']:null, 'Created', $t, (int)$pdo->lastInsertId(), [], $vals);
                    flash('ok', $E['single'] . ' added.');
                }
                redirect("crud.php?t=$t");
            } catch (PDOException $ex) {
                $errors[] = friendly_error($ex);
            }
        }
    }
}

if (in_array($action, ['new', 'edit', 'delete'], true) && !$canWrite) {
    flash('err', 'Your role can view these records but not change them.');
    redirect("crud.php?t=$t");
}

$row = null;
if (in_array($action, ['edit', 'delete'], true)) {
    $stmt = $pdo->prepare("SELECT * FROM `$t` WHERE `$pk` = ? AND ($scopeSql)");
    $stmt->execute([$id, ...$scopeParams]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('err', 'That record no longer exists.');
        redirect("crud.php?t=$t");
    }
    if (!$form) {
        foreach ($E['fields'] as $name => $f) {
            $form[$name] = (string)($row[$name] ?? '');
        }
    }
}

function cell(array $f, $v): string
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

page_start($E['title'], $t);

if ($action === 'new' || $action === 'edit') {
    $editing = $action === 'edit';
    ?>
    <div class="head"><h1><?= $editing ? 'Edit' : 'Add' ?> <?= e(strtolower($E['single'])) ?></h1>
      <a class="btn ghost" href="crud.php?t=<?= e($t) ?>">Back to <?= e(strtolower($E['title'])) ?></a></div>

    <?php if ($errors): ?>
      <div class="flash err"><strong>Please fix the following:</strong>
        <ul><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="post" class="card form" action="crud.php?t=<?= e($t) ?>&action=<?= $editing ? 'edit&id=' . $id : 'new' ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= $editing ? $id : 0 ?>">
      <?php foreach ($E['fields'] as $name => $f):
          if ($f['type'] === 'derived' || ($user['level'] === 'mayor' && $name === 'college_id')) continue;
          $val = $form[$name] ?? ($f['default'] ?? '');
          if (!$editing && !isset($form[$name])) {
              if (isset($_GET[$name])) $val = (string)$_GET[$name];
              elseif (($f['fk'] ?? '') === 'officer' && $myOfficerId) $val = (string)$myOfficerId;
          }
          if ($val === 'today') $val = date('Y-m-d');
          $req = empty($f['optional']) ? 'required' : '';
          ?>
        <label class="<?= $f['type'] === 'textarea' ? 'wide' : '' ?>">
          <span><?= e($f['label']) ?><?= $req ? ' <b>*</b>' : ' <i>(optional)</i>' ?></span>
          <?php if ($f['type'] === 'fk'):
              $opts = fk_options($f['fk']); ?>
            <select name="<?= e($name) ?>" <?= $req ?>>
              <option value="">— choose —</option>
              <?php foreach ($opts as $oid => $olabel): ?>
                <option value="<?= (int)$oid ?>" <?= (string)$val === (string)$oid ? 'selected' : '' ?>><?= e($olabel) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!$opts): ?>
              <small class="warn">Nothing to choose yet —
                <?php if ($f['fk'] === 'fund_request'): ?><a href="requests.php">review requests</a><?php else: ?><a href="crud.php?t=<?= e($f['fk']) ?>&action=new">add one first</a><?php endif; ?>.</small>
            <?php endif; ?>
          <?php elseif ($f['type'] === 'select'): ?>
            <select name="<?= e($name) ?>" <?= $req ?>>
              <?= options_html($f['options'], (string)$val, !$req, $f['groups'] ?? []) ?>
            </select>
          <?php elseif ($f['type'] === 'phone'): ?>
            <input type="tel" name="<?= e($name) ?>" maxlength="<?= (int)$f['max'] ?>" value="<?= e($val) ?>" <?= $req ?> inputmode="tel" placeholder="09XX XXX XXXX">
          <?php elseif ($f['type'] === 'textarea'): ?>
            <textarea name="<?= e($name) ?>" rows="3" maxlength="<?= (int)$f['max'] ?>" <?= $req ?>><?= e($val) ?></textarea>
          <?php elseif ($f['type'] === 'money'): ?>
            <input type="number" name="<?= e($name) ?>" step="0.01" min="<?= !empty($f['positive']) ? '0.01' : '0' ?>"
                   value="<?= e($val) ?>" <?= $req ?> inputmode="decimal">
          <?php elseif ($f['type'] === 'number'): ?>
            <input type="number" name="<?= e($name) ?>" min="<?= (int)$f['min'] ?>" max="<?= (int)$f['max'] ?>"
                   value="<?= e($val) ?>" <?= $req ?>>
          <?php elseif ($f['type'] === 'date'): ?>
            <input type="date" name="<?= e($name) ?>" value="<?= e($val) ?>" <?= $req ?>>
          <?php else: ?>
            <input type="text" name="<?= e($name) ?>" maxlength="<?= (int)$f['max'] ?>" value="<?= e($val) ?>" <?= $req ?>
                   <?= isset($f['suggest']) ? 'list="dl_' . e($name) . '"' : '' ?>
                   <?= $name === 'school_year' ? 'pattern="\d{4}-\d{4}" placeholder="2026-2027"' : '' ?>>
            <?php if (isset($f['suggest'])): ?>
              <datalist id="dl_<?= e($name) ?>"><?php foreach ($f['suggest'] as $s) echo '<option value="' . e($s) . '">'; ?></datalist>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($f['hint'])): ?><small><?= e($f['hint']) ?></small><?php endif; ?>
        </label>
      <?php endforeach; ?>
      <div class="actions wide">
        <button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Add ' . e(strtolower($E['single'])) ?></button>
        <a class="btn ghost" href="crud.php?t=<?= e($t) ?>">Cancel</a>
      </div>
    </form>
    <?php
    page_end();
    exit;
}

if ($action === 'delete') {
    ?>
    <div class="head"><h1>Delete <?= e(strtolower($E['single'])) ?>?</h1></div>
    <div class="card">
      <p>You are about to permanently delete this record:</p>
      <dl class="detail">
        <?php foreach ($E['fields'] as $name => $f): ?>
          <dt><?= e($f['label']) ?></dt><dd><?= e(cell($f, $row[$name])) ?></dd>
        <?php endforeach; ?>
      </dl>
      <form method="post" action="crud.php?t=<?= e($t) ?>" class="actions">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="do" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn danger" type="submit">Yes, delete</button>
        <a class="btn ghost" href="crud.php?t=<?= e($t) ?>">Cancel</a>
      </form>
    </div>
    <?php
    page_end();
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$list = $pdo->prepare("SELECT * FROM `$t` WHERE ($scopeSql) ORDER BY {$E['order']} LIMIT 2000");
$list->execute($scopeParams);
$rows = $list->fetchAll();
$display = [];
foreach ($rows as $r) {
    $cells = [];
    foreach ($E['fields'] as $name => $f) {
        $cells[$name] = cell($f, $r[$name]);
    }
    if ($q !== '' && stripos(implode(' | ', $cells), $q) === false) {
        continue;
    }
    $display[] = [$r[$pk], $cells];
}
?>
<div class="head">
  <h1><?= e($E['title']) ?> <span class="count"><?= count($display) ?></span></h1>
  <?php if ($canWrite): ?><a class="btn" href="crud.php?t=<?= e($t) ?>&action=new">+ Add <?= e(strtolower($E['single'])) ?></a>
  <?php else: ?><span class="pill closed">View only</span><?php endif; ?>
</div>

<form method="get" class="search">
  <input type="hidden" name="t" value="<?= e($t) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search <?= e(strtolower($E['title'])) ?>…">
  <button class="btn ghost" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="btn ghost" href="crud.php?t=<?= e($t) ?>">Clear</a><?php endif; ?>
</form>

<div class="card tablewrap">
<table>
  <thead><tr>
    <th>#</th>
    <?php foreach ($E['fields'] as $f): ?>
      <th class="<?= $f['type'] === 'money' ? 'num' : '' ?>"><?= e($f['label']) ?></th>
    <?php endforeach; ?>
    <th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($display as [$rid, $cells]): ?>
    <tr>
      <td class="muted"><?= (int)$rid ?></td>
      <?php foreach ($E['fields'] as $name => $f): ?>
        <td class="<?= $f['type'] === 'money' ? 'num' : '' ?>"><?= e($cells[$name]) ?></td>
      <?php endforeach; ?>
      <td class="rowact">
        <?php if ($canWrite): ?>
        <a href="crud.php?t=<?= e($t) ?>&action=edit&id=<?= (int)$rid ?>">Edit</a>
        <a class="del" href="crud.php?t=<?= e($t) ?>&action=delete&id=<?= (int)$rid ?>">Delete</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$display): ?>
    <tr><td colspan="<?= count($E['fields']) + 2 ?>" class="empty">
      <?= $q !== '' ? 'No matches for “' . e($q) . '”.' : ($canWrite ? 'No records yet. Click “Add” to create the first one.' : 'No records yet.') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php page_end();
