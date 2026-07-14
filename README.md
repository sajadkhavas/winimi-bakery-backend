# ToolMaster Backend (Laravel 11 + Filament 3)

بکند کامل Headless برای فرانت‌اند React (PWA) — شامل API نسخه v1، پنل ادمین Filament، مدیریت محصولات، RFQ، بلاگ، اسلایدر و تنظیمات سایت.

---

## پیش‌نیازهای سرور
- PHP **8.2+** (با extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysql`/`pdo_mysql`, `openssl`, `tokenizer`, `xml`, `zip`)
- MySQL **8.0+** (یا MariaDB 10.6+)
- Composer 2.x
- Nginx یا Apache
- (اختیاری) Redis برای کش/صف

برای دستورات کامل راه‌اندازی سرور → فایل **`DEPLOYMENT.md`** را ببینید.

---

## نصب سریع (Local / Dev)
```bash
composer install
cp .env.example .env
php artisan key:generate
# DB credentials در .env تنظیم شود
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

پنل ادمین: `http://localhost:8000/admin`
- ایمیل: `admin@toolmaster.com`
- رمز: `Admin@2025!Change` (بعد از اولین ورود تغییر دهید)

API: `http://localhost:8000/api/v1/products`

---

## ساختار

```
backend/
├── app/
│   ├── Console/Commands/GenerateSitemap.php
│   ├── Filament/                    ← پنل ادمین (8 Resource + SiteSettings + Widgets)
│   ├── Http/Controllers/Api/V1/    ← 10 کنترلر API
│   ├── Http/Resources/             ← 5 JsonResource
│   ├── Mail/                        ← ایمیل تأیید و اعلان RFQ
│   └── Models/                      ← 11 مدل Eloquent
├── config/
├── database/
│   ├── migrations/                  ← 10 جدول
│   └── seeders/                     ← داده‌های اولیه
├── resources/views/
│   ├── emails/                      ← تمپلیت ایمیل‌ها
│   └── filament/pages/             ← صفحه تنظیمات
└── routes/api.php                   ← مسیرهای v1
```

---

## امکانات پنل ادمین (Filament)
| منو | توضیح |
|-----|-------|
| 📦 محصولات | تب‌های پایه/مشخصات‌فنی/گالری/سئو + KeyValue specs + RichEditor |
| 📂 دسته‌بندی‌ها | با مدیریت زیرمجموعه‌ها (RelationManager) + سئو کامل |
| 🏷 برندها | تب اطلاعات + سئو + لوگو |
| 📰 مقالات بلاگ | RichEditor، تگ‌ها، تاریخ انتشار، سئو |
| 📋 استعلام‌ها (RFQ) | لیست با Badge شماره‌ی pending + جزئیات اقلام |
| ✉️ پیام‌های تماس | Badge unread + تغییر وضعیت |
| 🖼 اسلایدر | reorderable + لینک + بج |
| ⚙️ تنظیمات سایت | 4 تب: صفحه‌اصلی/شرکت/شبکه‌اجتماعی/سئو |
| 📊 Dashboard | StatsOverview + LatestRfqWidget |

---

## اتصال فرانت‌اند به این API

در فایل `.env` پروژه‌ی React:
```
VITE_API_BASE_URL=https://api.toolmaster.com/api/v1
```

لایه `src/api/` پروژه‌ی React از قبل کاملاً سازگار با ساختار پاسخ‌های این بکند تنظیم شده است.

---

## فهرست endpoints

```
GET  /api/v1/products
GET  /api/v1/products/featured
GET  /api/v1/products/{slug}
GET  /api/v1/products/{slug}/similar
GET  /api/v1/categories
GET  /api/v1/categories/{slug}
GET  /api/v1/categories/{slug}/products
GET  /api/v1/categories/{slug}/subcategories
GET  /api/v1/brands
GET  /api/v1/brands/{slug}
GET  /api/v1/brands/{slug}/products
GET  /api/v1/blog
GET  /api/v1/blog/latest
GET  /api/v1/blog/{slug}
GET  /api/v1/search?q=...
GET  /api/v1/settings
GET  /api/v1/sliders
POST /api/v1/rfq
GET  /api/v1/rfq/{reference}
POST /api/v1/contact
POST /api/v1/newsletter
```
