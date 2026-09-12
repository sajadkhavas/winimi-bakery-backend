# WINIMI Backend — Post-Audit32 GA4 Settings Compatibility Handoff

تاریخ: 2026-09-12

این سند ادامه‌ی maintenance بعد از `docs/ADMIN_AUDIT32_CONTINUATION_FA.md` است. Audit32 همچنان CLOSED است و این فایل آن را دوباره باز نمی‌کند.

## GitHub closure

```text
REPOSITORY=sajadkhavas/winimi-bakery-backend
PR=30
TITLE=Sync storefront settings dotted-key compatibility
PR_HEAD=c478956cb573763bde44889c3126d2abc43ac010
MERGE_SHA=b83af12e227ac9cb08b30fc78f01ec4798afbcc3
RUNTIME_CODE_MAIN_AT_CLOSURE=b83af12e227ac9cb08b30fc78f01ec4798afbcc3
STATE=MERGED
```

Backend Phase18 post-merge:

```text
PHASE18_BACKEND_RUN=34665867392
PHASE18_BACKEND_RESULT=SUCCESS
```

نکته: بعد از این closure ممکن است `main` به‌علت commitهای docs-only جلوتر از `b83af12...` باشد. این به معنی deploy شدن code جدید Backend نیست. Runtime authority این maintenance همان merge/code SHA بالا و active release واقعی سرور است تا زمانی که deploy جدید جداگانه اثبات شود.

## Compatibility contract

Hotfix فقط رفتار public settings را برای storefront سازگار کرد:

- ساختار nested موجود که با `Arr::set` ساخته می‌شود حفظ شد.
- هر public setting همچنین با literal dotted key در خروجی موجود است.
- کلیدهای مهم این sync:
  - `consent.analytics_enabled`
  - `integrations.google_tag_mode`
  - `integrations.google_tag_id`
- هیچ migration، seed یا business-data mutation در این hotfix وجود نداشت.

## Frontend closure مرتبط

Frontend نهایی این maintenance در repository `sajadkhavas/cooci` با PR #65 Merge شد:

```text
FRONTEND_PR=65 MERGED
FRONTEND_RUNTIME_SOURCE=44e6b4318cf67883fef49063624c26c41ebbdbd2
FRONTEND_PRODUCTION_RELEASE=33ddd21b10b4e66c62a5
GA_ID=G-96JJNX40BV
GA_MODE=ROOT_ONLY
GA_CONSENT_COMMAND=0
CUSTOMER_CONSENT_POPUP=REMOVED
LOGO_PERMISSION_FIX=0755_DIRS_0644_FILES
CSP_GOOGLE_COLLECT=PASS
PUBLIC_HTTP=200
API_HTTP=200
DATABASE_MUTATION=NO
BACKEND_MUTATION_DURING_FINAL_FRONTEND_DEPLOY=NO
```

جزئیات کامل Frontend و Production در:

- `sajadkhavas/cooci/docs/WINIMI_LIVING_HANDOFF_FA.md`
- `sajadkhavas/cooci/docs/WINIMI_POST_HANDOFF_2026-09-12_GA4_LOGO_CSP_CLOSURE_FA.md`

## قانون ادامه

```text
ADMIN_AUDIT32=CLOSED
PR30=CLOSED
REPEAT_COMPAT_HOTFIX=NO
REPEAT_FRONTEND_GA4_LOGO_CSP_DEPLOY=NO
ORDER_MUTATION=NO
PAYMENT_MUTATION=NO
NEXT=POST_HANDOFF_MAINTENANCE_ONLY
```

در چت بعدی ابتدا اسناد بالا خوانده شوند و active runtime به‌صورت read-only بررسی شود. این maintenance فقط در صورت regression evidence جدید دوباره باز شود.
