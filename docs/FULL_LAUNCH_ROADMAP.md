# Winimi Bakery Full Launch Roadmap

Roadmap lock: `2026-07-20-phase-16`

## Delivery rule

The application must be internally complete, fully integrated and deployed before external activation. At the end of the internal roadmap, no implementation work may remain except supplying and validating these three external inputs:

1. payment gateway credentials / Zarinpal Merchant ID
2. eNAMAD badge code
3. SMS provider API key and approved OTP template

The backend is completed and contract-frozen before frontend integration. Frontend and backend remain separate repositories connected through the frozen production API contract.

## Completed foundation

- Phase 1–9.5: complete modern storefront foundation
- Phase 10: Laravel/Filament foundation and legacy boundary
- Phase 11: bakery catalog, Variants, price, stock and administration
- Phase 12: customer OTP architecture, secure sessions and profile
- Phase 13: checkout, orders, immutable snapshots and inventory reservations
- Phase 13.5: repository audit and full-launch roadmap lock
- Phase 14: provider-ready payment backend
- Phase 15: complete store operations backend
- Phase 16: backend completion and contract freeze

## Phase 14 — Provider-ready payment backend — complete

- persistent `payment_attempts`
- provider-neutral interface and deterministic testing provider
- Zarinpal adapter disabled by default
- idempotent initiation, verification, callback replay and retry
- atomic verified-payment, order, reservation and stock transition
- Filament inspection and redacted metadata
- payment and inventory regression tests

Only the real Merchant ID remains missing from payment activation.

## Phase 15 — Complete store operations backend — complete

### Customer and delivery

- reusable customer-owned addresses
- province/city delivery zones and wildcard fallback
- standard, chilled and pickup rules
- Filament-managed fees and limits
- minimum order, free delivery and preparation windows
- checkout snapshot of zone and fees

### Order operations

- controlled fulfillment state machine
- pickup-specific ready-to-delivered path
- tracking-code requirement
- row-locked history and timestamps
- private internal notes
- one-time restock after paid-order cancellation
- customer-safe public timeline
- Filament operations console

### Content, reviews and inquiries

- bakery-specific public settings and content
- legal, shipping, FAQ, gallery, blog and city pages
- disabled eNAMAD slot
- verified-purchase review moderation
- contact, gift and corporate inquiries
- spam, rate-limit, duplicate and hashed-IP protection

### Notifications

- transactional outbox
- SMS templates
- disabled/testing/Kavenegar-ready providers
- encrypted destinations, retries and stale-processing recovery
- one-minute non-overlapping scheduler

## Phase 16 — Backend completion and contract freeze — complete

The backend now reports `backend_complete=ready` and is ready for Phase 17.

### Frozen public contract

- all official public and authenticated paths finalized
- OpenAPI 3.1 document at `/api/system/openapi`
- contract version `2026-07-20-phase-16`
- success and error envelopes include contract metadata
- stable error codes independent from Persian messages
- catalog filter and sort values frozen
- pagination shape, defaults and maximums frozen
- public identifiers and customer ownership behavior frozen

### Security and authorization

- central API exception rendering
- missing and cross-customer resources share the same 404 behavior
- address and order IDOR regression tests
- provider secrets, internal notes and raw payloads remain private
- inherited ToolMaster API excluded from OpenAPI
- legacy routes disabled by default in production

### Data and performance

- reviewed indexes for catalog, order, payment, address, content, review and inquiry queries
- documented query and index review
- deterministic staging acceptance seeder
- dry, chilled and gift test products
- Tehran, Karaj, Andisheh and nationwide dry-delivery test zones
- seeder is idempotent and refuses production execution

### Operations and recovery

- queue, scheduler, cache, session, media and storage policies finalized
- private backup and 14-day retention baseline
- restore drill and disaster-recovery runbook
- executable `backend:readiness` command
- CI performs read-only formatting checks and never mutates source
- CI validates migrations, staging seeding, cached routes, Filament, tests and security

No frontend integration began before this gate was defined. Phase 17 must consume the frozen contract rather than redefining it.

## Phase 17 — Full frontend/backend integration — next

The production storefront will connect to the frozen backend contract:

- one typed API client with timeout, request ID, envelope parsing and CSRF handling
- backend catalog, categories, pagination, filters and product details
- real customer session, OTP, profile and addresses
- Variant-aware cart reconciliation against server stock and prices
- server-authoritative checkout and Idempotency-Key handling
- account order list/detail/cancellation
- payment initiation, retry and callback-state UI
- content, reviews, contact, gift and corporate forms
- production removal of localStorage orders and static catalog fallback
- mock providers retained only for development and automated tests

Any required backend contract change must be explicit and versioned. Silent schema changes are prohibited.

## Phase 18 — End-to-end completion

- full desktop/mobile user journeys
- accessibility and keyboard regression
- catalog, auth, cart, checkout, order and payment-state tests
- concurrent stock and retry scenarios
- offline/reconnect and expired-session behavior
- final business content and product entry
- legal, privacy, shipping and return-policy completion
- SEO, sitemap, structured data and canonical review
- performance budgets and production bundle validation
- admin acceptance tests
- `end_to_end_verified=ready`

## Phase 19 — Production server deployment

The complete system is deployed while all external integrations remain disabled:

- production database and migrations
- frontend hosting and Laravel API
- `winimibakery.com` and `api.winimibakery.com`
- DNS, HTTPS and secure cookies
- persistent media/storage
- supervised queue workers and scheduler
- cache/Redis decision
- backup and restore verification
- log rotation, health checks and monitoring
- CI/CD release and rollback
- disabled-provider smoke tests
- `production_deployed=ready`

## Phase 20 — External activation only

No feature development is allowed. Actions are limited to:

1. set payment credentials and perform a low-value live payment test
2. insert the eNAMAD badge code and verify its domain behavior
3. set the SMS key/template and verify OTP/order messages

After these three checks, public purchasing is enabled.

## Definition of internally complete

Internal completion means:

- backend complete and contract-frozen
- frontend uses the backend for every production dynamic flow
- no production order, auth or catalog source depends on browser storage or static demo data
- all required daily admin operations exist
- deployment, queue, scheduler, backups, logs and rollback work
- payment, eNAMAD and SMS code paths already exist and wait only for external values
