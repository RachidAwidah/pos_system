# Copilot Instructions

This repository is a Laravel 13 application.

When working in this project:

- Prefer Laravel conventions and keep changes minimal.
- Use `php artisan` for framework tasks when possible.
- Keep `.env` values local and do not commit secrets.
- Treat `database/database.sqlite` as the default local database when SQLite is used.
- Check `composer.json` and `package.json` before introducing new dependencies.
- Prefer updating existing files over adding new abstractions unless required.
- If you add or change frontend assets, keep the Vite build flow in mind.

Common commands:

- `composer install`
- `php artisan key:generate`
- `php artisan migrate`
- `npm install`
- `npm run dev`
- `npm run build`

If a Dockerfile or deployment-related file is being edited, preserve compatibility with the Laravel public document root and the current SQLite-based local setup.
