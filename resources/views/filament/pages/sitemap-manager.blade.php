<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border p-6 shadow-sm bg-white dark:bg-gray-900">
            <h3 class="font-bold text-gray-700 dark:text-gray-200 mb-4">وضعیت Sitemap اصلی وینیمی</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg p-4 {{ $reachable ? 'bg-green-50 border border-green-200 dark:bg-green-950 dark:border-green-800' : 'bg-red-50 border border-red-200 dark:bg-red-950 dark:border-red-800' }}">
                    <div class="flex items-center gap-2">
                        @if($reachable)
                            <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
                            <span class="text-sm font-medium text-green-700 dark:text-green-300">Sitemap سالم است</span>
                        @else
                            <x-heroicon-o-x-circle class="w-5 h-5 text-red-500" />
                            <span class="text-sm font-medium text-red-700 dark:text-red-300">نیاز به بررسی</span>
                        @endif
                    </div>
                </div>

                <div class="rounded-lg p-4 bg-gray-50 border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-xs text-gray-400 mb-1">تعداد URLها</div>
                    <div class="text-2xl font-bold text-gray-700 dark:text-gray-200">
                        {{ $urlCount ?? '—' }}
                    </div>
                </div>

                <div class="rounded-lg p-4 bg-gray-50 border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-xs text-gray-400 mb-1">آخرین بررسی</div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ $lastChecked ?? '—' }}
                    </div>
                </div>
            </div>

            @if($statusMessage)
                <div class="mt-4 rounded-lg border p-3 text-sm {{ $reachable ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-950 dark:border-green-800 dark:text-green-300' : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-300' }}">
                    {{ $statusMessage }}
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="refreshSitemapStatus" icon="heroicon-o-arrow-path">
                بررسی مجدد Sitemap
            </x-filament::button>

            <x-filament::button
                tag="a"
                :href="$sitemapUrl"
                target="_blank"
                color="gray"
                icon="heroicon-o-eye">
                مشاهده Sitemap اصلی
            </x-filament::button>
        </div>

        <div class="rounded-xl border p-5 bg-blue-50 border-blue-200 dark:bg-blue-950 dark:border-blue-800">
            <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-2">راهنما</h4>
            <ul class="text-sm text-blue-600 dark:text-blue-400 space-y-1 list-disc list-inside">
                <li>Sitemap به‌صورت داینامیک توسط فرانت وینیمی ساخته می‌شود و نیازی به ساخت فایل دستی ندارد.</li>
                <li>آدرس canonical: <code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">https://winimibakery.com/sitemap.xml</code></li>
                <li>فقط همین آدرس را در Google Search Console ثبت کنید.</li>
                <li>وجود localhost، ToolMaster یا URLهای پروژه قبلی در Sitemap یک خطای بحرانی محسوب می‌شود.</li>
                <li>آدرس قدیمی API به Sitemap اصلی وینیمی هدایت می‌شود تا هیچ نسخه موازی یا stale باقی نماند.</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>
