# Winimi Bakery Backend

Laravel 12 + Filament 3 headless-commerce backend for the Winimi Bakery storefront.

The production frontend lives in `sajadkhavas/cooci`. This repository owns the API, Filament administration, database, catalog, customer authentication, orders, inventory and payments.

## Current status

| Area | Status |
|---|---|
| Laravel / Filament foundation | Implemented |
| System health and contract endpoints | Implemented |
| Bakery catalog, categories and Variants | Implemented in Phase 11 |
| Customer OTP, secure sessions and profile | Implemented in Phase 12 |
| Checkout, orders and inventory reservations | Implemented in Phase 13 |
| Order inspection in Filament | Implemented in Phase 13 |
| Legacy ToolMaster API | Preserved with deprecation headers |
| Zarinpal payment lifecycle | Planned for Phase 14 |

Machine-readable status:

```text
GET /api/system/contracts
```

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
- backend foundation, catalog, customer-auth and order audits
- scoped Laravel Pint formatting
- complete fresh migrations on SQLite
- cached configuration and routes
- command and Filament resource discovery
- OTP and session security behavior
- server-authoritative checkout totals and immutable snapshots
- Idempotency replay and conflict behavior
- reservation-aware stock and Oversell prevention
- customer order ownership, cancellation and expiration
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

Checkout requires an authenticated active customer and a unique `Idempotency-Key` header. The server:

- accepts only Variant IDs and quantities as cart truth
- locks Variant rows in a stable order
- calculates all prices and fees
- creates immutable order-item snapshots
- enforces cooling delivery rules
- reserves inventory without decrementing physical stock
- returns the same order for an exact idempotent replay
- returns HTTP 409 when a key is reused for a different request

Expired reservations are released automatically:

```bash
php artisan inventory:release-expired
```

Detailed documentation:

- `docs/API_CONTRACT.md`
- `docs/CATALOG_API.md`
- `docs/CUSTOMER_AUTH.md`
- `docs/ORDERS_CHECKOUT.md`

## Safe local configuration

OTP and checkout are disabled by default. Local OTP testing may use:

```env
APP_ENV=local
SMS_PROVIDER=testing
OTP_EXPOSE_TEST_CODE=true
```

Local checkout testing requires explicit activation and delivery choices:

```env
CHECKOUT_ENABLED=true
DELIVERY_STANDARD_ENABLED=true
DELIVERY_STANDARD_FEE_TOMAN=0
DELIVERY_CHILLED_ENABLED=true
DELIVERY_CHILLED_FEE_TOMAN=0
DELIVERY_PICKUP_ENABLED=true
DELIVERY_PICKUP_FEE_TOMAN=0
```

Production delivery methods and fees must be approved before activation. Payment remains unavailable until Phase 14.

## Filament management

The navigation group `فروشگاه وینیمی` contains:

- دسته‌های بیکری
- محصولات بیکری
- مشتریان
- سفارش‌ها

Orders are read-only in Phase 13. Operational status transitions will be expanded alongside verified payment and fulfillment rules.

## Frontend integration boundary

The target frontend is `sajadkhavas/cooci`. Until the dedicated integration phase, production frontend modes remain disabled even though backend catalog, auth and order contracts are implemented.

```env
VITE_USE_BACKEND=true
VITE_API_BASE_URL=https://api.winimibakery.com
VITE_AUTH_MODE=disabled
VITE_PAYMENT_MODE=disabled
```

Backend secrets such as SMS keys and Zarinpal Merchant ID must never use `VITE_*` variables.

## Production session and checkout baseline

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

Keep checkout disabled until real delivery methods and fees are configured. Keep payment disabled until Phase 14 verification is deployed.

## Deployment principle

A production release must run at minimum:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

The scheduler must run every minute so expired inventory reservations are released. Production also requires HTTPS, a queue worker, database backups, application/PHP logs, `APP_DEBUG=false` and server-only secrets.

## Phase roadmap

- Phase 10: foundation and migration boundary — complete
- Phase 11: bakery catalog, Variants, stock and Filament resources — complete
- Phase 12: OTP authentication, customer sessions and profile — complete
- Phase 13: checkout, orders and inventory reservations — complete
- Phase 14: Zarinpal payment lifecycle
- Phase 15+: reviews, notifications, reporting and full frontend integration
