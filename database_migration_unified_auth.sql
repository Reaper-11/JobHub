USE JobHub;

/*
Run this on an existing JobHub database only once.

If every legacy password is already hashed, this SQL can do the structural upgrade
and the basic account-row migration.

If any legacy table still stores plain-text passwords, use `php migrate_unified_auth.php`
instead of this SQL so those passwords are re-hashed with `password_hash()`.
*/

CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('jobseeker', 'company', 'admin') NOT NULL,
    status ENUM('active', 'inactive', 'blocked', 'pending') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS account_id INT NULL AFTER id;

ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS account_id INT NULL AFTER id;

ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS account_id INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER username,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER password,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER created_at;

ALTER TABLE users MODIFY email VARCHAR(150) NOT NULL;
ALTER TABLE companies MODIFY email VARCHAR(150) NOT NULL;

INSERT INTO accounts (full_name, email, password, role, status, created_at, updated_at)
SELECT
    u.name,
    LOWER(u.email),
    u.password,
    'jobseeker',
    CASE
        WHEN LOWER(COALESCE(u.account_status, 'active')) = 'blocked' THEN 'blocked'
        WHEN LOWER(COALESCE(u.account_status, 'active')) = 'removed' THEN 'inactive'
        WHEN COALESCE(u.is_active, 1) <> 1 THEN 'blocked'
        ELSE 'active'
    END,
    COALESCE(u.created_at, NOW()),
    COALESCE(u.updated_at, NOW())
FROM users u
LEFT JOIN accounts a ON a.email = LOWER(u.email)
WHERE a.id IS NULL
  AND COALESCE(u.email, '') <> ''
  AND COALESCE(u.password, '') <> '';

INSERT INTO accounts (full_name, email, password, role, status, created_at, updated_at)
SELECT
    c.name,
    LOWER(c.email),
    c.password,
    'company',
    CASE
        WHEN COALESCE(c.is_active, 1) <> 1 THEN 'inactive'
        WHEN COALESCE(c.is_approved, 0) = -1 THEN 'inactive'
        ELSE 'active'
    END,
    COALESCE(c.created_at, NOW()),
    COALESCE(c.updated_at, NOW())
FROM companies c
LEFT JOIN accounts a ON a.email = LOWER(c.email)
WHERE a.id IS NULL
  AND COALESCE(c.email, '') <> ''
  AND COALESCE(c.password, '') <> '';

INSERT INTO accounts (full_name, email, password, role, status, created_at, updated_at)
SELECT
    COALESCE(NULLIF(ad.username, ''), 'Administrator'),
    LOWER(COALESCE(NULLIF(ad.email, ''), CONCAT('admin+', ad.id, '@jobhub.local'))),
    ad.password,
    'admin',
    'active',
    COALESCE(ad.created_at, NOW()),
    COALESCE(ad.updated_at, NOW())
FROM admins ad
LEFT JOIN accounts a ON a.email = LOWER(COALESCE(NULLIF(ad.email, ''), CONCAT('admin+', ad.id, '@jobhub.local')))
WHERE a.id IS NULL
  AND COALESCE(ad.password, '') <> '';

UPDATE users u
JOIN accounts a
  ON a.email = LOWER(u.email) AND a.role = 'jobseeker'
SET u.account_id = a.id
WHERE u.account_id IS NULL;

UPDATE companies c
JOIN accounts a
  ON a.email = LOWER(c.email) AND a.role = 'company'
SET c.account_id = a.id
WHERE c.account_id IS NULL;

UPDATE admins ad
JOIN accounts a
  ON a.email = LOWER(COALESCE(NULLIF(ad.email, ''), CONCAT('admin+', ad.id, '@jobhub.local'))) AND a.role = 'admin'
SET ad.account_id = a.id,
    ad.email = COALESCE(NULLIF(ad.email, ''), CONCAT('admin+', ad.id, '@jobhub.local'))
WHERE ad.account_id IS NULL;

ALTER TABLE users
    ADD UNIQUE KEY uq_users_account_id (account_id);

ALTER TABLE companies
    ADD UNIQUE KEY uq_companies_account_id (account_id);

ALTER TABLE admins
    ADD UNIQUE KEY uq_admins_account_id (account_id);

ALTER TABLE users
    ADD CONSTRAINT fk_users_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE;

ALTER TABLE companies
    ADD CONSTRAINT fk_companies_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE;

ALTER TABLE admins
    ADD CONSTRAINT fk_admins_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE;

/*
Optional cleanup after you confirm all code uses `accounts`:

ALTER TABLE notifications MODIFY recipient_type ENUM('user', 'company', 'admin') NOT NULL;
ALTER TABLE admins MODIFY email VARCHAR(150) NOT NULL;
UPDATE admins SET email = CONCAT('admin+', id, '@jobhub.local') WHERE email IS NULL OR email = '';
*/
