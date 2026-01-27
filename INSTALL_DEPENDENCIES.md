# Installing Composer Dependencies

## Quick Fix

The system needs Composer dependencies installed. Run this command in the project directory:

```bash
cd c:\xampp\htdocs\clinic
composer install
```

## If Composer is Not Installed

1. Download Composer from: https://getcomposer.org/download/
2. Install it globally
3. Then run `composer install` in the project directory

## Alternative: Skip Laravel Pages

If you don't need Laravel features right now, you can access pages that don't require it:

- **Login**: `http://localhost/clinic/public/login.php`
- **Companies**: `http://localhost/clinic/public/company.php`
- **Patients**: `http://localhost/clinic/public/patients.php`
- **Medical List**: `http://localhost/clinic/public/medical_list.php`

These pages work without Composer dependencies.

## What Was Fixed

1. Made `vendor/autoload.php` optional for PDF generators
2. Added error handling in `index.php` to show a helpful message
3. All non-Laravel pages can now work without Composer

## After Installing Dependencies

Once `composer install` completes successfully, you can access:
- Laravel dashboard: `http://localhost/clinic/public/`
- All Laravel routes
