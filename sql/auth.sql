CREATE TABLE IF NOT EXISTS user_account (
  account_id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED  NULL,            -- NULL only for the admin
  email           VARCHAR(120)  NOT NULL,
  display_name    VARCHAR(80)   NULL,            -- used by the admin account
  password_hash   VARCHAR(255)  NOT NULL,
  role            VARCHAR(10)   NOT NULL DEFAULT 'student',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME      NULL,
  verified_at     DATETIME      NOT NULL,
  last_login      DATETIME      NULL,
  profile_photo   VARCHAR(80)   NULL,            -- file name inside uploads/avatars
  PRIMARY KEY (account_id),
  UNIQUE KEY uq_account_email (email),
  UNIQUE KEY uq_account_student (student_id),
  CONSTRAINT fk_account_student FOREIGN KEY (student_id)
    REFERENCES student (student_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT ck_account_role CHECK (role IN ('admin','student'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pending_signup (
  pending_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  email         VARCHAR(120)  NOT NULL,
  student_no    VARCHAR(20)   NOT NULL,
  first_name    VARCHAR(50)   NOT NULL,
  middle_name   VARCHAR(50)   NULL,
  last_name     VARCHAR(50)   NOT NULL,
  program       VARCHAR(80)   NOT NULL,
  college       VARCHAR(100)  NULL,
  year_level    TINYINT UNSIGNED NOT NULL,
  school_year   VARCHAR(9)    NULL,
  contact_no    VARCHAR(20)   NULL,
  password_hash VARCHAR(255)  NOT NULL,
  code_hash     VARCHAR(255)  NOT NULL,
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_sent_at  DATETIME      NOT NULL,
  expires_at    DATETIME      NOT NULL,
  PRIMARY KEY (pending_id),
  UNIQUE KEY uq_pending_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fund_request (
  request_id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  requested_by     INT UNSIGNED  NOT NULL,
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
) ENGINE=InnoDB;
