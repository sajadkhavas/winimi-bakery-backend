# Winimi Frontend / Backend API Contract

Contract version: `2026-07-19-phase-13`

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

Products use server-calculated Variant price and reservation-aware availability. The inherited `/api/v1/products*` endpoints are legacy industrial endpoints and are not the Winimi storefront contract.

## Customer authentication contract — implemented in Phase 12

```text
POST  /api/auth/otp/request
POST  /api/auth/otp/verify
GET   /api/auth/me
POST  /api/auth/logout
PATCH /api/account/profile
```

Customer accounts are independent from Filament administrator users. OTP challenges are short-lived, one-time, attempt-limited, hashed and rate-limited. Challenge mobile payloads are encrypted and customer sessions use the isolated `customer` guard.

### `POST /api/auth/otp/request`

```json
{
  "mobile": "09123456789"
}
```

Success is HTTP 202 and returns `challengeId`, `expiresIn` and `retryAfter`. Production never returns the code.

### `POST /api/auth/otp/verify`

```json
{
  "mobile": "09123456789",
  "challengeId": "01K...ULID",
  "code": "123456"
}
```

Successful verification consumes the challenge, creates or updates the customer, logs in through the `customer` guard and rotates the session ID.

### `PATCH /api/account/profile`

```json
{
  "fullName": "نام مشتری",
  "email": "customer@example.com",
  "marketingConsent": true
}
```

The mobile cannot be changed through the profile endpoint.

Provider and deployment details are documented in `docs/CUSTOMER_AUTH.md`.

## Orders contract — implemented in Phase 13

```text
POST /api/checkout
GET  /api/account/orders
GET  /api/account/orders/{orderId}
POST /api/account/orders/{orderId}/cancel
```

All routes require an authenticated, active customer account.

### `POST /api/checkout`

Required header:

```text
Idempotency-Key: a-unique-random-value-at-least-16-characters
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
      "variantId": "01K...ULID",
      "quantity": 2
    }
  ]
}
```

The request must not include authoritative item price, subtotal, delivery fee, discount or grand total fields.

Success is HTTP 201. An exact idempotent replay is HTTP 200 with `meta.replayed=true`. Reusing the key for a different payload returns HTTP 409.

```json
{
  "success": true,
  "data": {
    "order": {
      "id": "01K...ULID",
      "number": "WNM-260719-ABCDEFGH",
      "status": "awaiting_payment",
      "paymentStatus": "unpaid",
      "totals": {
        "subtotalToman": 160000,
        "deliveryFeeToman": 30000,
        "packagingFeeToman": 10000,
        "discountToman": 0,
        "grandTotalToman": 200000
      },
      "reservationExpiresAt": "2026-07-19T18:00:00+03:30",
      "items": []
    },
    "payment": {
      "available": false,
      "state": "not-configured"
    }
  },
  "meta": {
    "replayed": false
  }
}
```

Server responsibilities:

- lock all referenced Variant rows in a stable order
- validate active category, product and Variant state
- subtract active unexpired reservations from available stock
- calculate current unit prices and every total on the server
- enforce chilled delivery for cooling-required products
- create immutable order-item snapshots
- create one customer-scoped order per idempotency key
- reserve stock temporarily without decrementing physical stock

### Account order routes

`GET /api/account/orders` returns only the authenticated customer's orders with pagination.

`GET /api/account/orders/{orderId}` returns 404 when the order does not belong to the authenticated customer.

`POST /api/account/orders/{orderId}/cancel` is allowed only for unpaid `awaiting_payment` orders. It transitions the order to `cancelled` and releases active reservations in the same transaction.

### Inventory reservation lifecycle

```text
active -> consumed
active -> released
active -> expired
```

- `consumed` is reserved for Phase 14 verified payment and decrements physical stock
- `released` is used for customer cancellation
- `expired` is used when the payment deadline passes

The scheduled `inventory:release-expired` command runs every minute without overlap. Full details are in `docs/ORDERS_CHECKOUT.md`.

## Payments contract — Phase 14, not implemented

```text
POST /api/orders/{orderId}/payments
POST /api/payments/zarinpal/verify
```

Merchant credentials remain server-only. A payment attempt must exist before redirect. Verification must be idempotent and atomic, and only verified payment may consume inventory reservations and mark an order paid.

## Legacy API policy

All `/api/v1/*` routes are temporary ToolMaster compatibility endpoints and return:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

No new Winimi commerce feature may be implemented under `/api/v1`.
