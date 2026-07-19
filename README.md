# Winimi Bakery Backend

Laravel 12 + Filament 3 headless-commerce backend for the Winimi Bakery storefront.

The production frontend lives in `sajadkhavas/cooci`. This repository owns the API, Filament administration, database, inventory, authentication, orders and payments.

## Current status

| Area | Status |
|---|---|
| Laravel / Filament foundation | Implemented |
| System health and contract endpoints | Implemented |
| Bakery catalog, categories and Variants | Implemented in Phase 11 |
| Bakery catalog Filament management | Implemented in Phase 11 |
| Customer mobile OTP and secure sessions | Implemented in Phase 12 |
| Customer profile and Filament management | Implemented in Phase 12 |
| Legacy ToolMaster API | Preserved with deprecation headers |
| Checkout, orders and inventory transactions | Planned for Phase 13 |
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

- Composer metadata and patched dependencies
- backend foundation audit
- bakery catalog architecture audit
- customer OTP/session security audit
- scoped Laravel Pint formatting
- complete fresh migrations on SQLite
- cached configuration and routes
- Filament resource/component discovery
- catalog and authentication API behavior
- OTP expiry, cooldown, attempt limits and one-time consumption
- customer profile, session rotation and logout
- Composer security audit

## System API

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

All API responses include `X-Request-ID` and `X-API-Version`.

## Bakery catalog API

```text
GET /api/catalog/categories
GET /api/catalog/products
GET /api/catalog/products/{slug}
```

The public bakery catalog uses `bakery_categories`, `bakery_products` and `bakery_product_variants`. Price and inventory belong to active Variants and are calculated by the server. Detailed documentation is in `docs/CATALOG_API.md`.

## Customer authentication API

```text
POST  /api/auth/otp/request
POST  /api/auth/otp/verify
GET   /api/auth/me
POST  /api/auth/logout
PATCH /api/account/profile
```

Customer authentication is isolated from administrator authentication:

- Filament administrators remain in `users` with the `web` guard
- storefront customers live in `customers`
- storefront sessions use the `customer` guard
- OTP codes are stored only as secure hashes
- challenge mobile payloads are encrypted
- session IDs rotate after verification
- logout invalidates the session and CSRF token
- failed attempts, expiry, resend cooldown and one-time use are enforced
- rate limits are independently keyed by IP, mobile and challenge
- no trusted Bearer token is stored in LocalStorage

Detailed documentation is in `docs/CUSTOMER_AUTH.md` and `docs/API_CONTRACT.md`.

### Local OTP testing

External SMS is disabled by default. For local or automated testing only:

```env
APP_ENV=local
SMS_PROVIDER=testing
OTP_EXPOSE_TEST_CODE=true
```

Production must never expose the code:

```env
APP_ENV=production
APP_DEBUG=false
SMS_PROVIDER=kavenegar
OTP_EXPOSE_TEST_CODE=false
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

Server-only keys such as `KAVENEGAR_API_KEY` and `ZARINPAL_MERCHANT_ID` must never use `VITE_*` variables.

## Filament management

The navigation group `فروشگاه وینیمی` contains:

- دسته‌های بیکری
- محصولات بیکری
- مشتریان

Customer accounts cannot be manually created or bulk deleted from Filament. Mobile numbers are read-only because changing a mobile requires a dedicated verification flow. Administrators may inspect profile data and enable or disable an account.

## Frontend integration boundary

The target frontend is `sajadkhavas/cooci`. Until the dedicated integration phase, frontend authentication remains disabled in production configuration even though the backend contract is implemented.

```env
VITE_USE_BACKEND=true
VITE_API_BASE_URL=https://api.winimibakery.com
VITE_AUTH_MODE=disabled
VITE_PAYMENT_MODE=disabled
```

## CORS and session configuration

Local example:

```env
FRONTEND_URLS=http://localhost:5173,http://localhost:4173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:4173,localhost:8000
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_ENCRYPT=true
```

Production example:

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
```

## Legacy API policy

Existing `/api/v1/*` routes remain temporarily for incremental migration and return deprecation headers. No new Winimi commerce feature may be implemented under `/api/v1`.

## Deployment principle

A production release must run at minimum:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Production also requires HTTPS, a queue worker, scheduler, database backups, application/PHP logs, `APP_DEBUG=false` and server-only secrets.

## Phase roadmap

- Phase 10: foundation and migration boundary — complete
- Phase 11: bakery catalog, Variants, stock and Filament resources — complete
- Phase 12: OTP authentication, customer sessions and profile — complete
- Phase 13: checkout, orders and inventory transactions
- Phase 14: Zarinpal payment lifecycle
- Phase 15+: reviews, notifications, reporting and full frontend integration
