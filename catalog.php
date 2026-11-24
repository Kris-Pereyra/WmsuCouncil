<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_admin();
$pdo = db();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'add_college') {
        $name = trim((string)($_POST['college_name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['college_code'] ?? '')));
        if ($name === '' || mb_strlen($name) > 150) $errors[] = 'College name is required (maximum 150 characters).';
        if (mb_strlen($code) > 30 || ($code !== '' && !preg_match('/^[A-Z0-9-]+$/', $code))) $errors[] = 'College code may contain only letters, numbers, and dashes.';
        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO college (college_name,college_code,is_active) VALUES (?,?,1)')->execute([$name,$code ?: null]);
                audit_event((int)$user['account_id'],null,'Added college','college',(int)$pdo->lastInsertId(),[],['college_name'=>$name,'college_code'=>$code]);
                flash('ok','College added to the active catalog.'); redirect('catalog.php');
            } catch (PDOException $e) { $errors[] = friendly_error($e); }
        }
    } elseif ($do === 'add_program') {
        $collegeId = (int)($_POST['college_id'] ?? 0);
        $program = trim((string)($_POST['program_name'] ?? ''));
        if ($collegeId < 1) $errors[] = 'Choose a college.';
        if ($program === '' || mb_strlen($program) > 80) $errors[] = 'Program name is required (maximum 80 characters).';
        $check = $pdo->prepare('SELECT college_name FROM college WHERE college_id=? AND is_active=1');
        $check->execute([$collegeId]); $collegeName = $check->fetchColumn();
        if (!$collegeName) $errors[] = 'The selected college is not active.';
        if (!$errors) {
            try {
                $pdo->prepare('INSERT INTO program_catalog (college_id,program_name,is_active) VALUES (?,?,1)')->execute([$collegeId,$program]);
                audit_event((int)$user['account_id'],null,'Added program to catalog','program_catalog',(int)$pdo->lastInsertId(),[],['college'=>$collegeName,'program'=>$program]);
                flash('ok','Program added. It will now appear in the college-first registration dropdown.'); redirect('catalog.php');
            } catch (PDOException $e) { $errors[] = friendly_error($e); }
        }
    } elseif ($do === 'toggle_college') {
        $id = (int)($_POST['college_id'] ?? 0);
        $pdo->prepare('UPDATE college SET is_active=1-is_active WHERE college_id=?')->execute([$id]);
        audit_event((int)$user['account_id'],null,'Changed college catalog status','college',$id);
        flash('ok','College catalog status updated.'); redirect('catalog.php');
    } elseif ($do === 'toggle_program') {
        $id = (int)($_POST['program_id'] ?? 0);
        $pdo->prepare('UPDATE program_catalog SET is_active=1-is_active WHERE program_id=?')->execute([$id]);
        audit_event((int)$user['account_id'],null,'Changed program catalog status','program_catalog',$id);
        flash('ok','Program catalog status updated.'); redirect('catalog.php');
    }
}
$colleges = $pdo->query('SELECT c.*,COUNT(p.program_id) program_count FROM college c LEFT JOIN program_catalog p ON p.college_id=c.college_id GROUP BY c.college_id ORDER BY c.college_name')->fetchAll();
$programs = $pdo->query('SELECT p.*,c.college_name FROM program_catalog p JOIN college c ON c.college_id=p.college_id ORDER BY c.college_name,p.program_name')->fetchAll();
page_start('College and program catalog','catalog'); ?>
<div class="head"><div><h1>College and program catalog</h1><p class="muted">Admin maintains the active college and program options used by student registration and enrollment records. Confirm names against the latest WMSU Registrar prospectus.</p></div></div>
<?php if ($errors): ?><div class="flash err"><ul><?php foreach ($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div><?php endif; ?>
<div class="two"><section class="card"><h2>Add a college</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="add_college"><label>Official college name<input name="college_name" maxlength="150" required></label><label>College code (optional)<input name="college_code" maxlength="30" placeholder="e.g. CCS"></label><button class="btn">Add college</button></form></section><section class="card"><h2>Add a program</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="add_program"><label>College<select name="college_id" required><option value="">Choose college</option><?php foreach($colleges as $c): if($c['is_active']): ?><option value="<?=(int)$c['college_id']?>"><?=e($c['college_name'])?></option><?php endif; endforeach;?></select></label><label>Program name<input name="program_name" maxlength="80" required></label><button class="btn">Add program</button></form></section></div>
<section class="card"><h2>Colleges</h2><div class="tablewrap"><table><thead><tr><th>College</th><th>Code</th><th>Programs</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($colleges as $c): ?><tr><td><?=e($c['college_name'])?></td><td><?=e($c['college_code']??'—')?></td><td><?=(int)$c['program_count']?></td><td><?= $c['is_active']?'<span class="pill approved">Active</span>':'<span class="pill closed">Inactive</span>' ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="toggle_college"><input type="hidden" name="college_id" value="<?=(int)$c['college_id']?>"><button class="btn ghost small"><?=$c['is_active']?'Deactivate':'Reactivate'?></button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<section class="card"><h2>Programs</h2><div class="tablewrap"><table><thead><tr><th>College</th><th>Program</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($programs as $pr): ?><tr><td><?=e($pr['college_name'])?></td><td><?=e($pr['program_name'])?></td><td><?= $pr['is_active']?'<span class="pill approved">Active</span>':'<span class="pill closed">Inactive</span>' ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="do" value="toggle_program"><input type="hidden" name="program_id" value="<?=(int)$pr['program_id']?>"><button class="btn ghost small"><?=$pr['is_active']?'Deactivate':'Reactivate'?></button></form></td></tr><?php endforeach;?></tbody></table></div></section>
<?php page_end(); ?>
