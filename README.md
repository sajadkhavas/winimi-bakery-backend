# Winimi Bakery Backend

Laravel 11 + Filament 3 backend for the Winimi Bakery storefront.

> Phase 10 establishes the backend foundation and migration boundary. The previous ToolMaster catalog remains available temporarily under `/api/v1/*`, but it is not the final bakery commerce contract.

## Current status

| Area | Status |
|---|---|
| Laravel / Filament foundation | Ready |
| System health and contract endpoints | Implemented |
| Legacy ToolMaster API | Preserved with deprecation headers |
| Bakery catalog and variants | Planned for Phase 11 |
| Mobile OTP and account sessions | Planned for Phase 12 |
| Checkout, orders and inventory | Planned for Phase 13 |
| Zarinpal payment | Planned for Phase 14 |

Machine-readable status:

```text
GET /api/system/contracts
```

## Requirements

- PHP 8.2+
- Composer 2
- SQLite for local development or MySQL 8 / MariaDB 10.6+
- required PHP extensions: bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo, tokenizer, xml, zip

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

No production admin password is documented or committed. Create or promote an administrator through a controlled server-side process.

## Validation

```bash
composer check
```

The CI pipeline validates:

- Composer metadata and dependency security
- Laravel boot and route cache
- database migrations on SQLite
- Pint formatting
- unit and feature tests
- system API contract
- absence of active ToolMaster identity in the Winimi foundation

## System endpoints

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

All system responses include:

```text
X-Request-ID
X-API-Version
```

A caller-provided `X-Request-ID` is returned when valid; otherwise the server creates a UUID.

## Frontend integration

The target frontend is `sajadkhavas/cooci`.

Frontend production settings will eventually use:

```env
VITE_USE_BACKEND=true
VITE_API_BASE_URL=https://api.winimibakery.com
VITE_AUTH_MODE=disabled
VITE_PAYMENT_MODE=disabled
```

Backend secrets such as SMS provider keys and Zarinpal Merchant ID must never use a `VITE_*` variable.

The exact contract is documented in:

- `docs/API_CONTRACT.md`
- `docs/BACKEND_AUDIT.md`
- frontend document `docs/account-auth-api-contract.md` in `cooci`

## Authentication architecture

The final customer flow will use:

- Iranian mobile OTP
- short-lived hashed OTP challenges
- Laravel server-managed sessions
- HttpOnly cookies
- session rotation after verification
- explicit CORS origins
- `credentials: include`
- no trusted Bearer token in LocalStorage

The old email/password Bearer-token API under `/api/v1/auth/*` is legacy and must not be used by the new frontend.

## Legacy API policy

Existing `/api/v1/*` routes are temporarily preserved to support incremental data migration. Their responses contain:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

Do not add new Winimi commerce features under `/api/v1`.

## CORS and session configuration

Local example:

```env
FRONTEND_URLS=http://localhost:5173,http://localhost:4173
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:4173,localhost:8000
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
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
LEGACY_TOOLMASTER_API_ENABLED=false
```

## Repository boundary

This repository still contains frontend files inherited from the previous project. They are not the production Winimi frontend and are not part of the Laravel deployment contract. The production frontend lives in `sajadkhavas/cooci`.

Removal of the inherited frontend snapshot will be completed after verifying that no custom Filament theme or build step depends on it.

## Deployment principle

A production release must run at minimum:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Also configure:

- HTTPS
- queue worker
- scheduler
- database backup
- application and PHP logs
- Redis where appropriate
- `APP_DEBUG=false`
- secret values only in the server environment

## Phase roadmap

- Phase 10: foundation and migration boundary
- Phase 11: bakery catalog, variants, stock and Filament resources
- Phase 12: OTP authentication and customer account
- Phase 13: checkout, orders and inventory transactions
- Phase 14: Zarinpal payment lifecycle
- Phase 15+: reviews, notifications, reporting and final frontend integration
