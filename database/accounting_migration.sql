-- Accounting System Migration
-- Standard double-entry bookkeeping structures

CREATE TABLE IF NOT EXISTS chart_of_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_code VARCHAR(20) NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  account_type ENUM('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  parent_id INT DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (account_code),
  INDEX (account_type),
  CONSTRAINT fk_coa_parent FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_closed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_number VARCHAR(50) NOT NULL,
  entry_date DATE NOT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (entry_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_entry_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  journal_entry_id INT NOT NULL,
  account_id INT NOT NULL,
  debit DECIMAL(18,2) DEFAULT 0,
  credit DECIMAL(18,2) DEFAULT 0,
  memo VARCHAR(255) DEFAULT NULL,
  INDEX (journal_entry_id),
  INDEX (account_id),
  CONSTRAINT fk_jel_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_jel_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounting_integrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider ENUM('QuickBooks','ZohoBooks') NOT NULL,
  client_id VARCHAR(255) DEFAULT NULL,
  client_secret VARCHAR(255) DEFAULT NULL,
  redirect_uri VARCHAR(255) DEFAULT NULL,
  access_token TEXT DEFAULT NULL,
  refresh_token TEXT DEFAULT NULL,
  token_expires_at DATETIME DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


