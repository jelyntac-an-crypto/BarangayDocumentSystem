
USE barangay_document_system;

-- 1. COMMON USER ACCOUNTS
CREATE TABLE users (
    id_number CHAR(9) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    username VARCHAR(19) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('resident', 'staff', 'security_admin') NOT NULL DEFAULT 'resident',
    email_verified_at DATETIME NULL,
    account_status ENUM('pending', 'active', 'disabled') NOT NULL DEFAULT 'pending',
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    lockout_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. RESIDENT PROFILES
CREATE TABLE resident_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    first_name VARCHAR(28) NOT NULL,
    middle_name VARCHAR(28) NULL,
    last_name VARCHAR(28) NOT NULL,
    name_extension VARCHAR(28) NULL,
    birth_date DATE NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    contact_number VARCHAR(11) NOT NULL,
    purok_street VARCHAR(50) NOT NULL,
    barangay VARCHAR(50) NULL,
    municipality_city VARCHAR(50) NULL,
    province VARCHAR(50) NULL,
    country VARCHAR(50) NULL,
    zip_code VARCHAR(4) NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated') NOT NULL,
    national_id_ciphertext TEXT NOT NULL,
    national_id_iv VARCHAR(32) NOT NULL,
    national_id_tag VARCHAR(32) NOT NULL,
    privacy_consent TINYINT(1) NOT NULL,
    privacy_consent_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. HASHED SECURITY ANSWERS
CREATE TABLE security_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    question_code ENUM('Q1', 'Q4', 'Q6') NOT NULL,
    answer_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_question (user_id, question_code),
    CONSTRAINT fk_answer_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. PASSWORD HISTORY
CREATE TABLE password_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_user_date (user_id, created_at),
    CONSTRAINT fk_password_history_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5. EMAIL AND AUTHENTICATION OTP
CREATE TABLE email_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    purpose ENUM('registration', 'login', 'password_reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_user_purpose (user_id, purpose),
    INDEX idx_otp_expiry (expires_at),
    CONSTRAINT fk_otp_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. LOGIN ATTEMPTS
CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NULL,
    attempted_username VARCHAR(19) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    attempt_status ENUM(
        'SUCCESS',
        'FAILED',
        'CAPTCHA_FAILED',
        'OTP_FAILED'
    ) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_ip_time (ip_address, attempted_at),
    INDEX idx_login_user_time (user_id, attempted_at),
    CONSTRAINT fk_login_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 7. IP BLOCKS
CREATE TABLE ip_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_ip_block_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. ACTIVITY LOGS
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NULL,
    event_type VARCHAR(100) NOT NULL,
    description VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    event_data LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_user_time (user_id, created_at),
    INDEX idx_activity_event_time (event_type, created_at),
    CONSTRAINT fk_activity_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. SECURITY FLAGS
CREATE TABLE security_flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ip_address VARCHAR(45) NULL,
    flag_type VARCHAR(100) NOT NULL,
    description VARCHAR(500) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME NULL,
    INDEX idx_flag_severity_time (severity, detected_at),
    CONSTRAINT fk_flag_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE SET NULL,
    CONSTRAINT fk_flag_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id_number)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 10. DOCUMENT TYPES
CREATE TABLE document_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 11. DOCUMENT REQUESTS
CREATE TABLE document_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    document_type_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(500) NOT NULL,
    status ENUM(
        'Pending',
        'Under Review',
        'Approved',
        'Rejected',
        'Ready for Claiming',
        'Claimed'
    ) NOT NULL DEFAULT 'Pending',
    reviewed_by CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NULL,
    reviewed_at DATETIME NULL,
    rejection_reason VARCHAR(500) NULL,
    claimed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_request_user_date (user_id, created_at),
    INDEX idx_request_status (status),
    CONSTRAINT fk_request_user
        FOREIGN KEY (user_id) REFERENCES users(id_number),
    CONSTRAINT fk_request_document_type
        FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    CONSTRAINT fk_request_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id_number)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 12. UPLOADED REQUIREMENTS
CREATE TABLE uploaded_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_upload_request
        FOREIGN KEY (request_id) REFERENCES document_requests(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 13. REQUEST STATUS HISTORY
CREATE TABLE request_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    changed_by CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NOT NULL,
    remarks VARCHAR(500) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_request_date (request_id, changed_at),
    CONSTRAINT fk_status_request
        FOREIGN KEY (request_id) REFERENCES document_requests(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_status_user
        FOREIGN KEY (changed_by) REFERENCES users(id_number)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 14. NOTIFICATIONS
CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id CHAR(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_user (user_id, is_read),
    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id) REFERENCES users(id_number)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- INITIAL DOCUMENT TYPES
INSERT INTO document_types (document_name, description) VALUES
('Barangay Clearance', 'Application for Barangay Clearance'),
('Certificate of Indigency', 'Application for Certificate of Indigency'),
('Certificate of Residency', 'Application for Certificate of Residency'),
('Barangay Certificate', 'Application for Barangay Certificate'),
('First-Time Job Seeker Certificate', 'Application for First-Time Job Seeker Certificate'),
('Business Clearance', 'Application for Business Clearance');
