-- ── Admins ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(200) NOT NULL UNIQUE,
    email         VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
 
-- ── School Years ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS school_years (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    label      VARCHAR(20)  NOT NULL UNIQUE,
    is_current TINYINT(1)   DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);
 
-- ── Borrow Records ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS borrow_records (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    school_year_id INT          NOT NULL,
    student_name   VARCHAR(200) NOT NULL,
    student_id     VARCHAR(50)  NOT NULL,
    year_level     VARCHAR(50),
    section        VARCHAR(50),
    semester       ENUM('1st Semester','2nd Semester') DEFAULT '1st Semester',
    tool_name      VARCHAR(200) NOT NULL,
    quantity       INT          DEFAULT 1,
    date_out       DATETIME     NOT NULL,
    due_date       DATETIME     NULL,
    returned       TINYINT(1)   DEFAULT 0,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_year_id) REFERENCES school_years(id)
);
 
-- ── Borrow Requests ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS borrow_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_name  VARCHAR(200) NOT NULL,
    student_email VARCHAR(200) NOT NULL,
    student_id    VARCHAR(50)  NOT NULL,
    year_level    VARCHAR(50),
    section       VARCHAR(50)  NULL,
    semester      ENUM('1st Semester','2nd Semester') DEFAULT '1st Semester',
    tool_name     VARCHAR(200) NOT NULL,
    quantity      INT          DEFAULT 1,
    status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
);
 
-- ── Default School Year ──────────────────────────────────────
INSERT INTO school_years (label, is_current)
VALUES ('2025-2026', 1)
ON DUPLICATE KEY UPDATE is_current = 1;
 
-- ── Default Admin Accounts ───────────────────────────────────
-- Passwords are activated by visiting /api/set_password.php
INSERT INTO admins (username, email, password_hash)
VALUES
    ('admin1', 'admin1@amt.local', 'PLACEHOLDER_RUN_SET_PASSWORD_PHP'),
    ('admin2', 'admin2@amt.local', 'PLACEHOLDER_RUN_SET_PASSWORD_PHP')
ON DUPLICATE KEY UPDATE id = id;
 