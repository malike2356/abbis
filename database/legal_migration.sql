-- Legal / Contracts tables

CREATE TABLE IF NOT EXISTS contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  contract_type VARCHAR(50) NOT NULL, -- client, vendor, subcontract, drilling
  counterparty VARCHAR(150) DEFAULT NULL,
  effective_date DATE DEFAULT NULL,
  file_path VARCHAR(255) NOT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


