<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="flex min-h-screen items-center justify-center p-6">
        <section class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold">تغيير كلمة المرور</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400">اختر كلمة مرور آمنة قبل المتابعة.</p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 flex flex-col gap-5">
                @csrf
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium">كلمة المرور الحالية</span>
                    <input type="password" name="old_password" autocomplete="current-password" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @error('old_password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium">كلمة المرور الجديدة</span>
                    <input type="password" name="new_password" autocomplete="new-password" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-950">
                    @error('new_password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium">تأكيد كلمة المرور الجديدة</span>
                    <input type="password" name="new_password_confirmation" autocomplete="new-password" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-950">
                </label>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2.5 font-medium text-white transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                    حفظ كلمة المرور
                </button>
            </form>
        </section>
    </main>
</body>
</html>
