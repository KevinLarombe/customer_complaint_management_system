CREATE DATABASE IF NOT EXISTS complaint_resolution_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE complaint_resolution_db;

CREATE TABLE subscribers (
  customer_no VARCHAR(20) PRIMARY KEY,
  customer_name VARCHAR(100) NOT NULL,
  service_address VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE technicians (
  technician_id VARCHAR(20) PRIMARY KEY,
  technician_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE issue_categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE priorities (
  priority_id INT AUTO_INCREMENT PRIMARY KEY,
  priority_name VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE ticket_statuses (
  status_id INT AUTO_INCREMENT PRIMARY KEY,
  status_name VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE user_accounts (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('support_staff','technician') NOT NULL DEFAULT 'technician',
  technician_id VARCHAR(20) UNIQUE NULL,
  CONSTRAINT fk_user_technician FOREIGN KEY (technician_id)
    REFERENCES technicians(technician_id)
) ENGINE=InnoDB;

CREATE TABLE tickets (
  ticket_id VARCHAR(20) PRIMARY KEY,
  customer_no VARCHAR(20) NOT NULL,
  category_id INT NOT NULL,
  priority_id INT NOT NULL,
  technician_id VARCHAR(20) NULL,
  status_id INT NOT NULL,
  opened_date DATE NOT NULL,
  resolution_summary TEXT NULL,
  CONSTRAINT fk_ticket_subscriber FOREIGN KEY (customer_no)
    REFERENCES subscribers(customer_no),
  CONSTRAINT fk_ticket_category FOREIGN KEY (category_id)
    REFERENCES issue_categories(category_id),
  CONSTRAINT fk_ticket_priority FOREIGN KEY (priority_id)
    REFERENCES priorities(priority_id),
  CONSTRAINT fk_ticket_technician FOREIGN KEY (technician_id)
    REFERENCES technicians(technician_id),
  CONSTRAINT fk_ticket_status FOREIGN KEY (status_id)
    REFERENCES ticket_statuses(status_id),
  CONSTRAINT chk_closed_resolution CHECK (
    status_id <> 4 OR
    (resolution_summary IS NOT NULL AND CHAR_LENGTH(TRIM(resolution_summary)) > 0)
  )
) ENGINE=InnoDB;

CREATE TABLE progress_notes (
  progress_id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id VARCHAR(20) NOT NULL,
  author_user_id INT NOT NULL,
  progress_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  progress_note TEXT NOT NULL,
  CONSTRAINT fk_progress_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets(ticket_id),
  CONSTRAINT fk_progress_author FOREIGN KEY (author_user_id)
    REFERENCES user_accounts(user_id)
) ENGINE=InnoDB;

INSERT INTO subscribers (customer_no, customer_name, service_address) VALUES
('C-01','Amy Cruz','Block 1'),
('C-02','Bong Lim','Block 4'),
('C-03','Cia Yu','Block 2'),
('C-04','Dan Paz','Block 5');

INSERT INTO technicians (technician_id, technician_name) VALUES
('TEC-01','Rai Ong'),
('TEC-02','Kim Tan');

INSERT INTO issue_categories (category_name) VALUES
('No internet'),('Slow speed'),('Billing concern'),('Intermittent'),('Router issue');

INSERT INTO priorities (priority_id, priority_name) VALUES
(1,'High'),(2,'Medium'),(3,'Low');

INSERT INTO ticket_statuses (status_id, status_name) VALUES
(1,'Open'),(2,'Assigned'),(3,'Resolved'),(4,'Closed');

INSERT INTO tickets
(ticket_id, customer_no, category_id, priority_id, technician_id, status_id, opened_date)
VALUES
('TK-01','C-01',1,1,'TEC-01',3,'2026-10-01'),
('TK-02','C-02',2,2,'TEC-02',2,'2026-10-02'),
('TK-03','C-01',3,3,'TEC-02',1,'2026-10-04'),
('TK-04','C-03',1,1,'TEC-01',2,'2026-10-05'),
('TK-05','C-04',4,2,'TEC-02',1,'2026-10-06'),
('TK-06','C-02',5,2,'TEC-01',1,'2026-10-07');
