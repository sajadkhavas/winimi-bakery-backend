# Winimi Bakery Full Launch Roadmap

Roadmap lock: `2026-07-19-phase-13.5`

## Delivery rule

The application must be internally complete, fully integrated and deployed before external activation. At the end of the internal roadmap, no implementation work may remain except supplying and validating these three external inputs:

1. payment gateway credentials / Zarinpal Merchant ID
2. eNAMAD badge code
3. SMS provider API key and approved OTP template

The backend is completed and contract-frozen before the frontend integration phase. The frontend and backend remain separate repositories and are connected through the production API contract.

## Completed foundation

- Phase 1–9.5: complete modern storefront foundation
- Phase 10: Laravel/Filament foundation and legacy boundary
- Phase 11: bakery catalog, Variants, price, stock and administration
- Phase 12: customer OTP architecture, secure sessions and profile
- Phase 13: checkout, orders, immutable snapshots and inventory reservations
- Phase 13.5: repository audit and full-launch roadmap lock

## Phase 14 — Provider-ready payment backend

The payment lifecycle is implemented without requiring live credentials:

- `payment_attempts` and immutable provider request/response metadata
- payment provider interface and deterministic testing provider
- Zarinpal request and verify adapter with live mode disabled by default
- retry payment for an existing eligible order
- idempotent initiation and verification
- authority, reference ID, gateway code and failure classification
- atomic verified-payment transition
- inventory reservation consumption only after verified payment
- safe handling for cancelled, expired, failed and already-verified callbacks
- Filament payment inspection
- provider logs with secrets and personal data redacted
- complete feature tests using the testing provider

At the end of Phase 14, only the real Merchant ID is missing from payment activation.

## Phase 15 — Complete store operations backend

All non-payment backend work is completed before integration:

### Customer and delivery

- reusable customer addresses
- province/city/delivery-zone configuration
- standard, chilled and pickup availability rules
- delivery and packaging fees managed through Filament instead of hardcoded frontend values
- preparation windows and operating limits

### Order operations

- controlled fulfillment state machine
- admin actions for confirm, prepare, dispatch, deliver and cancel
- stock and reservation safety for every transition
- internal notes and status history
- order search, filters and export-ready data

### Content and trust

- bakery-specific site settings
- contact information and social links
- FAQ, legal pages, shipping policy and homepage content
- blog, gallery and city-page content source
- eNAMAD placeholder/slot with the real badge code disabled until supplied

### Reviews and forms

- verified-purchase review submission and moderation
- contact, gift and corporate inquiry storage
- spam/rate-limit protection
- admin review and inquiry resources

### Notifications

- notification outbox and delivery state
- OTP/order SMS templates
- testing/disabled SMS provider and Kavenegar-ready adapter
- no production SMS is sent without the external key and approved template

## Phase 16 — Backend completion and contract freeze

The backend becomes ready to transfer into the production storefront integration:

- all public and authenticated endpoints finalized
- OpenAPI or equivalent machine-readable schemas
- consistent response envelope and error codes
- pagination/filter/sort contracts frozen
- authorization and IDOR regression tests
- queue, scheduler, cache, media and storage policies finalized
- database indexes and query review
- seeders/factories for staging acceptance data
- backup, restore, retention and audit-log procedures
- legacy ToolMaster routes disabled for production
- backend readiness audit reports `backend_complete=ready`

No frontend integration starts until this gate is green.

## Phase 17 — Full frontend/backend integration

The production storefront is connected to the frozen backend contract:

- one shared API client with timeout, request ID, response-envelope parsing and CSRF handling
- backend catalog, categories, server pagination, filters and product details
- real customer session, OTP, profile and addresses
- Variant-aware cart reconciliation against server stock and prices
- server-authoritative checkout and Idempotency-Key handling
- account order list/detail/cancellation
- payment initiation, retry and callback-state UI using backend responses
- content, reviews, contact, gift and corporate forms connected
- production removal of localStorage orders and static catalog fallback
- mock providers retained only for development and automated tests

## Phase 18 — End-to-end completion

- full desktop/mobile user journeys
- accessibility and keyboard regression
- catalog, auth, cart, checkout, order and payment-state tests
- concurrent stock and retry scenarios
- offline/reconnect and expired-session behavior
- final real business content and product entry
- legal, privacy, shipping and return-policy completion
- SEO, sitemap, structured data and canonical review
- performance budgets and production bundle validation
- admin acceptance tests
- release checklist reports `end_to_end_verified=ready`

## Phase 19 — Production server deployment

The complete system is deployed while all three external integrations remain safely disabled:

- production database and migrations
- frontend hosting and Laravel API deployment
- `winimibakery.com` and `api.winimibakery.com`
- DNS, HTTPS and secure cookies
- persistent media/storage configuration
- queue workers and one-minute scheduler
- cache/Redis decision and configuration
- backups and restore verification
- log rotation, health checks and monitoring
- CI/CD release and rollback procedure
- production smoke tests with testing/disabled providers
- launch gate reports `production_deployed=ready`

## Phase 20 — External activation only

No feature development is allowed in this phase. The only actions are:

1. set payment gateway credentials and perform a low-value live payment test
2. insert the supplied eNAMAD badge code and verify its link/domain behavior
3. set the SMS provider key/template and verify OTP/order messages

After the three external checks pass, public purchasing is enabled.

## Definition of internally complete

Internal completion means:

- backend complete and contract-frozen
- frontend uses the backend for every production dynamic flow
- no production order, auth or catalog source depends on browser storage or static demo data
- all admin operations required for daily business exist
- server deployment, queue, scheduler, backups, logs and rollback work
- payment, eNAMAD and SMS code paths already exist and are disabled only because their external values have not been supplied
