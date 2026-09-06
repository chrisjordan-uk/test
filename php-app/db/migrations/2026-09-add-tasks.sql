-- Only needed if you imported database.sql before this update.
-- Skip this if you're setting the app up fresh — it's already in database.sql.

CREATE TABLE IF NOT EXISTS tasks (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(200) NOT NULL,
  description   TEXT,
  assigned_to   INT UNSIGNED NOT NULL,
  assigned_by   INT UNSIGNED DEFAULT NULL,
  priority      ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  due_date      DATE DEFAULT NULL,
  status        ENUM('pending','done') NOT NULL DEFAULT 'pending',
  completed_at  TIMESTAMP NULL DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_tasks_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
    ON DELETE SET NULL,
  INDEX idx_tasks_assigned_to (assigned_to),
  INDEX idx_tasks_status (status),
  INDEX idx_tasks_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
