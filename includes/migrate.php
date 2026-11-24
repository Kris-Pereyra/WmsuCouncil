<?php
declare(strict_types=1);

function schema_has_table(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->execute([DB_NAME, $table]);
    return (bool)$st->fetchColumn();
}

function schema_has_index(PDO $pdo, string $table, string $index): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([DB_NAME, $table, $index]);
    return (bool)$st->fetchColumn();
}

function schema_has_constraint(PDO $pdo, string $table, string $constraint): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
    $st->execute([DB_NAME, $table, $constraint]);
    return (bool)$st->fetchColumn();
}

function schema_has_column(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([DB_NAME, $table, $column]);
    return (bool)$st->fetchColumn();
}

function migrate_schema(PDO $pdo): void
{
    $person = [
        'middle_name' => 'VARCHAR(50) NULL AFTER first_name',
        'college'     => 'VARCHAR(100) NULL',
        'school_year' => 'VARCHAR(9) NULL',
        'contact_no'  => 'VARCHAR(20) NULL',
    ];
    $adds = [
        'student'        => $person,
        'pending_signup' => $person,
        'user_account'   => ['profile_photo' => 'VARCHAR(80) NULL'],
    ];
    foreach ($adds as $table => $cols) {
        if (!schema_has_table($pdo, $table)) {
            continue;
        }
        foreach ($cols as $col => $def) {
            if (!schema_has_column($pdo, $table, $col)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        }
    }

    // University-wide schema extension. Existing core tables and the database name remain unchanged.
    if (schema_has_table($pdo, 'student') && !schema_has_column($pdo, 'student', 'suffix')) {
        $pdo->exec("ALTER TABLE student ADD COLUMN suffix VARCHAR(20) NULL AFTER last_name");
    }
    if (schema_has_table($pdo, 'pending_signup') && !schema_has_column($pdo, 'pending_signup', 'suffix')) {
        $pdo->exec("ALTER TABLE pending_signup ADD COLUMN suffix VARCHAR(20) NULL AFTER last_name");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS college (
      college_id INT UNSIGNED NOT NULL AUTO_INCREMENT, college_name VARCHAR(150) NOT NULL, college_code VARCHAR(30) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (college_id), UNIQUE KEY uq_college_name (college_name), UNIQUE KEY uq_college_code (college_code)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS program_catalog (
      program_id INT UNSIGNED NOT NULL AUTO_INCREMENT, college_id INT UNSIGNED NOT NULL, program_name VARCHAR(160) NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (program_id), UNIQUE KEY uq_college_program (college_id, program_name),
      CONSTRAINT fk_program_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");
    $collegeNames = [
      'College of Agriculture','College of Architecture','College of Asian and Islamic Studies','College of Computing Studies',
      'College of Criminal Justice Education','College of Engineering','College of Forestry and Environmental Studies',
      'College of Home Economics','College of Law','College of Liberal Arts','College of Medicine','College of Nursing',
      'College of Sports Science and Physical Education','College of Public Administration and Development Studies',
      'College of Science and Mathematics','College of Social Work and Community Development','College of Teacher Education'
    ];
    $insCollege = $pdo->prepare('INSERT IGNORE INTO college (college_name) VALUES (?)');
    foreach ($collegeNames as $collegeName) $insCollege->execute([$collegeName]);
    $programSeed = [
      'College of Agriculture' => ['BS Agribusiness','BS Agriculture major in Animal Science','BS Agriculture major in Crop Science','BS Food Technology'],
      'College of Architecture' => ['BS Architecture'],
      'College of Asian and Islamic Studies' => ['BA Asian Studies','BA Islamic Studies','Diploma in Arabic Language'],
      'College of Computing Studies' => ['BS Computer Science','BS Information Technology','Associate in Computer Technology'],
      'College of Criminal Justice Education' => ['BS Criminology'],
      'College of Engineering' => ['BS Civil Engineering','BS Computer Engineering','BS Electrical Engineering','BS Mechanical Engineering'],
      'College of Forestry and Environmental Studies' => ['BS Forestry'],
      'College of Home Economics' => ['BS Home Economics'],
      'College of Law' => ['Juris Doctor'],
      'College of Liberal Arts' => ['AB English','AB History','AB Political Science','BS Economics','BS Psychology'],
      'College of Medicine' => ['Doctor of Medicine'],
      'College of Nursing' => ['BS Nursing'],
      'College of Sports Science and Physical Education' => ['Bachelor of Physical Education','BS Exercise and Sports Sciences'],
      'College of Public Administration and Development Studies' => ['BS Public Administration'],
      'College of Science and Mathematics' => ['BS Biology','BS Chemistry','BS Mathematics'],
      'College of Social Work and Community Development' => ['BS Social Work'],
      'College of Teacher Education' => ['Bachelor of Elementary Education','Bachelor of Secondary Education','Bachelor of Early Childhood Education'],
    ];
    $getCollege = $pdo->prepare('SELECT college_id FROM college WHERE college_name = ?');
    $putProgram = $pdo->prepare('INSERT IGNORE INTO program_catalog (college_id, program_name) VALUES (?, ?)');
    foreach ($programSeed as $collegeName => $programs) {
      $getCollege->execute([$collegeName]); $cid = (int)$getCollege->fetchColumn();
      foreach ($programs as $programName) $putProgram->execute([$cid, $programName]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_enrollment (
      enrollment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL, college_id INT UNSIGNED NOT NULL,
      program_id INT UNSIGNED NULL, program_name_snapshot VARCHAR(160) NOT NULL, year_level TINYINT UNSIGNED NOT NULL,
      school_year VARCHAR(9) NOT NULL, enrollment_status ENUM('Pending','Verified','Not Enrolled','Archived') NOT NULL DEFAULT 'Verified',
      verified_by INT UNSIGNED NULL, verified_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (enrollment_id), UNIQUE KEY uq_student_school_year (student_id, school_year), KEY idx_enrollment_college_year (college_id, school_year),
      CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_enrollment_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_enrollment_program FOREIGN KEY (program_id) REFERENCES program_catalog(program_id) ON UPDATE CASCADE ON DELETE SET NULL,
      CONSTRAINT fk_enrollment_verifier FOREIGN KEY (verified_by) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement (
      announcement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, college_id INT UNSIGNED NULL, created_by INT UNSIGNED NOT NULL,
      title VARCHAR(180) NOT NULL, body TEXT NOT NULL, announcement_type ENUM('General','Event','Payment','Meeting','Project','Donation') NOT NULL DEFAULT 'General',
      publish_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, event_date DATE NULL, deadline DATE NULL, school_year VARCHAR(9) NULL,
      status ENUM('Draft','Published','Archived') NOT NULL DEFAULT 'Draft', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NULL, PRIMARY KEY (announcement_id), KEY idx_announcement_scope (college_id,status,publish_at),
      CONSTRAINT fk_announcement_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_announcement_author FOREIGN KEY (created_by) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_requirement (
      requirement_id INT UNSIGNED NOT NULL AUTO_INCREMENT, fee_code VARCHAR(30) NOT NULL, fee_name VARCHAR(100) NOT NULL,
      amount DECIMAL(10,2) NOT NULL, school_year VARCHAR(9) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (requirement_id), UNIQUE KEY uq_fee_year (fee_code,school_year), CHECK (amount > 0)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_payment (
      payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL, college_id INT UNSIGNED NOT NULL,
      requirement_id INT UNSIGNED NOT NULL, school_year VARCHAR(9) NOT NULL, amount_due DECIMAL(10,2) NOT NULL,
      status ENUM('Unpaid','Proof Submitted','Under Verification','Verified','Rejected') NOT NULL DEFAULT 'Unpaid',
      paid_at DATETIME NULL, verified_by INT UNSIGNED NULL, verified_at DATETIME NULL, receipt_no VARCHAR(60) NULL,
      college_share DECIMAL(10,2) NOT NULL DEFAULT 0, university_share DECIMAL(10,2) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (payment_id),
      UNIQUE KEY uq_student_fee_year (student_id,requirement_id,school_year), KEY idx_payment_college_year (college_id,school_year,status),
      CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_payment_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_payment_requirement FOREIGN KEY (requirement_id) REFERENCES payment_requirement(requirement_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_payment_verifier FOREIGN KEY (verified_by) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_proof (
      proof_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payment_id BIGINT UNSIGNED NOT NULL, file_name VARCHAR(180) NOT NULL,
      original_name VARCHAR(180) NOT NULL, mime_type VARCHAR(80) NOT NULL, uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (proof_id), KEY idx_proof_payment (payment_id),
      CONSTRAINT fk_proof_payment FOREIGN KEY (payment_id) REFERENCES student_payment(payment_id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS fund_distribution (
      distribution_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payment_id BIGINT UNSIGNED NOT NULL, college_id INT UNSIGNED NOT NULL,
      school_year VARCHAR(9) NOT NULL, gross_amount DECIMAL(10,2) NOT NULL, college_amount DECIMAL(10,2) NOT NULL,
      university_amount DECIMAL(10,2) NOT NULL, distributed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (distribution_id), UNIQUE KEY uq_distribution_payment (payment_id),
      CONSTRAINT fk_distribution_payment FOREIGN KEY (payment_id) REFERENCES student_payment(payment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_distribution_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
      audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, account_id INT UNSIGNED NULL, college_id INT UNSIGNED NULL,
      action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id BIGINT UNSIGNED NULL,
      old_values JSON NULL, new_values JSON NULL, ip_address VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (audit_id), KEY idx_audit_college_time (college_id,created_at),
      CONSTRAINT fk_audit_account FOREIGN KEY (account_id) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE SET NULL,
      CONSTRAINT fk_audit_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB");

    if (schema_has_table($pdo, 'project') && !schema_has_column($pdo, 'project', 'request_id')) {
      $pdo->exec('ALTER TABLE project ADD COLUMN request_id INT UNSIGNED NULL AFTER budget_id');
    }
    if (schema_has_table($pdo, 'project') && !schema_has_index($pdo, 'project', 'uq_project_request')) {
      $pdo->exec('CREATE UNIQUE INDEX uq_project_request ON project (request_id)');
    }

    // College-specific budgets and sources allow each Mayor to work only with their own council records.
    if (schema_has_table($pdo, 'budget')) {
      if (!schema_has_column($pdo, 'budget', 'college_id')) $pdo->exec('ALTER TABLE budget ADD COLUMN college_id INT UNSIGNED NULL AFTER budget_id');
      if (schema_has_index($pdo, 'budget', 'uq_budget_year')) $pdo->exec('ALTER TABLE budget DROP INDEX uq_budget_year');
      if (!schema_has_index($pdo, 'budget', 'uq_budget_college_year')) $pdo->exec('CREATE UNIQUE INDEX uq_budget_college_year ON budget (college_id,school_year)');
      $pdo->exec("UPDATE budget b JOIN (SELECT p.budget_id, MIN(cl.college_id) college_id, COUNT(DISTINCT cl.college_id) college_count FROM project p JOIN officer o ON o.officer_id=p.officer_id JOIN student s ON s.student_id=o.student_id JOIN college cl ON cl.college_name=s.college GROUP BY p.budget_id HAVING COUNT(DISTINCT cl.college_id)=1) x ON x.budget_id=b.budget_id SET b.college_id=x.college_id WHERE b.college_id IS NULL");
    }
    if (schema_has_table($pdo, 'fund_source')) {
      if (!schema_has_column($pdo, 'fund_source', 'college_id')) $pdo->exec('ALTER TABLE fund_source ADD COLUMN college_id INT UNSIGNED NULL AFTER source_id');
      if (!schema_has_index($pdo, 'fund_source', 'idx_fund_source_college')) $pdo->exec('CREATE INDEX idx_fund_source_college ON fund_source (college_id)');
    }

    // Snapshot the college and school year on legacy transaction rows so later profile changes do not rewrite history.
    foreach (['collection' => ['college_id' => 'INT UNSIGNED NULL', 'school_year' => 'VARCHAR(9) NULL'], 'expense' => ['college_id' => 'INT UNSIGNED NULL', 'school_year' => 'VARCHAR(9) NULL']] as $table => $cols) {
      if (!schema_has_table($pdo, $table)) continue;
      foreach ($cols as $col => $def) if (!schema_has_column($pdo, $table, $col)) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    }
    if (schema_has_table($pdo, 'collection')) {
      $pdo->exec("UPDATE collection c JOIN student s ON s.student_id=c.student_id JOIN college cl ON cl.college_name=s.college SET c.college_id=cl.college_id,c.school_year=s.school_year WHERE c.college_id IS NULL");
      if (!schema_has_index($pdo, 'collection', 'idx_collection_college_year')) $pdo->exec('CREATE INDEX idx_collection_college_year ON collection (college_id,school_year)');
    }
    if (schema_has_table($pdo, 'expense')) {
      $pdo->exec("UPDATE expense e JOIN project p ON p.project_id=e.project_id JOIN budget b ON b.budget_id=p.budget_id JOIN officer o ON o.officer_id=p.officer_id JOIN student s ON s.student_id=o.student_id JOIN college cl ON cl.college_name=s.college SET e.college_id=cl.college_id,e.school_year=b.school_year WHERE e.college_id IS NULL");
      if (!schema_has_index($pdo, 'expense', 'idx_expense_college_year')) $pdo->exec('CREATE INDEX idx_expense_college_year ON expense (college_id,school_year)');
    }

    // Role assignment and college-scoped workflow additions.
    $pdo->exec("CREATE TABLE IF NOT EXISTS college_mayor (
      assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, college_id INT UNSIGNED NOT NULL,
      account_id INT UNSIGNED NOT NULL, assigned_by INT UNSIGNED NOT NULL,
      start_date DATE NOT NULL, end_date DATE NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (assignment_id),
      KEY idx_mayor_college_active (college_id,is_active), KEY idx_mayor_account (account_id,is_active),
      CONSTRAINT fk_mayor_college FOREIGN KEY (college_id) REFERENCES college(college_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_mayor_account FOREIGN KEY (account_id) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_mayor_assigner FOREIGN KEY (assigned_by) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");

    if (schema_has_table($pdo, 'fund_request')) {
      $requestAdds = [
        'college_id' => 'INT UNSIGNED NULL',
        'school_year' => 'VARCHAR(9) NULL',
        'request_type' => "VARCHAR(30) NOT NULL DEFAULT 'Event'",
        'updated_at' => 'DATETIME NULL',
      ];
      foreach ($requestAdds as $col => $def) {
        if (!schema_has_column($pdo, 'fund_request', $col)) $pdo->exec("ALTER TABLE fund_request ADD COLUMN `$col` $def");
      }
      if (!schema_has_column($pdo, 'fund_request', 'college_id')) { /* kept for older MariaDB compatibility */ }
      $pdo->exec("UPDATE fund_request fr JOIN user_account ua ON ua.account_id=fr.requested_by JOIN student s ON s.student_id=ua.student_id JOIN college c ON c.college_name=s.college SET fr.college_id=c.college_id WHERE fr.college_id IS NULL");
      if (!schema_has_index($pdo, 'fund_request', 'idx_request_college_status')) $pdo->exec("CREATE INDEX idx_request_college_status ON fund_request (college_id,status)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_verification_log (
      log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, payment_id BIGINT UNSIGNED NOT NULL,
      account_id INT UNSIGNED NOT NULL, action VARCHAR(30) NOT NULL, remarks VARCHAR(255) NULL,
      receipt_no VARCHAR(60) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (log_id), KEY idx_payment_log (payment_id,created_at),
      CONSTRAINT fk_payment_log_payment FOREIGN KEY (payment_id) REFERENCES student_payment(payment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_payment_log_account FOREIGN KEY (account_id) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE RESTRICT
    ) ENGINE=InnoDB");

    // Central university fund ledger: the 30% share is posted once when a student payment is verified.
    $pdo->exec("CREATE TABLE IF NOT EXISTS university_fund_ledger (
      entry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, entry_date DATE NOT NULL, school_year VARCHAR(9) NOT NULL,
      direction ENUM('In','Out') NOT NULL, category VARCHAR(80) NOT NULL, reference_no VARCHAR(80) NULL,
      description VARCHAR(255) NOT NULL, amount DECIMAL(12,2) NOT NULL, source_payment_id BIGINT UNSIGNED NULL,
      created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (entry_id), UNIQUE KEY uq_university_payment (source_payment_id), KEY idx_university_ledger_date (entry_date,school_year), CHECK (amount > 0),
      CONSTRAINT fk_university_payment FOREIGN KEY (source_payment_id) REFERENCES student_payment(payment_id) ON UPDATE CASCADE ON DELETE RESTRICT,
      CONSTRAINT fk_university_creator FOREIGN KEY (created_by) REFERENCES user_account(account_id) ON UPDATE CASCADE ON DELETE SET NULL
    ) ENGINE=InnoDB");
    // Backfill existing verified student fee payments without duplicating a central-fund credit.
    $pdo->exec("INSERT IGNORE INTO university_fund_ledger (entry_date,school_year,direction,category,reference_no,description,amount,source_payment_id,created_by)
      SELECT DATE(COALESCE(sp.verified_at,sp.paid_at,sp.created_at)),sp.school_year,'In','Student fee 30% share',
             COALESCE(sp.receipt_no,CONCAT('PAY-',sp.payment_id)),'30% university share from verified student fee',sp.university_share,sp.payment_id,sp.verified_by
      FROM student_payment sp WHERE sp.status='Verified' AND sp.university_share>0");

    // Default annual fee requirements are created per school year when the admin opens that year.
    $currentStart = (int)date('n') >= 6 ? (int)date('Y') : (int)date('Y') - 1;
    $currentSY = $currentStart . '-' . ($currentStart + 1);
    $feeInsert = $pdo->prepare('INSERT IGNORE INTO payment_requirement (fee_code, fee_name, amount, school_year) VALUES (?, ?, ?, ?)');
    $feeInsert->execute(['INSURANCE', 'Student Insurance', 70.00, $currentSY]);
    $feeInsert->execute(['CSC', 'College Student Council Payment', 200.00, $currentSY]);

    // Funding requests (officers ask, only the administrator decides).
    if (schema_has_table($pdo, 'user_account') && !schema_has_table($pdo, 'fund_request')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS fund_request (
               request_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
               requested_by     INT UNSIGNED  NOT NULL,
               college_id       INT UNSIGNED  NULL,
               school_year      VARCHAR(9)    NULL,
               request_type     VARCHAR(30)   NOT NULL DEFAULT 'Event',
               updated_at       DATETIME      NULL,
               event_title      VARCHAR(120)  NOT NULL,
               event_date       DATE          NOT NULL,
               venue            VARCHAR(120)  NULL,
               purpose          VARCHAR(500)  NOT NULL,
               amount_requested DECIMAL(12,2) NOT NULL,
               status           VARCHAR(10)   NOT NULL DEFAULT 'Pending',
               created_at       DATETIME      NOT NULL,
               decided_by       INT UNSIGNED  NULL,
               decided_at       DATETIME      NULL,
               admin_remarks    VARCHAR(255)  NULL,
               PRIMARY KEY (request_id),
               KEY idx_request_status (status),
               KEY idx_request_by (requested_by),
               CONSTRAINT fk_request_by FOREIGN KEY (requested_by)
                 REFERENCES user_account (account_id) ON UPDATE CASCADE ON DELETE CASCADE,
               CONSTRAINT fk_request_decider FOREIGN KEY (decided_by)
                 REFERENCES user_account (account_id) ON UPDATE CASCADE ON DELETE SET NULL,
               CONSTRAINT ck_request_amount CHECK (amount_requested > 0),
               CONSTRAINT ck_request_status CHECK (status IN ('Pending','Approved','Declined'))
             ) ENGINE=InnoDB"
        );
    }

    // Re-check after the legacy table creation block so fresh installations also receive the extensions.
    if (schema_has_table($pdo, 'fund_request')) {
      $requestAdds = ['college_id' => 'INT UNSIGNED NULL', 'school_year' => 'VARCHAR(9) NULL', 'request_type' => "VARCHAR(30) NOT NULL DEFAULT 'Event'", 'updated_at' => 'DATETIME NULL'];
      foreach ($requestAdds as $col => $def) if (!schema_has_column($pdo, 'fund_request', $col)) $pdo->exec("ALTER TABLE fund_request ADD COLUMN `$col` $def");
      $pdo->exec("UPDATE fund_request fr JOIN user_account ua ON ua.account_id=fr.requested_by JOIN student s ON s.student_id=ua.student_id JOIN college c ON c.college_name=s.college SET fr.college_id=c.college_id WHERE fr.college_id IS NULL");
      if (!schema_has_index($pdo, 'fund_request', 'idx_request_college_status')) $pdo->exec("CREATE INDEX idx_request_college_status ON fund_request (college_id,status)");
      if (schema_has_table($pdo, 'project') && !schema_has_constraint($pdo, 'project', 'fk_project_request')) {
        $pdo->exec('ALTER TABLE project ADD CONSTRAINT fk_project_request FOREIGN KEY (request_id) REFERENCES fund_request(request_id) ON UPDATE CASCADE ON DELETE RESTRICT');
      }
    }
}
