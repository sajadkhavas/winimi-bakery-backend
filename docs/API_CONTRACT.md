# Winimi Frontend / Backend API Contract

Contract version: `2026-07-19`
Frontend base URL example: `https://api.winimibakery.com`

## General rules

- All requests and responses use JSON except payment-provider redirects and media files.
- Frontend requests use `credentials: include`.
- Authentication uses an HttpOnly Laravel session cookie, not LocalStorage Bearer tokens.
- State-changing cookie-authenticated requests must follow the selected CSRF architecture.
- Monetary values are integer toman values in the application contract.
- The server recalculates price, stock, packaging and delivery. Client totals are never trusted.
- Public identifiers for OTP challenges, orders and payment attempts must be non-sequential.
- Error responses must not expose stack traces, provider payloads, secrets or existence of a registered mobile.
- `X-Request-ID` is accepted and returned. The backend creates one when absent.
- `Idempotency-Key` is required for checkout and payment-attempt creation.

## Foundation endpoints implemented in Phase 10

### `GET /api/system/health`

Liveness check. Does not require the database.

### `GET /api/system/ready`

Readiness check. Returns HTTP 503 when the database connection is unavailable.

### `GET /api/system/meta`

Returns service, framework and contract metadata.

### `GET /api/system/contracts`

Returns the implementation status of each contract group. A route is usable only when its status is `implemented`.

## Standard foundation response envelope

```json
{
  "success": true,
  "data": {},
  "meta": {
    "requestId": "uuid",
    "apiVersion": "1"
  }
}
```

Error example:

```json
{
  "success": false,
  "message": "درخواست معتبر نیست.",
  "errors": {
    "mobile": ["شماره موبایل معتبر نیست."]
  },
  "meta": {
    "requestId": "uuid",
    "apiVersion": "1"
  }
}
```

Existing legacy resources may keep their old resource envelope during migration. New Winimi endpoints must follow their exact frontend payload contract first and may add non-breaking metadata.

## Catalog target — Phase 11

- `GET /api/catalog/products`
- `GET /api/catalog/products/{slug}`
- `GET /api/catalog/categories`

Required product concepts:

- stable ID and slug
- product code
- category
- variants with independent price and stock
- integer toman price
- optional regular and sale price
- cooling requirement
- media with verified status
- ingredients, allergens, shelf life and storage information with verified status
- active and featured flags
- server-calculated availability

The old `/api/v1/products*` endpoints are a legacy industrial adapter and are not compatible with the final frontend Product type.

## Authentication target — Phase 12

### `POST /api/auth/otp/request`

Request:

```json
{
  "mobile": "09123456789"
}
```

Response:

```json
{
  "challengeId": "OTP-public-random-id",
  "expiresIn": 120,
  "retryAfter": 60
}
```

### `POST /api/auth/otp/verify`

```json
{
  "mobile": "09123456789",
  "challengeId": "OTP-public-random-id",
  "code": "123456"
}
```

Response:

```json
{
  "user": {
    "id": "public-user-id",
    "mobile": "09123456789",
    "fullName": "نام مشتری",
    "createdAt": "2026-07-19T07:00:00.000Z"
  }
}
```

OTP requirements:

- normalize Persian, Arabic and English digits
- store only a cryptographic hash
- short expiry and limited attempts
- rate-limit by mobile, IP and session/device context
- invalidate after success
- rotate session ID after login
- never return or log the production code

Additional account endpoints:

- `GET /api/auth/me`
- `POST /api/auth/logout`
- `PATCH /api/account/profile`
- `GET /api/account/orders`
- `GET /api/account/orders/{orderId}`

Every order query must be scoped server-side to the authenticated account. Unauthorized owned-resource lookups return 404.

## Checkout and order target — Phase 13

### `POST /api/checkout`

Required header:

```text
Idempotency-Key: CHK-random-token
```

Request:

```json
{
  "customer": {
    "fullName": "نام گیرنده",
    "mobile": "09123456789",
    "province": "تهران",
    "city": "تهران",
    "address": "آدرس کامل",
    "postalCode": "1234567890",
    "notes": "اختیاری"
  },
  "deliveryMethod": "standard",
  "items": [
    {
      "productId": "product-public-id",
      "variantId": "variant-public-id",
      "quantity": 2
    }
  ]
}
```

Response expected by the frontend:

```json
{
  "order": {},
  "payment": {
    "attemptId": "payment-attempt-public-id",
    "redirectUrl": "https://payment-provider.example/redirect",
    "authority": "optional-provider-authority"
  }
}
```

Server responsibilities:

- validate active products and variants
- lock and check stock transactionally
- calculate every price and fee in the server
- validate chilled delivery rules
- create one order per idempotency key
- store an immutable order-item price snapshot
- never accept subtotal or total from the client as authoritative

## Payment target — Phase 14

- `POST /api/orders/{orderId}/payments`
- `POST /api/payments/zarinpal/verify`

Payment creation request:

```json
{
  "provider": "zarinpal"
}
```

Verification request expected by the frontend:

```json
{
  "orderId": "order-public-id",
  "authority": "provider-authority",
  "status": "OK"
}
```

Verification response:

```json
{
  "state": "success",
  "order": {},
  "refId": "provider-reference-id"
}
```

Allowed states are `success`, `failed`, `cancelled` and `unknown`.

Payment rules:

- Merchant ID remains server-only.
- Create a PaymentAttempt before redirecting.
- Verify amount, authority, order and attempt atomically.
- Verification must be idempotent.
- Never mark an order paid from callback query parameters alone.
- Preserve provider reference IDs and sanitized failure reasons.

## Legacy API policy

All current `/api/v1/*` routes are preserved temporarily and return:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

No new Winimi commerce feature may be implemented under the legacy route group.
