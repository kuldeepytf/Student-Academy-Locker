-- ========================================
-- SAI COLLEGE - Education Management System (Merged Full SQL)
-- Combined: education_system.sql + update_database.sql + fix_test_results.sql + create_users_only.sql
-- NOTE: This single file contains schema, optional updates, diagnostics and demo user inserts.
-- Review sections marked OPTIONAL before running on production.
-- ========================================

-- ---------- BASE DATABASE & SCHEMA ----------

-- Drop existing database if exists
DROP DATABASE IF EXISTS education_system;
CREATE DATABASE education_system;
USE education_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Student', 'Teacher', 'Admin') DEFAULT 'Student',
    course VARCHAR(50) DEFAULT NULL,
    rollno VARCHAR(50) DEFAULT NULL,
    age INT DEFAULT NULL,
    course_year VARCHAR(10) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    phone_changes INT DEFAULT 0,
    address_changes INT DEFAULT 0,
    subject VARCHAR(100) DEFAULT NULL,
    qualification VARCHAR(100) DEFAULT NULL,
    experience INT DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    security_question VARCHAR(255) DEFAULT NULL,
    security_answer VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    remember_token VARCHAR(100) DEFAULT NULL,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE users ADD UNIQUE KEY unique_roll_course (course, rollno);

-- Notices table
CREATE TABLE notices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    course VARCHAR(50) DEFAULT 'All',
    posted_by INT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Notes table
CREATE TABLE notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    course VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Assignments table
CREATE TABLE assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    subject VARCHAR(100) NOT NULL,
    course VARCHAR(50) NOT NULL,
    due_date DATETIME NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Assignment submissions table
CREATE TABLE assignment_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_late TINYINT(1) DEFAULT 0,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (assignment_id, student_id)
);

-- Tests table
CREATE TABLE tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    course VARCHAR(50) NOT NULL,
    time_limit INT NOT NULL,
    total_marks INT NOT NULL,
    pass_marks INT NOT NULL,
    allow_multiple_attempts TINYINT(1) DEFAULT 0,
    status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
    scheduled_date DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Test questions table
CREATE TABLE test_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    marks INT DEFAULT 1,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

-- Test results table
CREATE TABLE test_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_id INT NOT NULL,
    student_id INT NOT NULL,
    marks_obtained INT NOT NULL,
    total_marks INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    status ENUM('Pass', 'Fail') NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Digital locker table
CREATE TABLE digital_locker (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    subject VARCHAR(100) DEFAULT 'General',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity log table
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Resources table
CREATE TABLE resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    url VARCHAR(500) NOT NULL,
    type ENUM('Video', 'Article', 'Website', 'Document') DEFAULT 'Website',
    subject VARCHAR(100) NOT NULL,
    course VARCHAR(50) DEFAULT 'All',
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Events/Calendar table
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type ENUM('exam', 'holiday', 'meeting', 'other') DEFAULT 'other',
    event_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    course VARCHAR(50) DEFAULT 'All',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Fees table (start, can be extended)
CREATE TABLE fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0
);

-- ---------- OPTIONAL UPDATES (from update_database.sql) ----------
-- These statements may be run after the main schema is in place.
-- Depending on your MySQL version, `ADD COLUMN IF NOT EXISTS` may or may not be supported.
-- If your server errors on these, run them manually after adjusting.

-- Add missing columns to users table if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_changes INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS address_changes INT DEFAULT 0;

-- Create fee_payments table if it doesn't exist
CREATE TABLE IF NOT EXISTS fee_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fee_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    transaction_id VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------- DIAGNOSTIC & FIX QUERIES (from fix_test_results.sql) ----------
-- Useful queries and checks to verify test_results and related data.
-- These are safe SELECTs and commented helpful fixes.

-- 1. Check if test_results table exists
SELECT 'Checking test_results table...' as Status;

-- 2. Create test_results table if it doesn't exist (safe guard)
CREATE TABLE IF NOT EXISTS test_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    test_id INT NOT NULL,
    student_id INT NOT NULL,
    marks_obtained INT NOT NULL,
    total_marks INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    status ENUM('Pass', 'Fail') NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Show current table structure
DESCRIBE test_results;

-- Check all test submissions (joined view)
SELECT 
    tr.id,
    t.title as test_name,
    u.name as student_name,
    tr.marks_obtained,
    tr.total_marks,
    tr.percentage,
    tr.status,
    tr.submitted_at
FROM test_results tr
JOIN tests t ON tr.test_id = t.id
JOIN users u ON tr.student_id = u.id
ORDER BY tr.submitted_at DESC;

-- Check for orphaned records (where test or student doesn't exist)
SELECT tr.* 
FROM test_results tr
LEFT JOIN tests t ON tr.test_id = t.id
WHERE t.id IS NULL;

SELECT tr.* 
FROM test_results tr
LEFT JOIN users u ON tr.student_id = u.id
WHERE u.id IS NULL;

-- Statistics
SELECT 
    status,
    COUNT(*) as count,
    AVG(percentage) as avg_percentage
FROM test_results
GROUP BY status;

-- ---------- DEMO USERS (from create_users_only.sql) ----------
-- Run this AFTER importing the main schema if you need demo accounts.
-- Delete existing demo users (safe to run)
DELETE FROM users WHERE email IN ('admin@school.com', 'teacher@school.com', 'sarah@school.com', 'alice@school.com', 'bob@school.com');

-- Create fresh users with password: password123 (hash provided)
INSERT INTO users (name, email, password, role, course, rollno, age, course_year, status, phone_changes, address_changes) VALUES
('Admin User', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', NULL, NULL, NULL, NULL, 'approved', 0, 0),
('John Teacher', 'teacher@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teacher', NULL, NULL, NULL, NULL, 'approved', 0, 0),
('Sarah Teacher', 'sarah@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Teacher', NULL, NULL, NULL, NULL, 'approved', 0, 0),
('Alice Student', 'alice@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'BCA', 'BCA2024001', 20, '1', 'approved', 0, 0),
('Bob Student', 'bob@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'B.Com', 'BCOM2024001', 21, '1', 'approved', 0, 0);

-- Final verification queries
SELECT id, name, email, role, status FROM users LIMIT 10;
SELECT id, title, course, status FROM tests LIMIT 10;
SELECT COUNT(*) as total_results FROM test_results;

-- End of merged SQL
