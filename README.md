# Winimi Bakery Backend

Laravel 12 + Filament 3 headless-commerce backend for the Winimi Bakery storefront.

The production frontend lives in `sajadkhavas/cooci`. This repository owns the API, Filament administration, database, catalog, customer authentication, checkout, orders, inventory, payments, content operations and notifications.

## Current status

| Area | Status |
|---|---|
| Laravel / Filament foundation | Implemented |
| System health and contract endpoints | Implemented |
| Bakery catalog, categories and Variants | Implemented in Phase 11 |
| Customer OTP, secure sessions and profile | Implemented in Phase 12 |
| Checkout, orders and inventory reservations | Implemented in Phase 13 |
| Full internal launch roadmap | Locked in Phase 13.5 |
| Provider-ready payment lifecycle | Implemented in Phase 14 |
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

No production administrator password or external credential is committed.

## Validation

```bash
composer check
```

Focused audits:

```bash
composer audit:catalog
composer audit:auth
composer audit:orders
composer audit:payments
composer audit:launch
```

GitHub Actions validates Composer metadata, fresh SQLite migrations, cached configuration/routes, Filament discovery, Pint formatting, all architecture audits, API/Filament tests and dependency security.

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

Catalog availability represents physical stock minus active unexpired reservations.

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

Checkout accepts only Variant IDs and quantities as cart truth, calculates prices and fees on the server, creates immutable snapshots and reserves inventory without decrementing physical stock.

Expired reservations are released by:

```bash
php artisan inventory:release-expired
```

### Payments

```text
POST /api/orders/{orderId}/payments
POST /api/payments/verify
POST /api/payments/zarinpal/verify
```

Phase 14 provides:

- persistent payment attempts
- provider-neutral initiation and verification contracts
- disabled, deterministic testing and Zarinpal providers
- customer ownership and idempotency enforcement
- pending-attempt reuse and controlled retry
- server-only amount and credential handling
- atomic verified-payment, order and inventory transition
- duplicate callback protection
- read-only Filament payment inspection
- sanitization of provider and card metadata

Checkout and payment remain separate operations. A callback status never marks an order paid without provider verification.

Detailed documentation:

- `docs/API_CONTRACT.md`
- `docs/CATALOG_API.md`
- `docs/CUSTOMER_AUTH.md`
- `docs/ORDERS_CHECKOUT.md`
- `docs/PAYMENTS.md`
- `docs/FULL_LAUNCH_ROADMAP.md`

## Safe defaults

```env
SMS_PROVIDER=disabled
OTP_EXPOSE_TEST_CODE=false
CHECKOUT_ENABLED=false
PAYMENT_ENABLED=false
PAYMENT_PROVIDER=disabled
ZARINPAL_MERCHANT_ID=
```

All delivery methods are also disabled by default. Backend secrets such as `KAVENEGAR_API_KEY` and `ZARINPAL_MERCHANT_ID` must never use `VITE_*` variables.

Local payment flow testing can explicitly use:

```env
PAYMENT_ENABLED=true
PAYMENT_PROVIDER=testing
```

The testing provider refuses production execution. Zarinpal refuses execution without a server-side Merchant ID.

## Filament management

The navigation group `فروشگاه وینیمی` currently contains:

- دسته‌های بیکری
- محصولات بیکری
- مشتریان
- سفارش‌ها
- تلاش‌های پرداخت

Payment attempts are read-only. They cannot be manually created, edited or bulk-deleted.

## Frontend integration boundary

The frontend remains on safe static/mock modes until the backend reaches `backend_complete=ready` in Phase 16. Phase 17 then connects every production dynamic flow to the frozen API.

```env
VITE_USE_BACKEND=true
VITE_API_BASE_URL=https://api.winimibakery.com
VITE_AUTH_MODE=disabled
VITE_PAYMENT_MODE=disabled
```

No provider credential is exposed through frontend variables.

## Production baseline before external activation

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
PAYMENT_ENABLED=false
PAYMENT_PROVIDER=disabled
ZARINPAL_MERCHANT_ID=
```

A production release must run at minimum:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

The scheduler runs every minute. Production also requires persistent storage, queue workers, backups with restore verification, logs, monitoring and rollback.

## Locked phase roadmap

- Phase 10: foundation and migration boundary — complete
- Phase 11: bakery catalog and administration — complete
- Phase 12: OTP authentication and customer account — complete
- Phase 13: checkout, orders and inventory reservations — complete
- Phase 13.5: full-launch audit and roadmap lock — complete
- Phase 14: provider-ready payment backend — complete
- Phase 15: complete store operations backend — next
- Phase 16: backend completion and contract freeze
- Phase 17: full frontend/backend integration
- Phase 18: end-to-end completion
- Phase 19: production server deployment
- Phase 20: external activation only

At the end of Phase 19, the only remaining inputs are payment gateway credentials, the eNAMAD badge code and the SMS provider key/template.