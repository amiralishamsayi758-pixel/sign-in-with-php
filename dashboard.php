<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/check-auth.php';

startVerificationSession();

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';
    $authenticatedUser = authenticatedUser($pdo);
} catch (Throwable $exception) {
    redirectTo('index.php?status=database-error');
}

if ($authenticatedUser === null) {
    redirectTo('index.php');
}

function dashboardEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$csrfToken = authenticationCsrfToken();
$flashMessage = pullAuthenticationFlash();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حساب کاربری | هم‌مسیر</title>
    <script>
        (() => {
            try {
                const saved = localStorage.getItem('theme');
                const dark = saved ? saved === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (error) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Vazirmatn', 'sans-serif'] } } }
        };
    </script>
    <style>
        .theme-icon { transition: opacity 400ms ease, transform 400ms ease; }
        .moon { opacity: 0; transform: rotate(-70deg) scale(.55); }
        .dark .sun { opacity: 0; transform: rotate(80deg) scale(.55); }
        .dark .moon { opacity: 1; transform: rotate(0) scale(1); }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition-duration: .01ms !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased transition-colors duration-500 dark:bg-slate-950 dark:text-slate-100">
    <main class="grid min-h-screen lg:grid-cols-[minmax(360px,.85fr)_minmax(520px,1.15fr)]">
        <section class="relative hidden min-h-screen overflow-hidden lg:block" aria-label="فضای کاری مدرن">
            <img src="https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1600&q=85" alt="فضای کاری روشن و مدرن" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-slate-950/60"></div>
            <div class="relative flex h-full flex-col justify-between p-12 xl:p-16">
                <p class="text-xl font-extrabold text-white">هم<span class="text-teal-300">‌مسیر</span></p>
                <div class="max-w-lg pb-10 text-white">
                    <p class="mb-4 border-r-2 border-[#e76f51] pr-3 text-sm font-semibold text-teal-100">ورود امن تکمیل شد</p>
                    <h1 class="text-4xl font-extrabold leading-tight xl:text-5xl">حساب شما آماده استفاده است</h1>
                    <p class="mt-5 leading-8 text-slate-200">هویت شما با کد تأیید بررسی شده و این صفحه فقط با توکن معتبر باز می‌شود.</p>
                </div>
            </div>
        </section>

        <section class="relative flex min-h-screen items-center justify-center bg-[linear-gradient(145deg,#f8fafc_0%,#edf7f5_52%,#e7eef2_100%)] px-4 py-20 transition-colors duration-500 dark:bg-[linear-gradient(145deg,#11161d_0%,#18242a_52%,#0d2021_100%)] sm:px-8 lg:px-12">
            <button id="themeToggle" type="button" aria-label="فعال کردن حالت تیره" class="absolute left-4 top-4 grid h-11 w-11 place-items-center rounded-md border border-white/80 bg-white/65 text-slate-700 shadow-sm backdrop-blur-lg transition duration-300 hover:bg-white/90 focus:outline-none focus:ring-4 focus:ring-teal-500/20 active:scale-95 dark:border-white/10 dark:bg-white/[.07] dark:text-slate-300 sm:left-6 sm:top-6">
                <span class="relative h-5 w-5" aria-hidden="true">
                    <svg class="theme-icon sun absolute inset-0 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.5 1.5M17.6 17.6l1.5 1.5M4.9 19.1l1.5-1.5M17.6 6.4l1.5-1.5"/></svg>
                    <svg class="theme-icon moon absolute inset-0 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z"/></svg>
                </span>
            </button>

            <div class="w-full max-w-xl rounded-lg border border-white/75 bg-white/[.72] p-6 shadow-[0_22px_60px_-28px_rgba(15,23,42,.35),0_0_38px_-30px_rgba(13,148,136,.35)] backdrop-blur-xl transition duration-500 dark:border-white/[.08] dark:bg-[#111b20]/[.85] dark:shadow-[0_24px_70px_-28px_rgba(0,0,0,.9),0_0_42px_-28px_rgba(45,212,191,.55)] sm:p-9 lg:p-10">
                <div class="mb-8 flex items-start justify-between gap-5">
                    <div>
                        <p class="mb-2 text-sm font-semibold text-teal-700 dark:text-teal-300">ناحیه کاربری</p>
                        <h2 class="text-2xl font-extrabold leading-snug sm:text-3xl">خوش آمدید، <?= dashboardEscape((string) $authenticatedUser['username']) ?></h2>
                        <p class="mt-3 text-sm leading-7 text-slate-500 dark:text-slate-400">ورود شما معتبر است و با فعالیت امن تمدید می‌شود.</p>
                    </div>
                    <div class="grid h-14 w-14 shrink-0 place-items-center rounded-full border border-teal-500/20 bg-teal-500/10 text-teal-700 dark:text-teal-300" aria-hidden="true">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4.5 6v5.2c0 4.5 3 7.8 7.5 9.8 4.5-2 7.5-5.3 7.5-9.8V6L12 3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
                    </div>
                </div>

                <?php if ($flashMessage !== null): ?>
                    <div class="mb-6 flex gap-3 rounded-md border border-emerald-500/30 bg-emerald-50/75 px-4 py-3 text-sm leading-7 text-emerald-800 dark:border-emerald-400/25 dark:bg-emerald-400/[.08] dark:text-emerald-200" role="status">
                        <span><?= dashboardEscape($flashMessage) ?></span>
                    </div>
                <?php endif; ?>

                <dl class="divide-y divide-slate-200/70 rounded-md border border-white/80 bg-white/45 px-5 text-sm shadow-inner backdrop-blur-lg dark:divide-white/10 dark:border-white/10 dark:bg-white/[.04]">
                    <div class="flex justify-between gap-4 py-4"><dt class="text-slate-500 dark:text-slate-400">جیمیل</dt><dd class="break-all font-semibold" dir="ltr"><?= dashboardEscape((string) $authenticatedUser['gmail']) ?></dd></div>
                    <div class="flex justify-between gap-4 py-4"><dt class="text-slate-500 dark:text-slate-400">شماره موبایل</dt><dd class="font-semibold" dir="ltr"><?= dashboardEscape((string) $authenticatedUser['phone']) ?></dd></div>
                    <div class="flex justify-between gap-4 py-4"><dt class="text-slate-500 dark:text-slate-400">وضعیت</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">تأییدشده</dd></div>
                </dl>

                <form action="logout.php" method="post" class="mt-7">
                    <input type="hidden" name="csrf_token" value="<?= dashboardEscape($csrfToken) ?>">
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-md border border-rose-500/25 bg-rose-50/60 px-5 py-3.5 font-bold text-rose-700 transition duration-300 hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-500/15 active:translate-y-px dark:border-rose-400/20 dark:bg-rose-500/[.07] dark:text-rose-200 dark:hover:bg-rose-500/[.12]">خروج امن</button>
                </form>
            </div>
        </section>
    </main>
    <script>
        const toggle = document.getElementById('themeToggle');
        function applyTheme(theme, save = false) {
            const dark = theme === 'dark';
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            toggle.setAttribute('aria-pressed', String(dark));
            toggle.setAttribute('aria-label', dark ? 'فعال کردن حالت روشن' : 'فعال کردن حالت تیره');
            if (save) try { localStorage.setItem('theme', theme); } catch (error) {}
        }
        toggle.addEventListener('click', () => applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark', true));
        applyTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    </script>
</body>
</html>
