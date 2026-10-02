# CYCLOAN — Complete System Description

> **Purpose**: Full documentation of the CYCLOAN (City Lending & Development Department) Loan Management System — every feature, workflow, user role, database table, and business rule — for rebuilding on another platform.

---

## 1. SYSTEM OVERVIEW

CYCLOAN is a **loan management system** for the City Lending & Development Department (CLDD) of Calamba City, Laguna, Philippines. It manages the full lifecycle of micro-loans: user registration → loan application → admin review/approval → loan creation with payment schedule → payment processing → loan closure.

### Tech Stack (Current)
- **Backend**: PHP 8.3 with Apache
- **Database**: MySQL 8.0 (via MySQLi)
- **Email**: PHPMailer via Gmail SMTP
- **PDF**: TCPDF for invoice/receipt generation
- **Frontend**: Vanilla HTML/CSS/JS + Bootstrap 5 + Font Awesome
- **Timezone**: Asia/Manila (UTC+8) throughout
- **Location**: Calamba City, Laguna, Philippines (barangays fetched from PSGC API)

### Four User Roles
| Role | DB Table | Dashboard | Description |
|------|----------|-----------|-------------|
| **User** | `users1` | `user_dashboard.php` | Loan applicants/borrowers |
| **Admin 1** | `admin1` | `admin1_dashboard.php` | First-level reviewer (pre-approval, credit investigation) |
| **Admin 2** | `admin2` | `admin2_dashboard.php` | Second-level approver (final approval, loan creation, payment processing) |
| **Superadmin** | `superadmins` | `Superadmin_dashboard.php` | Full system control (admin management, interest rates, reports, activity logs) |

---

## 2. DATABASE SCHEMA

### 2.1 User & Auth Tables

#### `users1` — Main user/borrower table
- `id` (PK, auto-increment)
- `first_name`, `middle_name`, `last_name`, `name_extension`, `nick_name`
- `birthday`, `age`, `birth_place`
- `civil_status` (Single, Married, Widowed, Separated, Live In)
- `contact` (11-digit Philippine phone)
- `email` (unique)
- `fb_account` (Facebook link)
- `password` (bcrypt hashed)
- `is_active` (0 = pending OTP verification, 1 = active)
- `profile_image` (default: `/assets/default.jpg`)
- `credit_points` (integer, default 0)
- `credit_points_updated_at`
- `res_house_no`, `res_street`, `res_subdivision`, `res_barangay`, `res_address`
- `bus_bldg_no`, `bus_street`, `bus_subdivision`, `bus_barangay`, `bus_address`
- `house_ownership` (Owned, Rented, Living with Parents/Relatives, Mortgaged)
- `occupation`
- `reg_voter` (yes_calamba, yes_not_calamba, no)
- `year_resident`
- `status` (active)

#### `admin1` — Admin Level 1
- `id`, `first_name`, `last_name`, `email`, `password`, `profile_img`, `phone`, `address`

#### `admin2` — Admin Level 2
- `id`, `first_name`, `last_name`, `email`, `password`, `profile_img`, `phone`, `address`

#### `superadmins` — Super Admin
- `id`, `email`, `password`, `profile_img`, `first_name`, `middle_name`, `last_name`, `phone`, `address`

#### `spouses` — Spouse information (for married users)
- `id`, `user_id` (FK → users1.id), `first_name`, `middle_name`, `last_name`, `name_extension`, `nick_name`, `reg_voter`, `birthday`, `age`, `occupation`, `dependents`, `birth_place`, `contact`, `email`, `fb_account`

#### `financial_info` — Financial details per user
- `id`, `user_id` (FK), `business_income`, `salary_income`, `remittance_income`, `other_income`, `business2_income`, `salary2_income`, `net_income`, `food_allowance`, `electricity_bill`, `water_bill`, `internet_bill`, `gas_bill`, `educational_allowance`, `car_amortization`, `insurance`, `other_expense`, `total_expenditures`, `expected_monthly_amortization`, `remaining_income`

#### `income_sources` — Selected income source types
- `id`, `user_id` (FK), `source_type`

#### `expenditure_types` — Selected expenditure types
- `id`, `user_id` (FK), `expense_type`

#### `otps` — One-time passwords for registration verification
- `id`, `user_id` (FK), `otp_hash` (bcrypt), `created_at`, `expires_at` (10 min), `attempt_count`

#### `password_reset_tokens` — Password reset tokens
- Token-based password recovery flow

#### `login_sessions` — Remember-me / persistent login tokens
- `id`, `user_id`, `user_type`, `token`, `ip_address`, `user_agent`

#### `logattempts` — Login attempt logging
- `id`, `email`, `success` (0/1), `attempt` (timestamp)

### 2.2 Loan Tables

#### `loan_types` — Loan type catalog
- `loan_type_id` (PK), `type_name` ('Individual', 'Cooperative')

