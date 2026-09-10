<x-filament-panels::page>
    <p>بخش موردنظر را انتخاب کنید، تغییرات همان بخش را انجام دهید و ذخیره کنید. قبل از رفتن به بخش دیگر، تغییرات را ذخیره کنید.</p>
    <nav class="flex flex-wrap gap-2" aria-label="بخش‌های تنظیمات فروشگاه">
        @foreach ($this::SECTIONS as $key => $definition)
            <x-filament::button
                :color="$section === $key ? 'primary' : 'gray'"
                wire:click="changeSection('{{ $key }}')"
                wire:loading.attr="disabled"
                :aria-current="$section === $key ? 'page' : null"
            >{{ $definition['label'] }}</x-filament::button>
        @endforeach
    </nav>
    <form wire:submit="save" class="space-y-6" wire:key="settings-{{ $section }}">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="disabled">ذخیره تنظیمات این بخش</x-filament::button>
    </form>
    <x-filament-actions::modals />
</x-filament-panels::page>
