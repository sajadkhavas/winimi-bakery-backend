# Winimi Frontend / Backend API Contract

Contract version: `2026-07-19-phase-14`

Frontend API origin example: `https://api.winimibakery.com`

## General rules

- All application requests and responses use JSON except media files and payment redirects.
- Frontend requests use `credentials: include`.
- Customer authentication uses a Laravel HttpOnly session cookie, not a LocalStorage Bearer token.
- State-changing first-party requests follow Laravel Sanctum CSRF protection.
- `X-Request-ID` is accepted and returned.
- Public identifiers are ULIDs or other non-sequential identifiers.
- Integer application prices are expressed in toman.
- Client totals, inventory, ownership and payment-state claims are never authoritative.
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

The mobile cannot be changed through the profile endpoint. Provider and deployment details are documented in `docs/CUSTOMER_AUTH.md`.

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
      "items": [],
      "payments": []
    },
    "payment": {
      "available": false,
      "state": "disabled",
      "initiationEndpoint": null
    }
  },
  "meta": {
    "replayed": false
  }
}
```

When a configured provider is ready, `available=true` and `initiationEndpoint` contains `/api/orders/{orderId}/payments`. Checkout never initiates a payment itself.

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

`GET /api/account/orders` returns only the authenticated customer's orders with pagination and payment-attempt summaries.

`GET /api/account/orders/{orderId}` returns 404 when the order does not belong to the authenticated customer.

`POST /api/account/orders/{orderId}/cancel` is allowed only for `awaiting_payment` orders without a pending or verified payment. It transitions the order to `cancelled` and releases active reservations in the same transaction.

### Inventory reservation lifecycle

```text
active -> consumed
active -> released
active -> expired
```

- `consumed` is used only by verified payment and decrements physical stock
- `released` is used for customer cancellation
- `expired` is used when the payment deadline passes

The scheduled `inventory:release-expired` command runs every minute without overlap. Full details are in `docs/ORDERS_CHECKOUT.md`.

## Payments contract — implemented in Phase 14

```text
POST /api/orders/{orderId}/payments
POST /api/payments/verify
POST /api/payments/zarinpal/verify
```

All payment routes require the authenticated active customer who owns the order or attempt.

### `POST /api/orders/{orderId}/payments`

Required header:

```text
Idempotency-Key: a-unique-random-value-at-least-16-characters
```

Success is HTTP 201. Replaying the same key or requesting another initiation while an unexpired attempt is pending returns HTTP 200 with `meta.replayed=true` and the existing attempt.

```json
{
  "success": true,
  "data": {
    "order": {
      "id": "01K...ORDER",
      "status": "awaiting_payment",
      "paymentStatus": "pending"
    },
    "payment": {
      "id": "01K...ATTEMPT",
      "provider": "zarinpal",
      "attemptNumber": 1,
      "status": "pending",
      "amountToman": 200000,
      "currency": "IRR",
      "authority": "A000...",
      "referenceId": null,
      "gatewayCode": "100",
      "redirectUrl": "https://gateway.example/start/A000...",
      "failure": null,
      "expiresAt": "2026-07-19T18:00:00+03:30"
    }
  },
  "meta": {
    "replayed": false
  }
}
```

The server refuses paid, cancelled, expired or reservation-expired orders. It snapshots the immutable server total and never accepts a client amount.

### `POST /api/payments/verify`

The Zarinpal-specific route accepts the same payload and is maintained as a compatibility alias.

```json
{
  "authority": "A000...",
  "status": "OK"
}
```

The callback status is only a hint. The backend selects the provider stored on the attempt and verifies the recorded authority and server amount with that provider.

Verified response:

```json
{
  "success": true,
  "data": {
    "verified": true,
    "order": {
      "status": "paid",
      "paymentStatus": "paid",
      "paidAt": "2026-07-19T18:05:00+03:30"
    },
    "payment": {
      "status": "verified",
      "referenceId": "987654321"
    }
  },
  "meta": {
    "replayed": false
  }
}
```

A repeated verified callback is HTTP 200 with `meta.replayed=true`; it never consumes inventory twice.

Failed or cancelled verification returns `verified=false`. The order remains `awaiting_payment`, reservations remain active until their deadline, and a new attempt may be created with a new idempotency key.

### Payment security rules

- payment credentials are server-only
- testing provider is forbidden in production
- live Zarinpal refuses to run without Merchant ID
- provider request and verification payloads are not exposed to storefront APIs
- Merchant ID is redacted before persisted administrative metadata
- card PAN and card hash are removed before persistence
- only provider verification may mark an order paid
- order, reservation, Variant and attempt updates commit atomically

Full details are in `docs/PAYMENTS.md`.

## Legacy API policy

All `/api/v1/*` routes are temporary ToolMaster compatibility endpoints and return:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

No new Winimi commerce feature may be implemented under `/api/v1`.