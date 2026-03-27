# Leads API Project

Starter scaffold for the Leads API Project with:

- MilesWeb-friendly PHP folder structure
- Admin authentication using MySQL on XAMPP, with `.env` fallback credentials
- Session-protected dashboard
- Responsive login page built with HTML, CSS, JavaScript, and Bootstrap 5

## Current structure

- `config/` bootstrap, env loader, helpers
- `controllers/` auth and dashboard controllers
- `models/` database models
- `services/` auth service
- `static/` CSS, JS, images
- `Templates/` PHP view templates
- `uploads/` future Excel uploads
- `index.php` front controller
- `routes.php` simple route map
- `.env` app and database credentials

## Local run

Use any PHP server that points to this folder, or run it through XAMPP/Apache.

### XAMPP setup

1. Copy this project into your XAMPP web root, for example:
   `htdocs/Lead_Management_-_Integration`
2. Make sure Apache is running in XAMPP.
3. Start MySQL in XAMPP.
4. Import [database/schema.sql](/Users/apple/Desktop/lending page/Lead_Management_-_Integration/database/schema.sql) into phpMyAdmin or MySQL.
5. Open:
   `http://localhost:8080/Lead_Management_-_Integration/login`

If Apache rewrite rules are enabled, the included `.htaccess` will route requests through `index.php`.

### Built-in PHP server

If PHP is installed on your machine, you can also run:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/login`.

## Database settings

Update `.env`:

```env
DB_HOST="127.0.0.1"
DB_PORT="3000"
DB_NAME="lead_management"
DB_USER="root"
DB_PASSWORD=""
DB_SOCKET="/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
```

XAMPP uses `root` with an empty password by default unless you changed it.

## Admin login

Default imported admin:

- Email: `admin@example.com`
- Password: `password`

The app first checks the `admins` table in MySQL. If the database is unavailable, it falls back to `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`.

## Next steps

- Build lead upload module
- Add Excel parsing and mapping
- Add API push logs and dashboard metrics
