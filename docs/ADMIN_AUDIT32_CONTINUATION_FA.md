# Audit ۳۲ موردی پنل WINIMI — Production Closure

آخرین reconciliation: 2026-09-11

## مرجع نهایی

- Repository Backend: `sajadkhavas/winimi-bakery-backend`
- Backend PR: #20 — `Complete WINIMI 32-item admin delivery audit` — **MERGED**
- Backend final PR HEAD: `e3d46ccec2a037f4226f5db10a07977ca08349a4`
- Backend accepted runtime source / merge SHA: `fc93669455d9bf22fe41260b192d76fb1e65f284`
- Backend Production release: `70044e514b51b463e18b`
- Repository Frontend: `sajadkhavas/cooci`
- Frontend companion PR: #58 — `Render WINIMI managed editor callouts safely` — **MERGED**
- Frontend implementation HEAD: `506519ced3d68e4c42991c848faf05d376063782`
- Frontend accepted runtime source / merge SHA: `ca074dbd0664c88a7d618299ba04d8d20d729b07`
- Frontend Production release: `deb601c6c31cb98f1cae`
- Production host: `hwsrv-1332134.hostwindsdns.com`
- F31 تاریخی بسته است؛ این سند maintenance پس از تحویل با عنوان Admin Audit 32 را ثبت می‌کند.

## نتیجهٔ نهایی

```text
AUDIT_CODE_SCOPE=32_OF_32_RECONCILED
BACKEND_PR20=MERGED
FRONTEND_PR58=MERGED
BACKEND_POST_MERGE_CI=PASS
FRONTEND_POST_MERGE_CI=PASS
COORDINATED_BACKEND_FRONTEND_PHASE18=PASS
PRODUCTION_MAINTENANCE_SYNC=PASS
HOMEPAGE_MAINTENANCE=PRODUCTION_CONFIRMED
MEDIA_DERIVATIVE_REGENERATION=PASS
FINAL_ADMIN_API_QA=PASS
FINAL_STOREFRONT_QA=PASS
ORDER_MUTATION=ZERO
PAYMENT_MUTATION=ZERO
FAKE_DELIVERY_ZONE_CREATED=NO
AUDIT32_STATUS=CLOSED
```

## GitHub acceptance evidence

### Backend exact-head acceptance — `e3d46ccec2a037f4226f5db10a07977ca08349a4`

- Backend CI #742 — run `34541429972` — `SUCCESS`
- Phase 18 Backend Acceptance #240 — run `34541429968` — `SUCCESS`
- F30 Storefront Backend Authority #162 — run `34541430019` — `SUCCESS`
- Phase 19 Production Package #228 — run `34541429944` — `SUCCESS`
- Review threads: `0` بلافاصله قبل از Merge.
- Base `main` قبل از Merge بدون drift و دقیقاً `513af66e3ea1bdaf1eb28abcaa5a3917c17a0383` بود.
- Merge با `expected_head_sha` انجام شد.

### Backend post-merge main — `fc93669455d9bf22fe41260b192d76fb1e65f284`

- Backend CI #743 — run `34541614246` — `SUCCESS`
- Phase 18 Backend Acceptance #241 — run `34541614236` — `SUCCESS`
- Phase 19 Production Package #229 — run `34541614252` — `SUCCESS`

### Frontend exact-head acceptance — `506519ced3d68e4c42991c848faf05d376063782`

- Frontend CI #1818 — run `34540720209` — `SUCCESS`
- Phase 18 End-to-End Acceptance #634 — run `34540720028` — `SUCCESS`
- Phase 8 Deployment Readiness #767 — run `34540720042` — `SUCCESS`
- Phase 19 Production Package #202 — run `34540720081` — `SUCCESS`

بعد از Merge شدن Backend، همان Phase18 روی Frontend HEAD دوباره اجرا شد. workflow Backend `main` را checkout کرد و job `103085747602` روی run `34540720028` با Backend `main=fc936694...` کامل `SUCCESS` شد. این coordinated acceptance شامل Laravel install/migrations/seed، backend adversarial acceptance، delivery contract، Production SSR build، desktop/mobile browser، SEO 10.3–10.9، PWA، final adversarial و scroll baseline بود.

