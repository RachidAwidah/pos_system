<x-layouts.app title="الإعدادات" subtitle="إعدادات المتجر والطباعة والمخزون">
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ($settingGroups as $group => $settings)
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 p-5"><h2 class="font-black">{{ match ($group) { 'general' => 'الإعدادات العامة', 'financial' => 'الإعدادات المالية', 'printing' => 'إعدادات الطباعة', 'inventory' => 'إعدادات المخزون', default => $group } }}</h2></header>
                <dl class="divide-y divide-slate-100 px-5">
                    @foreach ($settings as $setting)
                        <div class="flex items-center justify-between gap-4 py-4"><dt><span class="block text-sm font-bold text-slate-800">{{ str($setting->key)->replace('_', ' ')->headline() }}</span><span class="mt-1 block text-xs text-slate-400">{{ $setting->key }}</span></dt><dd class="max-w-56 truncate rounded-lg bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">@if (is_bool($setting->typedValue())) {{ $setting->typedValue() ? 'مفعّل' : 'متوقف' }} @else {{ is_array($setting->typedValue()) ? json_encode($setting->typedValue()) : ($setting->typedValue() ?: '—') }} @endif</dd></div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
    <p class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm font-bold text-blue-700">التعديل متوفر عبر API حالياً، وستضاف نماذج التحرير إلى واجهة Blade بعد اعتماد شكل الإعدادات النهائي.</p>
</x-layouts.app>