#### `loan_applications` — Loan application records
- `application_id` (formatted string ID, e.g., "APP-2025-00001")
- `loan_id` (VARCHAR, human-readable loan ID)
- `user_id` (FK → users1.id)
- `loan_type_id` (FK → loan_types)
- `amount_applied` (decimal, min ₱10,000, max ₱1,000,000)
- `status` (Pending, Active, Closed, Archived, Rejected)
- `pre_approval_status` — Admin 1's pre-approval decision
- `credit_investigation_status` — Admin 1's credit investigation result
- `final_loan_amount` — Set when Admin 2 creates the actual loan
- `term_length` (6, 12, 18, 24, 36 months)
- `repayment_frequency` (Monthly, Quarterly, Semi-Annually, Annually)
- `purpose` (Start-up Capital, Supplement Working Capital, Others)
- `project_type` (Agricultural-based, Non-Agricultural)
- `project_description` (20-500 chars)
- `loan_status` (new, renewal)
- `created_at`, `updated_at`

#### `loans` — Active/closed loan records (created by Admin 2)
- `loan_id` (PK, auto-increment)
- `application_id` (FK → loan_applications)
- `user_id` (FK → users1)
- `amount` (principal)
- `duration` (months)
- `interest_rate`
- `monthly_payment`
- `remaining_balance` (starts at principal + total interest)
- `total_paid` (accumulated)
- `status` (active, closed)
- `payment_frequency`
- `payment_amount` (per schedule installment)
- `total_interest`
- `total_principal`
- `created_at`, `updated_at`

#### `payment_schedules` — Amortization schedule per loan
- `payment_id` (PK)
- `loan_id` (FK → loans)
- `due_date`
- `amount` (total installment = principal + interest)
- `status` (pending, partial, Paid, Unpaid)
- `paid_at`, `payment_date_actual`
- `amount_paid` (accumulated)
- `interest_amount`, `principal_amount`
- `interest_paid`, `principal_paid` (accumulated)
- `late_fee`, `late_fee_paid`
- `updated_at`

#### `payment_history` — Payment transaction log
- `id`, `payment_id` (FK), `loan_id` (FK), `amount_paid`, `interest_paid`, `principal_paid`, `payment_type` (full, interest, principal, custom), `payment_date`, `invoice_number`

#### `payments` — Payment records (alternative/legacy table)
- `payment_id`, `schedule_id`, `loan_id`, `amount_paid`, `payment_date`, `payment_method`

#### `invoices` — Generated invoices
- `id`, `payment_id`, `loan_id`, `payment_history_id`, `amount_paid`, `interest_paid`, `principal_paid`, `payment_date`, `invoice_number`

#### `interest_rates` — Configurable interest rates by term
- `id`, `term_length` (6, 12, 18, 24, 36), `interest_rate` (decimal), `updated_at`, `updated_by` (FK → superadmins)
- Default: 6.00% for 12-month term

#### `loan_application_documents` — Uploaded documents per application
- Links documents to loan applications

#### `loan_requirements` — Document requirement definitions per loan type
- Defines which documents are required for Individual vs Cooperative loans

#### `uploaded_files` — File upload metadata
- `id`, file path, type, size, associated application

### 2.3 Document Tables