بلافاصله قبل از Merge Frontend:

- PR head دقیقاً `506519ced3d68e4c42991c848faf05d376063782` بود.
- Review threads: `0`.
- Frontend `main` بدون drift و دقیقاً `c0393a041036510675a77216ffe867520216b3e4` بود.
- Merge با `expected_head_sha` انجام شد.

### Frontend post-merge main — `ca074dbd0664c88a7d618299ba04d8d20d729b07`

- Frontend CI #1819 — run `34542268389` — `SUCCESS`
- Phase 8 Deployment Readiness #768 — run `34542268400` — `SUCCESS`
- Phase 19 Production Package #203 — run `34542268410` — `SUCCESS`
- Phase 18 End-to-End Acceptance #635 — run `34542268430` — `SUCCESS`

## Production Source Lock و releaseهای واقعی

Runtime implementation source با tooling/docs-only main یکی نیست. Production packageها از sourceهای پذیرفته‌شده زیر ساخته شدند:

```text
BACKEND_RUNTIME_SOURCE=fc93669455d9bf22fe41260b192d76fb1e65f284
BACKEND_RELEASE=70044e514b51b463e18b

FRONTEND_RUNTIME_SOURCE=ca074dbd0664c88a7d618299ba04d8d20d729b07
FRONTEND_RELEASE=deb601c6c31cb98f1cae

BACKEND_DEPLOY_TOOLING=f370b6c5f39b5f8ded16137953af2ed3ce7922a9
FRONTEND_DEPLOY_TOOLING=164098a7b69caf37f4ede7616b7d02ac481d2629
```

Backend و Frontend هر دو از release deterministic و verify‌شده فعال شدند. Build workspace پس از closure موفق پاک شد.

## Production preflight و config evidence

قبل از activation نهایی:

```text
BACKEND_ENV_SHA=edae0af2a804f8901cb683370157ff4997b993b396e856088439164e5c3f2568
FRONTEND_ENV_SHA=97945445a405b492b961ab296ecfb416aaae52cab9ee9a7aefe1bebdb7dc5fcb
ORDERS=6
PAYMENT_ATTEMPTS=6
GOOGLE_AUTH_ENABLED=true
OTP_ENABLED=false
SMS_PROVIDER=disabled
ORDER_SMS_PROVIDER=disabled
CHECKOUT_ENABLED=true
PAYMENT_ENABLED=true
PAYMENT_PROVIDER=zarinpal
ZARINPAL_SANDBOX=false
```

Backend/Frontend preflight هر دو PASS شدند. Google Login و پرداخت واقعی زرین‌پال قبلاً در F31 اثبات شده بودند و در این maintenance دوباره تکرار نشدند.

## Migration evidence

سه migration جدید Audit32 روی Production موفق اجرا شدند:

```text
2026_09_10_221000_add_weight_range_to_bakery_product_variants=APPLIED_ONCE
2026_09_10_223000_add_cover_url_to_bakery_content_pages=APPLIED_ONCE
2026_09_11_001000_add_admin_followup_audit_fields=APPLIED_ONCE
```

هیچ‌کدام به `orders` یا `payment_attempts` mutation تجاری اضافه نکردند. شمارش business قبل و بعد از sync و QA ثابت ماند:

```text
ORDERS=6 -> 6
PAYMENT_ATTEMPTS=6 -> 6
```

## Media regeneration evidence — Audit #4

Regeneration تاریخی Production بعد از deploy واقعی Backend انجام شد و دیگر نباید تکرار شود مگر evidence جدید defect ایجاد کند.

قبل از regeneration:

```text
MEDIA_ASSETS=18
SOURCE_MEDIA=18
CONVERSIONS_READY=15
PREVIEW_BUDGET_OK=15
SOURCE_MISSING=0
OVERSIZED=0
FAILED_JOBS=9
```

