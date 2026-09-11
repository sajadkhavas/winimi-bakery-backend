# Audit ۳۲ موردی پنل WINIMI — GitHub Closure

آخرین GitHub reconciliation: 2026-09-11

## مرجع نهایی

- Repository Backend: `sajadkhavas/winimi-bakery-backend`
- Backend PR: #20 — `Complete WINIMI 32-item admin delivery audit` — **MERGED**
- Backend final PR HEAD: `e3d46ccec2a037f4226f5db10a07977ca08349a4`
- Backend accepted main / merge SHA: `fc93669455d9bf22fe41260b192d76fb1e65f284`
- Repository Frontend: `sajadkhavas/cooci`
- Frontend companion PR: #58 — `Render WINIMI managed editor callouts safely` — **MERGED**
- Frontend implementation HEAD: `506519ced3d68e4c42991c848faf05d376063782`
- Frontend accepted main / merge SHA: `ca074dbd0664c88a7d618299ba04d8d20d729b07`
- F31 تاریخی بسته و Production آن قبلاً تحویل شده است؛ Admin Audit 32 یک maintenance پس از تحویل است.
- **Accepted mainهای بالا هنوز به‌عنوان Production deployed source این maintenance ثبت نشده‌اند.**

## نتیجهٔ GitHub closure

```text
AUDIT_CODE_SCOPE=32_OF_32_RECONCILED
BACKEND_PR20=MERGED
FRONTEND_PR58=MERGED
BACKEND_POST_MERGE_CI=PASS
FRONTEND_POST_MERGE_CI=PASS
COORDINATED_BACKEND_FRONTEND_PHASE18=PASS
PRODUCTION_MAINTENANCE_SYNC=PENDING
```

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

بعد از Merge شدن Backend، همان Phase18 روی Frontend HEAD دوباره اجرا شد. workflow صریحاً Backend `main` را checkout می‌کند و job جدید `103085747602` روی run `34540720028` با Backend `main=fc936694...` کامل `SUCCESS` شد. این coordinated acceptance شامل Laravel install/migrations/seed، backend adversarial acceptance، delivery contract، Production SSR build، desktop/mobile browser، SEO 10.3–10.9، PWA، final adversarial و scroll baseline بود.

بلافاصله قبل از Merge Frontend:
- PR head همچنان دقیقاً `506519ced3d68e4c42991c848faf05d376063782` بود.
- Review threads: `0`.
- Frontend `main` بدون drift و دقیقاً `c0393a041036510675a77216ffe867520216b3e4` بود.
- Merge با `expected_head_sha` انجام شد.

### Frontend post-merge main — `ca074dbd0664c88a7d618299ba04d8d20d729b07`

- Frontend CI #1819 — run `34542268389` — `SUCCESS`
- Phase 8 Deployment Readiness #768 — run `34542268400` — `SUCCESS`
- Phase 19 Production Package #203 — run `34542268410` — `SUCCESS`
- Phase 18 End-to-End Acceptance #635 — run `34542268430` — `SUCCESS`

## ماتریس نهایی Audit ۳۲ موردی

