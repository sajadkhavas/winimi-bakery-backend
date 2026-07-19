# Winimi Bakery Backend

Laravel 12 + Filament 3 headless-commerce backend for the Winimi Bakery storefront.

The production frontend lives in `sajadkhavas/cooci`. This repository owns the API, Filament administration, database, catalog, customer authentication, orders, inventory, content operations, notifications and payments.

## Current status

| Area | Status |
|---|---|
| Laravel / Filament foundation | Implemented |
| System health and contract endpoints | Implemented |
| Bakery catalog, categories and Variants | Implemented in Phase 11 |
| Customer OTP architecture, secure sessions and profile | Implemented in Phase 12 |
| Checkout, orders and inventory reservations | Implemented in Phase 13 |
| Full internal launch roadmap | Locked in Phase 13.5 |
| Provider-ready payment lifecycle | Phase 14 |
| Complete store operations backend | Phase 15 |
| Backend completion and contract freeze | Phase 16 |
| Full frontend integration | Phase 17 |
| End-to-end completion | Phase 18 |
| Production deployment | Phase 19 |
| Three external activations only | Phase 20 |

Machine-readable status:

```text
GET /api/system/contracts
```

The locked roadmap is documented in `docs/FULL_LAUNCH_ROADMAP.md`.

## Requirements

- PHP 8.2+
- Composer 2
- SQLite for local development or MySQL 8 / MariaDB 10.6+
- PHP extensions: bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo, tokenizer, xml and zip

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Admin panel:

```text
http://localhost:8000/admin
```

No production admin password is documented or committed. Create or promote administrators through a controlled server-side process.

## Validation

```bash
composer check
```

GitHub Actions validates:

- Composer metadata and dependency security
- backend foundation, full-launch, catalog, customer-auth and order audits
- scoped Laravel Pint formatting
- complete fresh migrations on SQLite
- cached configuration and routes
- command and Filament resource discovery
- OTP and session security behavior
- server-authoritative checkout totals and immutable snapshots
- Idempotency replay and conflict behavior
- reservation-aware stock and Oversell prevention
- customer order ownership, cancellation and expiration
- exact three-item external activation boundary
- Composer security audit

## Implemented APIs

### System

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

### Bakery catalog

```text
GET /api/catalog/categories
GET /api/catalog/products
GET /api/catalog/products/{slug}
```

Catalog stock represents physical stock minus active unexpired order reservations.

### Customer authentication

```text
POST  /api/auth/otp/request
POST  /api/auth/otp/verify
GET   /api/auth/me
POST  /api/auth/logout
PATCH /api/account/profile
```

Customers are isolated from administrator users. Storefront sessions use the `customer` guard and OTP challenges are hashed, encrypted, attempt-limited and rate-limited.

### Checkout and orders

```text
POST /api/checkout
GET  /api/account/orders
GET  /api/account/orders/{orderId}
POST /api/account/orders/{orderId}/cancel
```

Checkout accepts only Variant IDs and quantities as cart truth, calculates all prices and fees on the server, creates immutable item snapshots and reserves inventory without decrementing physical stock before verified payment.

Expired reservations are released by:

```bash
php artisan inventory:release-expired
```

Detailed documentation:

- `docs/API_CONTRACT.md`
- `docs/CATALOG_API.md`
- `docs/CUSTOMER_AUTH.md`
- `docs/ORDERS_CHECKOUT.md`
- `docs/FULL_LAUNCH_ROADMAP.md`

## Safe defaults

OTP, checkout and payment are disabled until their operating configuration is approved:

```env
SMS_PROVIDER=disabled
OTP_EXPOSE_TEST_CODE=false
CHECKOUT_ENABLED=false
PAYMENT_PROVIDER=disabled
```

All delivery methods are also disabled by default. Backend secrets such as `KAVENEGAR_API_KEY` and `ZARINPAL_MERCHANT_ID` must never use `VITE_*` variables.

## Filament management

The navigation group `فروشگاه وینیمی` currently contains:

- دسته‌های بیکری
- محصولات بیکری
- مشتریان
- سفارش‌ها

Store operations, content, reviews, inquiries, notifications and controlled order actions are completed before the backend contract freeze in Phase 16.

## Frontend integration boundary

The frontend remains on its safe static/mock modes until the backend reaches `backend_complete=ready`. Phase 17 then replaces every production dynamic source with the frozen backend API.

```env
VITE_USE_BACKEND=true
VITE_API_BASE_URL=https://api.winimibakery.com
VITE_AUTH_MODE=disabled
VITE_PAYMENT_MODE=disabled
```

## Production baseline

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.winimibakery.com
FRONTEND_URLS=https://winimibakery.com,https://www.winimibakery.com
SANCTUM_STATEFUL_DOMAINS=winimibakery.com,www.winimibakery.com
SESSION_DOMAIN=.winimibakery.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
LEGACY_TOOLMASTER_API_ENABLED=false
CHECKOUT_ENABLED=false
PAYMENT_PROVIDER=disabled
```

A production release must run at minimum:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

The scheduler must run every minute. Production also requires HTTPS, persistent storage, queue workers, database backups, restore verification, logs, monitoring and a rollback procedure.

## Locked phase roadmap

- Phase 10: foundation and migration boundary — complete
- Phase 11: bakery catalog and administration — complete
- Phase 12: OTP authentication and customer account — complete
- Phase 13: checkout, orders and inventory reservations — complete
- Phase 13.5: full-launch audit and roadmap lock — current
- Phase 14: provider-ready payment backend
- Phase 15: complete store operations backend
- Phase 16: backend completion and contract freeze
- Phase 17: full frontend/backend integration
- Phase 18: end-to-end completion
- Phase 19: production server deployment
- Phase 20: external activation only

At the end of Phase 19, the only remaining inputs are the payment gateway credentials, eNAMAD badge code and SMS provider key/template.