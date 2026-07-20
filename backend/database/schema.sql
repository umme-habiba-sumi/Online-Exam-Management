-- ExamPortal — MySQL schema
-- Run once in phpMyAdmin or: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS exam_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE exam_portal;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  email_verified TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL,
  roll_or_id VARCHAR(50) NULL,
  department VARCHAR(50) DEFAULT 'CSE',
  designation VARCHAR(100) NULL,
  avatar VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  otp_code VARCHAR(6) NOT NULL,
  is_verified TINYINT(1) DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS question_bank (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_code VARCHAR(20) NOT NULL,
  topic VARCHAR(100) NULL,
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  subject_code VARCHAR(20) NOT NULL,
  duration_minutes INT NOT NULL,
  total_marks INT NOT NULL,
  start_time DATETIME NOT NULL,
  status ENUM('draft','published','closed') DEFAULT 'draft',
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  question_bank_id INT NULL,
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  marks INT DEFAULT 1,
  order_no INT DEFAULT 0,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (question_bank_id) REFERENCES question_bank(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  student_id INT NOT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  score INT NULL,
  status ENUM('in_progress','submitted') DEFAULT 'in_progress',
  UNIQUE KEY one_attempt_per_student (exam_id, student_id),
  FOREIGN KEY (exam_id) REFERENCES exams(id),
  FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exam_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  exam_question_id INT NOT NULL,
  selected_option ENUM('A','B','C','D') NULL,
  is_correct TINYINT(1) NULL,
  UNIQUE KEY one_answer_per_question (attempt_id, exam_question_id),
  FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (exam_question_id) REFERENCES exam_questions(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('exam_submitted','exam_created') NOT NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- Required for Vercel / serverless (PHP session files do not persist)
CREATE TABLE IF NOT EXISTS app_sessions (
  id VARCHAR(128) NOT NULL PRIMARY KEY,
  payload MEDIUMTEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

