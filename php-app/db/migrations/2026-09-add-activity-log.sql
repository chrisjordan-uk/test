-- Only needed if you imported database.sql before this update.
-- Skip this if you're setting the app up fresh — it's already in database.sql.

CREATE TABLE IF NOT EXISTS activity_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED DEFAULT NULL,
  username      VARCHAR(50) DEFAULT NULL,
  action        VARCHAR(50) NOT NULL,
  entity_type   VARCHAR(50) DEFAULT NULL,
  entity_id     INT UNSIGNED DEFAULT NULL,
  description   VARCHAR(500) NOT NULL,
  ip_address    VARCHAR(45) DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_log_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  INDEX idx_activity_log_created (created_at),
  INDEX idx_activity_log_user (user_id),
  INDEX idx_activity_log_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
