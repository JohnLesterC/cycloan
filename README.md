# CYCLOAN - Loan Management System

A comprehensive PHP-based loan management system with user registration, loan applications, payment processing, and administrative dashboards.

## Features

- **User Management**: Registration, authentication, profile management
- **Loan Applications**: Multi-step application process with credit investigation
- **Payment Processing**: Loan payment scheduling and tracking
- **Interest Rate Management**: Dynamic interest rates by loan term (6, 12, 18, 24, 36 months)
- **Admin Dashboards**: Separate interfaces for superadmin, admin1, and admin2
- **Activity Logging**: Comprehensive audit trail
- **Email Notifications**: OTP verification, loan updates, payment reminders
- **Multi-language Support**: Flexible language configuration

## Tech Stack

- **Backend**: PHP 8.4.1 (procedural)
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (vanilla)
- **Server**: Apache 2.4+
- **Timezone**: Asia/Manila (UTC+8)

## Local Development Setup

### Prerequisites
- XAMPP (PHP + Apache + MySQL)
- PHP 8.4.1+
- MySQL 8.0+

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/CYCLOAN.git
   cd CYCLOAN
   ```

2. **Set up database**
   ```bash
   # Import the database dump
   mysql -u root -p < database/cycloan_db.sql
   ```

3. **Configure database connection** (Local only)
   - Edit `CYCLOAN_db.php` with your local credentials
   - Or use environment variables (see Deployment section)

4. **Start services**
   ```bash
   # Via XAMPP control panel or:
   php -S 0.0.0.0:8000 -t .
   ```

5. **Access the app**
   - Open: http://localhost:8000/registration.php

## Project Structure

```
├── CSS/                        # Stylesheets
├── JAVASCRIPT/                 # Client-side scripts
├── IMAGE/                      # Images and assets
├── DATABASE/                   # Database dumps and SQL files
├── phpmailer/                  # Email library
├── tcpdf/                      # PDF generation
├── CYCLOAN_db.php              # Database connection
├── registration.php            # User registration
├── login.php                   # User login
├── index.php                   # Home/dashboard
├── admin1_dashboard.php        # Admin1 interface
├── admin2_dashboard.php        # Admin2 interface
├── process_*.php               # Form handlers
└── README.md                   # This file
```

## Database Schema

### Key Tables
- `users1` - User accounts
- `loan_applications` - Loan requests
- `loans` - Active loans
- `payment_schedules` - Payment plans
- `interest_rates` - Interest rates by term
- `notifications` - System notifications
- `activity_logs` - Audit trail
- `email_queue` - Email dispatch queue

## Deployment

### Free Hosting Options

#### Option 1: Render (Recommended)
1. Push code to GitHub
2. Sign up at [render.com](https://render.com)
3. Create new Web Service from GitHub
4. Set environment variables:
   ```
   DB_HOST=your_db_host
   DB_NAME=cycloan_db
   DB_USER=username
   DB_PASS=password
   ```
5. Deploy automatically on every push

#### Option 2: InfinityFree
- Unlimited free PHP hosting
- Free MySQL database
- Upload via FTP
- Custom domain support

#### Option 3: Replit
- Quick PHP + MySQL setup
- Direct GitHub integration
- Good for development/testing

### Environment Variables

Create a `.env` file (never commit this):
```
DB_HOST=localhost
DB_NAME=cycloan_db
DB_USER=root
DB_PASS=password
SMTP_HOST=smtp.gmail.com
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
```

## Security Notes

⚠️ **Do NOT commit these files:**
- `.env` (database credentials)
- Local `CYCLOAN_db.php` configuration
- PHPMailer credentials

All sensitive data should use environment variables in production.

## API Endpoints (AJAX)

- `get_loan_details.php?id=X` - Fetch loan information
- `get_interest_rate.php?term_length=12` - Get current interest rate
- `process_registration.php` - Handle registration form
- `process_login.php` - Handle login form

## Common Issues & Solutions

### Database Connection Failed
- Ensure MySQL is running
- Verify `DB_HOST`, `DB_USER`, `DB_PASS` in CYCLOAN_db.php

### Session Warnings
- All `ini_set()` for session settings must come BEFORE `session_start()`
- See registration.php for proper order

### Email Not Sending
- Enable "Less secure app access" if using Gmail
- Or use app-specific passwords
- Check PHPMailer configuration in process_registration.php

## Development Workflow

1. Create feature branch: `git checkout -b feature/your-feature`
2. Make changes and test locally
3. Commit: `git commit -am "Add feature description"`
4. Push: `git push origin feature/your-feature`
5. Create Pull Request on GitHub

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a Pull Request

## License

Proprietary - CYCLOAN Loan Management System

## Support

For issues or questions, contact the development team.

## Recent Updates

- ✅ Fixed MySQLi extension (php.ini configuration)
- ✅ Rewrote interest rate management with term_length support
- ✅ Fixed session ini_set() warning (moved before session_start())
- ✅ Created comprehensive system documentation for interviews

---

**Last Updated**: May 27, 2026
**System Version**: 1.0.0
