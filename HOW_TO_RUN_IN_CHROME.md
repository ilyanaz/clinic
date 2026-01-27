# How to Run the System in Chrome

## Method 1: Using XAMPP (Recommended)

### Step 1: Start XAMPP Services
1. Open **XAMPP Control Panel**
2. Start **Apache** (click "Start" button - should turn green)
3. Start **MySQL** (click "Start" button - should turn green)

### Step 2: Open Chrome Browser
1. Open **Google Chrome**
2. In the address bar, type one of these URLs:

**Login Page:**
```
http://localhost/clinic/public/login.php
```

**Dashboard (after login):**
```
http://localhost/clinic/public/index.php
```

**Or access via Laravel route:**
```
http://localhost/clinic/public/
```

### Step 3: Login
- **Username**: `admin`
- **Password**: `admin123`

---

## Method 2: Using Laravel Built-in Server

### Step 1: Open Command Prompt or PowerShell
Navigate to the project directory:
```bash
cd c:\xampp\htdocs\clinic
```

### Step 2: Start Laravel Server
```bash
php artisan serve
```

You should see:
```
INFO  Server running on [http://127.0.0.1:8000]
```

### Step 3: Open Chrome
Open Chrome and go to:
```
http://127.0.0.1:8000
```

or

```
http://localhost:8000
```

---

## Quick Access URLs

Once the system is running, you can access these pages directly:

### Main Pages:
- **Login**: `http://localhost/clinic/public/login.php`
- **Dashboard**: `http://localhost/clinic/public/index.php`
- **Companies**: `http://localhost/clinic/public/company.php`
- **Patients**: `http://localhost/clinic/public/patients.php`
- **Medical List**: `http://localhost/clinic/public/medical_list.php?company_id=1`

### Reports:
- **Employee Report**: `http://localhost/clinic/public/employee_report.php`
- **MS Report**: `http://localhost/clinic/public/ms_report.php`
- **Abnormal Workers**: `http://localhost/clinic/public/abnormal_workers_report.php`

---

## Troubleshooting

### If you see "404 Not Found":
1. Make sure Apache is running in XAMPP
2. Check the URL path is correct: `/clinic/public/`
3. Verify files exist in `c:\xampp\htdocs\clinic\public\`

### If you see "Database Connection Error":
1. Make sure MySQL is running in XAMPP
2. Import the database: `c:\xampp\htdocs\clinic3\medical.sql`
3. Check database credentials in `.env` file

### If you see "Access Denied":
1. Check MySQL password in `.env` file (should be empty for XAMPP)
2. Verify database `medical` exists in phpMyAdmin

### If pages don't load:
1. Check XAMPP error logs: `c:\xampp\apache\logs\error.log`
2. Check PHP error logs: `c:\xampp\php\logs\php_error_log`
3. Make sure all required files are in `public` directory

---

## Default Login Credentials

- **Username**: `admin`
- **Password**: `admin123`

---

## Quick Start Checklist

- [ ] XAMPP Control Panel is open
- [ ] Apache is running (green indicator)
- [ ] MySQL is running (green indicator)
- [ ] Database `medical` is imported
- [ ] Chrome browser is open
- [ ] URL: `http://localhost/clinic/public/login.php`

---

## Notes

- The system uses the `medical` database from `c:\xampp\htdocs\clinic3\medical.sql`
- All links have been updated to work correctly in Chrome
- The system will automatically create the database if it doesn't exist (via `clinic_database.php`)