#### `documents` — Document records
- Stores uploaded loan documents (2x2 picture, voter's certificate, residence certificate, barangay clearance, business permit, farm plan & budget, loan project proposal, audited financial statement, bank statement, BIR registration)

#### `document_types` — Document type catalog
- Defines document categories

#### `document_remarks` — Admin remarks on documents
- Admin can add remarks/notes on specific documents

#### `document_audit` — Document change audit trail
- Tracks document uploads, updates, deletions

#### `remarks` — General remarks on loan applications
- Admin can add remarks to loan applications

### 2.4 Notification Tables

#### `notifications` — System notification templates/log
- General notification records

#### `user_notifications` — Per-user notification inbox
- `notification_id` (PK), `user_id`, `title`, `message`, `short_message`, `is_read` (0/1), `priority`, `action_url`, `action_text`, `type_id` (FK), `created_at`

#### `notification_types` — Notification type catalog
- `type_id` (PK), `type_name`, `type_display_name`, `icon_class`, `color_class`
- Types: payment, loan_status, document, system, reminder, etc.

#### `user_notification_settings` — Per-user notification preferences
- Users can configure which notification types they receive

### 2.5 Credit Points Tables

#### `credit_points_history` — Points change log
- `id`, `user_id` (FK), `points_change` (±), `previous_points`, `new_points`, `reason`, `loan_id`, `admin_id`, `admin_role`, `created_at`

#### `credit_points_settings` — Configurable points rules
- `setting_name`, `setting_value`
- Settings:
  - `points_per_completed_loan`
  - `bonus_points_first_loan`
  - `points_per_ontime_payment`
  - `points_deduction_late_payment`
  - `points_deduction_default`
  - `minimum_credit_points`

### 2.6 Activity & Security Tables

#### `activity_logs` — System-wide activity audit trail
- `log_id` (PK), `user_id`, `user_role` (User, Admin1, Admin2, superadmin), `admin_email`, `action_type` (create, update, delete, login, logout, view, approve, reject, send_reminder, upload, status_update, comment, assign, password_reset_requested, password_reset_completed), `module` (loan_application, user, admin, interest_rate, payment, document, remarks, payment_schedule, loan_type, notification), `description`, `affected_id`, `created_at`

#### `admin_activity_log` — Admin-specific activity log
- Separate log for admin actions

#### `admin_accounts` — Admin account registry
- Tracks admin accounts

#### `two_factor_methods` — 2FA configuration per user
- `id`, `user_id`, `method` (email, sms, totp), `phone_number`, `totp_secret`, `is_enabled`

#### `two_factor_challenges` — 2FA challenge records
- `id`, `user_id`, `challenge_id`, `method`, `code_hash`, `verified`, `attempt_count`, `expires_at`, `verified_at`

#### `two_factor_backup_codes` — 2FA backup codes
- `id`, `user_id`, `code_hash`, `used`, `used_at`

### 2.7 Email System Tables

#### `email_queue` — Queued email messages
- Stores emails for async sending

#### `email_queue_settings` — Email queue configuration
- Settings for batch sending, retry, etc.

#### `email_batch_logs` — Email batch sending logs
- Tracks batch email operations

---

## 3. USER REGISTRATION WORKFLOW

### Step-by-Step Flow

1. **Data Privacy Consent Modal** — User must accept the Data Privacy Act (RA 10173) consent before seeing the registration form. Declining shows "Registration Access Denied" message.

2. **Voter Registration Requirement** — Modal explaining that COMELEC voter registration is mandatory. User must confirm they are a registered voter.

3. **Multi-Step Registration Form** (5 or 6 steps depending on civil status):
   - **Step 1: Personal Information** — First name, middle name, last name, name extension, nickname, birthday (auto-calculates age, must be ≥21), birth place, civil status, contact (11-digit Philippine phone), email + confirm email, Facebook account, occupation (dropdown with "Other" option), registered voter status, length of residency
   - **Step 2: Residential Address** — House/unit no., street/block, subdivision, barangay (fetched from PSGC API for Calamba City), house ownership, complete address (auto-composed)
   - **Step 3: Business Address** — Building/unit no., street/block, subdivision, barangay, complete business address
   - **Step 4: Spouse Information** (only if Married/Separated/Live In) — Spouse's first name, middle name, last name, extension, nickname, registered voter, birthday, age, occupation, dependents, birth place, contact, email, Facebook
   - **Step 5 (or 4): Financial Information** — Income sources (checkboxes: business, salary, remittance, other, business2, salary2), amounts per source, net income, expenditure types (checkboxes: food allowance, electricity, water, internet, gas, educational, car amortization, insurance, other), amounts per expenditure, total expenditures, expected monthly amortization, remaining income
   - **Step 6 (or 5): Account Security** — Password (8-128 chars, must include uppercase, lowercase, number, special character), confirm password

4. **CSRF Protection** — CSRF token generated per session, validated on every POST

5. **Server-Side Validation** — All fields validated via `security_validation.php` functions (validateString, validateInteger, validateDecimal, validateEmail, validatePhoneNumber, validateEnum, validateDate)

6. **Database Insert** — Data saved to `users1`, `spouses` (if applicable), `financial_info`, `income_sources`, `expenditure_types`

7. **OTP Generation** — 6-digit OTP generated with cryptographic randomness, bcrypt-hashed, stored in `otps` table with 10-minute expiry

8. **OTP Email** — Sent via PHPMailer/Gmail SMTP with professional HTML template

9. **OTP Verification** (`verify_otp.php`) — User enters 6-digit code, rate-limited (5 attempts), verified against bcrypt hash, account activated (`is_active = 1`), OTP deleted, welcome email sent

10. **Activation Success** — Redirect to `activation_success.php` showing confirmation

### Language Support
- English and Tagalog translations via `languages.php`
- Language selector in top-right corner of registration page

---

## 4. AUTHENTICATION & SECURITY

### Login Flow (`index.php`)
1. User enters email + password
2. System checks **4 tables sequentially**: `users1` → `admin1` → `admin2` → `superadmins`
3. Password verified via `password_verify()` (bcrypt)
4. On success: session variables set (`user_id`, `email`, `role`), redirect to role-specific dashboard
5. On failure: error logged to `logattempts` table
6. **Remember Me** — Stores email/password in cookies for 30 days (NOTE: stores plaintext password in cookie — security concern)
7. **Login attempt logging** — All attempts logged with email and success/failure

### Session Management (`session_config.php`)
- Session timeout: 2400 seconds (40 minutes) of inactivity
- Session ID regeneration every 60 minutes
- Secure cookie settings: HttpOnly, SameSite=Strict, Secure (HTTPS only)
- Session regeneration on login with email-based session ID

### Role-Based Access Control (`includes/auth.php`)
- `$ROLE_DASHBOARD` maps roles to dashboard files
- If logged in and on login page → redirect to dashboard
- If not logged in and on any other page → redirect to login
- If on wrong dashboard for role → redirect to correct dashboard
- Shared pages (notifications, profile, records) accessible to all roles

### Two-Factor Authentication (`two_factor_auth.php`)
- **Methods**: Email OTP, SMS OTP, TOTP (Google Authenticator)
- **Challenge generation** — Creates challenge with hashed code, 10-min expiry
- **Rate limiting** — Via `RateLimiter` class (Redis or file-based)
- **Backup codes** — 10 single-use backup codes
- **TOTP** — Base32-encoded secret for authenticator apps
- Tables: `two_factor_methods`, `two_factor_challenges`, `two_factor_backup_codes`

### Password Reset Flow
1. `forget_pass.php` — User enters email
2. `submit_forget_pass.php` — Sends reset link email
3. `confirm_password_reset.php` — User clicks link, enters new password
4. `process_password_reset.php` — Updates password, logs activity
5. `complete_password_reset.php` / `password_reset_success.php` — Confirmation

### Rate Limiting (`rate_limiter.php`)
- Registration: 5 per IP per hour
- OTP request: 3 per email per hour
- OTP verification: 5 per email per 5 minutes
- Uses Redis (if available) or file-based fallback

### Security Validation (`security_validation.php`)
- `validateString()` — Length-bounded string sanitization
- `validateInteger()` — Range-checked integer
- `validateDecimal()` — Decimal/float validation
- `validateEmail()` — Email format validation
- `validatePhoneNumber()` — Philippine phone format (09XXXXXXXXX)
- `validateEnum()` — Whitelist-based enum validation
- `validateDate()` — Date format validation
- `logSecurityError()` — Security event logging

---

## 5. USER DASHBOARD (`user_dashboard.php`)

### Features
- **Profile sidebar** — Name, profile image, credit points display
- **Loan application list** — Shows all user's loan applications with status, amount, type, dates
- **Active/Pending loan check** — Prevents new loan application if user has active/pending loan
- **Apply for loan button** — Links to `loan_register.php` (disabled if active/pending loan exists)
- **Payment history timeline** — Last 10 payments with amount, date, method, loan type
- **Credit points widget** — Current points + last 5 transaction history
- **Interest rate display** — Current rate fetched via AJAX from `interest_rates` table
- **Navigation** — Links to notifications, profile, payment history, activity log

### Data Fetched
- User profile from `users1`
- Loan applications from `loan_applications` JOIN `loan_types` LEFT JOIN `loans`
- Payment history from `payments` JOIN `payment_schedules` JOIN `loans` JOIN `loan_applications` JOIN `loan_types`
- Credit points from `users1.credit_points` + `credit_points_history`

---

## 6. LOAN APPLICATION WORKFLOW

### 6.1 Loan Registration (`loan_register.php` → `loan_register_process.php`)

**4-step form:**

1. **Basic Details** — Loan type (Individual or Cooperative), loan status (new/renewal — currently disabled, only "new" allowed), amount applied (₱10,000–₱1,000,000)

2. **Project Details** — Term length (Individual: 6/12/18 months; Cooperative: 6/12/18/24/36 months), repayment frequency (Monthly/Quarterly/Semi-Annually/Annually), purpose (Start-up Capital/Supplement Working Capital/Others), project type (Agricultural-based/Non-Agricultural), project description (20-500 chars)

3. **Document Requirements** — File uploads (JPEG/PNG/PDF, max 5MB each):
   - **Individual loans**: 2x2 Picture, Voter's Certificate, Residence Certificate, Barangay Clearance, Business Permit
   - **Agricultural projects additionally**: Farm Plan & Budget
   - **Cooperative loans**: Loan Project Proposal, Audited Financial Statement, Bank Statement (last 6 months), BIR Registration

4. **Overview** — Review all details before submission, shows calculated interest, total payable amount, payment schedule preview

**Processing** (`loan_register_process.php`):
- File upload to `Uploads/` directory with unique filenames
- Validation of all inputs
- Insert into `loan_applications` table with status = 'Pending'
- Store uploaded files in `loan_application_documents` / `uploaded_files`
- Redirect to user dashboard with success message

### 6.2 Admin 1 Review (`admin1_dashboard.php`)

**Admin 1 responsibilities:**
- View pending loan applications
- **Pre-approval** — Review basic application details, set `pre_approval_status`
- **Credit investigation** — Review user's financial info, credit points, documents; set `credit_investigation_status`
- Add remarks to applications
- Request additional documents from users
- Forward approved applications to Admin 2
- Send email reminders to users for missing documents

**Application status flow at Admin 1:**
- Pending → Pre-Approved (pre_approval_status set) → Credit Investigation Passed → Forwarded to Admin 2

### 6.3 Admin 2 Approval (`admin2_dashboard.php`)

**Admin 2 responsibilities:**
- View applications forwarded by Admin 1
- **Final approval** — Review all details, set final loan amount
- **Loan creation** — Creates the actual loan record via `create_loan_process.php`
- **Payment schedule generation** — Calculates amortization schedule based on:
  - Principal amount
  - Interest rate (from `interest_rates` table by term length)
  - Duration (months)
  - Payment frequency
- **Payment processing** — Process payments via `pay_balance.php`
- **Interest rate management** — View/update interest rates
- **Document review** — View uploaded documents, add remarks
- **Send reminders** — Email users about upcoming/overdue payments

**Loan creation** (`create_loan_process.php`):
1. Validates application is Active
2. Checks no existing loan for the application
3. Database transaction:
   - Insert into `loans` table (amount, duration, interest_rate, payment_amount, remaining_balance = principal + interest, status = 'active')
   - Insert payment schedule entries into `payment_schedules` (one per installment with due_date, principal_amount, interest_amount, total amount)
   - Update `loan_applications.final_loan_amount`
   - Log activity in `activity_logs`
4. Generate PDF invoice using TCPDF (loan summary, payment schedule table)
5. Send email to user with PDF invoice attached

### 6.4 Payment Processing (`pay_balance.php`)

**Payment types:**
- **Full** — Pay remaining balance for a schedule entry (interest + principal)
- **Interest** — Pay only the interest portion
- **Principal** — Pay only the principal portion
- **Custom** — Specify custom interest and principal amounts (must sum to total)

**Process:**
1. Validate payment amount against remaining due
2. Calculate interest/principal split based on payment type
3. Database transaction:
   - Insert into `payment_history`
   - Update `payment_schedules` (amount_paid, interest_paid, principal_paid, status → 'partial' or 'Paid')
   - Update `loans` (remaining_balance -= principal_paid, total_paid += amount_paid)
   - Check if loan fully paid → set `loans.status = 'closed'`, `loan_applications.status = 'Closed'`
   - Insert into `invoices`
   - Log activity in `activity_logs`
4. Create notification for user (`createPaymentNotification`)
5. Generate PDF receipt using TCPDF
6. Send payment receipt email with PDF attached

### 6.5 Loan Status Lifecycle

```
Pending → Active → Closed
                → Archived
         → Rejected
```

1. **Pending** — Application submitted, awaiting Admin 1 review
2. **Active** — Approved by Admin 2, loan created, payment schedule active
3. **Closed** — All payments completed, remaining_balance ≤ 0
4. **Archived** — Admin can archive old records
5. **Rejected** — Application denied

---

## 7. RECORDS MANAGEMENT

### Shared record pages accessible to all admin roles:

#### `active_records.php` — Active loan applications
- Shows all applications with status = 'Active'
- Displays applicant name, loan type, amount, term, payment progress
- Admin can view details, process payments

#### `pending_records.php` — Pending loan applications
- Shows all applications with status = 'Pending'
- Admin 1 can review, pre-approve, add remarks
- Admin 2 can view but cannot create loan until approved

#### `closed_records.php` — Closed/completed loans
- Shows all loans with status = 'Closed'
- Historical view of completed loans with full payment history

#### `archived_records.php` — Archived records
- Shows archived applications/loans
- Can be restored if needed

### User-side records:
- `user_active_record.php` — User's active loans
- `user_closed_records.php` — User's closed loans
- `user_history_activity.php` — User's activity history

---

## 8. SUPERADMIN DASHBOARD (`Superadmin_dashboard.php`)

### Features
- **System overview** — Total users, total loans, total amount disbursed, active loans
- **Loan type graph** — Pie chart showing Individual vs Cooperative loan distribution
- **Due accounts** — Payments due within next 30 days
- **Activity logs** — Last 50 system activities with user name, role, action, module, timestamp
- **Admin management** — Add/remove Admin 1 and Admin 2 accounts (`add_admin.php`, `remove_admin.php`)
- **Interest rate management** — View/update interest rates by term length
- **Reports** — Link to `reports_analytics.php` and `reports_record.php`
- **Profile management** — Link to `profileSuperadmin.php`

### Admin Management (`add_admin.php`)
- Superadmin can create new Admin 1 or Admin 2 accounts
- Generates temporary password
- Sends welcome email to new admin
- Can remove admin accounts (`remove_admin.php`)

### Interest Rate Management
- View current rates by term length (6, 12, 18, 24, 36 months)
- Update rates (creates new row in `interest_rates` with `updated_by` = superadmin ID)
- View rate history
- API endpoints via `interest_rate_api.php`:
  - `get_all_interest_rates` — Current rates for all terms
  - `get_interest_rate_history` — Historical rate changes
  - `get_rate` — Single rate by term length

---

## 9. NOTIFICATION SYSTEM

### Architecture
- `NotificationManager.php` — Core notification class (MySQLi)
- `NotificationManager_MySQLi.php` — Alternative MySQLi implementation
- `notifications.php` — User-facing notification page
- `notification_widget.php` — Dashboard notification widget
- `notification_center.php` — Admin notification center
- `notification_api.php` — AJAX endpoints
- `sse_notifications.php` — Server-Sent Events for real-time notifications
- `NotificationStream.php` — SSE stream handler

### Notification Flow
1. System events (payment, loan status change, document upload) create notifications
2. Notifications stored in `user_notifications` with `notification_types` reference
3. Users see unread count badge on dashboard
4. `notifications.php` shows paginated notification list with:
   - Filter by type
   - Filter by read/unread
   - Search by title/message
   - Mark as read/unread
   - Mark all as read
   - Pagination (10/20/50 per page)
5. Real-time updates via SSE (`sse_notifications.php`)

### Notification Types
- Payment received
- Loan status update
- Document request/reminder
- Payment due reminder
- System announcements
- Credit points change

### User Notification Settings
- Users can configure which notification types they receive
- Stored in `user_notification_settings`

---

## 10. CREDIT POINTS SYSTEM

### Purpose
Gamification/rewards system to encourage good financial behavior.

### Points Rules (configurable via `credit_points_settings`)
| Setting | Description |
|---------|-------------|
| `points_per_completed_loan` | Points awarded when a loan is fully paid |
| `bonus_points_first_loan` | Extra bonus for first-ever completed loan |
| `points_per_ontime_payment` | Points for paying on or before due date |
| `points_deduction_late_payment` | Points deducted for late payment |
| `points_deduction_default` | Points deducted for loan default |
| `minimum_credit_points` | Minimum allowed points (floor) |

### Operations
- **Add points** — Award points with reason, optional loan_id and admin info
- **Deduct points** — Remove points (clamped to minimum)
- **Set points** — Admin override to exact value
- **Award loan completion** — Auto-award on loan close (with first-loan bonus check)
- **Award on-time payment** — Auto-award when payment made by due date
- **Deduct late payment** — Auto-deduct when payment is late
- **Deduct loan default** — Auto-deduct when loan defaults

### History & Leaderboard
- `credit_points_history` — Full audit trail of all points changes
- Leaderboard — Top users by credit points
- Displayed on user dashboard and admin dashboards

---

## 11. REPORTS & ANALYTICS

### `reports_analytics.php` — Analytics dashboard
Accessible to all admin roles. Shows:

**Operational Analytics:**
- Applications today/this week/this month
- Approvals today/this month
- Real-time KPI dashboard
- Loan type distribution
- Payment collection rates
- Due accounts (next 30 days)
- Overdue accounts

**Financial Analytics:**
- Total amount disbursed
- Total interest collected
- Outstanding balances
- Repayment rates
- Default rates

### `reports_record.php` — Detailed records report
- Comprehensive loan portfolio report
- Individual loan details
- Payment histories
- Exportable data

---

## 12. EMAIL SYSTEM

### Configuration (`env_config.php`, `email_config.php`)
- SMTP: smtp.gmail.com
- Port: 587 (STARTTLS)
- Multiple sender accounts:
  - `scycloan@gmail.com` (OTP, loan creation, admin notifications)
  - `cycloancldd@gmail.com` (welcome, payment receipts, support)

### Email Types
1. **OTP Email** — 6-digit verification code for registration
2. **Welcome Email** — Sent after account activation
3. **Loan Creation Email** — Loan summary + PDF invoice attachment
4. **Payment Receipt Email** — Payment details + PDF receipt attachment
5. **Password Reset Email** — Reset link
6. **2FA Code Email** — Two-factor authentication code
7. **Document Reminder Email** — Missing document reminders
8. **Payment Due Reminder** — Upcoming payment notifications

### Email Queue (`EmailQueueManager.php`, `process_email_queue.php`)
- Async email queue for batch sending
- Retry mechanism
- Batch logging in `email_batch_logs`

### Email Templates (`email_templates.php`)
- Professional HTML email templates
- Green gradient header with CYCLOAN logo
- Responsive design
- Consistent branding

---

## 13. PDF GENERATION

### TCPDF Integration
- **Loan Invoice PDF** — Generated on loan creation:
  - Invoice details (application ID, loan ID, borrower name, date)
  - Loan summary (principal, interest, total)
  - Loan terms (duration, frequency, payment amount, first payment date)
  - Full payment schedule table
  - Important reminders
  - Saved to `uploads/invoices/`

- **Payment Receipt PDF** — Generated on payment:
  - Receipt details (recipient, loan ID, invoice number, date)
  - Payment summary (interest paid, principal paid, total)
  - Payment information (type, date)
  - Important notice
  - Attached to payment email

---

## 14. PROFILE MANAGEMENT

### User Profile (`profile.php`)
- View/edit personal information
- Update contact details
- Change password
- Upload/update profile image
- View credit points
- Check for active/pending loans (restricts certain edits)

### Admin Profiles
- `profileAdmin1.php` — Admin 1 profile management
- `profileAdmin2.php` — Admin 2 profile management
- `profileSuperadmin.php` — Superadmin profile management
- Each has corresponding update handlers:
  - `update_profile.php` (user)
  - `update_adminProfile1.php` (admin1)
  - `update_adminProfile2.php` (admin2)
  - `update_superAdminProfile.php` (superadmin)

---

## 15. ACTIVITY LOGGING

### `activity_logs` Table
Every significant action is logged:
- **User actions**: login, logout, loan application, payment, profile update
- **Admin actions**: approve, reject, create loan, process payment, update interest rate, send reminder, add/remove admin
- **System actions**: password reset, document upload, status update

### Log Entry Structure
- `user_id` — Who performed the action
- `user_role` — User, Admin1, Admin2, superadmin
- `admin_email` — Email (for admin actions)
- `action_type` — What action was performed
- `module` — Which module was affected
- `description` — Human-readable description
- `affected_id` — ID of affected record
- `created_at` — Timestamp

### Activity Log Pages
- `history_activity.php` — Admin view of all activity logs
- `user_history_activity.php` — User's own activity history

---

## 16. LANDING PAGE & PUBLIC PAGES

### `landing_page.php`
- Public-facing landing page for CYCLOAN
- Image slideshow showcasing the department
- Steps to apply for a loan
- Links to login and registration
- Department information

### `privacy_policy.php`
- Data Privacy Act (RA 10173) compliance page
- Explains data collection, usage, storage, and user rights
- Contact information for privacy concerns

### `whatis.php`
- Information about CYCLOAN/CLDD

### `steps.php`
- Loan application steps guide

---

## 17. BARANGAY DATA

### `get_barangays.php`
- Fetches barangays of Calamba City from PSGC (Philippine Standard Geographic Code) API
- PSGC code for Calamba City: `045134000`
- API URL: `https://psgc.gitlab.io/api/cities/{psgc_code}/barangays/`
- Caches results for 7 days in `cache/calamba_barangays.json`
- Fallback to hardcoded barangay list if API is unreachable
- Used in registration form and loan application form

---

## 18. CONFIGURATION & ENVIRONMENT

### Environment Variables (`env_config.php`)
| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | Database host | localhost |
| `DB_NAME` | Database name | cycloan_db |
| `DB_USER` | Database user | root |
| `DB_PASS` | Database password | (empty) |
| `DB_PORT` | Database port | 3306 |
| `MAIL_HOST` | SMTP host | smtp.gmail.com |
| `MAIL_USERNAME` | SMTP username | scycloan@gmail.com |
| `MAIL_PASSWORD` | SMTP password | (app password) |
| `MAIL_PORT` | SMTP port | 587 |
| `MAIL_ENCRYPTION` | Encryption method | tls |
| `OTP_EXPIRATION_MINUTES` | OTP validity | 10 |
| `OTP_MAX_ATTEMPTS` | Max OTP attempts | 5 |
| `OTP_LENGTH` | OTP digit count | 6 |
| `RATE_LIMIT_ENABLED` | Enable rate limiting | true |
| `RATE_LIMIT_REGISTRATION_PER_IP` | Registration limit | 5/hour |
| `RATE_LIMIT_OTP_PER_EMAIL` | OTP request limit | 3/hour |
| `RATE_LIMIT_VERIFICATION_PER_EMAIL` | Verification limit | 5/5min |
| `TWO_FACTOR_ENABLED` | Enable 2FA | true |
| `TWO_FACTOR_METHOD` | Default 2FA method | email |
| `SESSION_LIFETIME` | Session timeout | 1800 (30 min) |
| `APP_NAME` | Application name | CYCLOAN |
| `APP_URL` | Application URL | http://localhost/CYCLOAN |
| `APP_ENV` | Environment | local |
| `APP_DEBUG` | Debug mode | true |
| `REDIS_ENABLED` | Redis for rate limiting | false |
| `REDIS_HOST` | Redis host | localhost |
| `REDIS_PORT` | Redis port | 6379 |

---

## 19. COMPLETE FILE INVENTORY

### Core PHP Files (174 total)

**Authentication & Session:**
- `index.php` — Login page
- `registration.php` — Multi-step registration form
- `process_registration.php` — Registration processing
- `verify_otp.php` — OTP verification form
- `process_otp1.php` — OTP verification processing
- `resend_otp.php` — Resend OTP
- `activation_success.php` — Activation confirmation
- `logout.php` — Session destruction
- `session_config.php` — Session security settings
- `session_check.php` — Session validation
- `two_factor_auth.php` — 2FA system
- `includes/auth.php` — Role-based access guard
- `includes/functions.php` — Token management functions

**Password Reset:**
- `forget_pass.php` — Request password reset
- `submit_forget_pass.php` — Send reset email
- `confirm_password_reset.php` — New password form
- `process_password_reset.php` — Process reset
- `complete_password_reset.php` — Reset completion
- `password_reset_success.php` — Success page
- `reset_password.php` — Reset helper

**Dashboards:**
- `user_dashboard.php` (150KB) — User dashboard
- `admin1_dashboard.php` (395KB) — Admin 1 dashboard
- `admin2_dashboard.php` (560KB) — Admin 2 dashboard
- `Superadmin_dashboard.php` (176KB) — Superadmin dashboard

**Loan Management:**
- `loan_register.php` — Loan application form (4 steps)
- `loan_register_process.php` — Loan application processing
- `create_loan_process.php` — Loan creation + payment schedule
- `pay_balance.php` — Payment processing
- `create_loan_payment.php` — Payment creation helper
- `fetch_payment_schedule.php` — AJAX payment schedule fetch
- `get_loan_details.php` — Loan details API
- `get_loan_details_user.php` — User loan details API
- `get_payment_history.php` — Payment history API
- `get_loan_remarks.php` — Loan remarks API
- `get_interest_rate.php` — Interest rate fetch
- `update_application.php` — Application update
- `update_main_status.php` — Status update

**Records:**
- `active_records.php` (97KB) — Active loan records
- `pending_records.php` (62KB) — Pending loan records
- `closed_records.php` (53KB) — Closed loan records
- `archived_records.php` (35KB) — Archived records
- `user_active_record.php` (35KB) — User's active records
- `user_closed_records.php` (26KB) — User's closed records
- `user_history_activity.php` — User activity history

**Notifications:**
- `notifications.php` (58KB) — Notification page
- `NotificationManager.php` (25KB) — Notification class
- `NotificationManager_MySQLi.php` (16KB) — MySQLi notification class
- `NotificationStream.php` (9KB) — SSE stream
- `notification_center.php` (13KB) — Admin notification center
- `notification_widget.php` (18KB) — Dashboard notification widget
- `notification_api.php` (5KB) — Notification AJAX API
- `notification_manager.php` (26KB) — Notification management
- `sse_notifications.php` (4KB) — SSE endpoint
- `AdminNotificationIntegration.php` (13KB) — Admin notification integration
- `DocumentNotificationHandler.php` (13KB) — Document notification handler

**Credit Points:**
- `credit_points_manager.php` (9KB) — Credit points class
- `manage_credit_points.php` (46KB) — Credit points management page
- `manage_credit_points_new.php` (29KB) — New credit points page
- `auto_credit_points.php` (3KB) — Auto credit points script

**Reports:**
- `reports_analytics.php` (33KB) — Analytics dashboard
- `reports_record.php` (235KB) — Detailed records report

**Admin Management:**
- `add_admin.php` (59KB) — Add admin accounts
- `remove_admin.php` (1KB) — Remove admin accounts

**Profile:**
- `profile.php` (45KB) — User profile
- `profileAdmin1.php` (21KB) — Admin 1 profile
- `profileAdmin2.php` (23KB) — Admin 2 profile
- `profileSuperadmin.php` (27KB) — Superadmin profile
- `update_profile.php` — Profile update handler
- `update_adminProfile1.php` — Admin 1 profile update
- `update_adminProfile2.php` — Admin 2 profile update
- `update_superAdminProfile.php` — Superadmin profile update
- `update_gmail_password.php` — Gmail password update

**Interest Rates:**
- `interest_rate_api.php` — Interest rate API
- `manage_interest_rate_modal.php` — Interest rate modal
- `get_interest_rate.php` — Rate fetcher

**Email:**
- `email_sender.php` — Email sender class
- `email_config.php` — Email configuration
- `email_templates.php` (30KB) — Email templates
- `email_queue_functions.php` (16KB) — Queue functions
- `EmailQueueManager.php` (19KB) — Queue manager class
- `process_email_queue.php` (13KB) — Queue processor
- `send_email.php` — Simple email sender
- `setup_email_queue.php` — Queue setup

**Security:**
- `security_validation.php` (13KB) — Input validation functions
- `rate_limiter.php` (11KB) — Rate limiting class
- `philippines_validation.php` (23KB) — Philippine-specific validation
- `enhanced_email_validation.php` (5KB) — Email validation

**Documents:**
- `file_upload.php` — File upload handler
- `update_file.php` — File update handler
- `get_documents.php` — Document fetcher
- `get_pending_documents.php` — Pending document fetcher

**Activity:**
- `history_activity.php` (30KB) — Activity log viewer
- `CSS/log_activity.php` — Activity log helper

**Configuration:**
- `CYCLOAN_db.php` — Database connection
- `env_config.php` — Environment configuration
- `timezone_config.php` — Timezone settings
- `includes/config.php` — Legacy config
- `languages.php` (16KB) — Language translations

**Public Pages:**
- `landing_page.php` — Landing page
- `privacy_policy.php` — Privacy policy
- `whatis.php` — About page
- `steps.php` — Steps guide
- `process_consent.php` — Consent processing

**Utilities:**
- `get_barangays.php` — Barangay API
- `applicant.php` (75KB) — Applicant detail view
- `process_registration.php` — Registration processing
- `submit_registration.php` — Registration submission
- `process_otp1.php` — OTP processing

---

## 20. KEY BUSINESS RULES

1. **Age requirement**: Users must be at least 21 years old to register
2. **Voter registration**: Must be a registered COMELEC voter
3. **Calamba City residency**: System is designed for Calamba City residents
4. **One active loan**: Users cannot apply for a new loan while having an active or pending loan
5. **Loan amount range**: ₱10,000 to ₱1,000,000
6. **Term lengths**: Individual (6/12/18 months), Cooperative (6/12/18/24/36 months)
7. **Interest rates**: Configurable per term length, default 6% for 12 months
8. **Payment types**: Full, Interest-only, Principal-only, Custom split
9. **Loan closure**: Automatic when remaining_balance ≤ ₱0.01
10. **Credit points**: Awarded for on-time payments and loan completion; deducted for late payments and defaults
11. **Two-level approval**: Admin 1 (pre-approval + credit investigation) → Admin 2 (final approval + loan creation)
12. **OTP expiry**: 10 minutes, max 5 attempts
13. **Session timeout**: 40 minutes of inactivity
14. **File upload limits**: JPEG/PNG/PDF only, max 5MB per file
15. **Document requirements vary by loan type**: Individual needs 5 docs (+1 for agricultural), Cooperative needs 4 docs
16. **Currency**: Philippine Peso (₱) throughout
17. **Timezone**: Asia/Manila (UTC+8) for all timestamps
18. **Language support**: English and Tagalog

---

## 21. EXTERNAL INTEGRATIONS

1. **Gmail SMTP** — Email sending via PHPMailer
2. **PSGC API** — Philippine Standard Geographic Code API for barangay data
3. **COMELEC** — Referenced for voter registration requirements (no API integration)
4. **TCPDF** — PDF generation for invoices and receipts
5. **Redis** (optional) — Rate limiting and session storage
6. **PHPMailer** — Email library

---

## 22. SECURITY FEATURES

1. **CSRF tokens** — Per-session tokens on all forms
2. **Password hashing** — bcrypt via `password_hash()`
3. **OTP hashing** — bcrypt with cost 12
4. **Input sanitization** — All inputs validated via `security_validation.php`
5. **Rate limiting** — Registration, OTP, verification endpoints
6. **Session security** — HttpOnly, SameSite=Strict, Secure cookies, regular ID regeneration
7. **Security headers** — X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Content-Security-Policy
8. **2FA support** — Email, SMS, TOTP methods
9. **Activity logging** — All significant actions logged
10. **Login attempt logging** — All login attempts recorded
11. **Role-based access control** — 4 roles with separate dashboards
12. **File upload validation** — Type, size, and extension validation

---

*This document represents the complete feature set and architecture of the CYCLOAN Loan Management System as of October 2026.*
