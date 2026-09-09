USE imsafe_oop_local;

CREATE TABLE IF NOT EXISTS local_users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, display_name VARCHAR(120) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, last_signed_in TIMESTAMP NULL, INDEX idx_local_users_email(email));

ALTER TABLE incidents
  ADD COLUMN local_user_id INT UNSIGNED NULL AFTER id,
  ADD INDEX idx_incident_user (local_user_id),
  ADD CONSTRAINT fk_incident_user FOREIGN KEY (local_user_id) REFERENCES local_users(id) ON DELETE SET NULL;
