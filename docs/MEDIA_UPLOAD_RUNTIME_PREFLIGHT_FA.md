# WINIMI — Media Upload Runtime Preflight

این چک فقط برای بستن خطای Production مربوط به `validation.uploaded` در کتابخانه رسانه است و نباید Order، Payment، Google Login، OTP/Kavenegar یا رسانه‌های موجود را تغییر دهد.

## قرارداد اپلیکیشن

- فرمت‌های مجاز: JPEG / PNG / WebP
- سقف هر فایل: **12 MiB = 12,582,912 bytes**
- سقف ابعاد: `6000×6000`
- Original حفظ می‌شود.
- Preview/Thumbnail مشتق‌شده WebP هستند.
- Create تک‌تصویر بدون Source معتبر باید با Validation متوقف شود و نباید رکورد orphan بسازد.
- Bulk Upload و Single Upload باید از یک سقف مشترک استفاده کنند.

## Read-only Production preflight

روی Host واقعی Backend و قبل از Deploy اجرا شود:

```bash
set -Eeuo pipefail

EXPECTED_HOST='hwsrv-1332134.hostwindsdns.com'
[[ "$(hostname -f)" == "$EXPECTED_HOST" ]] || {
  echo "FATAL=WRONG_HOST actual=$(hostname -f)"
  exit 1
}

echo '[NGINX_BODY_LIMIT]'
nginx -T 2>/dev/null | grep -nE 'client_max_body_size' || true

echo '[PHP_FPM_UPLOAD_LIMITS]'
if command -v php-fpm8.3 >/dev/null 2>&1; then
  php-fpm8.3 -i 2>/dev/null | grep -E '^(upload_max_filesize|post_max_size) => ' || true
elif [[ -x /usr/sbin/php-fpm8.3 ]]; then
  /usr/sbin/php-fpm8.3 -i 2>/dev/null | grep -E '^(upload_max_filesize|post_max_size) => ' || true
else
  echo 'PHP_FPM_BINARY_NOT_FOUND'
fi

echo '[CLI_REFERENCE_ONLY]'
php -r 'printf("upload_max_filesize=%s\npost_max_size=%s\n", ini_get("upload_max_filesize"), ini_get("post_max_size"));'
```

## Acceptance

- `client_max_body_size` برای vhost Backend نباید کمتر از 12 MiB باشد؛ مقدار فعلی مستندشده `50M` مناسب است ولی باید روی Host واقعی تأیید شود.
- `upload_max_filesize` در **PHP-FPM** باید حداقل `12M` باشد.
- `post_max_size` در **PHP-FPM** باید از سقف فایل بزرگ‌تر باشد؛ `16M` یا بیشتر مناسب است.
- خروجی CLI فقط مرجع است؛ ملاک Production مقدار FPM است.
- تا وقتی این سه سقف روی Host واقعی تأیید نشده‌اند، خطای قبلی فایل حدود 5MB را نباید صرفاً به Filament یا Spatie نسبت داد.

## Post-deploy acceptance

پس از Deploy نهایی فقط یک Upload کنترل‌شده با فایل تصویری معتبر حدود 5MB انجام شود. معیار قبولی:

1. `validation.uploaded` رخ ندهد.
2. Create بدون Source نامعتبر ممکن نباشد.
3. با Source معتبر فقط یک `BakeryMediaAsset` ساخته شود.
4. Original باقی بماند و conversionها طبق pipeline موجود ساخته شوند.
5. هیچ regeneration گروهی روی رسانه‌های قبلی اجرا نشود.

این تست به Payment/Checkout/Google Login وابسته نیست و نباید acceptance قبلی آن‌ها را تکرار کند.
