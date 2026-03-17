# Leads API Project

Starter scaffold for the Leads API Project with:

- MilesWeb-friendly PHP folder structure
- Admin authentication using `.env` credentials
- Session-protected dashboard
- Responsive login page built with HTML, CSS, JavaScript, and Bootstrap 5

## Current structure

- `config/` bootstrap, env loader, helpers
- `controllers/` auth and dashboard controllers
- `models/` reserved for future database models
- `services/` auth service
- `static/` CSS, JS, images
- `Templates/` PHP view templates
- `uploads/` future Excel uploads
- `index.php` front controller
- `routes.php` simple route map
- `.env` admin credentials

## Local run

Use any PHP server that points to this folder:

```bash
php -S localhost:8000
```

Then open `/login`.

## Admin credentials

Update `.env`:

```env
ADMIN_EMAIL="your-admin-email@example.com"
ADMIN_PASSWORD="your-secure-password"
```

## Next steps

- Add database configuration in `config/`
- Build lead upload module
- Add Excel parsing and mapping
- Add API push logs and dashboard metrics
