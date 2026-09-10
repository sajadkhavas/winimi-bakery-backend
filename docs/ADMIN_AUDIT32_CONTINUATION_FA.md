# ادامهٔ Audit ۳۲ موردی پنل — PR #20

## مرجع ادامه

- Repository: `sajadkhavas/winimi-bakery-backend`
- PR: #20، Draft؛ Branch: `maintenance/winimi-admin-audit32-completion-20260911`
- شروع این ادامه: `3fc735412114949d68d79984f295401b264dc510`
- Main مبنا: `513af66e3ea1bdaf1eb28abcaa5a3917c17a0383`
- تغییرات محلی قدیمی چت وارد این Branch نشده‌اند.
- F31 تاریخی بسته است؛ این PR اصلاحات پس از تحویل است.

## شواهد شروع

F30 run `34534207277` / job `103061898382`: ۷ شکست، ۱۶۷ تست موفق.
Backend CI `34534207369` و Package `34534207358` نیز در regression شکست خورده‌اند؛ Phase18 `34534207320` موفق است.
این وضعیت سبز یا آمادهٔ Deploy محسوب نمی‌شود.

## تغییرات این ادامه

1. ترتیب Media: ساخت pending، اتصال Original، تولید مشتق‌ها، سپس بررسی بودجه و ready. Import مسدودشونده با API رسمی Spatie مشتق‌ها را پیش از تخصیص تکمیل می‌کند. Guard سقف ۱MB حذف نشده است. هیچ Import روی Production اجرا نشده.
2. احترام به تنظیم رسمی صف conversion؛ تست‌ها حالت همگام و Production تنظیم صف خود را حفظ می‌کند.
3. تمام گروه‌های منابع محلی پنل به شش گروه Audit منتقل شدند.
4. Store Settings در مسیر قبلی به فرم بخش‌بندی‌شده با یک Save برای هر بخش تبدیل شد؛ مقدارها از رکوردهای موجود F30 خوانده می‌شوند. key/type/group/is_public از مرورگر قابل تغییر نیست. ذخیره اتمیک و تشخیص تغییر هم‌زمان مدیر دیگر اضافه شد. صفحهٔ رکوردهای فنی فقط برای super_admin باقی مانده.
5. Sanitizer اکنون پیش از unwrap، فرزندان تودرتو را پاک می‌کند. نبود DOM خطاست؛ strip_tags با حفظ attributeها جایگزین امنیتی محسوب نمی‌شود.
6. تست اختصاصی `AdminAudit32CompletionTest` برای ذخیرهٔ گروهی، حفاظت قرارداد، مجوز، تعارض و HTML تودرتو اضافه شد.

## بررسی رسمی Editor

نسخهٔ قفل‌شدهٔ `awcodes/filament-tiptap-editor` برابر v3.5.16 است.
در سورس رسمی `resources/views/tiptap-editor.blade.php`، Undo، Redo، Erase و Fullscreen مستقل از فهرست profile رندر می‌شوند؛ افزودن تکراری لازم نیست.
Callout و Preview هنوز باید در همین نسخه با UI امن تکمیل/تست شوند؛ وجود Blockquote یا Fullscreen را معادل آن‌ها گزارش نمی‌کنیم.

## باقی‌ماندهٔ پذیرش

- اجرای CI تغییرات فعلی و رفع تمام خطاهای واقعی؛ PHP/Composer در محیط محلی حاضر نیست.
- تکمیل Callout/Preview و اثبات عملکرد Editor/Media.
- تکمیل regression و تطبیق نهایی تک‌تک ۳۲ مورد، از جمله حقوق دسترسی منابع افزونه‌ها.
- مستندات مرجع فرانت و بک‌اند با SHA و Run ID پذیرفته‌شده؛ فعلاً ادعای تکمیل ندارند.
- exact-head سبز، خروج از Draft، Merge و post-merge CI؛ سپس فقط یک Final Deploy.

## محدودیت‌های ثابت

Production، Order، Payment و Google Login دست‌نخورده‌اند. هیچ محتوای جعلی یا Delivery Zone آزمایشی ساخته نشده. Media قدیمی حذف نشده. دستور regenerate در Production هنوز اجرا نشده. شواهد خرید و اعلان واقعی قبلی تکرار نمی‌شوند.

## منابع رسمی استفاده‌شده

- [Filament 3: فرم Livewire](https://filamentphp.com/docs/3.x/forms/adding-a-form-to-a-livewire-component)
- [Spatie 11.23.7 FileManipulator](https://github.com/spatie/laravel-medialibrary/blob/11.23.7/src/Conversions/FileManipulator.php)
- [Tiptap v3.5.16 toolbar](https://github.com/awcodes/filament-tiptap-editor/blob/v3.5.16/resources/views/tiptap-editor.blade.php)
- قرارداد داخلی: `database/migrations/2026_09_06_153500_seed_storefront_authority.php`
- Gate: `.github/workflows/f30-storefront-authority.yml`
