# Leads API Project

Starter scaffold for the Leads API Project with:

- MilesWeb-friendly PHP folder structure
- Admin authentication using MySQL with a database-backed admin account
- Session-protected dashboard
- Responsive login page built with HTML, CSS, JavaScript, and Bootstrap 5

## Current structure

- `config/` bootstrap, env loader, helpers
- `controllers/` auth and dashboard controllers
- `models/` database models
- `services/` auth service
- `static/` CSS, JS, images
- `Templates/` HTML view templates
- `uploads/` upload storage
- `index.php` front controller
- `routes.php` simple route map
- `.env` app and database credentials

## Local run

Use any PHP server that points to this folder, or run it through XAMPP/Apache.

### XAMPP setup

1. Copy this project into your XAMPP web root, for example `htdocs\leding-page-`.
2. Make sure Apache is running in XAMPP.
3. Start MySQL in XAMPP.
4. Copy `.env.example` to `.env` and keep the local XAMPP values:

```env
APP_URL="http://localhost:8080/leding-page-"
ADMIN_EMAIL="admin@gmail.com"
ADMIN_PASSWORD="admin@123"
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="lead_management"
DB_USER="root"
DB_PASSWORD=""
DB_SOCKET="/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
SESSION_SAVE_PATH=""
```

5. Create the `lead_management` database.
6. Import `database/schema.sql` into phpMyAdmin or MySQL.
7. Open `http://localhost:8080/leding-page-/login`.

If Apache rewrite rules are enabled, the included `.htaccess` will route requests through `index.php`.

On macOS XAMPP, this project now prefers the TCP host from `.env` and only uses a Unix socket when `DB_SOCKET` is explicitly set. That avoids connecting to the wrong local MySQL instance when both XAMPP and another MySQL server are installed.

### Built-in PHP server

If PHP is installed on your machine, you can also run:

```bash
php -S localhost:8080
```

Then open `http://localhost:8080/login`.

## Database settings

The app reads database settings from `.env` through `config/Database.php`. You do not need to change the PHP code when moving between XAMPP and MilesWeb. Only update the `.env` values for that environment.

### Local XAMPP example

```env
APP_URL="http://localhost:8080/leding-page-"
ADMIN_EMAIL="admin@gmail.com"
ADMIN_PASSWORD="admin@123"
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="lead_management"
DB_USER="root"
DB_PASSWORD=""
DB_SOCKET="/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
DB_CHARSET="utf8mb4"
SESSION_SAVE_PATH=""
```

XAMPP on Windows usually works with `127.0.0.1` and port `3306`. On macOS XAMPP, use `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock` for `DB_SOCKET`.

### MilesWeb example

```env
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME="your_milesweb_database"
DB_USER="your_milesweb_database_user"
DB_PASSWORD="your_milesweb_database_password"
DB_SOCKET=""
DB_CHARSET="utf8mb4"
SESSION_SAVE_PATH=""
```

If MilesWeb gives you a different MySQL hostname in cPanel, use that value instead of `localhost`.

If `SESSION_SAVE_PATH` is empty, the app automatically stores sessions in `uploads/sessions`, which avoids permission problems with default PHP temp folders on many local and shared-host setups.

## Admin login

Default admin login after the schema is installed:

- Email: `admin@gmail.com`
- Password: `admin@123` or the value of `ADMIN_PASSWORD` in `.env`

The app creates the `users` table and seeds the default admin once. Authentication reads the hashed password from MySQL, and the initial seed now uses `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`.
