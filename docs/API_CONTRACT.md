# Winimi Frontend / Backend API Contract

Contract version: `2026-07-20-phase-15`

Frontend API origin example: `https://api.winimibakery.com`

## General rules

- All application requests and responses use JSON except media files and payment redirects.
- Frontend requests use `credentials: include`.
- Customer authentication uses a Laravel HttpOnly session cookie, not a LocalStorage Bearer token.
- State-changing first-party requests follow Laravel Sanctum CSRF protection.
- `X-Request-ID` is accepted and returned.
- Public domain identifiers are ULIDs or other non-sequential identifiers.
- Integer application prices are expressed in toman.
- Client totals, inventory, delivery fees, ownership, fulfillment and payment-state claims are never authoritative.
- Error responses never expose stack traces, credentials, raw provider payloads, internal notes or unhashed IP addresses.

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

`GET /api/system/contracts` is the machine-readable source of truth for implementation status.

## System — implemented

```text
GET /api/system/health
GET /api/system/ready
GET /api/system/meta
GET /api/system/contracts
```

## Catalog — implemented in Phase 11

```text
GET /api/catalog/categories
GET /api/catalog/products
GET /api/catalog/products/{slug}
GET /api/catalog/products/{slug}/reviews
```

Products use server-calculated Variant prices and reservation-aware stock. Public reviews include approved verified-purchase reviews only.

## Customer authentication and account — implemented in Phases 12 and 15

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

OTP challenges are short-lived, one-time, attempt-limited, hashed and rate-limited. Address identifiers are customer-owned public ULIDs. The server enforces ownership on every address lookup and keeps at most one default active address per customer.

Address request:

```json
{
  "title": "خانه",
  "recipientName": "نام گیرنده",
  "mobile": "09123456789",
  "province": "تهران",
  "city": "تهران",
  "address": "آدرس کامل",
  "postalCode": "1234567890",
  "isDefault": true
}
```

## Delivery and checkout — implemented in Phases 13 and 15

```text
GET  /api/delivery/options
POST /api/checkout
GET  /api/account/orders
GET  /api/account/orders/{orderId}
POST /api/account/orders/{orderId}/cancel
```

Delivery options accept optional `province`, `city`, `subtotalToman` and `requiresCooling` query parameters. The response describes the matched delivery zone, preparation window and enabled methods. It is informational; checkout recalculates everything.

Checkout requires:

```text
Idempotency-Key: a-unique-random-value-at-least-16-characters
```

Checkout may use a saved address:

```json
{
  "addressId": "01K...ADDRESS",
  "deliveryMethod": "standard",
  "items": [
    {
      "variantId": "01K...VARIANT",
      "quantity": 2
    }
  ]
}
```

Or a one-time recipient snapshot:

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
      "variantId": "01K...VARIANT",
      "quantity": 2
    }
  ]
}
```

The request must never include authoritative item price, subtotal, delivery fee, packaging fee, discount or grand total fields.

The server:

- locks referenced Variants in a stable order
- checks active category/product/Variant state
- subtracts active unexpired reservations from stock
- calculates item totals
- resolves the most specific active delivery zone
- enforces cooling, minimum order and daily capacity rules
- calculates free-delivery thresholds and packaging fees
- snapshots the delivery zone and preparation min/max window
- creates immutable item snapshots and active reservations
- replays only an exact customer-scoped idempotent request

Order resources include public fulfillment timestamps, delivery-zone summary and status timeline. They never include internal admin notes.

## Payments — implemented in Phase 14

```text
POST /api/orders/{orderId}/payments
POST /api/payments/verify
POST /api/payments/zarinpal/verify
```

Payment initiation uses a unique `Idempotency-Key`. Checkout never starts a gateway redirect itself. The callback status is a hint only; the backend verifies the recorded provider, authority and immutable server amount.

Only verified payment may atomically:

- mark the payment attempt verified
- mark the order paid
- consume reservations
- decrement physical stock
- queue the `order.paid` notification

Duplicate verified callbacks never consume inventory twice. Payment and Zarinpal credentials remain server-only and payment is disabled by default until Phase 20.

Full details are in `docs/PAYMENTS.md`.

## Store content and trust — implemented in Phase 15

```text
GET /api/store/settings
GET /api/store/pages/{slug}
GET /api/store/faqs
GET /api/store/gallery
GET /api/store/posts
GET /api/store/posts/{slug}
GET /api/store/cities/{slug}
```

Only active or published bakery content is returned. The eNAMAD badge code is returned only when its admin setting is enabled and a non-empty external badge code exists; otherwise `badgeCode` is null.

## Verified-purchase reviews — implemented in Phase 15

```text
GET  /api/catalog/products/{slug}/reviews
POST /api/account/orders/{orderId}/reviews
```

Review request:

```json
{
  "orderItemId": 123,
  "rating": 5,
  "title": "عالی",
  "body": "محصول تازه و باکیفیت بود."
}
```

The order must belong to the authenticated customer and be delivered. The item must belong to that order. One review per customer/order item is accepted. New reviews are pending and do not appear publicly before moderation.

## Contact, gift and corporate inquiries — implemented in Phase 15

```text
POST /api/inquiries
```

```json
{
  "type": "corporate",
  "fullName": "شرکت نمونه",
  "mobile": "09123456789",
  "email": "buyer@example.com",
  "subject": "سفارش سازمانی",
  "message": "متن درخواست",
  "metadata": {
    "quantity": 100
  },
  "website": ""
}
```

Supported types are `contact`, `gift` and `corporate`. The route has rate limiting, honeypot validation, duplicate-message protection, normalized mobile data and HMAC-hashed IP storage.

## Fulfillment operations — implemented in Phase 15

Customer-facing state progression:

```text
awaiting_payment
paid
confirmed
preparing
ready
dispatched
delivered
cancelled
expired
```

Admin transitions are controlled by the backend state machine. Pickup orders move from ready directly to delivered. Other delivery methods require dispatched with a tracking code before delivered.

Cancellation before payment releases reservations. Cancellation after payment restores consumed stock exactly once and marks the reservation `restocked`. It does not claim a financial refund occurred; `paymentStatus` stays paid until an actual refund is processed.

## Notification outbox — implemented in Phase 15

Order events are queued transactionally and dispatched by the scheduled `notifications:dispatch` command. Destination values are encrypted at rest.

Providers:

- `disabled`: leaves pending rows untouched
- `testing`: allowed only outside production
- `kavenegar`: requires a server-side API key

No SMS credential or raw provider response is exposed through the storefront contract.

Full operational details are in `docs/STORE_OPERATIONS.md`.

## Legacy API policy

All `/api/v1/*` routes are temporary ToolMaster compatibility endpoints and return:

```text
Deprecation: true
X-Winimi-Legacy-Domain: toolmaster
Link: </api/system/contracts>; rel="deprecation"
```

No new Winimi commerce feature may be implemented under `/api/v1`.
