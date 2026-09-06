# Baseline قطعی Backend وینیمی

تاریخ ثبت: ۲۰۲۶-۰۸-۲۹ UTC

## هویت Production

| مورد | مقدار |
|---|---|
| Repository | `sajadkhavas/winimi-bakery-backend` |
| Branch | `phase-25/f5-zarinpal-production` |
| Source SHA | `eb002a6d5f093e7780d3cf6333b3e5f83f96e57b` |
| Active release | `/var/www/winimi/backend/releases/eb002a6d5f093e7780d3` |
| Queue/Scheduler | فعال و Process CWD منطبق با Release |
| Health / Ready | HTTP 200 در ممیزی ۲۰۲۶-۰۸-۲۹ |

مقایسه محتوایی Release با Git tree همین SHA نشان داد: فایل Source اصلاح‌شده `0`، اختلاف mode برابر `0` و Source delta قابل‌ثبت برابر `0` است. نبودن `bootstrap/cache/.gitignore` و وجود `storage`/`public/storage` رفتار بسته‌بندی و Runtime هستند و نباید به‌عنوان تغییر Source از Production Commit شوند.

## وضعیت قابلیت‌ها

- چرخه پرداخت زرین‌پال در Production پیاده‌سازی شده است؛ توسعه مجدد لازم نیست.
- OTP و کنترل‌های امنیتی آن باید در کد باقی بمانند.
- تا تکمیل حساب کاوه‌نگار کارفرما، OTP باید با Feature Flag در Backend غیرفعال شود؛ صرفاً مخفی‌سازی UI کافی نیست.
- Auth موقت تحویل با Google Login خواهد بود.
- بعد از اولین Google Login، دریافت شماره موبایل ایران اجباری است؛ تا OTP موفق، شماره تأییدنشده و `phone_verified_at` خالی می‌ماند.
- Account linking صرفاً بر پایه ورودی Frontend، ایمیل دلخواه یا شماره تکراری ممنوع است؛ Google provider subject و سیاست امن Linking لازم است.
- Credentialهای Google، Kavenegar، Zarinpal و Production فقط در Secret/Environment قرار می‌گیرند و وارد Git نمی‌شوند.

## کارهای Backend باقی‌مانده تا تحویل

1. قرارداد امن Google OAuth و Session/Cookie
2. Migration/Model لازم برای Provider identity و وضعیت تأیید موبایل
3. تکمیل اجباری شماره موبایل با جلوگیری از Merge خودکار حساب تکراری
4. Feature Flag برای غیرفعال‌سازی کامل Endpointهای عمومی OTP
5. تست Auth، Rate limit، CSRF/state، Logout و Account collision
6. Regression سفارش، موجودی، پرداخت موفق/ناموفق، Callback و Idempotency
7. ثبت Handoff، Production activation و rollback evidence

هر فاز بعدی باید از SHA فعال بالا یا Baseline پذیرفته‌شده‌ای که صریحاً جانشین آن شده شروع شود؛ `main` به‌تنهایی منبع وضعیت Production نیست.
