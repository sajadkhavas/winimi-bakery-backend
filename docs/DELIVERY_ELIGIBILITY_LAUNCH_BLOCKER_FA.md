# WINIMI — قرارداد نهایی Eligibility ارسال قبل از Launch

تاریخ: 2026-09-11

این سند Issue #25 را برای اصلاح منطق Checkout محدود می‌کند و مجوز تغییر سیاست قیمت‌گذاری ارسال، پرداخت، سفارش‌های موجود یا روش‌های legacy نیست.

## قانون کسب‌وکار

- سبد کاملاً خشک / بدون نیاز به زنجیره سرد: ارسال سراسری کشور با روش فعال استاندارد.
- اگر حتی یک قلم `requires_cooling=true` باشد، کل سبد از نظر Eligibility یخچالی است.
- شهرهای تأییدشده پایه برای محصول یخچالی: `تهران`، `کرج`، `اندیشه`.
- محدوده‌های اطراف فقط زمانی مجازند که نام دقیق شهر واقعاً تأیید شده و در یک `DeliveryZone` فعال با `city` صریح و `chilled_enabled=true` ثبت شده باشد.
- Zone استان‌محور بدون شهر، wildcard برای ارسال سرد نیست.
- Frontend مرجع نهایی Eligibility نیست؛ Checkout Backend باید قبل از ساخت Order/OrderItem/Reservation مقصد نامعتبر را رد کند.

## سیاست‌هایی که تغییر نمی‌کنند

- روش canonical سفارش جدید: `standard`.
- `chilled` و `pickup` به‌عنوان مقادیر legacy حفظ می‌شوند و به Checkout جدید اضافه نمی‌شوند.
- هزینه پیک داخل مبلغ سفارش محاسبه نمی‌شود.
- `delivery_fee_toman=0` و `delivery_zone_id=null` برای سفارش جدید حفظ می‌شوند.
- هزینه ارسال هنگام تحویل مستقیماً به پیک پرداخت می‌شود.
- DeliveryZone برای قیمت‌گذاری authoritative نمی‌شود؛ فقط می‌تواند پوشش سرد را به یک شهر صریح و تأییدشده توسعه دهد.
- پرداخت زرین‌پال، Google Login، OTP/Kavenegar و سفارش‌های پرداخت‌شده خارج از Scope این اصلاح‌اند.

## Acceptance

1. Dry cart در مقصد خارج از شهرهای پایه بدون DeliveryZone قابل Checkout باشد.
2. Chilled/mixed cart در مقصد پشتیبانی‌نشده با 422 و قبل از business mutation رد شود.
3. تهران، کرج و اندیشه بدون نیاز به seed ساختگی DeliveryZone پذیرفته شوند.
4. یک شهر تست خارج از پایه تنها با Zone فعال + `chilled_enabled=true` مجاز شود.
5. Zone غیرفعال، Zone بدون chilled، یا Zone بدون city مجوز ایجاد نکند.
6. `/api/delivery/options` Eligibility را با همان قرارداد نمایش دهد، بدون تغییر fee policy.
7. تست‌های Checkout قبلی، idempotency، inventory reservation و payment contract regression نداشته باشند.
8. قبل از Production فقط preflight/read-only انجام شود؛ real payment acceptance تکرار نشود مگر evidence قبلی invalidate شود.
