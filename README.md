# SSMS — Secondary School Management System

A self-hosted school management system for Ugandan secondary schools, built with plain PHP 8 and MySQL/MariaDB. It covers the full school day: admissions, students, academics, examinations and UNEB, report cards, fees and accounting, HR and payroll, attendance, boarding, library, transport, inventory, communication, compliance, and a parent/student portal.

The application is a classic server-rendered MVC app. There is no JavaScript framework and no build step. Every request enters through a small root `index.php`, which loads the real front controller and route table in `backend/index.php`; all server-side code lives under `backend/`, and every module is permission-gated.

---

## Contents

- [Feature overview](#feature-overview)
- [Roles](#roles)
- [Requirements](#requirements)
- [Installation](#installation)
- [First login](#first-login)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Database](#database)
- [Report cards and PDFs](#report-cards-and-pdfs)
- [Backups](#backups)
- [Scheduled tasks](#scheduled-tasks)
- [REST API](#rest-api)
- [Security model](#security-model)
- [Project structure](#project-structure)
- [Troubleshooting](#troubleshooting)
- [Known limitations](#known-limitations)
- [License](#license)

---

## Feature overview

### Administration
- School profile (name, motto, logo, contact details, currency, date format, timezone).
- Academic years and terms, with activation.
- Classes and streams, departments, houses.
- Users, roles and granular permissions, forced password change, password reset.
- Settings registry per school, audit log viewer.
- CSV import/export and ID cards where relevant.

### Students
- Student register with profiles, photos, guardian links, houses and current placement.
- Admissions: applications, decisions, admission letters.
- Parents and guardians, linked to students and portal accounts.
- Subject registration (including A-Level combinations) and bulk registration.
- Class/stream allocation (single and bulk), promotion, transfers with certificates.
- Discipline incidents, document uploads and verification, alumni register, student ID cards.

### Academics
- Curricula, subjects, A-Level combinations and subject components.
- Subject and teacher allocations.
- Schemes of work, timetables and timetable slots, homework and submissions.
- School calendar events, syllabi, topic coverage tracking, co-curricular-style activities.

### Examinations and report cards
- Exams per term, exam timetables, mark entry (CA and exam), mark verification/approval.
- Result processing into `subject_results` and `result_summaries`, result publishing.
- Result slips, transcripts, certificate eligibility checks.
- Report cards: per-student view and PDF, editable teacher/head comments, class and stream positions, class CSV export, and batch printing for a whole class or stream (one page per student, with page breaks for printing).

### UNEB
- Candidate registration by level (UCE/UACE), index numbers, subjects.
- Continuous assessment (CA) submissions, CA subject scores, submission workflow (draft → submitted → confirmed).
- National results entry and candidate readiness reporting.
- Module 22 realigns the older UNEB schema and removes the legacy `cycle` column layout on upgrade.

### Finance and accounting
- Fee structures and structure items, bulk invoicing.
- Invoices, payments, receipts (print, reprint, void, email, SMS), payment verification.
- Bank accounts, bank transactions and reconciliation.
- Fee reminders, fee clearance workflow and clearance certificates.
- Chart of accounts, budgets and budget lines, expenses, petty cash funds and vouchers.
- Online payment gateways and gateway transaction logging.
- CSV export of fee data.

### Human resources and payroll
- Staff records, contracts, appointment letters, staff ID cards.
- Leave types, leave applications and approvals.
- Payroll periods and payroll items.
- Recruitment (vacancies, applications, decisions) and staff appraisals.

### Attendance
- Daily student attendance by class/stream.
- Staff attendance.
- Summary and rate views used by reports.

### Health and welfare
- Medical records, sick bay visits, medical referral forms.
- Access is restricted by the `health` permission.

### Operations
- **Library:** books, categories, issues and returns, fines, overdue notices.
- **Boarding:** dormitories, bed allocation, boarding status, allocation letters.
- **Transport:** routes, vehicles, drivers, route sheets.
- **Inventory:** item categories, stock items, requisitions and approvals.
- **Canteen:** products, student wallets, deposits and wallet transactions.
- **Gate:** visitor check-in/check-out, gate passes.

### Communication and co-curricular
- Announcements and notification templates, with publish approval.
- Clubs and activities.

### Compliance
- EMIS returns (create, validate, submit).
- Government grants, receipts and expenditure tracking.

### Reports and analytics
- 34 database views power dashboards and reports (fee balances, debtors, attendance rates, grade distribution, ranked results, payroll, expiry alerts, and more).
- CSV and PDF export.

### Portal
- Parent and student self-service dashboard, confined to the account holder's own records.
- Parents see each linked child's fee balance and overdue amount, attendance rate, and latest published exam average with class rank. Students see the same for themselves.
- Published notices and data-consent management.
- Parent and student accounts cannot open staff modules; any staff route redirects them back to the portal.

### Support and security
- Support tickets.
- Database backups (create, download, delete).
- Two-factor authentication (TOTP authenticator app).
- Database view installer (recreates missing report views).
- Scheduled tasks (define, toggle, run on demand).

---

## Roles

The seed data creates these 16 roles:

| Role | Slug | Purpose |
|---|---|---|
| Administrator | `administrator` | Full access to every module and setting |
| Head Teacher | `head_teacher` | Approves key decisions across modules |
| Deputy Head Teacher | `deputy_head_teacher` | Administration and discipline support |
| Director of Studies | `director_of_studies` | Academic planning, timetables, exams |
| Teacher | `teacher` | Attendance, marks, continuous assessment |
| Bursar | `bursar` | Fees, payments, expenditure approvals |
| Accountant | `accountant` | Transactions, reconciliation, reporting |
| Registrar | `registrar` | Admission, student records, promotion, transfers |
| Librarian | `librarian` | Library circulation, stock, fines |
| Nurse | `nurse` | Health records and sick bay (restricted data) |
| Boarding Warden | `warden` | Dormitories, beds, roll calls, exeats |
| Gate Officer | `gate_officer` | Visitors, gate passes, vehicle log |
| HR Officer | `hr_officer` | Staff records, contracts, leave, payroll prep |
| Storekeeper | `storekeeper` | Stock, requisitions, purchase orders, assets |
| Parent | `parent` | Portal access to their children's fees, results, attendance, notices and consents |
| Student | `student` | Portal access to their own fees, results, attendance, notices and consents |

Permissions are per module (`administration`, `students`, `academics`, `examinations`, `uneb`, `finance`, `online_payments`, `hr`, `attendance`, `health`, `library`, `boarding`, `transport`, `inventory`, `communication`, `cocurricular`, `gate`, `canteen`, `compliance`, `reports`, `security`, `portal`) and per action (`view`, `add`, `edit`, `approve`, `print`, `delete`). They are editable on the **Roles & permissions** screen.

---

## Requirements

- PHP 8.1 or newer (tested on PHP 8.5 with XAMPP) with `pdo_mysql`, `mbstring`, `openssl`, `curl`, and `fileinfo`.
- MySQL 8 or MariaDB 10.4+.
- Apache with `mod_rewrite` (XAMPP default) or another web server that serves `index.php`.
- Write access for the web server user to `uploads/` and `backups/`.
- Optional: `mysqldump` on the server PATH for backups.
- Optional: Composer, to install Dompdf for true PDF output.

---

## Installation

### 1. Place the project

Copy or clone the project into your web root. With XAMPP the conventional location is:

```
C:\xampp\htdocs\school mgt system
```

### 2. Configure the environment

```bash
cp .env.example .env
```

Edit `.env` and set at least:

```dotenv
DB_HOST=127.0.0.1
DB_NAME=ssms
DB_USER=root
DB_PASS=
APP_URL=http://localhost/school%20mgt%20system
APP_TIMEZONE=Africa/Kampala
```

Never commit `.env`. The repository only ships `.env.example` with placeholder values.

### 3. Create the database

Import the single installer. It creates the database, all tables, seed data and views, in that order:

```bash
mysql -u root -p < database/SSMS_full_install.sql
```

Or import `database/SSMS_full_install.sql` through phpMyAdmin.

The installer is idempotent for tables (`CREATE TABLE IF NOT EXISTS`) and ends with the UNEB schema alignment block.

If you prefer module-by-module installation, create the database first and run the files in numeric order:

```
00_admin_module.sql          09_boarding_module.sql      18_portal_security_module.sql
01_hr_module.sql             10_transport_module.sql     19_reports_views.sql
02_student_module.sql        11_inventory_module.sql     20_seed_data.sql
03_academic_module.sql       12_communication_module.sql 21_uneb_module.sql
04_examination_module.sql    13_health_module.sql        22_uneb_schema_alignment.sql
05_finance_module.sql        14_cocurricular_module.sql  23_scheduler_module.sql
06_online_payment_module.sql 15_gate_module.sql
07_attendance_module.sql     16_canteen_module.sql
08_library_module.sql        17_compliance_module.sql
```

Each module file begins with `USE ssms;`, so the database must already exist.

### 4. Optional: install Dompdf

```bash
cd backend
composer install
```

`composer.json` lives inside `backend/`, so dependencies install into `backend/vendor/`. A `vendor/` folder at the project root from older installs is still detected. Without Dompdf, PDF routes still work but serve a printable HTML page instead of a binary PDF (see [Report cards and PDFs](#report-cards-and-pdfs)).

### 5. Open the application

```
http://localhost/school%20mgt%20system/index.php
```

If you see the login page before setting an administrator password, open:

```
http://localhost/school%20mgt%20system/index.php?r=install
```

---

## First login

The seed data creates one school and one staff account:

| Field | Value |
|---|---|
| School | Demo Secondary School |
| Username | `admin` |
| Email | `admin@demo.sc.ug` |
| Password | none — set it on the install page |

Steps:

1. Open `?r=install`, select `admin`, and set a password (minimum length is enforced).
2. Sign in at `?r=login`. The account is flagged to change its password on first login, so you will be taken to the change-password screen.
3. Complete the school profile, then review academic years and terms, classes and streams, departments, houses, grading scales and fee structures.
4. Create user accounts for staff and assign roles. Parent and student portal accounts must be linked to a guardian or student record.
5. Confirm the grading scales match your assessment policy before processing results.

---

## Configuration

Configuration is read from `.env` by `backend/config/config.php`. If a key is missing, the defaults below apply.

| Key | Default | Purpose |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_NAME` | `ssms` | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | empty | Database password |
| `APP_NAME` | `SSMS` | Fallback application name |
| `APP_TAGLINE` | `Secondary School Management System` | Fallback tagline |
| `APP_TIMEZONE` | `Africa/Kampala` | PHP timezone |
| `APP_URL` | empty | Absolute application URL |
| `APP_ENV` | `production` | `local` shows password-reset links when mail fails; `production` never does |
| `SESSION_SECURE` | auto (HTTPS on) | Force secure session cookies |
| `SESSION_HTTPONLY` | `1` | HttpOnly session cookies |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_ENCRYPTION` | empty / `587` / `tls` | SMTP mail delivery |
| `SMTP_FROM_NAME`, `SMTP_FROM_ADDRESS` | `SSMS` / `no-reply@localhost` | Sender identity |
| `SMS_PROVIDER`, `SMS_API_KEY`, `SMS_API_SECRET`, `SMS_SENDER_ID`, `SMS_GATEWAY_URL`, `SMS_DEFAULT_LANGUAGE` | empty / `SSMS` / `en` | SMS gateway |

Mail and SMS are optional. When they are not configured, the application falls back to `mail()` (if available) and otherwise reports that delivery was not possible. Password-reset links are only displayed on screen when `APP_ENV=local`.

Runtime settings such as the maximum failed login count and password length live in the `settings` table and are editable under **Settings**.

---

## Architecture

- **Front controller** — every request enters through the root `index.php`, a one-line bridge to `backend/index.php`, which holds the route table. Routes look like `index.php?r=students/profile&student_id=4`. Apache blocks direct access to `backend/`, so the public entry point stays at the site root.
- **Router** — `backend/core/Router.php` maps `GET`/`POST` routes to controller actions and attaches metadata such as `auth => true` and `perm => [module, action]`.
- **Controllers** — extend `Controller`, which provides `render()`, `allowed()`, `respond()` (JSON for AJAX, flash + redirect otherwise) and access to the authenticated user.
- **Models** — static classes extending `Model`, with small query helpers (`query`, `one`, `scalar`, `execute`) over PDO.
- **Views** — plain PHP templates in `frontend/views`, rendered inside `frontend/layout`.
- **Security helpers** — `Auth` (session user, permissions, 2FA), `Csrf`, `Validator`, `helpers.php` (`e()`, `url()`, `asset()`, `redirect()`, `audit()`, and so on).
- **Services** — `PdfService`, `MailService`, `SmsService`, `ImportExportService`, `CronScheduler`.
- **Multi-tenancy** — every business table carries `school_id`; queries scope to the signed-in user's school. The shipped UI assumes one school per installation.

POST requests are CSRF-checked by the router and permission-checked before the controller action runs. Sensitive actions write to `audit_logs`.

---

## Database

The schema ships as 24 SQL files in `database/` and totals **221 tables and 34 views**. The full installer contains every statement from every module, ordered as:

1. database creation and session settings,
2. all `CREATE TABLE` statements,
3. deferred `ALTER TABLE` statements,
4. seed data (school, roles, permissions, admin user, academic year and terms, classes, streams, departments, houses, curricula, subjects, combinations, grading scales, settings, chart of accounts, and more),
5. views,
6. the UNEB schema alignment block from module 22.

| File | Covers |
|---|---|
| `00_admin_module.sql` | Schools, users, roles, permissions, settings, academic years, terms, classes, streams, departments, houses, audit logs |
| `01_hr_module.sql` | Staff, contracts, leave, payroll, recruitment, appraisals |
| `02_student_module.sql` | Students, guardians, admissions, discipline, documents, alumni, transfers, promotion |
| `03_academic_module.sql` | Curricula, subjects, combinations, allocations, timetables, schemes, homework, syllabus, events |
| `04_examination_module.sql` | Exams, schedules, marks, grading scales, result summaries, report cards, transcripts |
| `05_finance_module.sql` | Fee structures, invoices, payments, receipts, banks, budgets, expenses, petty cash, clearances, chart of accounts |
| `06_online_payment_module.sql` | Payment gateways and gateway transactions |
| `07_attendance_module.sql` | Student and staff attendance |
| `08_library_module.sql` | Books, categories, borrowings, fines |
| `09_boarding_module.sql` | Dormitories, beds, allocations, exeats |
| `10_transport_module.sql` | Routes, vehicles, drivers, route assignments |
| `11_inventory_module.sql` | Item categories, stock, requisitions, purchase orders, assets |
| `12_communication_module.sql` | Announcements, notification templates, message logs |
| `13_health_module.sql` | Medical records, sick bay visits, referrals |
| `14_cocurricular_module.sql` | Clubs and activities |
| `15_gate_module.sql` | Visitors, gate passes, vehicle log |
| `16_canteen_module.sql` | Products, wallets, wallet transactions |
| `17_compliance_module.sql` | EMIS returns, grants, grant receipts and expenditure |
| `18_portal_security_module.sql` | Portal access, password resets, 2FA codes, support tickets, backups |
| `19_reports_views.sql` | 34 reporting views |
| `20_seed_data.sql` | Demo school, roles, permissions, admin account, reference data |
| `21_uneb_module.sql` | UNEB levels, candidates, candidate subjects, CA submissions, CA scores, national results |
| `22_uneb_schema_alignment.sql` | Drops the legacy `cycle`-based UNEB tables and recreates the current schema |
| `23_scheduler_module.sql` | `cron_jobs` and `cron_job_logs` |

All SQL files are comment-free. The same schema is used for fresh installs and upgrades, apart from the guarded UNEB realignment in module 22.

---

## Report cards and PDFs

Report cards are built from `subject_results` and `result_summaries`, which are filled by **Examinations → Process results**. Processing requires verified or locked marks and an active grading scale for the class level, so run the exam workflow in order:

1. create the exam and schedule,
2. enter marks,
3. verify/approve marks,
4. process results,
5. publish results,
6. open **Academics → Report cards**.

From the report card screen you can:

- view a student card with subject marks, grades, totals, average, class position, stream position and comments,
- edit teacher and head comments (permission: `academics/edit`),
- download or print a single card as PDF,
- **Print all cards** for a class or stream (one card per page),
- **Export CSV** of the class ranking.

PDF output uses Dompdf when `vendor/autoload.php` exists. If Composer dependencies are not installed, the route serves a formatted HTML page with a notice; use the browser's **Print → Save as PDF** instead. The same fallback applies to invoices, receipts, letters, ID cards and slips.

---

## Backups

**Support & security → Data backups** creates SQL dumps with `mysqldump`, stores them in `backups/`, and lets you download or delete them. The database name and credentials are passed through a temporary option file, not the command line.

Requirements:

- `mysqldump` must be available on the server PATH (on XAMPP, add `C:\xampp\mysql\bin` to PATH or run the web server with it available).
- The web server user must be able to write to `backups/`.
- `backups/` is blocked from direct web access by `.htaccess`.

Backups are not scheduled automatically; create them before upgrades or bulk imports.

---

## Scheduled tasks

`cron_jobs` and `cron_job_logs` (module 23) support named jobs with a handler class, handler method, interval in minutes, active flag, and run history. Jobs are managed at **Support & security → Scheduled tasks** and can be run on demand from the same screen.

`cron.php` in the project root is the OS-level runner. It only runs from the command line, takes a non-blocking lock so two runs cannot overlap, runs every due job through `CronScheduler::runDueJobs()`, prints one line per job and exits with status 1 when a job fails.

Test it manually first:

```bash
C:\xampp\php\php.exe "C:\xampp\htdocs\school mgt system\cron.php"
```

**Windows Task Scheduler** — create a task that repeats every 5 minutes:

| Field | Value |
|---|---|
| Program/script | `C:\xampp\php\php.exe` |
| Add arguments | `"C:\xampp\htdocs\school mgt system\cron.php"` |
| Start in | `C:\xampp\htdocs\school mgt system` |

**Linux cron** — add to the web user's crontab:

```bash
*/5 * * * * /usr/bin/php "/var/www/school mgt system/cron.php" >> /var/log/ssms-cron.log 2>&1
```

The task runner must use the same PHP binary, `.env` file and database as the web application.

---

## REST API

Two session-authenticated JSON endpoints exist for subject registration, intended for mobile or third-party clients:

| Method | Route | Permission |
|---|---|---|
| POST | `api/v1/subjects/register` | `students/edit` |
| POST | `api/v1/subjects/bulk` | `students/edit` |

Send `X-Requested-With: XMLHttpRequest` (or `Accept: application/json`) to receive JSON instead of a redirect. Requests also need a valid session and CSRF token.

---

## Security model

- **Passwords** are stored with `password_hash()` (bcrypt/Argon2 per PHP defaults). Minimum length is configurable; resets expire after 30 minutes and are single-use.
- **Account lockout** after a configurable number of failed attempts, with a temporary lock timestamp.
- **Forced password change** on first login and after administrative resets.
- **CSRF** tokens are required on POST requests and regenerated at login when enabled.
- **Sessions** use HttpOnly and SameSite=Lax cookies, strict mode, a 4-hour lifetime, and are regenerated on login/logout.
- **Two-factor authentication** supports TOTP authenticator apps, with a 10-minute login challenge and attempt limits.
- **Permissions** are checked per route and again inside controllers; the sidebar hides modules the user cannot view.
- **Portal confinement** — parent and student accounts are restricted by the router to the portal, profile, password and logout routes, so staff modules and their school-wide data are unreachable to them.
- **Audit trail** records sign-ins, failed sign-ins, 2FA events, password events and module changes.
- **Sensitive paths** are denied by `.htaccess`: `.env`, `backend/`, `database/`, `frontend/`, `backups/`, `composer.*`, SQL dumps and documentation.
- **Error handling** logs details to the PHP error log and shows a generic 500 page, or JSON for API requests; stack traces are never shown.
- **Secrets** belong in `.env` or server configuration only. Do not commit database passwords, SMTP credentials or SMS API keys.

Before going live: set `APP_ENV=production`, use a dedicated database user with a strong password, enable HTTPS and `SESSION_SECURE=1`, change the seeded admin password, and review every role's permissions.

---

## Project structure

```
school mgt system/
├── index.php                 Public entry point; bridges to backend/index.php
├── cron.php                  Scheduled-task runner for Task Scheduler or cron
├── .htaccess                 Rewrite rules and path denials
├── .env.example              Environment template (copy to .env)
├── backend/
│   ├── index.php             Front controller and route table
│   ├── config/config.php     .env loader and constants
│   ├── core/                 Router, Auth, Csrf, Database, Model, helpers
│   ├── controllers/          One controller per feature area
│   ├── models/               Static query classes per module
│   ├── lib/                  Pdf, mail, SMS, import/export, scheduler, validator
│   ├── composer.json         Optional Dompdf dependency
│   └── fix_finance_perms.php Command-line permission diagnostic
├── frontend/
│   ├── layout/               Header, sidebar, footer
│   └── views/                PHP templates per module
├── css/  js/                 Stylesheets and scripts
├── database/                 Modules 00–23 + SSMS_full_install.sql
├── uploads/                  User uploads (gitignored, blocked from direct access)
└── backups/                  SQL dumps (blocked from direct access)
```

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Login says "No password is set for this account yet" | Fresh install; admin has no password | Open `?r=install` and set it |
| Login page loops back to login | Session directory not writable or cookies blocked | Check PHP session path and browser cookie settings |
| 500 on any page | Missing tables or PHP error | Check `C:\xampp\apache\logs\error.log`; re-import the full installer |
| Reports are empty or crash | Views missing | Run **Support & security → Database setup** to install views |
| PDF link shows an HTML page | `vendor/` (Dompdf) not installed | Run `composer install --working-dir=backend`, or print the page to PDF |
| Backup creation fails | `mysqldump` not on PATH or `backups/` not writable | Add MySQL `bin` to PATH; check folder permissions |
| Marks entered but no report card | Results not processed, or marks not verified | Verify marks, then **Process results** |
| Positions are blank | Result summary missing for the student | Process results for the exam |
| Email/SMS not delivered | Gateway not configured | Configure SMTP/SMS in `.env` or Settings |
| Times are offset | Timezone mismatch | Set `APP_TIMEZONE` and check PHP's `date.timezone` |

---

## Known limitations

- The UI assumes one school per installation. The schema is multi-tenant (`school_id`), but there is no school switcher.
- Scheduled tasks run only when `cron.php` is triggered by the operating system; configure Task Scheduler or cron as described above.
- Dompdf is optional, so PDF routes degrade to printable HTML when Composer dependencies are absent.
- No automated test suite is bundled. Validate changes against a running environment.
- SMS and email delivery depend on configured gateways and provider behaviour.

---

## License

MIT. See `backend/composer.json`.
