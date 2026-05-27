## CYCLOAN — Copilot instructions (concise)

Purpose: Help an AI coding assistant be immediately useful in this repository by describing the app architecture, common coding patterns, integration points, and dev workflows.

- Big picture
  - This is a classic PHP LAMP-style monolithic web app (procedural PHP). Pages are individual PHP files (e.g., `registration.php`, `index.php`, `user_dashboard.php`) that post to handler scripts (`process_*.php`).
  - DB connection is a simple mysqli instance provided by `CYCLOAN_db.php` (global `$conn`). Many scripts `require_once 'CYCLOAN_db.php'`.
  - Client assets: `CSS/`, `JAVASCRIPT/`, `IMAGE/`. Third-party libs live in `phpmailer/` and `tcpdf/`.

- Key flows & files (use these as entry points)
  - Registration flow: `registration.php` (multi-step UI) -> `process_registration.php` (validation, DB inserts, OTP via PHPMailer) -> `verify_otp.php`.
  - Authentication and profiles: `login`/`index.php`, `profile.php`, `update_profile.php`.
  - Loans & payments: `create_loan_process.php`, `create_loan_payment.php`, `get_loan_details.php`.
  - DB credentials and connection: `CYCLOAN_db.php` (DO NOT change creds in a PR — treat as secret; prefer env/config in future changes).

- Project-specific conventions to follow
  - Filenames: use snake_case PHP files and `process_*.php` for form handlers. Example: `process_registration.php`, `process_otp1.php`.
  - Session usage: form state and messages are stored in `$_SESSION`. Common keys: `form_data`, `success_message`, `error_message`, `otp_email`, `data_privacy_consented`.
  - Multi-step forms: `registration.php` uses a `?step=` query param and stores intermediate inputs in `$_SESSION['form_data']`; handlers redirect back to `registration.php?step=N` with `$_SESSION['fresh_redirect']` flags.
  - DB patterns: use prepared statements (`$conn->prepare(...)`) and `bind_param`. Typical tables (seen in `process_registration.php`) include: `users1`, `spouses`, `financial_info`, `income_sources`, `expenditure_types`, `otps`.

- Integrations & external dependencies
  - Email: `phpmailer/` is used (see `process_registration.php` -> `sendOTPEmail`). Credentials may be embedded; treat them as secrets and prefer app-passwords.
  - PDF: `tcpdf/` directory is present for any PDF generation tasks.
  - Database dump: `database/cycloan_db (39).sql` — use this to bootstrap a local dev DB.

- How to run locally (developer workflow)
  - Quick dev server: ensure PHP is installed or use XAMPP. From project root run (PowerShell):
    php -S 0.0.0.0:8000 -t .
    Then open: http://localhost:8000/registration.php
  - MySQL: import `database/cycloan_db (39).sql` into a local MySQL instance. Update `CYCLOAN_db.php` if you use different DB creds (do not commit changes to credentials).

- Debugging tips
  - Check `debug_log.txt` and `error_log` entries printed by scripts (`error_log(...)` used in handlers like `process_registration.php`).
  - Turn on display errors locally only while developing: set `ini_set('display_errors', 1); error_reporting(E_ALL);` at top of a test script — do not commit this to production files.

- What to avoid / security notes
  - Do not commit DB credentials or app passwords. If you must change `CYCLOAN_db.php` for local testing, keep edits local or use an env-based replacement.
  - PHPMailer credentials are present in `process_registration.php` — replace with env vars before committing.

- Editing guidance for AI
  - When modifying a flow, update both the UI page (`registration.php`) and its handler (`process_registration.php`). Preserve the `$_SESSION['form_data']` shape.
  - Prefer small, localized changes. For DB schema changes, include migration SQL and update `database/` dump.
  - For front-end changes, mirror class names and expected input `name` attributes used by `process_*.php` (e.g., `net_income`, `income_sources[]`, `expenditure_types[]`).

If anything above is unclear or you want specific examples (e.g., how to add a new column to `users1` and wire it through the registration flow), tell me which area to expand and I will update this file.