1. **Push consent و eligible count — RECONCILED:** Broadcast عمومی فقط `marketingRecipients()` فعال و opt-in شده را هدف می‌گیرد و تعداد واجد شرایط پیش از ارسال نمایش داده می‌شود.
2. **Review integrity — RECONCILED:** `status` و `published_at` در فرم مستقیم قابل تغییر نیستند؛ transition فقط از Actionهای تأیید/رد انجام می‌شود.
3. **Media source of truth — RECONCILED:** Bakery Media Library مرجع تصاویر جدید پنل است؛ مسیرهای مستقیم Product در runtime غیرفعال‌اند، Curator قدیمی Navigation ندارد و Media قبلی حذف نشده است.
4. **WebP/regeneration — CODE READY / PRODUCTION ACTION REMAINS:** `thumb`/`preview` واقعی WebP تولید و تست می‌شوند و regeneration مشتق‌ها با قرارداد رسمی Spatie و `--force` آماده است؛ regeneration دارایی‌های تاریخی Production هنوز عمداً اجرا نشده است.
5. **Image optimization — RECONCILED:** مشتق‌های بهینه، محدودیت ابعاد/ورودی و سقف عملی preview حداکثر ۱MB برای مصرف جدید enforce می‌شود؛ Original حفظ می‌شود.
6. **Media operator metadata — RECONCILED:** Preview، URL Original/Optimized، اندازه، ابعاد، فرمت و وضعیت conversion در پنل در دسترس‌اند.
7. **Gallery media/link picker — RECONCILED:** تصویر از Media Library و مقصد از Internal Link picker انتخاب می‌شود؛ legacy value بدون حذف حفظ می‌شود.
8. **Blog featured image — RECONCILED:** Cover از Media Library مرکزی انتخاب می‌شود.
9. **Category/Home image authority — RECONCILED:** Category image از authority مرکزی می‌آید و Frontend تصویر API را مقدم بر fallback محلی مصرف می‌کند.
10. **Category landing internal links — RECONCILED:** picker جستجوپذیر برای مقاله/محصول/دسته/صفحه و URL مدیریت‌شده وجود دارد.
11. **FAQ UX — RECONCILED:** Category ساختاریافته، reorder، editor مدیریت‌شده و حذف bulk خطرناک حذف شده است.
12. **Modern editor — RECONCILED:** Tiptap مرکزی با formatting متداول، Media Library، internal link، server-side sanitization، native `hurdle` به‌عنوان Callout، Undo/Redo داخلی پکیج و Preview محتوای ذخیره‌نشده تکمیل شده است. Frontend فقط قرارداد Callout مجاز را render می‌کند.
13. **Article detail polish — RECONCILED:** typography/heading/list/quote/media/spacing و ساختار مقاله در Frontend acceptance موجود است.
14. **Home article/mobile polish — RECONCILED:** کارت‌های مقاله و رفتار responsive در Frontend پذیرفته شده‌اند.
15. **User-visible branding/PWA — RECONCILED:** manifest، favicon، Apple Touch و maskable icons روی WINIMI هستند؛ فایل‌های توسعه‌ای غیرقابل‌نمایش به‌عنوان branding کاربر محسوب نشده‌اند.
16. **Admin WINIMI branding — RECONCILED:** brand name/logo/favicon پنل WINIMI است.
17. **Admin translation — RECONCILED:** labelهای اپراتوری و موارد باقی‌مانده Tiptap/ابزارهای حساس فارسی‌سازی شده‌اند؛ raw technical values فقط در بخش‌های فنی حفظ می‌شوند.
18. **Navigation architecture — RECONCILED:** فقط شش گروه `فروشگاه`، `محتوا`، `بازاریابی و سئو`، `ارتباطات`، `تنظیمات فروشگاه`، `سیستم و امنیت` مجازند و regression test این قرارداد را قفل می‌کند.
19. **Sensitive/developer permissions — RECONCILED:** Queue/Cache/File/Activity/API/Translation/Sitemap/Site Health/Authentication Log و ابزارهای حساس طبق نقش محدود شده‌اند؛ actionهای حساس فقط به پنهان‌کردن Navigation متکی نیستند و guard داخلی دارند.
20. **Users meaning/access — RECONCILED:** Resource به `مدیران پنل` تغییر نام داده و مدیریت آن Super Admin-only است؛ secretهای 2FA نمایش داده نمی‌شوند.
21. **Store Settings UX — RECONCILED:** فرم‌های owner-facing بخش‌بندی‌شده از authority واقعی F30 ساخته شده‌اند؛ key/type/group/is_public از browser قابل hydrate نیست، Save در سطح بخش است و concurrent edit fail-closed می‌شود.
22. **Customer privacy — RECONCILED:** شماره تماس برای اپراتور عادی mask و برای سطح مجاز محدود شده؛ فرم اجازهٔ bypass مستقیم state را نمی‌دهد.
23. **Customer disable audit — RECONCILED:** غیرفعال/فعال‌سازی از Action کنترل‌شده با confirmation، reason، actor و timestamp ثبت می‌شود و تاریخچه سفارش حذف نمی‌شود.
24. **Delivery validation — RECONCILED:** min/max preparation coherence enforce شده و bulk delete خطرناک وجود ندارد.
25. **Province/City normalization — RECONCILED:** ورودی location با trim، ی/ک فارسی، ZWNJ و whitespace normalization یکدست می‌شود.
26. **Real Delivery Zone — RUNTIME BUSINESS DATA ACTION:** نبود Zone واقعی defect کد نیست؛ فقط داده واقعی کسب‌وکار باید در Production ثبت شود یا مسیر ارسال fail-closed/غیرفعال بماند. هیچ Zone جعلی ساخته نشده است.
27. **Payment operator UX — RECONCILED:** خلاصهٔ انسانی مقدم است و authority/payload/gateway codes در جزئیات فنی محدود/جمع‌شونده قرار دارند.
28. **Notification Outbox labels — RECONCILED:** provider/template/channel/status برای اپراتور فارسی و قابل فهم‌اند؛ raw code در DB حفظ شده است.
29. **General Push preview — RECONCILED:** عنوان، متن، مقصد، نوع، consent scope و eligible count قبل از confirmation نمایش داده می‌شوند.
30. **Inquiry follow-up — RECONCILED:** owner، internal note و last-action timestamp به workflow اضافه شده است.
31. **City Pages — RECONCILED BY POLICY:** صفحهٔ شهری جعلی ایجاد نشده؛ editor/media/link استاندارد برای محتوای واقعی آماده است.
32. **No fake filler content — RECONCILED BY POLICY:** برای Article، City Page، Review، Inquiry، Gallery، Category یا Delivery Zone دادهٔ جعلی Production ایجاد نشده است.

### نتیجهٔ Audit

`AUDIT_CODE_SCOPE = 32/32 RECONCILED`

