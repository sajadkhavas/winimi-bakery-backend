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
| Legacy ToolMaster API | Preserved with deprecation headers |
| Mobile OTP and account sessions | Planned for Phase 12 |
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
- scoped Laravel Pint formatting
- complete fresh migrations on SQLite
- cached configuration and routes
- Filament resource/component discovery
- public catalog API behavior
- real Filament create-form rendering
- Composer security audit

## System API

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

All API responses include:

```text
X-Request-ID
X-API-Version
```

## Bakery catalog API

```text
GET /api/catalog/categories
GET /api/catalog/products
GET /api/catalog/products/{slug}
```

Supported product-list filters:

```text
category
search
featured
requiresCooling
inStock
sort=featured|newest|name|price-asc|price-desc
page
perPage
```

The public bakery catalog is backed by independent commerce tables:

```text
bakery_categories
bakery_products
bakery_product_variants
```

The inherited industrial `products`, `categories`, `brands`, `subcategories` and RFQ tables are not used as bakery-commerce truth.

Catalog rules:

- public IDs are ULIDs rather than database sequence IDs
- price and stock belong to Variants
- current product price is calculated from active Variants
- inventory is calculated by the server
- inactive products and categories are not public
- products require at least one active Variant to be public
- ingredients, allergens and storage data are returned only after content verification
- media carries its verification state
- sale price must be lower than regular price

Detailed catalog documentation:

- `docs/CATALOG_API.md`
- `docs/API_CONTRACT.md`
- `docs/BACKEND_AUDIT.md`

## Filament catalog management

The admin navigation group `فروشگاه وینیمی` contains:

- `دسته‌های بیکری`
- `محصولات بیکری`

Product management includes:

- category, product code and slug
- short and complete descriptions
- cooling requirement and preparation time
- active and featured states
- ingredients and allergens
- content and media verification
- main image and gallery
- SEO fields
- Variant name and SKU
- weight
- regular and sale price
- stock quantity and low-stock threshold
- active and default Variant state

The Filament panel uses the Winimi Bakery identity and no longer depends on the inherited frontend Vite manifest.

## Frontend integration boundary

The target frontend is `sajadkhavas/cooci`.

Production frontend variables will eventually use:

```env
VITE_USE_BACKEND=true
VITE_API_BASE_URL=https://api.winimibakery.com
VITE_AUTH_MODE=disabled
VITE_PAYMENT_MODE=disabled
```

Catalog data can be integrated after the dedicated frontend/backend integration phase. Authentication and payment must remain disabled until their server contracts are implemented.

Backend secrets such as SMS provider keys and Zarinpal Merchant ID must never use `VITE_*` variables.

## Authentication architecture

The final customer flow will use:

- Iranian mobile OTP
- short-lived hashed OTP challenges
- Laravel server-managed sessions
- HttpOnly cookies
- session rotation after verification
- explicit credentialed CORS origins
- no trusted Bearer token in LocalStorage

The inherited email/password Bearer-token endpoints under `/api/v1/auth/*` are legacy and must not be used by the new frontend.

## Legacy API policy

Existing `/api/v1/*` routes are preserved temporarily for incremental migration. Responses contain:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

Disable the legacy layer in production when migration no longer requires it:

```env
LEGACY_TOOLMASTER_API_ENABLED=false
```

No new Winimi commerce feature may be implemented under `/api/v1`.

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

## Security baseline

Required patched branches include:

- Laravel Framework `12.61.1+`
- Filament `3.3.53+`
- Guzzle `7.12.1+`
- PSR-7 `2.12.1+`
- Laravel 12 compatible Filament Authentication Log plugin

`composer audit` is mandatory in CI.

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
- Phase 12: OTP authentication and customer account
- Phase 13: checkout, orders and inventory transactions
- Phase 14: Zarinpal payment lifecycle
- Phase 15+: reviews, notifications, reporting and full frontend integration
