# JobHub

JobHub is a role-based job portal built with plain PHP and MySQL for local XAMPP deployment. It supports three main actors:

- Job seekers who browse jobs, apply, upload CVs, and receive recommendations
- Companies that register, complete verification, post jobs, and manage applicants
- Admins who approve companies and jobs, review verification requests, and monitor platform activity

## Core Features

### Job Seeker

- Register and log in with unified account-based authentication
- Edit profile, preferred category, experience level, skills, and CV
- Browse jobs with keyword, category, location, salary, job type, and experience filters
- View job details and apply with a cover letter
- Edit or cancel applications
- Bookmark jobs and review notifications
- Get personalized recommendations from `includes/recommendation.php`

### Company

- Register a company account and sign in from the same login page
- Submit company verification details and upload proof documents
- Wait for admin approval and verification before posting new jobs
- Create, edit, close, reopen, and manage job posts
- Review applicants, open CVs, and update application status with response messages
- Receive company-side notifications for verification decisions

### Admin

- Sign in through the unified login system
- Approve or reject company registrations
- Approve or reject job posts
- Review company verification requests and send remarks
- View users, applications, jobs, companies, and activity logs
- Manage support inbox messages and send replies

## Tech Stack

- PHP 8+ with `mysqli`
- MySQL or MariaDB
- Bootstrap 5 for most shared pages
- Tailwind CSS and Font Awesome on the landing page
- Session-based authentication with role guards
- Optional PHPMailer-based SMTP replies for the support module

## Project Structure

```text
JobHub/
|-- index.php                      # Landing page and job search
|-- login.php                      # Unified login for all roles
|-- register.php                   # Job seeker registration
|-- user-account.php               # Job seeker profile and CV upload
|-- my-applications.php            # Job seeker application history
|-- my-bookmarks.php               # Saved jobs
|-- notifications.php              # Job seeker notifications
|-- contact-support.php            # Support request form
|-- company/                       # Employer dashboard and job management
|-- admin/                         # Admin dashboard and moderation pages
|-- includes/                      # Auth, security, notifications, helpers
|-- uploads/                       # CVs and company verification files
|-- database.sql                   # Fresh database setup
|-- database_migration_unified_auth.sql
|-- migrate_unified_auth.php
```

## Local Setup

### 1. Place the project in XAMPP

Copy the project into:

```text
C:\xampp\htdocs\JobHub
```

### 2. Start services

Start Apache and MySQL from the XAMPP control panel.

### 3. Create the database

Import [`database.sql`](database.sql) into MySQL.

Example with phpMyAdmin:

1. Open `http://localhost/phpmyadmin`
2. Create or select a database named `JobHub`
3. Import `database.sql`

### 4. Check database connection

Edit [`db.php`](db.php) if your MySQL credentials are different:

```php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "JobHub";
```

If your project is not served from `http://localhost/JobHub/`, also update:

```php
define('JOBHUB_APP_URL', 'http://localhost/JobHub/');
```

### 5. Make uploads writable

Make sure these folders exist and are writable by PHP:

- `uploads/cv`
- `uploads/company_verification`

### 6. Open the project

Visit:

```text
http://localhost/JobHub/
```

## Default Admin Login

After importing [`database.sql`](database.sql), the seeded admin account is:

- Email: `admin@jobhub.local`
- Password: `admin123`

Use the normal login page:

```text
http://localhost/JobHub/login.php
```

## Authentication Model

The project uses unified authentication through the `accounts` table. Each account maps to one role:

- `jobseeker`
- `company`
- `admin`

Role-specific profile data is then stored in:

- `users`
- `companies`
- `admins`

The login page automatically redirects each authenticated user to the correct dashboard.

## Database Notes

### Fresh install

Use [`database.sql`](database.sql).

### Existing older database

If you already have an older JobHub database and want to migrate it to unified auth:

- Use [`database_migration_unified_auth.sql`](database_migration_unified_auth.sql) if old passwords are already hashed
- Use [`migrate_unified_auth.php`](migrate_unified_auth.php) if old passwords may still be plain text

Example:

```bash
php migrate_unified_auth.php
```

## Recommendation Engine

The recommendation system is implemented in [`includes/recommendation.php`](includes/recommendation.php).

It scores jobs using:

- Preferred category
- Experience level
- User skills
- Search and view behavior
- Application history
- Job recency

If there is not enough profile or behavior data, it falls back to recent or popular active jobs. You can tune the weights in the `$RECOMMENDATION_CONFIG` array.

## Important Operational Notes

- Companies cannot post jobs until the account is approved, verified, and operationally active
- CV files are stored under `uploads/cv`
- Company verification documents are stored under `uploads/company_verification`
- Job expiration is calculated from duration and optional deadline fields
- Most forms use CSRF validation and password hashing is handled with PHP password APIs

## Support Module Caveat

The support pages are present in the project:

- [`contact-support.php`](contact-support.php)
- [`admin/support-messages.php`](admin/support-messages.php)

They expect a `support_messages` table, but that table is not created in [`database.sql`](database.sql) in the current repo state. If you want to use the support module, add that table before testing those pages.

Optional email replies are configured in [`includes/support_mail_config.php`](includes/support_mail_config.php). PHPMailer is only needed if you enable SMTP replies.

## Main Routes

- `/index.php` - public landing page and job search
- `/login.php` - unified login
- `/register.php` - job seeker registration
- `/company/company-register.php` - company registration
- `/user-account.php` - job seeker profile
- `/company/company-dashboard.php` - company dashboard
- `/admin/admin-dashboard.php` - admin dashboard

## Project Status Report

### Completed Features

- Unified authentication through the `accounts` table with role-based redirects for job seekers, companies, and admins
- Job seeker workflow covering registration, login, profile editing, CV upload, bookmarking, notifications, and applications
- Company workflow covering registration, verification submission, job posting, job management, applicant review, and status updates
- Admin workflow covering company approval, job approval, verification review, support inbox management, and activity monitoring
- Recommendation engine that uses profile fields and user behavior to rank jobs
- File upload support for CVs and company verification documents

### Pending Issues

- The support module depends on a `support_messages` table that is not included in [`database.sql`](database.sql)
- Support reply email requires manual SMTP setup and PHPMailer availability before it can be used in practice
- Database credentials and `JOBHUB_APP_URL` are still configured directly in [`db.php`](db.php), so deployment requires manual file edits
- There is no proper automated test suite in the repository yet; only a temporary script, [`tmp_http_profile_test.php`](tmp_http_profile_test.php), is present

### Exact Next Priorities

1. Add the `support_messages` schema to [`database.sql`](database.sql) and provide a migration path for existing installs.
2. Move database, app URL, and mail settings into a single environment or config layer instead of hardcoding them in source files.
3. Add a basic smoke-test path for login, job posting, applying, CV upload, and verification review so regressions are easier to catch.
