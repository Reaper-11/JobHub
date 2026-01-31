-- Create the database
CREATE DATABASE IF NOT EXISTS JobHub;
USE JobHub;

-- Reference tables for standardization
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,  -- e.g., IT, Marketing, Finance
    description TEXT
);

CREATE TABLE industries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE  -- e.g., Software Development, Healthcare
);

CREATE TABLE job_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE  -- Full-time, Part-time, Contract, Internship, Remote
);

CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Users table: unified for job seekers (role-based)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,  -- hashed
    role ENUM('seeker', 'employer', 'admin') DEFAULT 'seeker',
    profile_image VARCHAR(255),
    location VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_email (email)
);

-- Seeker-specific profile details (1:1 with users where role='seeker')
CREATE TABLE seeker_profiles (
    user_id INT PRIMARY KEY,
    summary TEXT,  -- professional summary / bio
    current_salary DECIMAL(12,2),
    expected_salary DECIMAL(12,2),
    experience_years INT DEFAULT 0,
    resume_path VARCHAR(255),  -- renamed from cv_path for clarity
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Education history (one user can have multiple)
CREATE TABLE educations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    degree VARCHAR(100) NOT NULL,  -- e.g., Bachelor's in Computer Science
    institution VARCHAR(150) NOT NULL,
    field_of_study VARCHAR(100),
    start_date DATE,
    end_date DATE,
    grade DECIMAL(4,2),  -- CGPA or percentage
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Work experience (multiple per user)
CREATE TABLE experiences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_title VARCHAR(100) NOT NULL,
    company_name VARCHAR(150),
    location VARCHAR(150),
    start_date DATE NOT NULL,
    end_date DATE,
    is_current TINYINT(1) DEFAULT 0,
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seeker skills (many-to-many with proficiency)
CREATE TABLE seeker_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level TINYINT DEFAULT 5,  -- 1-10 scale
    UNIQUE KEY uniq_user_skill (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- Companies (employers)
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,  -- hashed
    website VARCHAR(200),
    location VARCHAR(150),
    industry_id INT,
    description TEXT,
    logo VARCHAR(255),
    is_approved TINYINT(1) DEFAULT 0,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (industry_id) REFERENCES industries(id)
);

-- Recruiters can be linked to companies (if multiple recruiters per company)
ALTER TABLE users
    ADD COLUMN company_id INT NULL AFTER role,
    ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- Jobs table (posted by company/recruiter)
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    posted_by INT NULL,  -- user_id of recruiter who posted (optional)
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(150) NOT NULL,
    job_type_id INT NOT NULL,
    category_id INT,
    min_salary DECIMAL(12,2),
    max_salary DECIMAL(12,2),
    salary_type ENUM('monthly', 'annual', 'hourly') DEFAULT 'monthly',
    experience_required INT DEFAULT 0,  -- min years
    application_deadline DATE,
    status ENUM('active', 'closed', 'draft', 'expired') DEFAULT 'active',
    views INT DEFAULT 0,
    application_count INT DEFAULT 0,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (job_type_id) REFERENCES job_types(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Job required skills (many-to-many)
CREATE TABLE job_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    skill_id INT NOT NULL,
    is_required TINYINT(1) DEFAULT 1,  -- required vs nice-to-have
    min_proficiency TINYINT DEFAULT 5,
    UNIQUE KEY uniq_job_skill (job_id, skill_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- Applications
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    cover_letter TEXT,
    resume_path VARCHAR(255),  -- if custom resume used for this app
    status ENUM('pending', 'reviewed', 'shortlisted', 'interview', 'offered', 'rejected', 'withdrawn') DEFAULT 'pending',
    rejection_reason TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_application (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

-- Bookmarks (saved jobs)
CREATE TABLE bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_bookmark (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

-- Admins (keep simple, or merge into users with role='admin')
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Optional: User deletion logs
CREATE TABLE user_deletion_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    reason TEXT NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);

-- Optional: Notifications (for new jobs, applications, messages, etc.)
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('application_update', 'new_job', 'message', 'bookmark_expiry') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default data (example)
INSERT INTO admins (username, password) VALUES 
('admin', '$2y$12$MmiMPnDRxytmC8IvqKc/0Ozk6KgKAm2oWi/Zgaf.T61IhNfniAGwe');

-- Add some default categories, types, etc.
INSERT INTO categories (name) VALUES ('IT/Software'), ('Marketing'), ('Finance'), ('Healthcare');
INSERT INTO job_types (name) VALUES ('Full-time'), ('Part-time'), ('Remote'), ('Contract');
