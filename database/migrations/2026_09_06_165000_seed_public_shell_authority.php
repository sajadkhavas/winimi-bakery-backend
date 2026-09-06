<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_settings')) {
            return;
        }

        $now = now();
        $definitions = [
            ['catalog', 'catalog.meta_title', 'string', 'محصولات وینیمی', 'SEO Title فروشگاه'],
            ['catalog', 'catalog.meta_description', 'string', 'مشاهده، جست‌وجو، فیلتر و خرید آنلاین محصولات فعال وینیمی با قیمت و موجودی دریافت‌شده از سرور.', 'Meta Description فروشگاه'],
            ['catalog', 'catalog.heading', 'string', 'محصولات وینیمی', 'H1 فروشگاه'],
            ['catalog', 'catalog.intro', 'string', 'محصول موردنظرت را با دسته‌بندی، جست‌وجو، مرتب‌سازی و فیلترهای فروشگاه پیدا کن.', 'مقدمه فروشگاه'],
            ['catalog', 'catalog.categories_title', 'string', 'دسته را انتخاب کن یا با فیلترها میان همه محصولات بگرد', 'عنوان دسته‌های فروشگاه'],
            ['catalog', 'catalog.categories_description', 'string', 'هر دسته یک URL مستقل و قابل اشتراک دارد، اما انتخاب، فیلتر، مرتب‌سازی و محصولات همگی داخل همین فروشگاه باقی می‌مانند.', 'توضیح دسته‌های فروشگاه'],
            ['catalog', 'catalog.all_products_label', 'string', 'همه محصولات', 'برچسب همه محصولات'],

            ['blog_index', 'blog_index.meta_title', 'string', 'راهنماهای وینیمی', 'SEO Title فهرست راهنماها'],
            ['blog_index', 'blog_index.meta_description', 'string', 'مقاله‌های منتشرشده وینیمی در موضوعات واقعی فروشگاه برای انتخاب، سفارش و نگهداری آگاهانه‌تر.', 'Meta Description فهرست راهنماها'],
            ['blog_index', 'blog_index.heading', 'string', 'راهنماهای وینیمی', 'H1 فهرست راهنماها'],
            ['blog_index', 'blog_index.intro', 'string', 'مقاله‌های منتشرشده وینیمی در موضوعات واقعی فروشگاه برای انتخاب، سفارش و نگهداری آگاهانه‌تر.', 'مقدمه فهرست راهنماها'],

            ['contact_page', 'contact_page.meta_title', 'string', 'تماس با ما', 'SEO Title تماس'],
            ['contact_page', 'contact_page.meta_description', 'string', 'راه‌های ارتباط رسمی با وینیمی و فرم امن ثبت درخواست پشتیبانی و همکاری.', 'Meta Description تماس'],
            ['contact_page', 'contact_page.heading', 'string', 'تماس با ما', 'H1 تماس'],
            ['contact_page', 'contact_page.intro', 'string', 'برای پشتیبانی، همکاری یا پیگیری، درخواست خود را ثبت کنید تا در پنل فروشگاه قابل پیگیری باشد.', 'مقدمه تماس'],
            ['contact_page', 'contact_page.phone_title', 'string', 'تلفن رسمی', 'عنوان تلفن'],
            ['contact_page', 'contact_page.email_title', 'string', 'ایمیل رسمی', 'عنوان ایمیل'],
            ['contact_page', 'contact_page.location_title', 'string', 'محدوده اعلام‌شده برند', 'عنوان محدوده'],
            ['contact_page', 'contact_page.map_label', 'string', 'مشاهده محدوده در نقشه', 'برچسب نقشه'],
            ['contact_page', 'contact_page.hours_title', 'string', 'ساعات پاسخ‌گویی', 'عنوان ساعات پاسخ‌گویی'],
            ['contact_page', 'contact_page.hours_note', 'string', 'بازه دقیق و ثابت اعلام نشده است؛ هماهنگی از مسیرهای رسمی انجام می‌شود.', 'یادداشت ساعات پاسخ‌گویی'],
            ['contact_page', 'contact_page.locations_cta_label', 'string', 'مناطق منتشرشده ارسال', 'CTA مناطق ارسال'],
            ['contact_page', 'contact_page.shop_cta_label', 'string', 'شروع سفارش از فروشگاه', 'CTA فروشگاه'],
            ['contact_page', 'contact_page.instagram_label', 'string', 'اینستاگرام رسمی', 'برچسب اینستاگرام'],
            ['contact_page', 'contact_page.inquiry_title', 'string', 'ثبت درخواست تماس', 'عنوان فرم تماس'],
            ['contact_page', 'contact_page.inquiry_description', 'string', 'پیام شما در بک‌اند ذخیره می‌شود و تیم فروشگاه می‌تواند آن را در پنل مدیریت بررسی و پیگیری کند.', 'توضیح فرم تماس'],
            ['contact_page', 'contact_page.inquiry_subject_label', 'string', 'موضوع درخواست', 'برچسب موضوع تماس'],
            ['contact_page', 'contact_page.inquiry_message_label', 'string', 'پیام شما', 'برچسب پیام تماس'],

            ['faq_page', 'faq_page.meta_title', 'string', 'سوالات متداول', 'SEO Title سوالات متداول'],
            ['faq_page', 'faq_page.meta_description', 'string', 'پاسخ‌های منتشرشده فروشگاه درباره سفارش، پرداخت، ارسال و محصولات.', 'Meta Description سوالات متداول'],
            ['faq_page', 'faq_page.heading', 'string', 'سوالات متداول', 'H1 سوالات متداول'],
            ['faq_page', 'faq_page.intro', 'string', 'پاسخ‌های مدیریت‌شده وینیمی', 'مقدمه سوالات متداول'],
            ['faq_page', 'faq_page.all_label', 'string', 'همه', 'برچسب همه دسته‌های FAQ'],
            ['faq_page', 'faq_page.support_title', 'string', 'پاسخ سوال‌تان را نیافتید؟', 'عنوان پشتیبانی FAQ'],
            ['faq_page', 'faq_page.whatsapp_label', 'string', 'پشتیبانی واتساپ', 'برچسب واتساپ FAQ'],
            ['faq_page', 'faq_page.contact_label', 'string', 'ثبت درخواست تماس', 'برچسب تماس FAQ'],

            ['gallery_page', 'gallery_page.meta_title', 'string', 'گالری', 'SEO Title گالری'],
            ['gallery_page', 'gallery_page.meta_description', 'string', 'تصاویر منتشرشده وینیمی از منبع محتوای فروشگاه.', 'Meta Description گالری'],
            ['gallery_page', 'gallery_page.heading', 'string', 'گالری تصاویر', 'H1 گالری'],
            ['gallery_page', 'gallery_page.intro', 'string', 'تصاویر مدیریت‌شده محصولات، بسته‌بندی و فرآیند آماده‌سازی', 'مقدمه گالری'],

            ['locations_page', 'locations_page.meta_title', 'string', 'مناطق منتشرشده ارسال وینیمی', 'SEO Title مناطق ارسال'],
            ['locations_page', 'locations_page.meta_description', 'string', 'صفحه‌های رسمی و منتشرشده وینیمی برای بررسی شرایط سفارش و ارسال در هر شهر؛ محدوده و روش نهایی تحویل در Checkout تأیید می‌شود.', 'Meta Description مناطق ارسال'],
            ['locations_page', 'locations_page.heading', 'string', 'مناطق منتشرشده ارسال وینیمی', 'H1 مناطق ارسال'],
            ['locations_page', 'locations_page.intro', 'string', 'صفحه‌های رسمی و منتشرشده وینیمی برای بررسی شرایط سفارش و ارسال در هر شهر؛ محدوده و روش نهایی تحویل در Checkout تأیید می‌شود.', 'مقدمه مناطق ارسال'],
            ['locations_page', 'locations_page.eyebrow', 'string', 'صفحات محلی مدیریت‌شده', 'برچسب مناطق ارسال'],
            ['locations_page', 'locations_page.city_cta_prefix', 'string', 'مشاهده شرایط', 'پیشوند CTA شهر'],
            ['locations_page', 'locations_page.brand_info_title', 'string', 'اطلاعات ثابت برند', 'عنوان اطلاعات برند'],
            ['locations_page', 'locations_page.brand_info_description', 'string', 'این اطلاعات برای شناسایی و ارتباط با وینیمی در همه صفحه‌ها یکسان است. وجود صفحه شهر به معنی وجود شعبه فیزیکی در آن شهر نیست.', 'توضیح اطلاعات برند'],

            ['reviews_page', 'reviews_page.meta_title', 'string', 'نظرهای تأییدشده مشتریان', 'SEO Title نظرهای مشتریان'],
            ['reviews_page', 'reviews_page.meta_description', 'string', 'نظرهای خرید تأییدشده و منتشرشده از بک‌اند وینیمی.', 'Meta Description نظرهای مشتریان'],
            ['reviews_page', 'reviews_page.heading', 'string', 'نظرهای تأییدشده مشتریان', 'H1 نظرهای مشتریان'],
            ['reviews_page', 'reviews_page.intro', 'string', 'فقط نظرهای تأییدشده مرتبط با سفارش تحویل‌شده نمایش داده می‌شوند.', 'مقدمه نظرهای مشتریان'],
            ['reviews_page', 'reviews_page.breadcrumb_label', 'string', 'نظرهای مشتریان', 'Breadcrumb نظرهای مشتریان'],

            ['managed_page_shell', 'managed_page_shell.related_title', 'string', 'مسیرهای مرتبط', 'عنوان مسیرهای مرتبط'],
            ['managed_page_shell', 'managed_page_shell.products_label', 'string', 'مشاهده محصولات', 'CTA محصولات صفحه مدیریت‌شده'],
            ['managed_page_shell', 'managed_page_shell.contact_label', 'string', 'تماس با پشتیبانی', 'CTA تماس صفحه مدیریت‌شده'],
            ['managed_page_shell', 'managed_page_shell.final_title', 'string', 'درباره محصولات یا شرایط سفارش سؤال دارید؟', 'عنوان CTA پایانی صفحه مدیریت‌شده'],
            ['managed_page_shell', 'managed_page_shell.final_description', 'string', 'اطلاعات نهایی هر محصول و وضعیت سفارش را پیش از ثبت درخواست بررسی کنید.', 'توضیح CTA پایانی صفحه مدیریت‌شده'],
            ['managed_page_shell', 'managed_page_shell.final_contact_label', 'string', 'ارتباط با وینیمی', 'CTA تماس پایانی'],
            ['managed_page_shell', 'managed_page_shell.final_shop_label', 'string', 'ورود به فروشگاه', 'CTA فروشگاه پایانی'],
        ];

        foreach ($definitions as [$group, $key, $type, $value, $label]) {
            DB::table('store_settings')->insertOrIgnore([
                'group' => $group,
                'key' => $key,
                'type' => $type,
                'value' => $value,
                'label' => $label,
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_settings')) {
            return;
        }

        DB::table('store_settings')
            ->whereIn('key', [
                'catalog.meta_title', 'catalog.meta_description', 'catalog.heading', 'catalog.intro', 'catalog.categories_title', 'catalog.categories_description', 'catalog.all_products_label',
                'blog_index.meta_title', 'blog_index.meta_description', 'blog_index.heading', 'blog_index.intro',
                'contact_page.meta_title', 'contact_page.meta_description', 'contact_page.heading', 'contact_page.intro', 'contact_page.phone_title', 'contact_page.email_title', 'contact_page.location_title', 'contact_page.map_label', 'contact_page.hours_title', 'contact_page.hours_note', 'contact_page.locations_cta_label', 'contact_page.shop_cta_label', 'contact_page.instagram_label', 'contact_page.inquiry_title', 'contact_page.inquiry_description', 'contact_page.inquiry_subject_label', 'contact_page.inquiry_message_label',
                'faq_page.meta_title', 'faq_page.meta_description', 'faq_page.heading', 'faq_page.intro', 'faq_page.all_label', 'faq_page.support_title', 'faq_page.whatsapp_label', 'faq_page.contact_label',
                'gallery_page.meta_title', 'gallery_page.meta_description', 'gallery_page.heading', 'gallery_page.intro',
                'locations_page.meta_title', 'locations_page.meta_description', 'locations_page.heading', 'locations_page.intro', 'locations_page.eyebrow', 'locations_page.city_cta_prefix', 'locations_page.brand_info_title', 'locations_page.brand_info_description',
                'reviews_page.meta_title', 'reviews_page.meta_description', 'reviews_page.heading', 'reviews_page.intro', 'reviews_page.breadcrumb_label',
                'managed_page_shell.related_title', 'managed_page_shell.products_label', 'managed_page_shell.contact_label', 'managed_page_shell.final_title', 'managed_page_shell.final_description', 'managed_page_shell.final_contact_label', 'managed_page_shell.final_shop_label',
            ])
            ->delete();
    }
};
