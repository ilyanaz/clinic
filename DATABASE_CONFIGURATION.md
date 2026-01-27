# Database Configuration

## Database Information

The system uses **only one database**: `medical`

- **Database File**: `c:\xampp\htdocs\clinic3\medical.sql`
- **Database Name**: `medical`
- **Host**: `localhost`
- **User**: `root`
- **Password**: (empty - XAMPP default)

## Configuration Files

### 1. PHP Files Configuration
**File**: `public/config/clinic_database.php`

```php
define('CLINIC_DB_HOST', 'localhost');
define('CLINIC_DB_NAME', 'medical');  // Database name (matches medical.sql)
define('CLINIC_DB_USER', 'root');
define('CLINIC_DB_PASS', '');  // XAMPP default: empty password
```

✅ **Status**: Already configured correctly

### 2. Laravel Configuration
**File**: `.env`

Should contain:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medical
DB_USERNAME=root
DB_PASSWORD=
```

## Importing the Database

If the database doesn't exist, you can import it using:

1. **Via phpMyAdmin**:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create database `medical` (if not exists)
   - Import `c:\xampp\htdocs\clinic3\medical.sql`

2. **Via Command Line**:
   ```bash
   mysql -u root -p medical < c:\xampp\htdocs\clinic3\medical.sql
   ```
   (Press Enter when prompted for password if using empty password)

3. **Via XAMPP MySQL**:
   ```bash
   cd c:\xampp\mysql\bin
   mysql.exe -u root medical < c:\xampp\htdocs\clinic3\medical.sql
   ```

## Verification

To verify the database is connected:

1. Access any page in the system
2. Check for database connection errors
3. The system will automatically create the database if it doesn't exist (via `clinic_database.php`)

## Important Notes

- **Single Database**: The entire system uses only the `medical` database
- **No Separate Databases**: All tables (users, patients, companies, etc.) are in the same `medical` database
- **Auto-Creation**: The `clinic_database.php` file will attempt to create the database if it doesn't exist
- **Schema Updates**: The `clinic_schema_updates.php` file ensures all required columns exist

## Database Tables

The `medical` database contains all system tables:
- `users` - System users
- `patient_information` - Patient data
- `company` - Company information
- `surveillance_metadata` - Medical surveillance records
- `audiometric_tests` - Audiometric test data
- And many more...

All tables are defined in `medical.sql`.