دو مورد زیر عمداً **کدنویسی ناقص محسوب نمی‌شوند**:

- #4: regeneration مشتق‌های media تاریخی روی سرور Production، بدون حذف Original.
- #26: ثبت Delivery Zone واقعی فقط در صورت وجود دادهٔ واقعی کسب‌وکار؛ در غیر این صورت مسیر مربوط باید fail-closed/غیرفعال بماند.

## Editor و Media — منبع رسمی

- نسخهٔ قفل‌شدهٔ `awcodes/filament-tiptap-editor` برابر `v3.5.16` است. `hurdle` از Node رسمی JS و Parser رسمی PHP خود پکیج استفاده می‌کند؛ Custom Extension حدسی اضافه نشده است.
- Preview از `Filament\Forms\Components\Actions\Action` نسخه Filament `v3.3.55` استفاده می‌کند؛ closure به state همان component دسترسی دارد و `modalSubmitAction(false)` مانع Save از Preview می‌شود.
- `ManagedHtmlSanitizer` خروجی Callout را فقط با class دقیق `filament-tiptap-hurdle` و toneهای محدود پذیرفته و attribute/class دلخواه را حذف می‌کند.
- Spatie Media Library نسخه قفل‌شده `11.23.7` است؛ regeneration در Production به دلیل ConfirmableTrait با `--force` انجام می‌شود و فقط derivativeهای `thumb` و `preview` هدف هستند.

## Production status پس از GitHub closure

GitHub closure کامل است، اما maintenance جدید هنوز روی Hostwinds deploy نشده است. Production همچنان runtime تاریخی F31 را اجرا می‌کند تا evidence جدید خلاف آن را ثابت کند:

```text
HISTORICAL_F31_FRONTEND_DEPLOYED_SOURCE=7d5e3fe03b11bc007652908b5f2fff2e78504b31
HISTORICAL_F31_BACKEND_DEPLOYED_SOURCE=a2e5c48e8c73c49caaac1f5c9cbb0f608f066e3b
LATEST_ACCEPTED_FRONTEND_MAIN=ca074dbd0664c88a7d618299ba04d8d20d729b07
LATEST_ACCEPTED_BACKEND_MAIN=fc93669455d9bf22fe41260b192d76fb1e65f284
PRODUCTION_MAINTENANCE_SYNC=PENDING
```

هیچ migration، restart، media regeneration، Delivery Zone mutation، Order mutation یا Payment mutation برای این maintenance از طریق GitHub انجام نشده است.

## قرارداد Final Production sync

Deployment واقعی باید فقط یک‌بار و با Source lock دقیق دو accepted main بالا انجام شود. مسیر versioned مخازن:

Backend:
- `deploy/bin/preflight-backend-server.sh`
- `deploy/bin/deploy-production-backend.sh`
- `deploy/bin/smoke-backend-production.sh`
- `deploy/bin/rollback-backend.sh`

Frontend:
- `deploy/bin/preflight-frontend-server.sh`
- `deploy/bin/deploy-production-frontend.sh`
- smoke و rollback scripts متناظر در repo Frontend.

در اجرای واقعی:
1. Host/source/release lock قبل از mutation ثبت شود.
2. Orders/Payment attempts و checksumهای shared env/runtime env قبل و بعد ثبت شوند.
3. Backend release جدید با migration امن و admin theme/assets فعال شود.
4. queue/scheduler/PHP-FPM فقط طبق deploy contract موجود restart/reload شوند.
5. derivative-only regeneration برای `thumb` و `preview` با `--force` انجام شود؛ Original حذف نشود.
6. Delivery Zone فقط با دادهٔ واقعی کسب‌وکار ثبت شود؛ در نبود اطلاعات واقعی هیچ دادهٔ آزمایشی ساخته نشود.
7. Frontend release deterministic و SSR runtime فعال شود.
8. readiness/health، public surfaces، PWA، admin QA و smoke scripts PASS شوند.
9. در صورت failure از rollback versioned استفاده شود.
10. release IDها و runtime evidence واقعی در `cooci/docs/WINIMI_LIVING_HANDOFF_FA.md` ثبت شوند.

## محدودیت‌های ثابت

Production، Order، Payment و Google Login در PRهای #20/#58 mutate نشده‌اند. Media قدیمی حذف نشده. هیچ محتوای جعلی یا Delivery Zone آزمایشی ساخته نشده. regeneration Production هنوز اجرا نشده است. شواهد Production جدید فقط پس از اجرای واقعی روی سرور ثبت می‌شوند.

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
NEXT=ADMIN_AUDIT32_SINGLE_PRODUCTION_SYNC
GITHUB_IMPLEMENTATION=COMPLETE
GITHUB_DOCUMENTATION=COMPLETE_AFTER_DOCS_PR_MERGE
PRODUCTION_MAINTENANCE_SYNC=PENDING
ORDER_MUTATION=FORBIDDEN
PAYMENT_MUTATION=FORBIDDEN
FAKE_BUSINESS_DATA=FORBIDDEN
```
