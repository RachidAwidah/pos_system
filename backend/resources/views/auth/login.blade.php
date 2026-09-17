<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | نقطة</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="grid min-h-screen lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -left-28 -top-28 size-96 rounded-full bg-brand-500/20 blur-3xl"></div>
            <div class="relative flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-2xl bg-brand-500 text-2xl font-black">P</span>
                <span class="text-2xl font-black">نقطة</span>
            </div>
            <div class="relative max-w-xl">
                <p class="text-sm font-bold text-brand-100">إدارة أسرع، قرارات أوضح</p>
                <h1 class="mt-4 text-5xl font-black leading-tight">كل عمليات متجرك في مكان واحد.</h1>
                <p class="mt-6 text-lg leading-8 text-slate-300">مبيعات، مخزون، عملاء وتقارير ضمن واجهة عربية سريعة ومصممة للكاشير والمدير.</p>
            </div>
            <p class="relative text-sm text-slate-500">POS System · Laravel 13</p>
        </section>

        <section class="flex items-center justify-center bg-white p-6 sm:p-10">
            <div class="w-full max-w-md">
                <div class="mb-10 lg:hidden">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-500 text-2xl font-black text-white">P</span>
                </div>
                <h2 class="text-3xl font-black text-slate-950">مرحباً بعودتك</h2>
                <p class="mt-2 text-slate-500">سجّل دخولك للوصول إلى لوحة التحكم.</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 flex flex-col gap-5">
                    @csrf
                    <label class="flex flex-col gap-2">
                        <span class="text-sm font-bold text-slate-700">البريد الإلكتروني</span>
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="flex flex-col gap-2">
                        <span class="text-sm font-bold text-slate-700">كلمة المرور</span>
                        <input type="password" name="password" autocomplete="current-password" required class="rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                        <span>تذكرني على هذا الجهاز</span>
                    </label>
                    <button type="submit" class="rounded-xl bg-brand-500 px-5 py-3.5 font-black text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600">تسجيل الدخول</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
