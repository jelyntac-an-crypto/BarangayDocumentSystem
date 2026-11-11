USE barangay_document_system;

-- 1. DROP THE 12 OLD FOREIGN KEY CONSTRAINTS

ALTER TABLE activity_logs
    DROP FOREIGN KEY fk_activity_user;

ALTER TABLE document_requests
    DROP FOREIGN KEY fk_request_user,
    DROP FOREIGN KEY fk_request_reviewer;

ALTER TABLE email_otps
    DROP FOREIGN KEY fk_otp_user;

ALTER TABLE login_attempts
    DROP FOREIGN KEY fk_login_user;

ALTER TABLE notifications
    DROP FOREIGN KEY fk_notification_user;

ALTER TABLE password_history
    DROP FOREIGN KEY fk_password_history_user;

ALTER TABLE request_status_history
    DROP FOREIGN KEY fk_status_user;

ALTER TABLE resident_profiles
    DROP FOREIGN KEY fk_profile_user;

ALTER TABLE security_answers
    DROP FOREIGN KEY fk_answer_user;

ALTER TABLE security_flags
    DROP FOREIGN KEY fk_flag_user,
    DROP FOREIGN KEY fk_flag_reviewer;

-- 2. CONVERT USERS PRIMARY KEY

-- Remove AUTO_INCREMENT from the old ID first.
ALTER TABLE users
    MODIFY id BIGINT(20) UNSIGNED NOT NULL;

-- Add the new ID Number column.
ALTER TABLE users
    ADD COLUMN id_number CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL FIRST;

-- Replace the old primary key.
ALTER TABLE users
    DROP PRIMARY KEY,
    DROP COLUMN id,
    ADD PRIMARY KEY (id_number);

-- 3. CONVERT ALL USER FOREIGN KEY COLUMNS

ALTER TABLE activity_logs
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NULL;

ALTER TABLE document_requests
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    MODIFY reviewed_by CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NULL;

ALTER TABLE email_otps
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

ALTER TABLE login_attempts
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NULL;

ALTER TABLE notifications
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

ALTER TABLE password_history
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

ALTER TABLE request_status_history
    MODIFY changed_by CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NULL;

ALTER TABLE resident_profiles
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

ALTER TABLE security_answers
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

ALTER TABLE security_flags
    MODIFY user_id CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NULL,
    MODIFY reviewed_by CHAR(9)
        CHARACTER SET ascii COLLATE ascii_bin NULL;

-- 4. ADD MISSING RESIDENT PROFILE FIELDS

ALTER TABLE resident_profiles
    ADD COLUMN name_extension VARCHAR(28) NULL
        AFTER last_name,
    ADD COLUMN privacy_consent TINYINT(1) NOT NULL
        AFTER national_id_tag,
    ADD COLUMN privacy_consent_at DATETIME NOT NULL
        AFTER privacy_consent;

-- 5. RECREATE THE 12 FOREIGN KEY CONSTRAINTS

ALTER TABLE activity_logs
    ADD CONSTRAINT fk_activity_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE SET NULL;

ALTER TABLE document_requests
    ADD CONSTRAINT fk_request_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE RESTRICT,
    ADD CONSTRAINT fk_request_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id_number)
    ON DELETE SET NULL;

ALTER TABLE email_otps
    ADD CONSTRAINT fk_otp_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE CASCADE;

ALTER TABLE login_attempts
    ADD CONSTRAINT fk_login_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE SET NULL;

ALTER TABLE notifications
    ADD CONSTRAINT fk_notification_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE CASCADE;

ALTER TABLE password_history
    ADD CONSTRAINT fk_password_history_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE CASCADE;

ALTER TABLE request_status_history
    ADD CONSTRAINT fk_status_user
    FOREIGN KEY (changed_by) REFERENCES users(id_number)
    ON DELETE SET NULL;

ALTER TABLE resident_profiles
    ADD CONSTRAINT fk_profile_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE CASCADE;

ALTER TABLE security_answers
    ADD CONSTRAINT fk_answer_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE CASCADE;

ALTER TABLE security_flags
    ADD CONSTRAINT fk_flag_user
    FOREIGN KEY (user_id) REFERENCES users(id_number)
    ON DELETE SET NULL,
    ADD CONSTRAINT fk_flag_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id_number)
    ON DELETE SET NULL;
