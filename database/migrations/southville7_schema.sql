-- SouthVille7 System Database Schema
-- Generated: December 17, 2025

CREATE DATABASE IF NOT EXISTS southville7_system_test
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE southville7_system;

-- Drop tables in reverse order of dependencies
DROP TABLE IF EXISTS logs;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS loan_payments;
DROP TABLE IF EXISTS deposits;
DROP TABLE IF EXISTS withdrawals;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS beneficiaries;
DROP TABLE IF EXISTS personal_data;
DROP TABLE IF EXISTS images;
DROP TABLE IF EXISTS member_ids;
DROP TABLE IF EXISTS employments;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS addresses;

-- ====================================
-- FOUNDATION TABLES (No Dependencies)
-- ====================================

-- Addresses Table
CREATE TABLE addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country VARCHAR(100) NULL,
    region VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    city_municipality VARCHAR(100) NULL,
    barangay VARCHAR(100) NULL,
    street VARCHAR(255) NULL,
    zip_code VARCHAR(20) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts Table
CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_no VARCHAR(11) NULL,
    phone_verified_at TIMESTAMP NULL,
    email VARCHAR(255) NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    facebook VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employments Table
CREATE TABLE employments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employment_type ENUM('full-time', 'part-time', 'self-employed', 'freelancer', 'government', 'private', 'ofw', 'retired', 'unemployed', 'student', 'homemaker') NULL,
    company VARCHAR(255) NULL,
    position VARCHAR(100) NULL,
    monthly_income DECIMAL(15, 2) NULL,
    other_income_source VARCHAR(255) NULL,
    other_income_amount DECIMAL(15, 2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member IDs Table
CREATE TABLE member_ids (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    national_id VARCHAR(64) NULL,
    tin VARCHAR(64) NULL,
    sss VARCHAR(64) NULL,
    philhealth VARCHAR(64) NULL,
    barangay_id VARCHAR(64) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Images Table
CREATE TABLE images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(16) NOT NULL,
    drive_file_id VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    is_archived VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- PERSONAL DATA (Depends on Foundation)
-- ====================================

-- Personal Data Table
CREATE TABLE personal_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personal_data_number VARCHAR(20) NOT NULL UNIQUE,
    title ENUM('Chairman', 'Vice Chairman', 'Secretary', 'Treasurer', 'Loan Officer', 'Member') NOT NULL DEFAULT 'Member',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    mid_name VARCHAR(100) NULL,
    suffix VARCHAR(50) NULL,
    birth_date DATE NULL,
    birthplace VARCHAR(255) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    education VARCHAR(255) NULL,
    civil_status ENUM('single', 'married', 'widowed', 'separated') NULL,
    religion VARCHAR(64) NULL,
    address_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,
    employment_id BIGINT UNSIGNED NULL UNIQUE,
    member_id BIGINT UNSIGNED NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (employment_id) REFERENCES employments(id) ON DELETE SET NULL,
    FOREIGN KEY (member_id) REFERENCES member_ids(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- USERS & AUTH (Depends on Personal Data)
-- ====================================

-- Users Table
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_number VARCHAR(20) NOT NULL UNIQUE,
    personal_data_id BIGINT UNSIGNED NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    role ENUM('developer', 'admin', 'manager', 'member', 'loan', 'treasurer', 'secretary', 'inactive', 'terminated') NOT NULL DEFAULT 'inactive',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (personal_data_id) REFERENCES personal_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beneficiaries Table
CREATE TABLE beneficiaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    mid_name VARCHAR(100) NULL,
    birth_date DATE NULL,
    relationship VARCHAR(100) NOT NULL,
    phone_no VARCHAR(11) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Reset Tokens
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions Table
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    
    INDEX sessions_user_id_index (user_id),
    INDEX sessions_last_activity_index (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- ACCOUNTS & FINANCIAL (Depends on Users)
-- ====================================

-- Accounts Table
CREATE TABLE accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NULL,
    account_type ENUM('savings', 'share-capital') NOT NULL DEFAULT 'savings',
    balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loans Table
CREATE TABLE loans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_number VARCHAR(20) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NULL,
    loan_type VARCHAR(50) NOT NULL,
    loan_amount DECIMAL(15, 2) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL,
    term_months INT NULL,
    payment_frequency ENUM('weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually') NOT NULL DEFAULT 'monthly',
    loan_status ENUM('pending', 'approved', 'active', 'paid_off') NOT NULL DEFAULT 'pending',
    applied_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    due_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deposits Table
CREATE TABLE deposits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deposit_number VARCHAR(20) NOT NULL UNIQUE,
    account_id BIGINT UNSIGNED NULL,
    deposit_amount DECIMAL(15, 2) NOT NULL,
    deposit_date DATETIME NULL,
    recorded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Withdrawals Table
CREATE TABLE withdrawals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    withdrawal_number VARCHAR(20) NOT NULL UNIQUE,
    account_id BIGINT UNSIGNED NULL,
    withdrawal_amount DECIMAL(15, 2) NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loan Payments Table
CREATE TABLE loan_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(20) NOT NULL UNIQUE,
    loan_id BIGINT UNSIGNED NULL,
    payment_amount DECIMAL(15, 2) NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- SYSTEM TABLES (Transactions & Logs)
-- ====================================

-- Transactions Table
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(20) NOT NULL UNIQUE,
    account_id BIGINT UNSIGNED NULL,
    processed_by BIGINT UNSIGNED NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'transfer', 'fee', 'interest') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description TEXT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs Table
CREATE TABLE logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    activity VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- INDEXES FOR PERFORMANCE
-- ====================================

CREATE INDEX idx_personal_data_number ON personal_data(personal_data_number);
CREATE INDEX idx_user_number ON users(user_number);
CREATE INDEX idx_account_number ON accounts(account_number);
CREATE INDEX idx_loan_number ON loans(loan_number);
CREATE INDEX idx_transaction_number ON transactions(transaction_number);
CREATE INDEX idx_loan_status ON loans(loan_status);
CREATE INDEX idx_account_type ON accounts(account_type);

-- ====================================
-- SAMPLE DATA
-- ====================================

-- Sample Addresses
INSERT INTO addresses (id, country, region, province, city_municipality, barangay, street, zip_code) VALUES
(1, 'Philippines', 'NCR', 'Metro Manila', 'Quezon City', 'Barangay Commonwealth', '123 Main Street', '1121'),
(2, 'Philippines', 'NCR', 'Metro Manila', 'Manila', 'Ermita', '456 Roxas Boulevard', '1000'),
(3, 'Philippines', 'Region IV-A', 'Laguna', 'Santa Rosa', 'Balibago', '789 Laguna Blvd', '4026'),
(4, 'Philippines', 'Region III', 'Pampanga', 'Angeles City', 'Balibago', '321 Clark Avenue', '2009'),
(5, 'Philippines', 'NCR', 'Metro Manila', 'Makati', 'Poblacion', '555 Ayala Avenue', '1210');

-- Sample Contacts
INSERT INTO contacts (id, phone_no, phone_verified_at, email, email_verified_at, facebook) VALUES
(1, '09171234567', NOW(), 'juan.delacruz@email.com', NOW(), 'fb.com/juandelacruz'),
(2, '09281234568', NOW(), 'maria.santos@email.com', NOW(), 'fb.com/mariasantos'),
(3, '09351234569', NOW(), 'pedro.reyes@email.com', NULL, 'fb.com/pedroreyes'),
(4, '09461234570', NOW(), 'ana.garcia@email.com', NOW(), NULL),
(5, '09171234571', NOW(), 'carlos.lopez@email.com', NOW(), 'fb.com/carloslopez'),
(6, '09281234572', NULL, 'admin@southville.com', NOW(), NULL);

-- Sample Employments
INSERT INTO employments (id, employment_type, company, position, monthly_income, other_income_source, other_income_amount, created_at, updated_at) VALUES
(1, 'full-time', 'ABC Corporation', 'Software Engineer', 45000.00, 'Freelance Projects', 10000.00, NOW(), NOW()),
(2, 'government', 'Department of Education', 'Teacher', 35000.00, NULL, NULL, NOW(), NOW()),
(3, 'self-employed', 'Juan\'s Sari-Sari Store', 'Business Owner', 25000.00, NULL, NULL, NOW(), NOW()),
(4, 'private', 'XYZ Trading Company', 'Sales Manager', 50000.00, 'Online Business', 15000.00, NOW(), NOW()),
(5, 'ofw', 'Dubai Construction Co.', 'Civil Engineer', 80000.00, NULL, NULL, NOW(), NOW());

-- Sample Member IDs
INSERT INTO member_ids (id, national_id, tin, sss, philhealth, barangay_id, created_at, updated_at) VALUES
(1, '1234-5678-9012-3456', '123-456-789-000', '01-2345678-9', '12-345678901-2', 'BRG-2023-001', NOW(), NOW()),
(2, '2345-6789-0123-4567', '234-567-890-111', '02-3456789-0', '23-456789012-3', 'BRG-2023-002', NOW(), NOW()),
(3, '3456-7890-1234-5678', '345-678-901-222', '03-4567890-1', '34-567890123-4', 'BRG-2023-003', NOW(), NOW()),
(4, '4567-8901-2345-6789', '456-789-012-333', '04-5678901-2', '45-678901234-5', 'BRG-2023-004', NOW(), NOW()),
(5, '5678-9012-3456-7890', '567-890-123-444', '05-6789012-3', '56-789012345-6', 'BRG-2023-005', NOW(), NOW());

-- Sample Personal Data
INSERT INTO personal_data (id, personal_data_number, title, first_name, last_name, mid_name, suffix, birth_date, birthplace, gender, education, civil_status, religion, address_id, contact_id, employment_id, member_id, created_at, updated_at) VALUES
(1, 'PDS000001', 'Chairman', 'Juan', 'Dela Cruz', 'Santos', NULL, '1975-06-15', 'Manila', 'male', 'Bachelor\'s Degree', 'married', 'Catholic', 1, 1, 1, 1, NOW(), NOW()),
(2, 'PDS000002', 'Treasurer', 'Maria', 'Santos', 'Reyes', NULL, '1982-03-22', 'Quezon City', 'female', 'Master\'s Degree', 'married', 'Catholic', 2, 2, 2, 2, NOW(), NOW()),
(3, 'PDS000003', 'Member', 'Pedro', 'Reyes', 'Garcia', 'Jr.', '1990-11-08', 'Santa Rosa', 'male', 'High School', 'single', 'Christian', 3, 3, 3, 3, NOW(), NOW()),
(4, 'PDS000004', 'Secretary', 'Ana', 'Garcia', 'Lopez', NULL, '1985-09-14', 'Angeles City', 'female', 'Bachelor\'s Degree', 'widowed', 'Catholic', 4, 4, 4, 4, NOW(), NOW()),
(5, 'PDS000005', 'Member', 'Carlos', 'Lopez', 'Martinez', NULL, '1988-01-30', 'Makati', 'male', 'Bachelor\'s Degree', 'married', 'Catholic', 5, 5, 5, 5, NOW(), NOW());

-- Sample Users (password is 'password' hashed with bcrypt)
INSERT INTO users (id, user_number, personal_data_id, email, password, role, created_at, updated_at) VALUES
(1, 'USR000001', 1, 'juan.delacruz@email.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5lE3gPWqHhOu.', 'admin', NOW(), NOW()),
(2, 'USR000002', 2, 'maria.santos@email.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5lE3gPWqHhOu.', 'treasurer', NOW(), NOW()),
(3, 'USR000003', 3, 'pedro.reyes@email.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5lE3gPWqHhOu.', 'member', NOW(), NOW()),
(4, 'USR000004', 4, 'ana.garcia@email.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5lE3gPWqHhOu.', 'secretary', NOW(), NOW()),
(5, 'USR000005', 5, 'carlos.lopez@email.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5lE3gPWqHhOu.', 'member', NOW(), NOW()),
(6, 'USR000006', NULL, 'admin@southville.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5lE3gPWqHhOu.', 'developer', NOW(), NOW());

-- Sample Beneficiaries
INSERT INTO beneficiaries (id, user_id, first_name, last_name, mid_name, birth_date, relationship, phone_no, created_at, updated_at) VALUES
(1, 1, 'Josefa', 'Dela Cruz', 'Santos', '1976-08-20', 'Spouse', '09171234577', NOW(), NOW()),
(2, 1, 'Juan Jr.', 'Dela Cruz', 'Santos', '2005-03-15', 'Son', NULL, NOW(), NOW()),
(3, 2, 'Roberto', 'Santos', 'Cruz', '1980-12-10', 'Spouse', '09281234578', NOW(), NOW()),
(4, 5, 'Carmen', 'Lopez', 'Reyes', '1989-05-22', 'Spouse', '09171234579', NOW(), NOW());

-- Sample Accounts
INSERT INTO accounts (id, account_number, user_id, account_type, balance, created_at, updated_at) VALUES
(1, 'ACNT000001', 1, 'savings', 50000.00, NOW(), NOW()),
(2, 'ACNT000002', 1, 'share-capital', 10000.00, NOW(), NOW()),
(3, 'ACNT000003', 2, 'savings', 75000.00, NOW(), NOW()),
(4, 'ACNT000004', 2, 'share-capital', 15000.00, NOW(), NOW()),
(5, 'ACNT000005', 3, 'savings', 25000.00, NOW(), NOW()),
(6, 'ACNT000006', 4, 'savings', 60000.00, NOW(), NOW()),
(7, 'ACNT000007', 5, 'savings', 100000.00, NOW(), NOW()),
(8, 'ACNT000008', 5, 'share-capital', 20000.00, NOW(), NOW());

-- Sample Loans
INSERT INTO loans (id, loan_number, user_id, loan_type, loan_amount, interest_rate, term_months, payment_frequency, loan_status, applied_at, approved_at, approved_by, due_date, created_at, updated_at) VALUES
(1, 'LOAN000001', 3, 'Personal Loan', 50000.00, 2.50, 12, 'monthly', 'active', '2024-01-15 10:00:00', '2024-01-16 14:30:00', 1, '2025-01-15', NOW(), NOW()),
(2, 'LOAN000002', 4, 'Business Loan', 150000.00, 3.00, 24, 'monthly', 'active', '2024-02-10 09:00:00', '2024-02-11 11:00:00', 1, '2026-02-10', NOW(), NOW()),
(3, 'LOAN000003', 5, 'Emergency Loan', 30000.00, 2.00, 6, 'monthly', 'paid_off', '2024-06-01 13:00:00', '2024-06-02 15:00:00', 2, '2024-12-01', NOW(), NOW()),
(4, 'LOAN000004', 3, 'Educational Loan', 75000.00, 2.75, 18, 'monthly', 'pending', '2024-12-10 08:30:00', NULL, NULL, NULL, NOW(), NOW());

-- Sample Deposits
INSERT INTO deposits (id, deposit_number, account_id, deposit_amount, deposit_date, recorded_by, created_at, updated_at) VALUES
(1, 'DEP000001', 1, 10000.00, '2024-01-05 09:30:00', 2, NOW(), NOW()),
(2, 'DEP000002', 3, 15000.00, '2024-01-10 10:15:00', 2, NOW(), NOW()),
(3, 'DEP000003', 5, 5000.00, '2024-02-15 14:20:00', 2, NOW(), NOW()),
(4, 'DEP000004', 1, 20000.00, '2024-03-20 11:45:00', 2, NOW(), NOW()),
(5, 'DEP000005', 7, 50000.00, '2024-04-10 08:00:00', 2, NOW(), NOW()),
(6, 'DEP000006', 2, 10000.00, '2024-05-05 13:30:00', 2, NOW(), NOW());

-- Sample Withdrawals
INSERT INTO withdrawals (id, withdrawal_number, account_id, withdrawal_amount, recorded_by, created_at, updated_at) VALUES
(1, 'WTH000001', 1, 5000.00, 2, NOW(), NOW()),
(2, 'WTH000002', 3, 10000.00, 2, NOW(), NOW()),
(3, 'WTH000003', 7, 20000.00, 2, NOW(), NOW()),
(4, 'WTH000004', 6, 15000.00, 2, NOW(), NOW());

-- Sample Loan Payments
INSERT INTO loan_payments (id, payment_number, loan_id, payment_amount, recorded_by, created_at, updated_at) VALUES
(1, 'PAY000001', 1, 4500.00, 2, NOW(), NOW()),
(2, 'PAY000002', 2, 6500.00, 2, NOW(), NOW()),
(3, 'PAY000003', 1, 4500.00, 2, NOW(), NOW()),
(4, 'PAY000004', 3, 5200.00, 2, NOW(), NOW()),
(5, 'PAY000005', 3, 5200.00, 2, NOW(), NOW()),
(6, 'PAY000006', 3, 5200.00, 2, NOW(), NOW());

-- Sample Transactions
INSERT INTO transactions (id, transaction_number, account_id, processed_by, transaction_type, amount, description, reference_type, reference_id, created_at, updated_at) VALUES
(1, 'TXN20240105000001', 1, 2, 'deposit', 10000.00, 'Initial deposit', 'deposits', 1, NOW(), NOW()),
(2, 'TXN20240110000002', 3, 2, 'deposit', 15000.00, 'Monthly savings', 'deposits', 2, NOW(), NOW()),
(3, 'TXN20240115000003', 1, 2, 'withdrawal', 5000.00, 'Emergency withdrawal', 'withdrawals', 1, NOW(), NOW()),
(4, 'TXN20240120000004', 1, 2, 'loan_payment', 4500.00, 'Loan payment for LOAN000001', 'loan_payments', 1, NOW(), NOW()),
(5, 'TXN20240125000005', NULL, 1, 'loan_disbursement', 50000.00, 'Loan disbursement for LOAN000001', 'loans', 1, NOW(), NOW()),
(6, 'TXN20240210000006', 2, 2, 'deposit', 10000.00, 'Share capital contribution', 'deposits', 6, NOW(), NOW());

-- Sample Logs
INSERT INTO logs (id, user_id, activity, description, ip_address, created_at, updated_at) VALUES
(1, 1, 'User Login', 'Admin logged in to system', '192.168.1.100', NOW(), NOW()),
(2, 2, 'Transaction Recorded', 'Recorded deposit DEP000001', '192.168.1.101', NOW(), NOW()),
(3, 1, 'Loan Approved', 'Approved loan LOAN000001', '192.168.1.100', NOW(), NOW()),
(4, 2, 'Transaction Recorded', 'Recorded withdrawal WTH000001', '192.168.1.101', NOW(), NOW()),
(5, 4, 'User Login', 'Secretary logged in to system', '192.168.1.102', NOW(), NOW()),
(6, 1, 'User Management', 'Created new user account', '192.168.1.100', NOW(), NOW());

-- ====================================
-- END OF SCHEMA & SAMPLE DATA
-- ====================================
