# ادامهٔ Audit ۳۲ موردی پنل — PR #20

## مرجع ادامه

- Repository Backend: `sajadkhavas/winimi-bakery-backend`
- Backend PR: #20 — Branch: `maintenance/winimi-admin-audit32-completion-20260911`
- Main مبنا: `513af66e3ea1bdaf1eb28abcaa5a3917c17a0383`
- شروع این ادامه: `3fc735412114949d68d79984f295401b264dc510`
- Implementation acceptance HEAD پیش از این ثبت مستندی: `c60ae02fc2448fbecc8b92013fe05f1ada2497d6`
- Repository Frontend: `sajadkhavas/cooci`
- Frontend companion PR: #58 — Branch: `maintenance/winimi-audit32-callout-render-20260911`
- Frontend implementation HEAD: `506519ced3d68e4c42991c848faf05d376063782`
- Frontend main مبنا: `c0393a041036510675a77216ffe867520216b3e4`
- F31 تاریخی بسته است؛ این کار اصلاحات پس از تحویل است و Production را در این مرحله تغییر نمی‌دهد.

## وضعیت پذیرش Implementation

### Backend — `c60ae02fc2448fbecc8b92013fe05f1ada2497d6`

- Backend CI #741 — run `34541247423` — `SUCCESS`
- Phase 18 Backend Acceptance #239 — run `34541247377` — `SUCCESS`
- F30 Storefront Backend Authority #161 — run `34541247389` — `SUCCESS`
- Phase 19 Production Package #227 — run `34541247436` — `SUCCESS`
- Full regression، migrations، Filament discovery، Pint و Composer security audit در Gateهای بالا PASS شده‌اند.

### Frontend — `506519ced3d68e4c42991c848faf05d376063782`

- Frontend CI #1818 — run `34540720209` — `SUCCESS`
- Phase 18 End-to-End Acceptance #634 — run `34540720028` — `SUCCESS`
- Phase 8 Deployment Readiness #767 — run `34540720042` — `SUCCESS`
- Phase 19 Production Package #202 — run `34540720081` — `SUCCESS`
- Phase18 شامل desktop/mobile browser acceptance، SSR، PWA، SEO 10.3–10.9، adversarial acceptance و scroll baseline است.

این Runها شواهد Implementation هستند. چون همین فایل مستند یک commit جدید ایجاد می‌کند، Merge نهایی فقط بعد از exact-head Gateهای HEAD نهایی PR و بررسی مجدد review-thread/base-drift انجام می‌شود.

## ماتریس نهایی Audit ۳۲ موردی

1. **Push consent و eligible count — RECONCILED:** Broadcast عمومی فقط `marketingRecipients()` فعال و opt-in شده را هدف می‌گیرد و تعداد واجد شرایط پیش از ارسال نمایش داده می‌شود.
2. **Review integrity — RECONCILED:** `status` و `published_at` در فرم مستقیم قابل تغییر نیستند؛ transition فقط از Actionهای تأیید/رد انجام می‌شود.
3. **Media source of truth — RECONCILED:** Bakery Media Library مرجع تصاویر جدید پنل است؛ مسیرهای مستقیم Product در runtime غیرفعال‌اند، Curator قدیمی Navigation ندارد و Media قبلی حذف نشده است.
4. **WebP/regeneration — CODE READY / PRODUCTION ACTION REMAINS:** thumb/preview واقعی WebP تولید و تست می‌شوند و regeneration مشتق‌ها با قرارداد رسمی Spatie و `--force` آماده است؛ regeneration دارایی‌های تاریخی Production هنوز عمداً اجرا نشده است.
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

دو مورد زیر عمداً **کدنویسی ناقص محسوب نمی‌شوند** و فقط در Final Production execution انجام/تصمیم‌گیری می‌شوند:

- #4: regeneration مشتق‌های media تاریخی روی سرور Production، بدون حذف Original.
- #26: ثبت Delivery Zone واقعی فقط در صورت وجود دادهٔ واقعی کسب‌وکار؛ در غیر این صورت مسیر مربوط باید fail-closed/غیرفعال بماند.

## Editor و Media — منبع رسمی

- نسخهٔ قفل‌شدهٔ `awcodes/filament-tiptap-editor` برابر `v3.5.16` است. `hurdle` از Node رسمی JS و Parser رسمی PHP خود پکیج استفاده می‌کند؛ Custom Extension حدسی اضافه نشده است.
- Preview از `Filament\Forms\Components\Actions\Action` نسخه Filament `v3.3.55` استفاده می‌کند؛ closure به state همان component دسترسی دارد و `modalSubmitAction(false)` مانع Save از Preview می‌شود.
- `ManagedHtmlSanitizer` خروجی Callout را فقط با class دقیق `filament-tiptap-hurdle` و toneهای محدود پذیرفته و attribute/class دلخواه را حذف می‌کند.
- Spatie Media Library نسخه قفل‌شده `11.23.7` است؛ regeneration در Production به دلیل ConfirmableTrait با `--force` انجام می‌شود و فقط derivativeهای `thumb` و `preview` هدف هستند.

## ترتیب بسته‌شدن PRها و Production

1. exact-head Gateهای Backend بعد از این ثبت مستندی باید سبز باشند.
2. review-thread هر دو PR باید صفر و base-drift باید دوباره صفر باشد.
3. Backend PR #20 با `expected_head_sha` Merge شود و post-merge main CI سبز شود.
4. Frontend PR #58 بعد از Backend accepted main دوباره هماهنگ بررسی شود؛ سپس با `expected_head_sha` Merge و post-merge CI سبز شود.
5. فقط پس از آن یک Final Production Deploy اجرا شود.
6. در Deploy نهایی: migration امن، admin assets/theme، restartهای لازم، health checks و derivative-only media regeneration انجام می‌شود؛ Order/Payment mutation ممنوع است.
7. Production admin QA پس از Deploy انجام و SHA/Run/Runtime evidence در Living Handoff ثبت می‌شود.

## محدودیت‌های ثابت

Production، Order، Payment و Google Login در این PR دست‌نخورده‌اند. Media قدیمی حذف نشده. هیچ محتوای جعلی یا Delivery Zone آزمایشی ساخته نشده. regeneration Production هنوز اجرا نشده است. شواهد Production فقط پس از اجرای واقعی روی سرور ثبت می‌شوند.

## منابع رسمی استفاده‌شده

- [Filament 3 — Forms / component actions](https://filamentphp.com/docs/3.x/forms/actions)
- [Filament v3.3.55 — Forms Action source](https://github.com/filamentphp/filament/blob/v3.3.55/packages/forms/src/Components/Actions/Action.php)
- [awcodes Tiptap v3.5.16 — Hurdle Node](https://github.com/awcodes/filament-tiptap-editor/blob/v3.5.16/src/Extensions/Nodes/Hurdle.php)
- [awcodes Tiptap v3.5.16 — Hurdle JS](https://github.com/awcodes/filament-tiptap-editor/blob/v3.5.16/resources/js/extensions/Hurdle.js)
- [Spatie Media Library 11.23.7](https://github.com/spatie/laravel-medialibrary/tree/11.23.7)
- قرارداد داخلی Storefront authority: `database/migrations/2026_09_06_153500_seed_storefront_authority.php`
- Gate داخلی: `.github/workflows/f30-storefront-authority.yml`
