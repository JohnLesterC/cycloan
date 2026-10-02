# CYCLOAN - Loan Management System

## Overview
PHP 8.3 procedural app with MySQL 8.0, served by Apache. No framework, no Composer dependencies (phpmailer/tcpdf are vendored in-tree). Vanilla HTML/CSS/JS frontend.

## Running the app
```bash
docker compose -f docker-compose.base44.yml up -d --build
```
- Web entry point: http://localhost:3000/ (maps to Apache port 80 inside container)
- Source is bind-mounted at `/var/www/html` — PHP file changes are live immediately (no rebuild needed)
- MySQL runs as a separate compose service (`db`); schema is auto-initialized from `docker/init.sql`

## Database
- Database name: `cycloan_db`
- Connection: `CYCLOAN_db.php` reads `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` from environment (falls back to localhost/root/''/cycloan_db)
- **No schema dump existed in the repo** — `docker/init.sql` was reconstructed from PHP source (INSERT/SELECT/CREATE statements across all .php files). If a table is missing a column, check the PHP queries and add it to `docker/init.sql`.
- Seed data includes: interest rates (6/12/18/24/36 months), loan types (Individual/Cooperative), document types, admin accounts, and a test user
- Admin login: `admin@cycloan.com` / `admin123` (superadmin), `admin1@cycloan.com` / `admin123`, `admin2@cycloan.com` / `admin123`
- User login: `user@cycloan.com` / `admin123`

## Key files
- `CYCLOAN_db.php` — main DB connection (uses `$conn` mysqli object)
- `env_config.php` — reads `.env` file, defines constants for email/security config
- `index.php` — login page (entry point)
- `registration.php` — multi-step user registration
- `admin1_dashboard.php`, `admin2_dashboard.php`, `Superadmin_dashboard.php` — admin dashboards
- `loan_register_process.php` — loan application submission
- `create_loan_process.php` — loan creation with payment schedule generation
- `includes/config.php` — separate mysqli connection (`$mysqli`), not used by main pages
- `timezone_config.php` — Philippine timezone (Asia/Manila) helpers

## Architecture notes
- Auth: session-based, checks `users1`, `admin1`, `admin2`, `superadmins` tables sequentially
- Sessions: `$_SESSION['user_id']`, `$_SESSION['email']`, `$_SESSION['role']`
- Email: PHPMailer (vendored), configured via env_config.php constants — not required for app to boot
- File uploads: go to `uploads/` directory (create if missing)
- No build step, no package manager — pure PHP + static assets
