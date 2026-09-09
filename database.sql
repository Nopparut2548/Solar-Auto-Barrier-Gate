CREATE DATABASE IF NOT EXISTS solar_auto_barrier_gate
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE solar_auto_barrier_gate;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_code VARCHAR(30) NOT NULL UNIQUE,
  student_name VARCHAR(150) NOT NULL,
  rfid_uid VARCHAR(32) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NULL,
  rfid_uid VARCHAR(32) NOT NULL,
  status ENUM('allowed','denied') NOT NULL DEFAULT 'allowed',
  tap_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_access_tap_at (tap_at),
  INDEX idx_access_student_id (student_id),
  INDEX idx_access_rfid_uid (rfid_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
