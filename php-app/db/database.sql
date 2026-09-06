-- Vinted Resell Business Manager - MySQL schema (PHP version)
-- Import this once through phpMyAdmin (or `mysql -u root -p dbname < database.sql`)
-- into an empty database before using the app.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Roles & permissions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(50) NOT NULL UNIQUE,
  description   VARCHAR(255) DEFAULT NULL,
  -- JSON-encoded map of feature -> level ('none' | 'view' | 'manage'),
  -- stored as plain TEXT so this works on any MySQL/MariaDB version.
  -- features: dashboard, inventory, products, profit, reports, users
  permissions   TEXT NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) DEFAULT NULL,
  email         VARCHAR(150) DEFAULT NULL,
  role_id       INT UNSIGNED NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Products
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_number  VARCHAR(50) DEFAULT NULL UNIQUE,
  name            VARCHAR(150) NOT NULL,
  brand           VARCHAR(100) DEFAULT NULL,
  category        VARCHAR(100) DEFAULT NULL,
  size            VARCHAR(50) DEFAULT NULL,
  color           VARCHAR(50) DEFAULT NULL,
  item_condition  VARCHAR(50) DEFAULT NULL,
  status          ENUM('available','listed','to_ship','shipped','sold','returned','rejected')
                    NOT NULL DEFAULT 'available',
  bought_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
  sold_price      DECIMAL(10,2) DEFAULT NULL,
  purchase_date   DATE DEFAULT NULL,
  sold_date       DATE DEFAULT NULL,
  buyer           VARCHAR(100) DEFAULT NULL,
  notes           TEXT,
  created_by      INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL,
  INDEX idx_products_status (status),
  INDEX idx_products_brand (brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Profit ledger (stock purchases + sales; can optionally link to a product)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type              ENUM('purchase','sale','expense') NOT NULL,
  product_id        INT UNSIGNED DEFAULT NULL,
  category          VARCHAR(50) DEFAULT NULL,
  description       VARCHAR(255) NOT NULL,
  amount            DECIMAL(10,2) NOT NULL,
  quantity          INT UNSIGNED NOT NULL DEFAULT 1,
  transaction_date  DATE NOT NULL,
  created_by        INT UNSIGNED DEFAULT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transactions_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_transactions_created_by FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL,
  INDEX idx_transactions_date (transaction_date),
  INDEX idx_transactions_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Activity log: who did what, when — logins and every meaningful change.
-- ---------------------------------------------------------------------------
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

SET FOREIGN_KEY_CHECKS = 1;
