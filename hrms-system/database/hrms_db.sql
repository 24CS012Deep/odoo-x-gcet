CREATE DATABASE IF NOT EXISTS hrms_db;
USE hrms_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('employee', 'admin', 'hr') DEFAULT 'employee',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    address TEXT,
    profile_picture VARCHAR(255) DEFAULT 'default.png',
    job_title VARCHAR(100),
    department VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    check_in DATETIME,
    check_out DATETIME,
    status ENUM('present', 'absent', 'half-day', 'leave') DEFAULT 'absent',
    hours_worked DECIMAL(5,2),
    date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (user_id, date)
);

-- Leave requests table
CREATE TABLE leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    leave_type ENUM('paid', 'sick', 'unpaid', 'annual') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    remarks TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    admin_comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Performance summary table
CREATE TABLE performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    month_year VARCHAR(10),
    attendance_score INT DEFAULT 0,
    productivity_score INT DEFAULT 0,
    teamwork_score INT DEFAULT 0,
    leadership_score INT DEFAULT 0,
    overall_score INT DEFAULT 0,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample admin user
INSERT INTO users (employee_id, email, password, role, first_name, last_name, job_title, salary) 
VALUES ('ADMIN001', 'admin@hrms.com', '$2y$10$YourHashedPasswordHere', 'admin', 'System', 'Administrator', 'HR Manager', 75000.00);

-- Insert sample employee
INSERT INTO users (employee_id, email, password, role, first_name, last_name, job_title, salary) 
VALUES ('EMP001', 'employee@hrms.com', '$2y$10$YourHashedPasswordHere', 'employee', 'John', 'Doe', 'Software Developer', 50000.00);