# POS System

Full-stack Point of Sale system with Laravel backend and Angular frontend, packaged with Docker for easy deployment.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11, PHP 8.3, MySQL 8.0 |
| Frontend | Angular 18, Nebular UI, ngx-translate |
| Auth | Laravel Sanctum (JWT) |
| Payments | Stripe (test/live mode) |
| Deployment | Docker, Nginx, Apache |

## Features

- **Sales & POS** - Quick sale interface with barcode support, tax calculation, and Stripe payments
- **Products & Inventory** - Product management with categories, warehouses, stock tracking, and low-stock alerts
- **Customers & Suppliers** - CRM with statements, credit notes, and supplier ledger
- **Purchase Orders** - Full procurement cycle: PO creation, goods receipts, supplier payments
- **Returns & Refunds** - Partial/full returns with automatic store credit calculation
- **Cash Management** - Shift opening/closing with cash session summaries
- **Reports** - Sales, profit, product, and customer analytics dashboards
- **Multi-user & Roles** - Role-based access control with granular permissions
- **i18n** - Arabic (default) and English support
- **Currency & FX** - Multi-currency with live exchange rates

## Project Structure

```
pos_system/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── OpenApi.php      # Swagger annotations
│   ├── routes/api.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── config/
│   ├── Dockerfile
│   └── .env.example
├── frontend/                # Angular SPA
│   ├── src/
│   │   └── app/
│   │       ├── pages/       # POS page components
│   │       ├── services/    # API services
│   │       ├── @theme/      # UI theme & layouts
│   │       └── @core/       # Core services
│   ├── nginx.conf
│   └── Dockerfile
├── docker-compose.yml
└── .env.example
```

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Git

### 1. Clone & Configure

```bash
git clone https://github.com/Rasheedowida/pos_system.git
cd pos_system
cp backend/.env.example backend/.env
```

Edit `backend/.env` with your settings:

```env
APP_KEY=              # Generate: php artisan key:generate
DB_PASSWORD=          # MySQL password
STRIPE_KEY=           # pk_test_... or pk_live_...
STRIPE_SECRET=        # sk_test_... or sk_live_...
FXFEED_API_KEY=       # Exchange rate API key
ADMIN_PASSWORD=       # Initial admin password
```

### 2. Start with Docker

```bash
# Production (no phpMyAdmin)
docker compose up -d

# Development (with phpMyAdmin on port 8080)
docker compose --profile dev up -d
```

Services:
- **Frontend**: http://localhost
- **Backend API**: http://localhost:8000/api/v1
- **phpMyAdmin**: http://localhost:8080 (dev profile only)

### 3. Initialize Database

```bash
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
```

Default admin login:
- Email: `admin@example.com`
- Password: (set via `ADMIN_PASSWORD` in `.env`)

## Local Development

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Frontend

```bash
cd frontend
npm install
npm start
```

Frontend runs on http://localhost:4200 by default.

## API Overview

All endpoints are prefixed with `/api/v1` and require Sanctum authentication (except login).

| Module | Endpoints | Permissions |
|--------|-----------|-------------|
| Auth | `POST /login`, `GET /me`, `POST /logout` | Public (login) |
| Users | CRUD `/users` | `users.*` |
| Roles | CRUD `/roles` | `roles.*` |
| Products | CRUD `/products`, `/products/reference-data` | `products.*` |
| Categories | CRUD `/categories` | `categories.*` |
| Customers | CRUD `/customers`, `/customers/{id}/statement` | `customers.*` |
| Suppliers | CRUD `/suppliers`, `/suppliers/{id}/ledger`, `/suppliers/{id}/payments` | `suppliers.*` |
| Inventory | `/inventory/balances`, `/inventory/adjustments`, `/inventory/damages`, `/inventory/transfers` | `inventory.*` |
| Stock Movements | `GET /stock-movements` | `inventory.view` |
| Orders (Sales) | CRUD `/orders`, `/pos/products`, `/pos/reference-data` | `sales.*` |
| Returns | `POST /orders/{id}/returns` | `sales.return` |
| Purchase Orders | CRUD `/purchase-orders`, `/purchase-orders/{id}/send`, `/receipts` | `purchases.*` |
| Cash Sessions | `/shifts`, `/shifts/{id}/summary`, `/shifts/{id}/close` | `shifts.*` |
| Customer Payments | `POST /customers/{id}/payments` | `customers.edit` |
| Supplier Payments | `POST /suppliers/{id}/payments` | `suppliers.edit` |
| Reports | `/reports/overview`, `/reports/sales`, `/reports/products`, `/reports/customers`, `/reports/profit` | `reports.*` |
| Stripe | `POST /payment/create-intent` | `sales.create` |
| Settings | `GET /settings/public`, `GET/PUT /settings` | `settings.*` |
| Audit Logs | `GET /audit-logs` | `audit_logs.view` |

## Permissions

Granular permission system with these modules:

| Module | Permissions |
|--------|------------|
| `users` | view, create, edit, delete |
| `roles` | view, create, edit, delete |
| `products` | view, create, edit, delete |
| `categories` | view, create, edit, delete |
| `customers` | view, create, edit, delete |
| `suppliers` | view, create, edit, delete |
| `inventory` | view, adjust, count |
| `sales` | create, view, return |
| `purchases` | view, create, edit, delete |
| `shifts` | open, close |
| `reports` | view_financial, view_sales |
| `settings` | view, edit |
| `audit_logs` | view |

## Environment Variables

### Backend (`backend/.env`)

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_KEY` | Laravel encryption key | (generated) |
| `APP_URL` | Application URL | `http://localhost` |
| `DB_HOST` | MySQL host | `127.0.0.1` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_DATABASE` | Database name | `pos_system` |
| `DB_USERNAME` | Database user | `root` |
| `DB_PASSWORD` | Database password | - |
| `STRIPE_KEY` | Stripe publishable key | - |
| `STRIPE_SECRET` | Stripe secret key | - |
| `FXFEED_API_KEY` | Exchange rate API key | - |
| `ADMIN_EMAIL` | Default admin email | `admin@example.com` |
| `ADMIN_PASSWORD` | Default admin password | - |
| `SANCTUM_STATEFUL_DOMAINS` | Domains for Sanctum auth | `localhost,localhost:3000` |
| `CORS_ALLOWED_ORIGINS` | Allowed CORS origins | `http://localhost:4200` |

### Docker Compose

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_ROOT_PASSWORD` | MySQL root password | `root` |
| `BACKEND_PORT` | Backend exposed port | `8000` |
| `FRONTEND_PORT` | Frontend exposed port | `80` |
| `PHPMYADMIN_PORT` | phpMyAdmin port (dev) | `8080` |

## Testing

```bash
cd backend

# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact tests/Feature/SalesReturnServiceTest.php

# Run with filter
php artisan test --compact --filter=test_name
```

## Deployment

### Production Build

```bash
# Build and start all services
docker compose -f docker-compose.yml up -d --build

# Run migrations
docker compose exec backend php artisan migrate --force

# Seed initial data (optional)
docker compose exec backend php artisan db:seed --force
```

### SSL/HTTPS

Place your SSL certificate files and update `frontend/nginx.conf` to listen on port 443 with SSL directives.

### Environment-Specific

```bash
# Create .env for production
cp backend/.env.example backend/.env.production

# Set strong values
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
DB_PASSWORD=strong_password_here
```

## License

Private project. All rights reserved.
