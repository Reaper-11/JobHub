CREATE DATABASE IF NOT EXISTS JobHub
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE JobHub;

-- clean re-import
DROP TABLE IF EXISTS bookmarks;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS job_view_logs;
DROP TABLE IF EXISTS job_search_logs;
DROP TABLE IF EXISTS saved_jobs;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS job_skills;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS job_types;
DROP TABLE IF EXISTS job_categories;
DROP TABLE IF EXISTS admins;

-- job categories
CREATE TABLE job_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE
);

-- job types
CREATE TABLE job_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE
);

-- skills
CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- companies
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    website VARCHAR(200) NULL,
    location VARCHAR(200) NOT NULL,
    logo_path VARCHAR(255) NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    verification_company_name VARCHAR(150) NULL,
    verification_registration_number VARCHAR(100) NULL,
    verification_phone VARCHAR(30) NULL,
    verification_address VARCHAR(255) NULL,
    verification_document_path VARCHAR(255) NULL,
    verification_status ENUM('pending','approved','rejected') NULL DEFAULT NULL,
    verification_admin_remarks VARCHAR(255) NULL,
    verification_submitted_at DATETIME NULL,
    verification_verified_at DATETIME NULL,
    verification_verified_by_admin_id INT NULL,
    rejection_reason VARCHAR(255) NULL,
    operational_state ENUM('active','on_hold','suspended') NOT NULL DEFAULT 'active',
    restriction_reason VARCHAR(255) NULL,
    restricted_at DATETIME NULL,
    restricted_by_admin_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
);

-- users (job seekers only)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password VARCHAR(255) NOT NULL,
    preferred_category VARCHAR(120) NULL,
    preferred_location VARCHAR(200) NULL,
    preferred_job_type VARCHAR(60) NULL,
    experience_level VARCHAR(40) NULL,
    skills TEXT NULL,
    cv_path VARCHAR(255) NULL,
    profile_image VARCHAR(255) NULL,
    role ENUM('seeker','admin') DEFAULT 'seeker',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
);

-- jobs
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL,
    company VARCHAR(150) NULL,
    title VARCHAR(200) NOT NULL,
    location VARCHAR(200) NULL,
    type VARCHAR(60) NULL,
    category VARCHAR(120) NULL,
    salary VARCHAR(120) NULL,
    application_duration VARCHAR(60) NULL,
    experience_level VARCHAR(40) NULL,
    description TEXT NOT NULL,
    status ENUM('active','closed','expired','draft') NOT NULL DEFAULT 'active',
    is_approved TINYINT(1) NOT NULL DEFAULT 1,
    application_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);

-- job skills
CREATE TABLE job_skills (
    job_id INT NOT NULL,
    skill_id INT NOT NULL,
    PRIMARY KEY (job_id, skill_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- user skills (optional: when skills are stored relationally instead of CSV)
CREATE TABLE user_skills (
    user_id INT NOT NULL,
    skill_id INT NOT NULL,
    PRIMARY KEY (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- applications
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    cover_letter TEXT NULL,
    cv_path VARCHAR(255) NULL,
    status ENUM('pending','shortlisted','interview','rejected','approved') NOT NULL DEFAULT 'pending',
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);

-- notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('user','company') NOT NULL,
    recipient_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_recipient_read_created
    ON notifications (recipient_type, recipient_id, is_read, created_at);

-- bookmarks
CREATE TABLE bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);

-- job search logs
CREATE TABLE job_search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    keyword VARCHAR(255) NULL,
    category VARCHAR(120) NULL,
    location VARCHAR(200) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- job view logs
CREATE TABLE job_view_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);

-- saved jobs (optional)
CREATE TABLE saved_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);

CREATE INDEX idx_job_search_logs_user_created ON job_search_logs (user_id, created_at);
CREATE INDEX idx_job_view_logs_user_created ON job_view_logs (user_id, created_at);
CREATE INDEX idx_job_view_logs_user_job ON job_view_logs (user_id, job_id);
CREATE INDEX idx_jobs_category_location_type_created ON jobs (category, location, type, created_at);
CREATE INDEX idx_jobs_created_at ON jobs (created_at);

-- admins
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- seed job types
INSERT INTO job_types (name) VALUES
('Full-time'),
('Part-time'),
('Contract'),
('Internship'),
('Freelance');

-- seed categories
INSERT INTO job_categories (name, slug) VALUES
('Information Technology','it'),
('Marketing','marketing'),
('Finance','finance');

-- admin account (username: admin, password: admin123)
-- bcrypt hash for "admin123"
INSERT INTO admins (username, password)
VALUES (
  'admin',
  '$2y$10$KopjEUQYOxEl4fGbVnlOleJATXGI5JaxBZO/P0hDjsvODSZ5qoxTS'
);

ALTER TABLE companies
ADD CONSTRAINT fk_companies_verification_admin
FOREIGN KEY (verification_verified_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL;
