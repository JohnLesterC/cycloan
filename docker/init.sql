-- CYCLOAN Database Schema - reconstructed from PHP source
-- Creates all tables needed by the application

CREATE DATABASE IF NOT EXISTS cycloan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cycloan_db;

SET time_zone = '+08:00';

-- ========== USERS ==========
CREATE TABLE IF NOT EXISTS users1 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  name_extension VARCHAR(20),
  nick_name VARCHAR(100),
  birthday VARCHAR(20),
  age INT,
  birth_place VARCHAR(255),
  civil_status VARCHAR(50),
  contact VARCHAR(20),
  email VARCHAR(255) NOT NULL UNIQUE,
  fb_account VARCHAR(255),
  res_house_no VARCHAR(50),
  res_street VARCHAR(255),
  res_subdivision VARCHAR(255),
  res_barangay VARCHAR(255),
  res_address TEXT,
  bus_bldg_no VARCHAR(50),
  bus_street VARCHAR(255),
  bus_subdivision VARCHAR(255),
  bus_barangay VARCHAR(255),
  bus_address TEXT,
  house_ownership VARCHAR(50),
  occupation VARCHAR(255),
  reg_voter VARCHAR(10),
  year_resident INT,
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) DEFAULT 0,
  profile_image VARCHAR(255) DEFAULT '/assets/default.jpg',
  credit_points INT DEFAULT 0,
  credit_points_updated_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== SPOUSES ==========
CREATE TABLE IF NOT EXISTS spouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  first_name VARCHAR(100),
  middle_name VARCHAR(100),
  last_name VARCHAR(100),
  name_extension VARCHAR(20),
  nick_name VARCHAR(100),
  reg_voter VARCHAR(10),
  birthday VARCHAR(20),
  age INT,
  occupation VARCHAR(255),
  dependents INT,
  birth_place VARCHAR(255),
  contact VARCHAR(20),
  email VARCHAR(255),
  fb_account VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== FINANCIAL INFO ==========
CREATE TABLE IF NOT EXISTS financial_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  business_income DECIMAL(15,2) DEFAULT 0,
  salary_income DECIMAL(15,2) DEFAULT 0,
  remittance_income DECIMAL(15,2) DEFAULT 0,
  other_income DECIMAL(15,2) DEFAULT 0,
  business2_income DECIMAL(15,2) DEFAULT 0,
  salary2_income DECIMAL(15,2) DEFAULT 0,
  net_income DECIMAL(15,2) DEFAULT 0,
  food_allowance DECIMAL(15,2) DEFAULT 0,
  electricity_bill DECIMAL(15,2) DEFAULT 0,
  water_bill DECIMAL(15,2) DEFAULT 0,
  internet_bill DECIMAL(15,2) DEFAULT 0,
  gas_bill DECIMAL(15,2) DEFAULT 0,
  educational_allowance DECIMAL(15,2) DEFAULT 0,
  car_amortization DECIMAL(15,2) DEFAULT 0,
  insurance DECIMAL(15,2) DEFAULT 0,
  other_expense DECIMAL(15,2) DEFAULT 0,
  total_expenditures DECIMAL(15,2) DEFAULT 0,
  expected_monthly_amortization DECIMAL(15,2) DEFAULT 0,
  remaining_income DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== INCOME SOURCES ==========
CREATE TABLE IF NOT EXISTS income_sources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  source_type VARCHAR(100),
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== EXPENDITURE TYPES ==========
CREATE TABLE IF NOT EXISTS expenditure_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  expense_type VARCHAR(100),
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== OTPS ==========
CREATE TABLE IF NOT EXISTS otps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  otp_hash VARCHAR(255) NOT NULL,
  attempt_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  verified_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== LOGIN ATTEMPTS ==========