فقط conversionهای `thumb` و `preview` با قرارداد Spatie و `--force` regenerate شدند. Originalها قبل و بعد با SHA-256 قفل شدند.

بعد از regeneration:

```text
MEDIA_ASSETS=18
SOURCE_MEDIA=18
CONVERSIONS_READY=18
PREVIEW_BUDGET_OK=18
SOURCE_MISSING=0
OVERSIZED=0
ORIGINAL_MEDIA_MUTATION=ZERO
FAILED_JOBS=9 -> 9
```

پس Audit #4 از `PRODUCTION ACTION REMAINS` به **PRODUCTION CLOSED / PASS** تغییر یافت.

## Delivery Zone evidence — Audit #26

هیچ دادهٔ ساختگی Production ساخته نشد:

```text
DELIVERY_ZONES=0
ACTIVE_DELIVERY_ZONES=0
FAKE_DELIVERY_ZONE_CREATED=NO
DELIVERY_ZONE_MUTATION=ZERO
```

Runtime فعلی عمداً zone را authority قیمت/checkout نمی‌داند:

```text
DELIVERY_ZONE_KEY=EXISTS
DELIVERY_ZONE_VALUE=NULL
DELIVERY_FEE_PAYMENT=PAY_ON_DELIVERY_TO_COURIER
DELIVERY_FEE_INCLUDED_IN_ORDER=FALSE
```

یعنی روش جاری merchant-arranged courier است و هزینهٔ ارسال در مبلغ سفارش محاسبه نمی‌شود و هنگام تحویل به پیک پرداخت می‌شود. اگر کسب‌وکار بعداً Zone واقعی بخواهد، فقط دادهٔ واقعی مالک فروشگاه ثبت می‌شود؛ نبود Zone فعلی defect کد یا blocker Audit32 نیست.

## Final Production QA

تمام surfaceهای زیر در closure نهایی HTTP 200 یا health PASS داشتند:

```text
/api/system/ready=200
/api/store/settings=200
/api/store/navigation=200
/api/catalog/categories=200
/api/catalog/products=200
/api/store/faqs=200
/api/store/gallery=200
/api/store/posts=200
/api/push/capabilities=200
/api/auth/capabilities=200
/api/delivery/options=200
/admin=200

https://winimibakery.com/=200
https://winimibakery.com/products=200
https://winimibakery.com/manifest.webmanifest=200
https://winimibakery.com/sw.js=200
SSR_HEALTH=PASS
```

سرویس‌های نهایی:

```text
nginx.service=active
php8.3-fpm.service=active
winimi-backend-queue.service=active
winimi-backend-scheduler.timer=active
winimi-backend-backup.timer=active
winimi-frontend.service=active
QUEUE_CWD=/var/www/winimi/backend/releases/70044e514b51b463e18b/app
```

Disk در closure نهایی:

```text
FREE_KIB=5464188
AVAILABLE≈5.3GiB
USE=82%
```

## Incidents حین sync و دلیل عدم تکرار کورکورانه

سه توقف tooling/test رخ داد که هیچ‌کدام defect Runtime پذیرفته‌شده نبودند:

1. Frontend preflight یک‌بار مستقیم اجرا شد و به‌دلیل executable bit با `Permission denied` متوقف شد. Deploy هنوز شروع نشده بود؛ invocation به `bash deploy/bin/preflight-frontend-server.sh` اصلاح شد.
2. پس از activation موفق Backend، wrapper بالادستی دقیقاً بعد از `queue:restart` وضعیت queue را بدون retry سنجید و در پنجره restart non-zero گرفت. state-aware proof نشان داد release `70044e...` فعال و queue از همان release اجرا می‌شود؛ Backend دوباره deploy نشد.
3. Delivery QA اولیه برای key با مقدار `null` از PHP null-coalescing (`??`) استفاده کرد و `zone: null` را اشتباه missing تشخیص داد. assertion با `array_key_exists()` اصلاح شد؛ runtime policy بدون mutation PASS شد.

