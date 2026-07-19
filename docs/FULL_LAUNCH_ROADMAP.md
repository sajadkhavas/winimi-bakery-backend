# Winimi Bakery Full Launch Roadmap

Roadmap lock: `2026-07-20-phase-15`

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
- Phase 14: provider-ready payment backend
- Phase 15: complete store operations backend

## Phase 14 — Provider-ready payment backend

Completed without requiring live credentials:

- persistent `payment_attempts`
- provider-neutral interface and deterministic testing provider
- Zarinpal request and verification adapter disabled by default
- idempotent initiation, callback replay and controlled retry
- atomic verified-payment, order, reservation and stock transition
- Filament inspection and redacted provider metadata
- full payment and inventory regression tests

Only the real Merchant ID remains missing from payment activation.

## Phase 15 — Complete store operations backend

Completed before frontend integration:

### Customer and delivery

- reusable customer-owned addresses with one default address
- province/city delivery-zone resolution with wildcard fallback
- standard, chilled and pickup rules
- delivery and packaging fees managed through Filament
- minimum order, free-delivery threshold, preparation windows and daily limits
- checkout snapshot of zone, fees and preparation range

### Order operations

- controlled `paid -> confirmed -> preparing -> ready -> dispatched -> delivered` state machine
- pickup-specific ready-to-delivered path
- tracking-code requirement for dispatched orders
- row-locked status history and timestamps
- private internal notes
- one-time restock after paid-order cancellation
- customer-safe public timeline without internal notes
- searchable and filterable Filament order operations console

### Content and trust

- bakery-specific public settings
- contact and social data
- FAQ, legal, shipping and homepage content
- bakery blog, gallery and city pages
- eNAMAD placeholder/slot disabled until the external badge code is supplied

### Reviews and forms

- verified-purchase review submission after delivery
- moderation and public approved-review summaries
- contact, gift and corporate inquiry storage
- honeypot, rate limit, duplicate protection and hashed IP storage
- Filament review and inquiry resources

### Notifications

- transactional notification outbox
- order SMS templates
- disabled and testing providers plus Kavenegar-ready adapter
- encrypted destinations, retries and stale-processing recovery
- one-minute non-overlapping scheduler
- no production SMS without the external key

## Phase 16 — Backend completion and contract freeze

The backend becomes ready to transfer into the production storefront integration:

- all public and authenticated endpoints finalized
- OpenAPI or equivalent machine-readable schemas
- consistent response envelope and error codes
- pagination/filter/sort contracts frozen
- authorization and IDOR regression tests expanded across every resource
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
