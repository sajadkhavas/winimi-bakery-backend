# Winimi Bakery Backend

Laravel 12 + Filament 3 headless-commerce backend for the Winimi Bakery storefront.

The production frontend lives in `sajadkhavas/cooci`. This repository owns the API, Filament administration, database, catalog, customer authentication, reusable addresses, delivery configuration, checkout, orders, inventory, payments, fulfillment, content, reviews, inquiries and notification outbox.

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
| Complete store operations backend | Implemented in Phase 15 |
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
composer audit:operations
composer audit:launch
```

GitHub Actions validates Composer metadata, all architecture audits, scoped Pint formatting, fresh SQLite migrations, cached configuration/routes, command and Filament discovery, all API/domain/admin tests and dependency security.

## Implemented APIs

### System

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

### Bakery catalog and reviews

```text
GET  /api/catalog/categories
GET  /api/catalog/products
GET  /api/catalog/products/{slug}
GET  /api/catalog/products/{slug}/reviews
POST /api/account/orders/{orderId}/reviews
```

Catalog availability represents physical stock minus active unexpired reservations. Reviews are accepted only for delivered, customer-owned order items and require moderation.

### Customer authentication and addresses

```text
POST   /api/auth/otp/request
POST   /api/auth/otp/verify
GET    /api/auth/me
POST   /api/auth/logout
PATCH  /api/account/profile
GET    /api/account/addresses
POST   /api/account/addresses
PUT    /api/account/addresses/{addressId}
DELETE /api/account/addresses/{addressId}
```

Customers are isolated from administrator users. OTP challenges are hashed, encrypted, attempt-limited and rate-limited. Address operations are customer-scoped and checkout can snapshot an owned saved address.

### Delivery, checkout and orders

```text
GET  /api/delivery/options
POST /api/checkout
GET  /api/account/orders
GET  /api/account/orders/{orderId}
POST /api/account/orders/{orderId}/cancel
```

Delivery zones, methods, fees, minimum order values, free-delivery thresholds, preparation windows and daily limits are managed through Filament. Checkout remains server-authoritative and idempotent.

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

Payment attempts are persistent, customer-owned and idempotent. Only provider verification atomically marks an order paid and consumes stock. Duplicate callbacks cannot decrement inventory twice.

### Store content and inquiries

```text
GET  /api/store/settings
GET  /api/store/pages/{slug}
GET  /api/store/faqs
GET  /api/store/gallery
GET  /api/store/posts
GET  /api/store/posts/{slug}
GET  /api/store/cities/{slug}
POST /api/inquiries
```

The content domain is bakery-specific and separate from legacy ToolMaster `/api/v1`. Inquiries support contact, gift and corporate types with rate limit, honeypot, duplicate protection and HMAC-hashed IP storage.

## Fulfillment and inventory safety

Controlled transitions:

```text
paid -> confirmed -> preparing -> ready -> dispatched -> delivered
                                  \-> delivered (pickup only)
```

Arbitrary status editing is disabled. Non-pickup dispatch requires a tracking code. Customer-facing order resources expose a public status timeline but never internal notes.

Admin cancellation before payment releases reservations. Cancellation after payment restores consumed stock exactly once and changes reservations to `restocked`. It intentionally keeps `payment_status=paid` until a real financial refund is completed.

## Notification outbox

Order events are transactionally queued and dispatched by:

```bash
php artisan notifications:dispatch --limit=100
```

The scheduler runs this every minute without overlap. Destinations are encrypted at rest.

Providers:

- `disabled`: safe default and leaves rows pending
- `testing`: deterministic outside production only
- `kavenegar`: requires server-side credentials

## Safe defaults

```env
SMS_PROVIDER=disabled
OTP_EXPOSE_TEST_CODE=false
CHECKOUT_ENABLED=false
PAYMENT_ENABLED=false
PAYMENT_PROVIDER=disabled
ZARINPAL_MERCHANT_ID=
ORDER_SMS_PROVIDER=disabled
KAVENEGAR_API_KEY=
```

No payment or SMS credential may use a `VITE_*` variable. eNAMAD code is stored as a non-public setting and is returned only when explicitly enabled and supplied.

## Filament operations

The admin panel includes:

- bakery categories and products
- customers and reusable-address inspection
- delivery zones and operating fees
- orders with controlled fulfillment actions, status history and internal notes
- payment-attempt inspection
- store settings and eNAMAD slot
- legal/shipping/homepage pages, FAQ, gallery, bakery blog and city pages
- verified-purchase review moderation
- contact/gift/corporate inquiry tracking
- notification templates and outbox inspection

Payment attempts and outbound notification records cannot be manually created.

## Documentation

- `docs/API_CONTRACT.md`
- `docs/CATALOG_API.md`
- `docs/CUSTOMER_AUTH.md`
- `docs/ORDERS_CHECKOUT.md`
- `docs/PAYMENTS.md`
- `docs/STORE_OPERATIONS.md`
- `docs/FULL_LAUNCH_ROADMAP.md`

## Frontend integration boundary

The frontend remains on safe static/mock modes until the backend reaches `backend_complete=ready` in Phase 16. Phase 17 then connects every production dynamic flow to the frozen API. No provider credential is exposed to the frontend.

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
ORDER_SMS_PROVIDER=disabled
ZARINPAL_MERCHANT_ID=
KAVENEGAR_API_KEY=
```

A production release must run at minimum:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Production also requires persistent storage, queue workers, one-minute scheduler, backups with restore verification, logs, monitoring and rollback.

## Locked phase roadmap

- Phase 10: foundation and migration boundary — complete
- Phase 11: bakery catalog and administration — complete
- Phase 12: OTP authentication and customer account — complete
- Phase 13: checkout, orders and inventory reservations — complete
- Phase 13.5: full-launch audit and roadmap lock — complete
- Phase 14: provider-ready payment backend — complete
- Phase 15: complete store operations backend — complete
- Phase 16: backend completion and contract freeze — next
- Phase 17: full frontend/backend integration
- Phase 18: end-to-end completion
- Phase 19: production server deployment
- Phase 20: external activation only

At the end of Phase 19, the only remaining inputs are payment gateway credentials, the eNAMAD badge code and the SMS provider key/template.