قانون آینده: اگر evidence فعلی معتبر است، هیچ‌یک از این مراحل صرفاً به‌خاطر history بالا دوباره اجرا نشوند.

## ماتریس نهایی Audit ۳۲ موردی

1. **Push consent و eligible count — CLOSED:** Broadcast عمومی فقط `marketingRecipients()` فعال و opt-in شده را هدف می‌گیرد و تعداد واجد شرایط پیش از ارسال نمایش داده می‌شود.
2. **Review integrity — CLOSED:** `status` و `published_at` در فرم مستقیم قابل تغییر نیستند؛ transition فقط از Actionهای تأیید/رد انجام می‌شود.
3. **Media source of truth — CLOSED:** Bakery Media Library مرجع تصاویر جدید پنل است؛ مسیرهای مستقیم Product در runtime غیرفعال‌اند، Curator قدیمی Navigation ندارد و Media قبلی حذف نشده است.
4. **WebP/regeneration — CLOSED / PRODUCTION PASS:** هر 18 source دارای `thumb`/`preview` آماده است، preview budget PASS است و Originalها بدون تغییر باقی مانده‌اند.
5. **Image optimization — CLOSED:** مشتق‌های بهینه، محدودیت ابعاد/ورودی و سقف preview حداکثر ۱MB enforce می‌شود.
6. **Media operator metadata — CLOSED:** Preview، URL Original/Optimized، اندازه، ابعاد، فرمت و وضعیت conversion در پنل در دسترس‌اند.
7. **Gallery media/link picker — CLOSED:** تصویر از Media Library و مقصد از Internal Link picker انتخاب می‌شود؛ legacy value بدون حذف حفظ می‌شود.
8. **Blog featured image — CLOSED:** Cover از Media Library مرکزی انتخاب می‌شود.
9. **Category/Home image authority — CLOSED:** Category image از authority مرکزی می‌آید و Frontend تصویر API را مقدم بر fallback محلی مصرف می‌کند.
10. **Category landing internal links — CLOSED:** picker جستجوپذیر برای مقاله/محصول/دسته/صفحه و URL مدیریت‌شده وجود دارد.
11. **FAQ UX — CLOSED:** Category ساختاریافته، reorder، editor مدیریت‌شده و حذف bulk خطرناک حذف شده است.
12. **Modern editor — CLOSED:** Tiptap مرکزی، Media Library، internal link، sanitization، native `hurdle` Callout و Preview تکمیل شده است؛ Frontend فقط قرارداد Callout مجاز را render می‌کند.
13. **Article detail polish — CLOSED:** typography/heading/list/quote/media/spacing و ساختار مقاله پذیرفته شده است.
14. **Home article/mobile polish — CLOSED:** کارت‌های مقاله و رفتار responsive پذیرفته شده‌اند.
15. **User-visible branding/PWA — CLOSED:** manifest، favicon، Apple Touch و maskable icons روی WINIMI هستند.
16. **Admin WINIMI branding — CLOSED:** brand name/logo/favicon پنل WINIMI است.
17. **Admin translation — CLOSED:** labelهای اپراتوری و ابزارهای مدیریت فارسی‌سازی شده‌اند؛ raw technical values فقط در بخش‌های فنی باقی مانده‌اند.
18. **Navigation architecture — CLOSED:** فقط شش گروه `فروشگاه`، `محتوا`، `بازاریابی و سئو`، `ارتباطات`، `تنظیمات فروشگاه`، `سیستم و امنیت` مجازند.
19. **Sensitive/developer permissions — CLOSED:** ابزارهای حساس طبق نقش محدود شده‌اند و guard داخلی دارند.
20. **Users meaning/access — CLOSED:** Resource `مدیران پنل` است و مدیریت آن Super Admin-only است؛ secretهای 2FA نمایش داده نمی‌شوند.
21. **Store Settings UX — CLOSED:** authority واقعی F30، save بخش‌بندی‌شده و concurrent-edit fail-closed برقرار است.
22. **Customer privacy — CLOSED:** شماره تماس برای اپراتور عادی mask و bypass state ممنوع است.
23. **Customer disable audit — CLOSED:** disable/enable کنترل‌شده با reason/actor/timestamp است و order history حذف نمی‌شود.
24. **Delivery validation — CLOSED:** preparation coherence enforce شده و bulk delete خطرناک وجود ندارد.
25. **Province/City normalization — CLOSED:** normalization ی/ک، ZWNJ و whitespace برقرار است.
26. **Real Delivery Zone — CLOSED BY BUSINESS POLICY:** Zone ساختگی ایجاد نشده؛ runtime فعلی zone را authority نمی‌داند. هر Zone آینده فقط با دادهٔ واقعی کسب‌وکار ثبت می‌شود.
27. **Payment operator UX — CLOSED:** خلاصه انسانی مقدم و gateway codes در جزئیات فنی محدود هستند.
28. **Notification Outbox labels — CLOSED:** provider/template/channel/status برای اپراتور قابل‌فهم‌اند.
29. **General Push preview — CLOSED:** عنوان، متن، مقصد، نوع، consent scope و eligible count قبل از confirmation نمایش داده می‌شوند.
30. **Inquiry follow-up — CLOSED:** owner، internal note و last-action timestamp در workflow وجود دارند.
31. **City Pages — CLOSED BY POLICY:** صفحه شهری جعلی ایجاد نشده و editor/media/link استاندارد برای داده واقعی آماده است.
32. **No fake filler content — CLOSED BY POLICY:** برای Article، City Page، Review، Inquiry، Gallery، Category یا Delivery Zone داده جعلی Production ایجاد نشده است.

