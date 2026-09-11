# WINIMI — Production Runtime Source Lock

آخرین reconciliation: 2026-09-11

این سند فقط برای جلوگیری از ابهام بین **Runtime implementation source** و branch `main` ایجاد شده است. Docs-only commitها می‌توانند `main` را جلو ببرند بدون اینکه Runtime code تغییر کند؛ بنابراین Deploy نباید صرفاً بر اساس «آخرین main» انجام شود.

## Runtime implementation source lock

```text
BACKEND_RUNTIME_SOURCE=fc93669455d9bf22fe41260b192d76fb1e65f284
FRONTEND_RUNTIME_SOURCE=ca074dbd0664c88a7d618299ba04d8d20d729b07
```

این دو SHA mergeهای پذیرفته‌شدهٔ پیاده‌سازی Admin Audit 32 هستند:

- Backend PR #20 → `fc93669455d9bf22fe41260b192d76fb1e65f284`
- Frontend PR #58 → `ca074dbd0664c88a7d618299ba04d8d20d729b07`

Docs-only closure commitهای بعدی Runtime tree را تغییر نداده‌اند. برای Production فقط همین source lockها یا descendant اثبات‌شده با Runtime tree یکسان معتبر است.

## Last proven Production runtime

تا زمانی که sync واقعی جدید روی Hostwinds اجرا و ثبت نشود، آخرین Production اثبات‌شده همان F31 تاریخی است:

```text
BACKEND_DEPLOYED_SOURCE=a2e5c48e8c73c49caaac1f5c9cbb0f608f066e3b
BACKEND_RELEASE=49045150d53cd2be5c2b

FRONTEND_DEPLOYED_SOURCE=7d5e3fe03b11bc007652908b5f2fff2e78504b31
FRONTEND_RELEASE=b0d20cd656e5e5d680c3

PRODUCTION_HOST=hwsrv-1332134.hostwindsdns.com
ADMIN_AUDIT32_PRODUCTION_SYNC=PENDING
```

## Production deployment source of truth

Backend scripts:
- `deploy/bin/preflight-backend-server.sh`
- `deploy/bin/deploy-production-backend.sh`
- `deploy/bin/deploy-backend.sh`
- `deploy/bin/smoke-backend-production.sh`
- `deploy/bin/rollback-backend.sh`

Frontend scripts:
- `deploy/README.md`
- `deploy/bin/preflight-frontend-server.sh`
- `deploy/bin/deploy-production-frontend.sh`
- smoke / rollback scripts in `sajadkhavas/cooci`

## Safety boundary

- Order mutation: forbidden.
- Payment mutation: forbidden.
- Fake business data: forbidden.
- Existing media originals: must be preserved.
- Google Login / checkout / Zarinpal accepted behavior from F31 must not be reopened or disabled by this maintenance.
- Audit #4 historical media regeneration is derivative-only (`thumb`, `preview`) with Spatie `--force`; Original is preserved.
- Audit #26 Delivery Zone is real business data only. If real data is unavailable, no fake Zone is created.

### Reconciled preflight contract

`preflight-backend-server.sh` is state-aware: it validates disabled and enabled production modes without rewriting `.env`. Enabled Zarinpal, Google, OTP/Kavenegar and Web Push require their real production dependencies; dormant credentials are permitted while a feature is disabled. The F31-accepted payment/auth/push state must be preserved. The canonical encrypted-backup secret is `BACKUP_ARCHIVE_PASSWORD`, matching `config/backup.php`.

## Required evidence for closing Production sync

A Production sync is complete only after real Hostwinds execution records:

1. exact Host/source/release lock;
2. before/after Order and Payment-attempt counts;
3. shared env/runtime env checksum preservation;
4. backend migration + admin-theme/assets activation;
5. queue/scheduler/PHP-FPM service health according to versioned deploy contract;
6. readiness/health + smoke PASS;
7. frontend deterministic SSR release + public surface/PWA PASS;
8. derivative-only Media regeneration result;
9. Delivery Zone real-data decision;
10. new active release IDs in `cooci/docs/WINIMI_LIVING_HANDOFF_FA.md`.

GitHub Actions currently validate CI/readiness/package contracts but do not SSH-deploy Hostwinds. Therefore no new Production release may be claimed until the above server evidence exists.
