CREATE DATABASE IF NOT EXISTS student_council_funds
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_council_funds;

CREATE TABLE student (
  student_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  student_no   VARCHAR(20)   NOT NULL,
  first_name   VARCHAR(50)   NOT NULL,
  middle_name  VARCHAR(50)   NULL,
  last_name    VARCHAR(50)   NOT NULL,
  suffix       VARCHAR(20)   NULL,
  program      VARCHAR(80)   NOT NULL,
  college      VARCHAR(100)  NULL,              
  year_level   TINYINT UNSIGNED NOT NULL,
  school_year  VARCHAR(9)    NULL,            
  contact_no   VARCHAR(20)   NULL,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_student_no (student_no),
  KEY idx_student_name (last_name, first_name),
  CONSTRAINT ck_student_year CHECK (year_level BETWEEN 1 AND 6)
) ENGINE=InnoDB;

CREATE TABLE officer (
  officer_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  student_id   INT UNSIGNED  NOT NULL,
  position     VARCHAR(40)   NOT NULL,      
  term_start   DATE          NOT NULL,
  term_end     DATE          NULL,             
  PRIMARY KEY (officer_id),
  KEY idx_officer_student (student_id),
  CONSTRAINT fk_officer_student FOREIGN KEY (student_id)
    REFERENCES student (student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_officer_term CHECK (term_end IS NULL OR term_end >= term_start)
) ENGINE=InnoDB;

CREATE TABLE fund_source (
  source_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  source_name  VARCHAR(60)   NOT NULL,         
  source_type  VARCHAR(30)   NOT NULL,      
  PRIMARY KEY (source_id),
  UNIQUE KEY uq_source_name (source_name)
) ENGINE=InnoDB;

CREATE TABLE collection (
  collection_id   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED   NOT NULL,     
  source_id       INT UNSIGNED   NOT NULL,
  officer_id      INT UNSIGNED   NOT NULL,     
  receipt_no      VARCHAR(20)    NOT NULL,
  amount          DECIMAL(10,2)  NOT NULL,
  collection_date DATE           NOT NULL,
  PRIMARY KEY (collection_id),
  UNIQUE KEY uq_receipt_no (receipt_no),
  KEY idx_collection_student (student_id),
  KEY idx_collection_source (source_id),
  KEY idx_collection_officer (officer_id),
  KEY idx_collection_date (collection_date),
  CONSTRAINT fk_collection_student FOREIGN KEY (student_id)
    REFERENCES student (student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_collection_source FOREIGN KEY (source_id)
    REFERENCES fund_source (source_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_collection_officer FOREIGN KEY (officer_id)
    REFERENCES officer (officer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_collection_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE budget (
  budget_id     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  school_year   VARCHAR(9)     NOT NULL,      
  total_amount  DECIMAL(12,2)  NOT NULL,
  status        VARCHAR(15)    NOT NULL DEFAULT 'Draft',
  PRIMARY KEY (budget_id),
  UNIQUE KEY uq_budget_year (school_year),
  CONSTRAINT ck_budget_total  CHECK (total_amount >= 0),
  CONSTRAINT ck_budget_status CHECK (status IN ('Draft','Approved','Closed'))
) ENGINE=InnoDB;

CREATE TABLE project (
  project_id        INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  budget_id         INT UNSIGNED   NOT NULL,
  officer_id        INT UNSIGNED   NOT NULL,  
  title             VARCHAR(120)   NOT NULL,
  allocated_amount  DECIMAL(12,2)  NOT NULL,
  start_date        DATE           NOT NULL,
  end_date          DATE           NULL,
  PRIMARY KEY (project_id),
  KEY idx_project_budget (budget_id),
  KEY idx_project_officer (officer_id),
  CONSTRAINT fk_project_budget FOREIGN KEY (budget_id)
    REFERENCES budget (budget_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_project_officer FOREIGN KEY (officer_id)
    REFERENCES officer (officer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_project_alloc CHECK (allocated_amount >= 0),
  CONSTRAINT ck_project_dates CHECK (end_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB;

CREATE TABLE expense_category (
  category_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  category_name  VARCHAR(60)   NOT NULL,   
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE expense (
  expense_id    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  project_id    INT UNSIGNED   NOT NULL,
  category_id   INT UNSIGNED   NOT NULL,
  officer_id    INT UNSIGNED   NOT NULL,       
  voucher_no    VARCHAR(20)    NOT NULL,
  amount        DECIMAL(10,2)  NOT NULL,
  expense_date  DATE           NOT NULL,
  description   VARCHAR(255)   NULL,
  PRIMARY KEY (expense_id),
  UNIQUE KEY uq_voucher_no (voucher_no),
  KEY idx_expense_project (project_id),
  KEY idx_expense_category (category_id),
  KEY idx_expense_officer (officer_id),
  KEY idx_expense_date (expense_date),
  CONSTRAINT fk_expense_project FOREIGN KEY (project_id)
    REFERENCES project (project_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_expense_category FOREIGN KEY (category_id)
    REFERENCES expense_category (category_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_expense_officer FOREIGN KEY (officer_id)
    REFERENCES officer (officer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_expense_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE VIEW v_project_spending AS
SELECT p.project_id,
       p.title,
       b.school_year,
       p.allocated_amount,
       COALESCE(SUM(e.amount), 0)                       AS total_spent,
       p.allocated_amount - COALESCE(SUM(e.amount), 0)  AS remaining
FROM project p
JOIN budget b          ON b.budget_id = p.budget_id
LEFT JOIN expense e    ON e.project_id = p.project_id
GROUP BY p.project_id, p.title, b.school_year, p.allocated_amount;

CREATE VIEW v_fund_balance AS
SELECT (SELECT COALESCE(SUM(amount), 0) FROM collection) AS total_collected,
       (SELECT COALESCE(SUM(amount), 0) FROM expense)    AS total_spent,
       (SELECT COALESCE(SUM(amount), 0) FROM collection)
     - (SELECT COALESCE(SUM(amount), 0) FROM expense)    AS current_balance;