CREATE TABLE IF NOT EXISTS logattempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255),
  success TINYINT(1) DEFAULT 0,
  attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_attempt (attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== ACTIVITY LOGS ==========
CREATE TABLE IF NOT EXISTS activity_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  user_role VARCHAR(50),
  admin_name VARCHAR(255),
  admin_email VARCHAR(255),
  action_type VARCHAR(50),
  module VARCHAR(50),
  activity_type VARCHAR(50),
  description TEXT,
  affected_id VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at),
  INDEX idx_action_type (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== ADMIN TABLES ==========
CREATE TABLE IF NOT EXISTS admin1 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  profile_img VARCHAR(255) DEFAULT 'default.png',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin2 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  profile_img VARCHAR(255) DEFAULT 'default.png',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS superadmins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  profile_img VARCHAR(255) DEFAULT 'default.png',
  phone VARCHAR(20),
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== ADMIN ACTIVITY LOG ==========
CREATE TABLE IF NOT EXISTS admin_activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(255) NOT NULL,
  target_admin_email VARCHAR(255),
  performed_by VARCHAR(255),
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45),
  INDEX idx_timestamp (timestamp),
  INDEX idx_performed_by (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== INTEREST RATES ==========
CREATE TABLE IF NOT EXISTS interest_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  interest_rate DECIMAL(5,2) NOT NULL,
  term_length VARCHAR(10) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(255),
  UNIQUE KEY uq_term (term_length)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== LOAN TYPES ==========
CREATE TABLE IF NOT EXISTS loan_types (
  loan_type_id INT AUTO_INCREMENT PRIMARY KEY,
  type_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== DOCUMENT TYPES ==========
CREATE TABLE IF NOT EXISTS document_types (
  document_type_id INT AUTO_INCREMENT PRIMARY KEY,
  document_name VARCHAR(255) NOT NULL,
  INDEX idx_document_type_id (document_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== LOAN APPLICATIONS ==========
CREATE TABLE IF NOT EXISTS loan_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id VARCHAR(50) UNIQUE,
  loan_id VARCHAR(50),
  user_id INT NOT NULL,
  loan_type_id INT,
  loan_status VARCHAR(50),
  amount_applied DECIMAL(15,2),
  term_length VARCHAR(10),
  repayment_frequency VARCHAR(50) DEFAULT 'Monthly',
  purpose VARCHAR(255),
  others_text TEXT,
  project_type VARCHAR(50),
  project_description TEXT,
  final_loan_amount DECIMAL(15,2),
  interest_rate DECIMAL(5,2),
  loan_category VARCHAR(50) DEFAULT 'Individual',
  status VARCHAR(50) DEFAULT 'Pending',
  credit_investigation_status VARCHAR(50) DEFAULT 'Not Required',
  pre_approval_status VARCHAR(50) DEFAULT 'Pending',
  approval_reason TEXT,
  is_archived TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_application_id (application_id),
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_credit_investigation (credit_investigation_status),
  INDEX idx_final_amount (final_loan_amount),
  INDEX idx_is_archived (is_archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== LOANS ==========
CREATE TABLE IF NOT EXISTS loans (
  loan_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id VARCHAR(50),
  user_id INT NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  duration INT,
  interest_rate DECIMAL(5,2),
  monthly_payment DECIMAL(15,2),
  remaining_balance DECIMAL(15,2),
  total_paid DECIMAL(15,2) DEFAULT 0.00,
  status VARCHAR(50) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  payment_frequency VARCHAR(50) DEFAULT 'monthly',
  payment_amount DECIMAL(15,2),
  total_interest DECIMAL(15,2),
  total_principal DECIMAL(15,2),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_application_id (application_id),
  INDEX idx_user_id (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== PAYMENT SCHEDULES ==========
CREATE TABLE IF NOT EXISTS payment_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  status VARCHAR(20) DEFAULT 'pending',
  paid_at TIMESTAMP NULL,
  amount_paid DECIMAL(15,2) DEFAULT 0.00,
  payment_date_actual TIMESTAMP NULL,
  interest_amount DECIMAL(15,2) DEFAULT 0.00,
  principal_amount DECIMAL(15,2) DEFAULT 0.00,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  interest_paid DECIMAL(15,2) DEFAULT 0.00,
  principal_paid DECIMAL(15,2) DEFAULT 0.00,
  late_fee DECIMAL(15,2) DEFAULT 0.00,
  late_fee_paid DECIMAL(15,2) DEFAULT 0.00,
  INDEX idx_loan_id (loan_id),
  INDEX idx_due_date (due_date),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== LOAN PAYMENTS ==========
CREATE TABLE IF NOT EXISTS loan_payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  payment_date DATE NOT NULL,
  amount_due DECIMAL(15,2),
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loan_id (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== PAYMENT HISTORY ==========
CREATE TABLE IF NOT EXISTS payment_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT,
  loan_id INT NOT NULL,
  amount_paid DECIMAL(15,2),
  interest_paid DECIMAL(15,2),
  principal_paid DECIMAL(15,2),
  payment_type VARCHAR(50),
  payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  invoice_number VARCHAR(100),
  INDEX idx_loan_id (loan_id),
  INDEX idx_payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== INVOICES ==========
CREATE TABLE IF NOT EXISTS invoices (
  invoice_id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT,
  loan_id INT,
  payment_history_id INT,
  amount_paid DECIMAL(15,2),
  interest_paid DECIMAL(15,2),
  principal_paid DECIMAL(15,2),
  payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  invoice_number VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loan_id (loan_id),
  INDEX idx_payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== DOCUMENTS ==========
CREATE TABLE IF NOT EXISTS documents (
  document_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id VARCHAR(50),
  document_type_id INT,
  file_path VARCHAR(500),
  file_type VARCHAR(100),
  file_size INT,
  status VARCHAR(50) DEFAULT 'Pending',
  status_updated_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_application_id (application_id),
  INDEX idx_document_type_id (document_type_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== REMARKS ==========
CREATE TABLE IF NOT EXISTS remarks (
  remark_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id VARCHAR(50),
  remark TEXT,
  created_by VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_application_id (application_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== DOCUMENT REMARKS ==========
CREATE TABLE IF NOT EXISTS document_remarks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT,
  application_id VARCHAR(50),
  remark TEXT,
  created_by VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_document_id (document_id),
  INDEX idx_application_id (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== DOCUMENT AUDIT ==========
CREATE TABLE IF NOT EXISTS document_audit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT,
  application_id VARCHAR(50),
  document_type_id INT,
  old_status VARCHAR(50),
  new_status VARCHAR(50),
  admin_id INT,
  admin_name VARCHAR(255),
  admin_role VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_document_id (document_id),
  INDEX idx_application_id (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== NOTIFICATIONS (SSE) ==========
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  notification_type VARCHAR(50) NOT NULL DEFAULT 'system_alert',
  module VARCHAR(100),
  action VARCHAR(50),
  priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
  recipient_id INT,
  recipient_role VARCHAR(50),
  sender_id INT,
  related_id INT,
  is_read BOOLEAN DEFAULT FALSE,
  read_at DATETIME NULL,
  sent_at DATETIME NULL,
  status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_recipient_id (recipient_id),
  INDEX idx_recipient_role (recipient_role),
  INDEX idx_created_at (created_at),
  INDEX idx_notification_type (notification_type),
  INDEX idx_is_read (is_read),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== NOTIFICATION TYPES ==========
CREATE TABLE IF NOT EXISTS notification_types (
  type_id INT AUTO_INCREMENT PRIMARY KEY,
  type_name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_type_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== USER NOTIFICATIONS ==========
CREATE TABLE IF NOT EXISTS user_notifications (
  notification_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_type VARCHAR(50) DEFAULT 'user',
  type_id INT,
  title VARCHAR(255) NOT NULL,
  message LONGTEXT NOT NULL,
  short_message VARCHAR(500),
  event_type VARCHAR(100),
  priority VARCHAR(50) DEFAULT 'normal',
  action_url VARCHAR(500),
  is_read BOOLEAN DEFAULT FALSE,
  read_at DATETIME,
  expires_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME,
  KEY idx_user_id (user_id),
  KEY idx_type_id (type_id),
  KEY idx_is_read (is_read),
  KEY idx_created_at (created_at),
  KEY idx_priority (priority),
  KEY idx_user_read (user_id, is_read),
  KEY idx_user_created (user_id, created_at),
  KEY idx_active_notifications (user_id, deleted_at, is_read),
  CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_notifications_type FOREIGN KEY (type_id) REFERENCES notification_types(type_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== NOTIFICATION PREFERENCES ==========
CREATE TABLE IF NOT EXISTS notification_preferences (
  preference_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  notification_type VARCHAR(50),
  is_enabled BOOLEAN DEFAULT TRUE,
  channel VARCHAR(50) DEFAULT 'in_app',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_type_channel (user_id, notification_type, channel),
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== NOTIFICATION TEMPLATES ==========
CREATE TABLE IF NOT EXISTS notification_templates (
  template_id INT AUTO_INCREMENT PRIMARY KEY,
  template_name VARCHAR(100) NOT NULL,
  subject VARCHAR(255),
  body LONGTEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_template_name (template_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== USER NOTIFICATION SETTINGS ==========
CREATE TABLE IF NOT EXISTS user_notification_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type_id INT,
  is_enabled TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_type (user_id, type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== EMAIL QUEUE ==========
CREATE TABLE IF NOT EXISTS email_queue (
  queue_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id VARCHAR(50) NOT NULL,
  user_id INT,
  event_type VARCHAR(50) NOT NULL,
  event_data LONGTEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  batch_id VARCHAR(100),
  processed BOOLEAN DEFAULT FALSE,
  sent_at TIMESTAMP NULL,
  retry_count INT DEFAULT 0,
  last_error TEXT,
  INDEX idx_application (application_id),
  INDEX idx_user (user_id),
  INDEX idx_processed (processed),
  INDEX idx_batch (batch_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_batch_log (
  batch_id VARCHAR(100) PRIMARY KEY,
  application_id VARCHAR(50) NOT NULL,
  user_id INT,
  recipient_email VARCHAR(255),
  batch_start_time TIMESTAMP,
  batch_end_time TIMESTAMP,
  event_count INT DEFAULT 0,
  email_subject VARCHAR(255),
  email_body_preview LONGTEXT,
  status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  sent_timestamp TIMESTAMP NULL,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_application (application_id),
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_queue_settings (
  setting_id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value VARCHAR(255),
  description TEXT,
  data_type VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== TWO-FACTOR AUTH ==========
CREATE TABLE IF NOT EXISTS two_factor_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  method ENUM('email', 'sms', 'totp') NOT NULL DEFAULT 'email',
  phone_number VARCHAR(20),
  totp_secret VARCHAR(32),
  is_enabled BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
  INDEX idx_enabled (is_enabled),
  INDEX idx_method (method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  challenge_id VARCHAR(64) NOT NULL UNIQUE,
  method ENUM('email', 'sms', 'totp') NOT NULL,
  code_hash VARCHAR(255),
  verified BOOLEAN DEFAULT FALSE,
  attempt_count INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP,
  verified_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
  INDEX idx_challenge_id (challenge_id),
  INDEX idx_user_expires (user_id, expires_at),
  INDEX idx_verified (verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_backup_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used BOOLEAN DEFAULT FALSE,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users1(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== PASSWORD RESET ==========
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expiry TIMESTAMP NOT NULL,
  user_table VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255),
  ip_address VARCHAR(45),
  attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  success TINYINT(1) DEFAULT 0,
  INDEX idx_email (email),
  INDEX idx_attempt_time (attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== LOGIN SESSIONS ==========
CREATE TABLE IF NOT EXISTS login_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_type VARCHAR(50),
  token VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== CREDIT POINTS ==========
CREATE TABLE IF NOT EXISTS credit_points_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  points_change INT NOT NULL,
  previous_points INT,
  new_points INT,
  reason VARCHAR(255),
  loan_id INT,
  admin_id INT,
  admin_role VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_loan_id (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credit_points_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_name VARCHAR(100) NOT NULL UNIQUE,
  setting_value VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========== SEED DATA ==========

-- Interest rates (6, 12, 18, 24, 36 months)
INSERT IGNORE INTO interest_rates (interest_rate, term_length, updated_by) VALUES
(4.00, '6', 'system'),
(6.00, '12', 'system'),
(8.00, '18', 'system'),
(10.00, '24', 'system'),
(12.00, '36', 'system');

-- Loan types
INSERT IGNORE INTO loan_types (type_name) VALUES
('Individual'),
('Cooperative');

-- Document types
INSERT IGNORE INTO document_types (document_name) VALUES
('2x2 Picture'),
('Voter''s Certificate'),
('Residence Certificate'),
('Barangay Clearance'),
('Business Permit'),
('Farm Plan and Budget'),
('Loan Project Proposal'),
('Audited Financial Statement'),
('Bank Statement'),
('BIR');

-- Notification types
INSERT IGNORE INTO notification_types (type_name, description) VALUES
('success', 'Success notifications - positive outcomes'),
('info', 'Information notifications - general updates'),
('warning', 'Warning notifications - caution needed'),
('error', 'Error notifications - problems/failures');

-- Email queue settings
INSERT IGNORE INTO email_queue_settings (setting_key, setting_value, description, data_type) VALUES
('batch_window_seconds', '300', 'Time window to collect events before sending (in seconds)', 'integer'),
('max_batch_size', '50', 'Maximum number of events to include in one batch', 'integer'),
('enable_queue', '1', 'Enable/disable the email queue system (1=enabled, 0=disabled)', 'boolean'),
('batch_delay_minutes', '5', 'Minutes to wait before processing queue', 'integer'),
('max_retry_attempts', '3', 'Maximum number of retry attempts for failed emails', 'integer');

-- Credit points settings
INSERT IGNORE INTO credit_points_settings (setting_name, setting_value) VALUES
('first_loan_bonus', '100'),
('on_time_payment_bonus', '10'),
('late_payment_penalty', '5'),
('default_penalty', '50');

-- Admin accounts (password: admin123)
-- Hash generated with: php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
INSERT IGNORE INTO superadmins (first_name, last_name, email, password) VALUES
('Super', 'Admin', 'admin@cycloan.com', '$2y$10$gfz0BKg0RPTILJcgFPHpp.RJGGlO8Au3SoIrIqLt/NO/9Uvmgx7x6');

INSERT IGNORE INTO admin1 (first_name, last_name, email, password) VALUES
('Admin', 'One', 'admin1@cycloan.com', '$2y$10$gfz0BKg0RPTILJcgFPHpp.RJGGlO8Au3SoIrIqLt/NO/9Uvmgx7x6');

INSERT IGNORE INTO admin2 (first_name, last_name, email, password) VALUES
('Admin', 'Two', 'admin2@cycloan.com', '$2y$10$gfz0BKg0RPTILJcgFPHpp.RJGGlO8Au3SoIrIqLt/NO/9Uvmgx7x6');

-- Test user account (password: user123)
INSERT IGNORE INTO users1 (first_name, last_name, email, password, is_active, contact, civil_status, occupation) VALUES
('Test', 'User', 'user@cycloan.com', '$2y$10$gfz0BKg0RPTILJcgFPHpp.RJGGlO8Au3SoIrIqLt/NO/9Uvmgx7x6', 1, '09123456789', 'Single', 'Business Owner');
