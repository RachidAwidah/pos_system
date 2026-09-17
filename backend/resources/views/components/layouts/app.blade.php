@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ config('app.name', 'POS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden"></div>

    <aside data-sidebar class="fixed inset-y-0 right-0 z-40 flex w-72 translate-x-full flex-col border-l border-slate-800 bg-slate-950 text-white shadow-2xl transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-20 items-center justify-between border-b border-white/10 px-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-brand-500 text-xl font-black shadow-lg shadow-brand-500/20">P</span>
                <span>
                    <span class="block text-lg font-black">نقطة</span>
                    <span class="block text-xs text-slate-400">نظام نقاط البيع</span>
                </span>
            </a>
            <button type="button" data-sidebar-toggle class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="إغلاق القائمة">✕</button>
        </div>

        @php
            $navigation = [
                ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => '⌂', 'permission' => null],
                ['route' => 'pos.index', 'match' => 'pos.*', 'label' => 'شاشة البيع', 'icon' => '▣', 'permission' => 'sales.create'],
                ['route' => 'products.index', 'match' => 'products.*', 'label' => 'المنتجات', 'icon' => '◇', 'permission' => 'products.view'],
                ['route' => 'categories.index', 'match' => 'categories.*', 'label' => 'شجرة التصنيفات', 'icon' => '⌘', 'permission' => 'categories.view'],
                ['route' => 'orders.index', 'match' => 'orders.*', 'label' => 'الفواتير', 'icon' => '▤', 'permission' => 'sales.view'],
                ['route' => 'reports.index', 'match' => 'reports.*', 'label' => 'التقارير والتحليلات', 'icon' => '▥', 'permission' => 'reports.view_financial'],
                ['route' => 'customers.index', 'match' => 'customers.*', 'label' => 'العملاء', 'icon' => '◎', 'permission' => 'customers.view'],
                ['route' => 'suppliers.index', 'match' => 'suppliers.*', 'label' => 'الموردون', 'icon' => '◉', 'permission' => 'suppliers.view'],
                ['route' => 'users.index', 'match' => 'users.*', 'label' => 'المستخدمون', 'icon' => '♙', 'permission' => 'users.view'],
                ['route' => 'roles.index', 'match' => 'roles.*', 'label' => 'الأدوار والصلاحيات', 'icon' => '◆', 'permission' => 'roles.view'],
                ['route' => 'settings.index', 'match' => 'settings.*', 'label' => 'الإعدادات', 'icon' => '⚙', 'permission' => 'settings.view'],
            ];
        @endphp

        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-4">
            @foreach ($navigation as $item)
                @continue($item['permission'] && ! auth()->user()->hasPermission($item['permission']))
                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold {{ request()->routeIs($item['match']) ? 'bg-brand-500 text-white shadow-lg shadow-brand-500/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                    <span class="grid size-7 place-items-center text-lg">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-500 font-black">{{ str(auth()->user()->full_name)->substr(0, 1) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">{{ auth()->user()->full_name }}</span>
                    <span class="block truncate text-xs text-slate-400">{{ auth()->user()->email }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-slate-400 hover:bg-red-500/10 hover:text-red-300" title="تسجيل الخروج">↪</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="min-h-screen lg:pr-72">
        <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur-lg sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <button type="button" data-sidebar-toggle class="grid size-10 place-items-center rounded-xl border border-slate-200 bg-white text-xl text-slate-700 shadow-sm lg:hidden" aria-label="فتح القائمة">☰</button>
                <div>
                    <h1 class="text-xl font-black text-slate-950 sm:text-2xl">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-1 hidden text-sm text-slate-500 sm:block">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-600 sm:block">{{ now()->translatedFormat('d M Y') }}</span>
                @if (auth()->user()->hasPermission('sales.create'))
                    <a href="{{ route('pos.index') }}" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600">عملية بيع جديدة</a>
                @endif
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</body>
</html>
