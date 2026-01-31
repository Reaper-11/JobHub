CREATE DATABASE IF NOT EXISTS JobHub
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE JobHub;

-- clean re-import
DROP TABLE IF EXISTS bookmarks;
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
    is_active TINYINT(1) DEFAULT 1
);

-- users (job seekers only)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('seeker','admin') DEFAULT 'seeker',
    is_active TINYINT(1) DEFAULT 1
);

-- jobs
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    type_id INT NOT NULL,
    description TEXT NOT NULL,
    FOREIGN KEY (category_id) REFERENCES job_categories(id),
    FOREIGN KEY (type_id) REFERENCES job_types(id)
);

-- job skills
CREATE TABLE job_skills (
    job_id INT NOT NULL,
    skill_id INT NOT NULL,
    PRIMARY KEY (job_id, skill_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
);

-- applications
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (job_id) REFERENCES jobs(id)
);

-- bookmarks
CREATE TABLE bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    UNIQUE (user_id, job_id)
);

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
