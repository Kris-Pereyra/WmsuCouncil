<?php
declare(strict_types=1);
require __DIR__ . '/includes/lib.php';
$user = require_admin();
$pdo = db();
$errors = [];
$catalog = programs_by_college();
$colleges = $pdo->query('SELECT college_id,college_name FROM college WHERE is_active=1 ORDER BY college_name')->fetchAll();
$students = $pdo->query('SELECT student_id,student_no,first_name,last_name FROM student ORDER BY last_name,first_name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $studentId = (int)($_POST['student_id'] ?? 0);
    $collegeName = trim((string)($_POST['college'] ?? ''));
    $program = trim((string)($_POST['program'] ?? ''));
    $yearLevel = (int)($_POST['year_level'] ?? 0);
    $schoolYear = trim((string)($_POST['school_year'] ?? ''));
    $status = (string)($_POST['enrollment_status'] ?? 'Verified');
    if ($studentId < 1) $errors[] = 'Choose a student.';
    if (!isset($catalog[$collegeName]) || !in_array($program, $catalog[$collegeName] ?? [], true)) $errors[] = 'Choose a valid college and a program belonging to it.';
    if ($yearLevel < 1 || $yearLevel > 6) $errors[] = 'Year level must be from 1 to 6.';
    if (!preg_match('/^(\d{4})-(\d{4})$/', $schoolYear, $m) || (int)$m[2] !== (int)$m[1] + 1) $errors[] = 'School year must look like 2026-2027.';
    if (!in_array($status, ['Pending','Verified','Not Enrolled','Archived'], true)) $errors[] = 'Choose a valid enrollment status.';
    $studentCheck = $pdo->prepare('SELECT student_id FROM student WHERE student_id=?');
    $studentCheck->execute([$studentId]);
    if (!$studentCheck->fetchColumn()) $errors[] = 'The selected student does not exist.';
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $collegeIdStmt = $pdo->prepare('SELECT college_id FROM college WHERE college_name=?');
            $collegeIdStmt->execute([$collegeName]);
            $collegeId = (int)$collegeIdStmt->fetchColumn();
            $programIdStmt = $pdo->prepare('SELECT program_id FROM program_catalog WHERE college_id=? AND program_name=?');
            $programIdStmt->execute([$collegeId,$program]);
            $programId = $programIdStmt->fetchColumn();
            $pdo->prepare('UPDATE student SET college=?,program=?,year_level=?,school_year=? WHERE student_id=?')->execute([$collegeName,$program,$yearLevel,$schoolYear,$studentId]);
            $pdo->prepare("INSERT INTO student_enrollment (student_id,college_id,program_id,program_name_snapshot,year_level,school_year,enrollment_status,verified_by,verified_at) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE college_id=VALUES(college_id),program_id=VALUES(program_id),program_name_snapshot=VALUES(program_name_snapshot),year_level=VALUES(year_level),enrollment_status=VALUES(enrollment_status),verified_by=VALUES(verified_by),verified_at=VALUES(verified_at)")
                ->execute([$studentId,$collegeId,$programId?:null,$program,$yearLevel,$schoolYear,$status,$user['account_id'],$status==='Verified'?now_str():null]);
            audit_event((int)$user['account_id'],null,'Recorded student enrollment','student_enrollment',$studentId,[],['college'=>$collegeName,'program'=>$program,'year_level'=>$yearLevel,'school_year'=>$schoolYear,'status'=>$status]);
            $pdo->commit();
            flash('ok','Enrollment record saved. Previous school-year records remain available.');
            redirect('enrollments.php?school_year='.urlencode($schoolYear));
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
$filterYear = trim((string)($_GET['school_year'] ?? ''));
$filterCollege = (int)($_GET['college_id'] ?? 0);
$sql = 'SELECT e.*,s.student_no,s.first_name,s.last_name,c.college_name FROM student_enrollment e JOIN student s ON s.student_id=e.student_id JOIN college c ON c.college_id=e.college_id WHERE (?=\'\' OR e.school_year=?) AND (?=0 OR e.college_id=?) ORDER BY e.school_year DESC,c.college_name,s.last_name,s.first_name';
$st=$pdo->prepare($sql);$st->execute([$filterYear,$filterYear,$filterCollege,$filterCollege]);$rows=$st->fetchAll();
$years=$pdo->query('SELECT DISTINCT school_year FROM student_enrollment ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN);
page_start('Enrollment records','enrollments'); ?>
<div class="head"><div><h1>School-year enrollment records</h1><p class="muted">Record each student's enrollment for a new school year. Each year is stored separately so earlier records are preserved.</p></div></div>
<?php if($errors): ?><div class="flash err"><ul><?php foreach($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div><?php endif; ?>
<section class="card"><h2>Record or update an enrollment</h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Student<select name="student_id" required><option value="">Choose student</option><?php foreach($students as $s): ?><option value="<?=(int)$s['student_id']?>"><?=e($s['last_name'].', '.$s['first_name'].' · '.$s['student_no'])?></option><?php endforeach;?></select></label><div class="two"><label>College<select name="college" id="enrollCollege" required><option value="">Choose college</option><?php foreach(array_keys($catalog) as $c): ?><option value="<?=e($c)?>"><?=e($c)?></option><?php endforeach;?></select></label><label>Program<select name="program" id="enrollProgram" required><option value="">Choose program</option></select></label></div><div class="two"><label>Year level<select name="year_level" required><?php for($i=1;$i<=6;$i++): ?><option value="<?=$i?>">Year <?=$i?></option><?php endfor;?></select></label><label>School year<input name="school_year" maxlength="9" placeholder="2026-2027" value="<?=e(school_year_options()[0])?>" required></label></div><label>Enrollment status<select name="enrollment_status"><option>Verified</option><option>Pending</option><option>Not Enrolled</option><option>Archived</option></select></label><button class="btn">Save enrollment record</button></form></section>
<form method="get" class="search"><label class="inline">School year<select name="school_year"><option value="">All years</option><?php foreach($years as $y): ?><option value="<?=e($y)?>" <?=$filterYear===$y?'selected':''?>><?=e($y)?></option><?php endforeach;?></select></label><label class="inline">College<select name="college_id"><option value="0">All colleges</option><?php foreach($colleges as $c): ?><option value="<?=(int)$c['college_id']?>" <?=$filterCollege===(int)$c['college_id']?'selected':''?>><?=e($c['college_name'])?></option><?php endforeach;?></select></label><button class="btn ghost">Filter</button></form>
<div class="card tablewrap"><table><thead><tr><th>Student</th><th>College / program</th><th>Year</th><th>School year</th><th>Status</th><th>Recorded</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['last_name'].', '.$r['first_name'])?><br><small class="muted"><?=e($r['student_no'])?></small></td><td><?=e($r['college_name'])?><br><small class="muted"><?=e($r['program_name_snapshot'])?></small></td><td><?= (int)$r['year_level']?></td><td><?=e($r['school_year'])?></td><td><?=e($r['enrollment_status'])?></td><td><?=e($r['created_at'])?></td></tr><?php endforeach;?><?php if(!$rows): ?><tr><td colspan="6" class="empty">No enrollment records match these filters.</td></tr><?php endif;?></tbody></table></div>
<script>
const enrollmentPrograms = <?=json_encode($catalog, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const collegeSelect = document.getElementById('enrollCollege');
const programSelect = document.getElementById('enrollProgram');
function updatePrograms(){const list=enrollmentPrograms[collegeSelect.value]||[];programSelect.innerHTML='<option value="">Choose program</option>';for(const name of list){const option=document.createElement('option');option.value=name;option.textContent=name;programSelect.appendChild(option);}programSelect.disabled=list.length===0;}
collegeSelect.addEventListener('change',updatePrograms);updatePrograms();
</script>
<?php page_end(); ?>
