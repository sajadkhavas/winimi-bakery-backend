<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">شروع سریع مدیریت وینیمی</x-slot>
        <x-slot name="description">سه مسیر پرکاربرد برای مدیریت روزانه؛ بدون نیاز به ورود به بخش‌های فنی.</x-slot>

        <div class="grid gap-4 md:grid-cols-3">
            <a
                href="{{ $this->productCreateUrl() }}"
                class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-cake" class="h-6 w-6 text-primary-600" />
                    <span class="font-bold text-gray-950 dark:text-white">افزودن محصول</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    محصول، قیمت، Variant، موجودی، تحویل و وضعیت انتشار را ثبت کنید.
                </p>
            </a>

            <a
                href="{{ $this->mediaLibraryUrl() }}"
                class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-photo" class="h-6 w-6 text-primary-600" />
                    <span class="font-bold text-gray-950 dark:text-white">افزودن یا تأیید تصویر</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Original را بارگذاری و وضعیت WebP، حجم بهینه و اتصال به محصول را بررسی کنید.
                </p>
            </a>

            <a
                href="{{ $this->blogUrl() }}"
                class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-newspaper" class="h-6 w-6 text-primary-600" />
                    <span class="font-bold text-gray-950 dark:text-white">ثبت مقاله یا راهنما</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    مقاله را با Editor مدیریت‌شده، لینک داخلی و Media Library مرکزی آماده انتشار کنید.
                </p>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
