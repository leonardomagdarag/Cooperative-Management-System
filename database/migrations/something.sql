create database system_test;

CREATE TABLE addresses (
    address_id VARCHAR(16) PRIMARY KEY,
    country VARCHAR(100),
    region VARCHAR(100),
    province VARCHAR(100),
    city_municipality VARCHAR(100),
    barangay VARCHAR(100),
    street VARCHAR(255),
    zip_code VARCHAR(20)
);

CREATE TABLE contacts (
    contact_id VARCHAR(16) PRIMARY KEY,
    phone_num1 VARCHAR(20),
    phone_verified_at DATETIME,
    email VARCHAR(255),
    email_verified_at DATETIME
);

CREATE TABLE personal_data (
    personal_data_id VARCHAR(16) PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    mid_name VARCHAR(100),
    suffix VARCHAR(50),
    birth_date DATE,
    address_id VARCHAR(16),
    contact_id VARCHAR(16),
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id)
        ON UPDATE CASCADE,
    FOREIGN KEY (address_id) REFERENCES addresses(address_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

CREATE TABLE users (
    user_id VARCHAR(16) PRIMARY KEY,
    personal_data_id VARCHAR(16), --Foreign Key to personal_data table
    contact_id VARCHAR(16), --Foreign Key to contacts table for verified email or phone

    password VARCHAR(255),
    role enum('admin', 'user', 'guest', 'inactive', 'terminated') default 'inactive',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,


    FOREIGN KEY (personal_data_id) REFERENCES personal_data(personal_data_id)
        ON UPDATE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id)
        ON UPDATE CASCADE
);

CREATE TABLE accounts (
    account_id VARCHAR(16) PRIMARY KEY,
    user_id VARCHAR(16),
    account_type enum('savings', 'checking', 'loan') DEFAULT 'savings',
    balance DECIMAL(15,2),
    created_at DATETIME default CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
);

CREATE TABLE loans (
    loan_id VARCHAR(16) PRIMARY KEY,
    account_id VARCHAR(16),
    loan_type VARCHAR(50),
    loan_amount DECIMAL(15,2),
    interest_rate DECIMAL(5,2),
    payment_frequency enum('weekly', 'monthly', 'quarterly', 'annually') default 'monthly',
    loan_status enum('active', 'paid_off', 'defaulted', 'closed') default 'active',
    approved_at DATETIME,
    approved_by VARCHAR(16),
    created_at DATETIME default CURRENT_TIMESTAMP,
    due_date DATE,

    Foreign KEY (account_id) REFERENCES accounts(account_id)
        ON UPDATE CASCADE
);

CREATE TABLE deposits (
    deposit_id VARCHAR(16) PRIMARY KEY,
    account_id VARCHAR(16),
    deposit_amount DECIMAL(15,2),
    deposit_date DATETIME,
    recorded_by VARCHAR(16),
    created_at DATETIME default CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id)
        ON UPDATE CASCADE
);

CREATE TABLE withdrawals (
    withdrawal_id VARCHAR(16) PRIMARY KEY,
    account_id VARCHAR(16),
    withdrawal_amount DECIMAL(15,2),
    recorded_by VARCHAR(16),
    created_at DATETIME default CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id)
        ON UPDATE CASCADE
);

CREATE TABLE loan_payments (
    payment_id VARCHAR(16) PRIMARY KEY,
    loan_id VARCHAR(16),
    payment_amount DECIMAL(15,2),
    created_at DATETIME default CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (loan_id) REFERENCES loans(loan_id)
        ON UPDATE CASCADE
);

CREATE TABLE transactions (
    transaction_id VARCHAR(16) PRIMARY KEY ,
    account_id VARCHAR(16),
    processed_by VARCHAR(16),
    transaction_type enum('deposit', 'withdrawal', 'loan_payment', 'transfer'),
    amount DECIMAL(15,2),

    created_at DATETIME default CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(account_id)
        ON UPDATE CASCADE
);

CREATE TABLE logs (
    log_id VARCHAR(16) PRIMARY KEY not null,
    user_id VARCHAR(16),
    activity VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE
);
