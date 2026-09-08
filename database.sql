CREATE DATABASE IF NOT EXISTS solar_gate_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE solar_gate_system;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  faculty VARCHAR(150) NULL,
  rfid_uid VARCHAR(32) NOT NULL UNIQUE,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id_ref INT UNSIGNED NULL,
  student_name VARCHAR(150) NOT NULL,
  student_code VARCHAR(30) NOT NULL,
  rfid_uid VARCHAR(32) NOT NULL,
  access_type ENUM('in','out','none') NOT NULL DEFAULT 'none',
  result ENUM('granted','denied') NOT NULL,
  event_time DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_access_student
    FOREIGN KEY (student_id_ref) REFERENCES students(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX idx_access_event_time (event_time),
  INDEX idx_access_student_ref (student_id_ref),
  INDEX idx_access_rfid_uid (rfid_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
