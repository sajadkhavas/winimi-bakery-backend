# Winimi Frontend / Backend API Contract

Contract version: `2026-07-19-phase-12`

Frontend API origin example: `https://api.winimibakery.com`

## General rules

- All application requests and responses use JSON except media files and payment redirects.
- Frontend requests use `credentials: include`.
- Customer authentication uses a Laravel HttpOnly session cookie, not a LocalStorage Bearer token.
- State-changing first-party requests follow Laravel Sanctum CSRF protection.
- `X-Request-ID` is accepted and returned.
- Public identifiers are ULIDs or other non-sequential identifiers.
- Integer application prices are expressed in toman.
- Client totals, inventory and ownership claims are never authoritative.
- Error responses never expose stack traces, provider secrets or raw provider payloads.

## Standard response envelope

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

Validation error example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "mobile": ["شماره موبایل معتبر نیست."]
  }
}
```

## System contract — implemented

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

`GET /api/system/contracts` is the machine-readable source of truth for implementation status.

## Catalog contract — implemented in Phase 11

```text
GET /api/catalog/categories
GET /api/catalog/products
GET /api/catalog/products/{slug}
```

Products use server-calculated Variant price, stock and availability. The inherited `/api/v1/products*` endpoints are legacy industrial endpoints and are not the Winimi storefront contract.

## Customer authentication contract — implemented in Phase 12

Customer accounts are independent from Filament administrator users.

### `POST /api/auth/otp/request`

Request:

```json
{
  "mobile": "09123456789"
}
```

Accepted input may contain Persian or Arabic digits and `+98`, `0098`, `98` or leading-zero formats.

Success response, HTTP 202:

```json
{
  "success": true,
  "data": {
    "challengeId": "01K...ULID",
    "expiresIn": 120,
    "retryAfter": 60
  },
  "message": "اگر شماره قابل ارسال باشد، کد ورود ارسال شد."
}
```

The response never reveals whether the mobile already belongs to a customer.

Possible errors:

- HTTP 422: invalid mobile format
- HTTP 429: route rate limit or resend cooldown; includes `Retry-After`
- HTTP 503: SMS provider disabled or unavailable

### `POST /api/auth/otp/verify`

Request:

```json
{
  "mobile": "09123456789",
  "challengeId": "01K...ULID",
  "code": "123456"
}
```

Success response:

```json
{
  "success": true,
  "data": {
    "user": {
      "id": "01K...ULID",
      "mobile": "09123456789",
      "fullName": null,
      "email": null,
      "mobileVerified": true,
      "marketingConsent": false,
      "createdAt": "2026-07-19T16:00:00.000000Z",
      "updatedAt": "2026-07-19T16:00:00.000000Z"
    }
  }
}
```

Successful verification consumes the challenge, creates or updates the customer, logs in through the `customer` guard and rotates the session ID.

### `GET /api/auth/me`

Requires an authenticated customer session. Returns the same `user` shape.

### `POST /api/auth/logout`

Requires an authenticated customer session. Logs out the `customer` guard, invalidates the session and rotates the CSRF token.

### `PATCH /api/account/profile`

Requires an authenticated customer session.

```json
{
  "fullName": "نام مشتری",
  "email": "customer@example.com",
  "marketingConsent": true
}
```

All fields are optional. The mobile cannot be changed through this endpoint.

### Authentication security requirements

- only a cryptographic hash of the OTP is stored
- challenge mobile payload is encrypted at rest
- mobile, IP and User-Agent lookup values use keyed hashes
- challenges expire quickly and are one-time
- failed attempts persist and enforce a maximum
- requesting a new challenge consumes previous active challenges
- request limits are keyed by IP and mobile
- verification limits are keyed by IP and challenge ID
- test codes may be exposed only in local/testing with an explicit flag
- production codes are never returned or logged
- disabled or failed SMS delivery removes the unused challenge
- inactive or deleted customer accounts cannot log in
- expired and consumed challenges are pruned automatically

Provider and deployment details are documented in `docs/CUSTOMER_AUTH.md`.

## Orders contract — Phase 13, not implemented

The following routes must remain unavailable until the order domain is complete:

```text
POST /api/checkout
GET /api/account/orders
GET /api/account/orders/{orderId}
```

### Future `POST /api/checkout`

Required header:

```text
Idempotency-Key: CHK-random-token
```

Target request:

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

The server will lock and validate stock, calculate all prices and fees, enforce chilled delivery and create one order per idempotency key.

## Payments contract — Phase 14, not implemented

```text
POST /api/orders/{orderId}/payments
POST /api/payments/zarinpal/verify
```

Merchant credentials remain server-only. A payment attempt must exist before redirect and verification must be idempotent and atomic.

## Legacy API policy

All `/api/v1/*` routes are temporary ToolMaster compatibility endpoints and return:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

No new Winimi commerce feature may be implemented under `/api/v1`.
