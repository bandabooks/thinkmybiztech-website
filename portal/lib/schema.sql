-- Think Mybiz Tech Client Hub schema. Run by portal/setup.php (idempotent: CREATE TABLE IF NOT EXISTS).
-- Money is stored in integer cents; quantities in thousandths. Never edit issued documents in SQL.

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  company VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  vat_number VARCHAR(20) NULL,
  address VARCHAR(400) NULL,
  password_hash VARCHAR(255) NULL,
  email_verified_at DATETIME NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
  source VARCHAR(40) NOT NULL DEFAULT 'portal',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('verify','reset','staff_reset') NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY k_subject (kind, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(24) NOT NULL UNIQUE,
  client_id INT UNSIGNED NULL,
  contact_name VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NULL,
  company VARCHAR(160) NULL,
  service VARCHAR(160) NULL,
  package_id VARCHAR(40) NULL,
  budget VARCHAR(60) NULL,
  timeline VARCHAR(60) NULL,
  description TEXT NOT NULL,
  source ENUM('website_form','portal','admin') NOT NULL,
  status ENUM('new','contacted','quoted','won','lost','closed') NOT NULL DEFAULT 'new',
  utm_source VARCHAR(80) NULL,
  utm_medium VARCHAR(80) NULL,
  utm_campaign VARCHAR(120) NULL,
  page VARCHAR(200) NULL,
  ip_hash CHAR(16) NULL,
  consent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY k_client (client_id),
  KEY k_email (contact_email),
  KEY k_status (status, created_at),
  CONSTRAINT fk_req_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NULL,
  sender ENUM('client','staff') NOT NULL,
  staff_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY k_client (client_id, created_at),
  CONSTRAINT fk_msg_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('quote','invoice') NOT NULL,
  number VARCHAR(30) NULL UNIQUE,
  client_id INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NULL,
  source_quote_id INT UNSIGNED NULL,
  status ENUM('draft','issued','accepted','declined','paid','part_paid','void') NOT NULL DEFAULT 'draft',
  issue_date DATE NULL,
  due_date DATE NULL,
  vat_registered TINYINT(1) NOT NULL DEFAULT 0,
  vat_rate_bp INT NOT NULL DEFAULT 0,
  subtotal_cents BIGINT NOT NULL DEFAULT 0,
  vat_cents BIGINT NOT NULL DEFAULT 0,
  total_cents BIGINT NOT NULL DEFAULT 0,
  paid_cents BIGINT NOT NULL DEFAULT 0,
  title VARCHAR(200) NULL,
  notes TEXT NULL,
  terms TEXT NULL,
  client_snapshot TEXT NULL,
  company_snapshot TEXT NULL,
  void_reason VARCHAR(300) NULL,
  responded_at DATETIME NULL,
  responded_name VARCHAR(120) NULL,
  created_by INT UNSIGNED NULL,
  issued_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY k_client (client_id, kind),
  KEY k_status (kind, status),
  CONSTRAINT fk_doc_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  position INT NOT NULL,
  description VARCHAR(500) NOT NULL,
  qty_milli INT NOT NULL,
  unit_cents BIGINT NOT NULL,
  net_cents BIGINT NOT NULL,
  vat_cents BIGINT NOT NULL,
  KEY k_doc (document_id, position),
  CONSTRAINT fk_line_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  amount_cents BIGINT NOT NULL,
  paid_on DATE NOT NULL,
  method VARCHAR(40) NOT NULL,
  reference VARCHAR(120) NULL,
  recorded_by INT UNSIGNED NOT NULL,
  reversed_at DATETIME NULL,
  reverse_reason VARCHAR(300) NULL,
  created_at DATETIME NOT NULL,
  KEY k_doc (document_id),
  CONSTRAINT fk_pay_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS counters (
  name VARCHAR(30) PRIMARY KEY,
  next_value INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  body TEXT NOT NULL,
  link_url VARCHAR(500) NULL,
  publish_at DATETIME NOT NULL,
  fb_status ENUM('pending','sending','posted','failed','off') NOT NULL DEFAULT 'pending',
  fb_post_id VARCHAR(80) NULL,
  fb_error VARCHAR(300) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY k_key (key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('client','staff','system','public') NOT NULL,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(30) NULL,
  entity_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip_hash CHAR(16) NULL,
  created_at DATETIME NOT NULL,
  KEY k_entity (entity, entity_id),
  KEY k_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
