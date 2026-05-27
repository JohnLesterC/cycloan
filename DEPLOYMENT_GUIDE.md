# GitHub Setup & Deployment Guide

## Step 1: Create GitHub Repository

1. Go to [github.com](https://github.com) and log in (or create account)
2. Click **"+"** icon → **"New repository"**
3. Configure:
   - **Repository name**: `CYCLOAN`
   - **Description**: `Loan Management System`
   - **Visibility**: Public or Private (your choice)
   - **Initialize with README**: ❌ No (we already have one)
4. Click **"Create repository"**

## Step 2: Connect Local Git to GitHub

After creating the repo on GitHub, you'll see instructions. Follow these:

```bash
# Navigate to project directory
cd D:\xampp\htdocs\CYCLOAN

# Add GitHub remote (replace YOUR_USERNAME and REPO_NAME)
git remote add origin https://github.com/YOUR_USERNAME/CYCLOAN.git

# Rename branch to main
git branch -M main

# Push code to GitHub
git push -u origin main
```

## Step 3: Verify Push

1. Go to your GitHub repo
2. You should see all files there
3. README.md should display automatically

---

## Deploy to Render (Free Hosting)

### Step 1: Sign Up & Connect GitHub
1. Go to [render.com](https://render.com)
2. Click "Sign up"
3. Choose "Sign up with GitHub"
4. Authorize Render to access your repositories

### Step 2: Create Web Service
1. Dashboard → **"New +"** → **"Web Service"**
2. Select your `CYCLOAN` repository
3. Configure:
   - **Name**: `cycloan`
   - **Environment**: `PHP`
   - **Build Command**: (leave empty for PHP)
   - **Start Command**: Leave empty
   - **Region**: Closest to you

### Step 3: Add Environment Variables
Before deploying, click **"Environment"** and add:

| Key | Value |
|-----|-------|
| `DB_HOST` | Your database host (after you provision MySQL) |
| `DB_NAME` | `cycloan_db` |
| `DB_USER` | Your database username |
| `DB_PASS` | Your database password |
| `SMTP_HOST` | `smtp.gmail.com` (if using Gmail) |
| `SMTP_USER` | Your email |
| `SMTP_PASS` | Gmail app password |

### Step 4: Update PHP Code
Modify `CYCLOAN_db.php` to use environment variables:

```php
<?php
// Use environment variables (production)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'cycloan_db';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Manila');
?>
```

### Step 5: Deploy
Click **"Deploy"** button. Render will:
1. Pull code from GitHub
2. Install PHP
3. Start the application
4. Assign you a URL like: `https://cycloan.onrender.com`

### Step 6: Set Up Database
You'll need to provision a MySQL database:

**Option A: Use Render PostgreSQL (then convert)**
- Render offers free PostgreSQL
- You'd need to convert CYCLOAN to use PostgreSQL

**Option B: Use External MySQL (Recommended)**
- Use free tier from: AWS RDS, PlanetScale, or Bit.io
- Add credentials to Render environment variables

---

## Deploy to InfinityFree (Traditional Hosting)

1. Sign up at [infinityfree.net](https://infinityfree.net)
2. Create account and domain
3. Use FTP client (WinSCP, FileZilla) to upload files:
   - Host: `ftp.infinityfree.com`
   - Upload to `public_html/` folder
4. Database: cpanel → MySQL Databases
5. Import `database/cycloan_db.sql`

---

## Automatic Deployment with GitHub

After setting up Render:
- Every time you push to GitHub: `git push origin main`
- Render automatically deploys the changes
- Your live site updates within minutes

```bash
# Make changes locally
# ... edit files ...

# Commit and push
git add .
git commit -m "Your message"
git push origin main

# Check Render dashboard - deployment starts automatically!
```

---

## Quick Commands Reference

```bash
# Clone on another machine
git clone https://github.com/YOUR_USERNAME/CYCLOAN.git

# Make changes and push
git add .
git commit -m "Description of changes"
git push origin main

# Create feature branch
git checkout -b feature/new-feature
git push origin feature/new-feature

# Pull latest changes
git pull origin main
```

---

## Troubleshooting

### Push fails with "fatal: The current branch main has no upstream"
```bash
git push -u origin main
```

### Can't connect to database after deploying
- Check environment variables in Render dashboard
- Verify database credentials are correct
- Ensure firewall allows external connections

### Files not appearing on GitHub
```bash
git status  # Check what's staged
git add .   # Stage all files
git commit -m "Message"
git push origin main
```

### PHP errors showing live
- Check Render logs: Dashboard → Logs
- Add error logging to identify issues
- Update CYCLOAN_db.php error handling

---

## Next Steps

1. ✅ Create GitHub repository
2. ✅ Push code to GitHub
3. ✅ Set up Render account
4. ✅ Provision MySQL database
5. ✅ Add environment variables to Render
6. ✅ Deploy & test live
7. ✅ Set up custom domain (optional)

---

**Questions?** Check GitHub Issues or Render documentation.
