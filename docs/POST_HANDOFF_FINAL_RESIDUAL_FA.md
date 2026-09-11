# WINIMI — Post-handoff final residual closure

این سند فقط سه اصلاح باقیمانده پس از Merge بسته اصلی Maintenance را ثبت می‌کند و هیچ فاز بسته‌شده‌ای را دوباره باز نمی‌کند.

## دامنه

1. گالری بیکری باید به‌جای URL دستی، تصویر را از `Bakery Media Library` انتخاب کند؛ URL فعلی قدیمی باید بدون حذف ناخواسته حفظ شود.
2. حذف گروهی آیتم‌های گالری مجاز نباشد و ترتیب نمایش از `sort_order` قابل جابه‌جایی باشد.
3. صفحات هسته `about`, `shipping`, `privacy`, `terms`, `quality` از حذف و تغییر ناخواسته slug در پنل محافظت شوند.

## ایمنی

- هیچ Business Data واقعی برای تست ساخته یا تغییر داده نمی‌شود.
- Checkout، Payment، Auth، Backup و Environment Production در این اصلاحات تغییر نمی‌کنند.
- هیچ Deploy مرحله‌ای انجام نمی‌شود.
- Merge فقط پس از سبزشدن Exact-head CI انجام می‌شود.
- Production فقط یک‌بار و پس از Post-merge CI و Read-only preflight کامل Deploy می‌شود.
