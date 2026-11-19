SSMS — Secondary School Management System
Symptom	Likely cause	Fix
Login says "No password is set for this account yet"	Fresh install; admin has no password	Open ?r=install and set it
Login page loops back to login	Session directory not writable or cookies blocked	Check PHP session path and browser cookie settings
500 on any page	Missing tables or PHP error	Check C:\xampp\apache\logs\error.log; re-import the full installer
Reports are empty or crash	Views missing	Run Support & security → Database setup to install views
PDF link shows an HTML page	vendor/ (Dompdf) not installed	Run composer install, or print the page to PDF
Backup creation fails	mysqldump not on PATH or backups/ not writable	Add MySQL bin to PATH; check folder permissions
Marks entered but no report card	Results not processed, or marks not verified	Verify marks, then Process results
Positions are blank	Result summary missing for the student	Process results for the exam
Email/SMS not delivered	Gateway not configured	Configure SMTP/SMS in .env or Settings
Times are offset	Timezone mismatch	Set APP_TIMEZONE and check PHP's date.timezone



Known limitations

•The UI assumes one school per installation. The schema is multi-tenant (school_id), but there is no school switcher.
•Scheduled tasks have no automatic runner; jobs must be triggered manually or by an external runner you add.
•Dompdf is optional, so PDF routes degrade to printable HTML when Composer dependencies are absent.
•No automated test suite is bundled. Validate changes against a running environment.
•SMS and email delivery depend on configured gateways and provider behaviour.



License

MIT. See composer.json.