## Editor و Media — منبع رسمی

- `awcodes/filament-tiptap-editor` قفل‌شده روی `v3.5.16` است.
- `Filament\Forms\Components\Actions\Action` از Filament `v3.3.55` استفاده می‌شود.
- `ManagedHtmlSanitizer` فقط class دقیق `filament-tiptap-hurdle` و toneهای محدود را می‌پذیرد.
- Spatie Media Library قفل‌شده روی `11.23.7` است؛ conversionهای Audit32 فقط `thumb` و `preview` هستند.

## شواهد تاریخی که بدون regression تکرار نمی‌شوند

- Google Login واقعی Production
- authenticated checkout
- پرداخت واقعی و verified زرین‌پال
- Web Push live delivery
- backup/restore/reboot/rollback Phase19B

Admin Audit32 هیچ مجوزی برای تکرار این تست‌های واقعی ایجاد نمی‌کند مگر evidence جدید regression مشخص کند.

## منابع رسمی استفاده‌شده

- [Filament 3 — Forms / component actions](https://filamentphp.com/docs/3.x/forms/actions)
- [Filament v3.3.55 — Forms Action source](https://github.com/filamentphp/filament/blob/v3.3.55/packages/forms/src/Components/Actions/Action.php)
- [awcodes Tiptap v3.5.16 — Hurdle Node](https://github.com/awcodes/filament-tiptap-editor/blob/v3.5.16/src/Extensions/Nodes/Hurdle.php)
- [awcodes Tiptap v3.5.16 — Hurdle JS](https://github.com/awcodes/filament-tiptap-editor/blob/v3.5.16/resources/js/extensions/Hurdle.js)
- [Spatie Media Library 11.23.7](https://github.com/spatie/laravel-medialibrary/tree/11.23.7)
- قرارداد داخلی Storefront authority: `database/migrations/2026_09_06_153500_seed_storefront_authority.php`
- Gate داخلی: `.github/workflows/f30-storefront-authority.yml`

## NEXT

```text
ADMIN_AUDIT32=CLOSED
PRODUCTION_MAINTENANCE_SYNC=PASS
PRODUCTION_REDEPLOY=NO
MEDIA_REGEN_REPEAT=NO
PAYMENT_RETEST=NO
GOOGLE_LOGIN_RETEST=NO
ORDER_MUTATION=NO
PAYMENT_MUTATION=NO
FAKE_BUSINESS_DATA=NO
NEXT=POST_HANDOFF_MAINTENANCE_ONLY
```
