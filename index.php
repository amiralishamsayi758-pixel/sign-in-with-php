<?php

declare(strict_types=1);

$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];
$old = array_merge(['gmail' => '', 'phone' => '', 'username' => ''], $old);
$codePreparedSuccessfully = ($_GET['status'] ?? '') === 'prepared';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="فرم ایجاد حساب کاربری">
    <title>ایجاد حساب کاربری</title>

    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('theme');
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const useDarkTheme = savedTheme ? savedTheme === 'dark' : systemPrefersDark;

                document.documentElement.classList.toggle('dark', useDarkTheme);
                document.documentElement.style.colorScheme = useDarkTheme ? 'dark' : 'light';
            } catch (error) {
                document.documentElement.classList.toggle(
                    'dark',
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                );
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        ink: '#172033',
                        brand: '#0f766e',
                        coral: '#e76f51'
                    }
                }
            }
        };
    </script>
    <style type="text/tailwindcss">
        @layer components {
            .field-input {
                @apply block w-full rounded-md border border-white/80 bg-white/60 px-11 py-3 text-left text-sm font-medium text-ink shadow-[inset_0_1px_0_rgba(255,255,255,0.95),0_10px_28px_-18px_rgba(15,23,42,0.35)] outline-none backdrop-blur-lg [transition-property:background-color,border-color,box-shadow,color,transform] duration-[400ms] ease-out placeholder:font-normal placeholder:text-slate-400 hover:border-teal-300/70 hover:bg-white/75 focus:border-teal-500/70 focus:bg-white/[0.85] focus:shadow-[inset_0_1px_0_rgba(255,255,255,1),0_14px_32px_-16px_rgba(13,148,136,0.3)] focus:ring-4 focus:ring-teal-500/10 active:bg-white/90 dark:border-white/10 dark:bg-white/[0.06] dark:text-slate-100 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_12px_28px_-18px_rgba(0,0,0,0.85)] dark:placeholder:text-slate-500 dark:hover:border-teal-300/30 dark:hover:bg-white/[0.09] dark:focus:border-teal-300/70 dark:focus:bg-white/[0.11] dark:focus:shadow-[inset_0_1px_0_rgba(255,255,255,0.13),0_14px_32px_-16px_rgba(13,148,136,0.3)] dark:focus:ring-teal-400/10 dark:active:bg-white/[0.13] lg:px-12 lg:py-3.5 lg:text-base xl:py-4;
                direction: ltr;
                -webkit-backdrop-filter: blur(16px) saturate(135%);
                backdrop-filter: blur(16px) saturate(135%);
            }

            .field-input.is-valid {
                @apply border-emerald-500/70 bg-emerald-50/70 pr-10 text-ink focus:border-emerald-500 focus:bg-emerald-50/90 focus:ring-emerald-500/10 dark:border-emerald-400/60 dark:bg-emerald-400/[0.08] dark:text-slate-100 dark:focus:border-emerald-300/80 dark:focus:bg-emerald-400/[0.13] dark:focus:ring-emerald-400/10;
            }

            .field-input.is-invalid {
                @apply border-rose-500 bg-rose-50/75 pr-10 text-ink focus:border-rose-600 focus:bg-rose-50/95 focus:ring-rose-500/10 dark:border-rose-400/80 dark:bg-rose-500/[0.09] dark:text-slate-100 dark:focus:border-rose-300 dark:focus:bg-rose-500/[0.14] dark:focus:ring-rose-400/15;
            }

            .field-message {
                @apply mt-1.5 min-h-5 text-xs text-slate-500 transition-colors duration-[400ms] dark:text-slate-400 lg:mt-2 lg:min-h-6 lg:text-sm;
            }
        }

        @keyframes logo-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes logo-orbit {
            to { transform: rotate(360deg); }
        }

        .auth-logo {
            animation: logo-float 4.5s ease-in-out infinite;
        }

        .auth-logo-orbit {
            animation: logo-orbit 14s linear infinite;
            transform-origin: center;
        }

        .theme-sun,
        .theme-moon {
            transition: opacity 400ms ease, transform 400ms ease;
            transform-origin: center;
        }

        .theme-sun {
            opacity: 1;
            transform: rotate(0deg) scale(1);
        }

        .theme-moon {
            opacity: 0;
            transform: rotate(-70deg) scale(0.55);
        }

        .dark .theme-sun {
            opacity: 0;
            transform: rotate(90deg) scale(0.55);
        }

        .dark .theme-moon {
            opacity: 1;
            transform: rotate(0deg) scale(1);
        }

        .logo-surface {
            fill: rgba(255, 255, 255, 0.78);
            stroke: rgba(15, 118, 110, 0.2);
            transition: fill 400ms ease, stroke 400ms ease;
        }

        .dark .logo-surface {
            fill: rgba(30, 41, 45, 0.88);
            stroke: rgba(94, 234, 212, 0.25);
        }

        .field-input:-webkit-autofill,
        .field-input:-webkit-autofill:hover,
        .field-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #172033;
            caret-color: #172033;
            border-color: rgba(94, 234, 212, 0.55);
            -webkit-box-shadow: inset 0 0 0 1000px rgba(248, 250, 252, 0.94);
            box-shadow: inset 0 0 0 1000px rgba(248, 250, 252, 0.94);
            transition: background-color 9999s ease-out 0s;
        }

        .dark .field-input:-webkit-autofill,
        .dark .field-input:-webkit-autofill:hover,
        .dark .field-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #f1f5f9;
            caret-color: #f1f5f9;
            -webkit-box-shadow: inset 0 0 0 1000px rgba(30, 45, 50, 0.96);
            box-shadow: inset 0 0 0 1000px rgba(30, 45, 50, 0.96);
        }

        @media (prefers-reduced-motion: reduce) {
            .auth-logo,
            .auth-logo-orbit,
            .theme-sun,
            .theme-moon {
                animation: none;
                transition: none;
            }

            .theme-panel,
            .theme-panel *,
            .logo-surface {
                scroll-behavior: auto;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-ink antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(360px,0.85fr)_minmax(520px,1.15fr)]">
        <section class="relative hidden min-h-screen overflow-hidden lg:block" aria-label="فضای کاری مدرن">
            <img
                src="https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1600&q=85"
                alt="فضای کاری روشن و مدرن"
                class="absolute inset-0 h-full w-full object-cover"
            >
            <div class="absolute inset-0 bg-slate-950/55"></div>
            <div class="relative flex h-full max-w-xl flex-col justify-between p-12 xl:p-16">
                <a href="#" class="w-fit text-xl font-extrabold text-white" aria-label="صفحه اصلی هم‌مسیر">
                    هم<span class="text-teal-300">‌مسیر</span>
                </a>

                <div class="pb-10 text-white">
                    <p class="mb-4 inline-flex border-r-2 border-coral pr-3 text-sm font-semibold text-teal-100">
                        شروعی ساده و مطمئن
                    </p>
                    <h1 class="max-w-lg text-4xl font-extrabold leading-tight xl:text-5xl">
                        حساب شما، نقطه شروع ارتباط‌های بهتر
                    </h1>
                    <p class="mt-5 max-w-md text-base leading-8 text-slate-200">
                        اطلاعات پایه را وارد کنید تا حساب کاربری شما برای مراحل بعدی آماده شود.
                    </p>
                </div>
            </div>
        </section>

        <section class="theme-panel relative flex min-h-screen items-center justify-center bg-[linear-gradient(145deg,#f8fafc_0%,#edf7f5_52%,#e7eef2_100%)] px-4 py-8 transition-colors duration-500 dark:bg-[linear-gradient(145deg,#11161d_0%,#18242a_52%,#0d2021_100%)] sm:px-8 lg:px-10 xl:px-14 2xl:px-20">
            <button
                id="themeToggle"
                type="button"
                aria-label="فعال کردن حالت تیره"
                class="absolute left-4 top-4 z-10 grid h-11 w-11 place-items-center rounded-md border border-white/80 bg-white/[0.65] text-slate-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.95),0_10px_24px_-16px_rgba(15,23,42,0.45)] backdrop-blur-lg transition-[background-color,border-color,color,box-shadow] duration-[400ms] hover:border-teal-300/70 hover:bg-white/[0.85] hover:text-teal-700 focus:outline-none focus:ring-4 focus:ring-teal-500/20 active:scale-95 dark:border-white/10 dark:bg-white/[0.07] dark:text-slate-300 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_12px_26px_-16px_rgba(0,0,0,0.8)] dark:hover:border-teal-300/35 dark:hover:bg-white/[0.11] dark:hover:text-teal-200 dark:focus:ring-teal-400/20 sm:left-6 sm:top-6"
            >
                <span class="relative h-5 w-5" aria-hidden="true">
                    <svg class="theme-sun absolute inset-0 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    <svg class="theme-moon absolute inset-0 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z"/></svg>
                </span>
            </button>

            <div class="w-full max-w-md lg:max-w-lg xl:max-w-[34rem] 2xl:max-w-xl">
                <a href="#" class="mb-8 block w-fit text-xl font-extrabold text-ink transition-colors duration-[400ms] dark:text-slate-100 lg:hidden" aria-label="صفحه اصلی هم‌مسیر">
                    هم<span class="text-brand transition-colors duration-[400ms] dark:text-teal-300">‌مسیر</span>
                </a>

                <div class="rounded-lg border border-white/75 bg-white/[0.72] p-6 shadow-[0_22px_60px_-28px_rgba(15,23,42,0.35),0_0_38px_-30px_rgba(13,148,136,0.35)] backdrop-blur-xl transition-[background-color,border-color,box-shadow] duration-500 dark:border-white/[0.08] dark:bg-[#111b20]/[0.85] dark:shadow-[0_24px_70px_-28px_rgba(0,0,0,0.9),0_0_42px_-28px_rgba(45,212,191,0.55)] sm:p-9 lg:p-10 xl:p-11">
                    <header class="mb-8 flex items-start justify-between gap-4 sm:gap-6 lg:mb-10 lg:gap-8">
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 text-sm font-semibold text-brand transition-colors duration-[400ms] dark:text-teal-300 lg:text-base">ایجاد حساب</p>
                            <h2 class="text-2xl font-extrabold leading-snug text-ink transition-colors duration-[400ms] dark:text-slate-50 sm:text-3xl lg:text-[2rem] lg:leading-[1.45] 2xl:text-4xl">اطلاعات خود را وارد کنید</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-500 transition-colors duration-[400ms] dark:text-slate-400 lg:mt-4 lg:text-base lg:leading-8">تمام فیلدها الزامی هستند.</p>
                        </div>

                        <div class="auth-logo relative mt-0.5 h-14 w-14 shrink-0 sm:h-16 sm:w-16 lg:h-[4.5rem] lg:w-[4.5rem]" role="img" aria-label="نشان هم‌مسیر">
                            <div class="absolute inset-1 rounded-full bg-teal-400/15 blur-lg transition-colors duration-[400ms] dark:bg-teal-400/25"></div>
                            <svg class="relative h-full w-full drop-shadow-[0_8px_12px_rgba(15,118,110,0.2)]" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle class="logo-surface" cx="36" cy="36" r="31" stroke-width="2"/>
                                <circle class="auth-logo-orbit" cx="36" cy="36" r="27" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" stroke-dasharray="24 12 5 18"/>
                                <path d="M22 38.5C22 30.49 28.27 24 36 24s14 6.49 14 14.5" stroke="#0f766e" stroke-width="5" stroke-linecap="round"/>
                                <path d="M25 46c3.25-3.33 6.92-5 11-5s7.75 1.67 11 5" stroke="#e76f51" stroke-width="5" stroke-linecap="round"/>
                                <circle cx="36" cy="35" r="4" fill="#0f766e"/>
                            </svg>
                        </div>
                    </header>

                    <?php if ($codePreparedSuccessfully): ?>
                        <div class="mb-6 rounded-md border border-emerald-500/30 bg-emerald-50/75 px-4 py-3 text-sm leading-7 text-emerald-800 shadow-sm backdrop-blur-md dark:border-emerald-400/25 dark:bg-emerald-400/[0.08] dark:text-emerald-200" role="status" aria-live="polite">
                            اطلاعات معتبر است و کد تأیید جدید با اعتبار دو دقیقه ایجاد شد.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($errors['general'])): ?>
                        <div class="mb-6 rounded-md border border-rose-500/35 bg-rose-50/80 px-4 py-3 text-sm leading-7 text-rose-800 shadow-sm backdrop-blur-md dark:border-rose-400/30 dark:bg-rose-500/[0.09] dark:text-rose-200" role="alert">
                            <?= e($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <form id="signupForm" action="process.php" method="post" novalidate class="space-y-5 lg:space-y-6 xl:space-y-7">
                        <div>
                            <label for="gmail" class="mb-2 block text-sm font-semibold text-slate-700 transition-colors duration-[400ms] dark:text-slate-300 lg:mb-2.5 lg:text-[0.95rem]">آدرس جیمیل</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                </span>
                                <input
                                    id="gmail"
                                    name="gmail"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    inputmode="email"
                                    pattern="^[a-zA-Z0-9.!#$%&amp;'*+/=?^_`{|}~-]+@gmail\.com$"
                                    placeholder="example@gmail.com"
                                    aria-describedby="gmailMessage"
                                    value="<?= e((string) $old['gmail']) ?>"
                                    <?= isset($errors['gmail']) ? 'aria-invalid="true"' : '' ?>
                                    class="field-input<?= isset($errors['gmail']) ? ' is-invalid' : '' ?>"
                                >
                                <span class="status-icon pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2" aria-hidden="true"></span>
                            </div>
                            <p id="gmailMessage" class="field-message<?= isset($errors['gmail']) ? ' text-rose-700 dark:text-rose-300' : '' ?>"><?= isset($errors['gmail']) ? e($errors['gmail']) : 'آدرس باید به @gmail.com ختم شود.' ?></p>
                        </div>

                        <div>
                            <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700 transition-colors duration-[400ms] dark:text-slate-300 lg:mb-2.5 lg:text-[0.95rem]">شماره موبایل</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 18h4"/></svg>
                                </span>
                                <input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    required
                                    autocomplete="tel"
                                    inputmode="numeric"
                                    pattern="^09[0-9]{9}$"
                                    minlength="11"
                                    maxlength="11"
                                    placeholder="09123456789"
                                    aria-describedby="phoneMessage"
                                    value="<?= e((string) $old['phone']) ?>"
                                    <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>
                                    class="field-input<?= isset($errors['phone']) ? ' is-invalid' : '' ?>"
                                >
                                <span class="status-icon pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2" aria-hidden="true"></span>
                            </div>
                            <p id="phoneMessage" class="field-message<?= isset($errors['phone']) ? ' text-rose-700 dark:text-rose-300' : '' ?>"><?= isset($errors['phone']) ? e($errors['phone']) : '۱۱ رقم و با 09 شروع شود.' ?></p>
                        </div>

                        <div>
                            <label for="username" class="mb-2 block text-sm font-semibold text-slate-700 transition-colors duration-[400ms] dark:text-slate-300 lg:mb-2.5 lg:text-[0.95rem]">نام کاربری</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                                </span>
                                <input
                                    id="username"
                                    name="username"
                                    type="text"
                                    required
                                    autocomplete="username"
                                    minlength="5"
                                    maxlength="10"
                                    placeholder="۵ تا ۱۰ کاراکتر"
                                    aria-describedby="usernameMessage"
                                    value="<?= e((string) $old['username']) ?>"
                                    <?= isset($errors['username']) ? 'aria-invalid="true"' : '' ?>
                                    class="field-input<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                                >
                                <span class="status-icon pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2" aria-hidden="true"></span>
                            </div>
                            <p id="usernameMessage" class="field-message<?= isset($errors['username']) ? ' text-rose-700 dark:text-rose-300' : '' ?>"><?= isset($errors['username']) ? e($errors['username']) : 'نام کاربری باید بین ۵ تا ۱۰ کاراکتر باشد.' ?></p>
                        </div>

                        <button
                            type="submit"
                            class="mt-2 flex w-full items-center justify-center gap-2 rounded-md bg-[linear-gradient(135deg,#0f766e,#115e59)] px-5 py-3.5 text-sm font-bold text-white shadow-[0_12px_28px_-14px_rgba(20,184,166,0.5)] transition-[filter,box-shadow,transform] duration-300 hover:brightness-110 hover:shadow-[0_14px_32px_-14px_rgba(45,212,191,0.65)] focus:outline-none focus:ring-4 focus:ring-teal-500/20 active:translate-y-px active:brightness-90 dark:shadow-[0_12px_28px_-14px_rgba(20,184,166,0.65)] dark:focus:ring-teal-400/20 lg:py-4 lg:text-base xl:py-[1.125rem]"
                        >
                            ادامه ثبت‌نام
                            <svg class="h-5 w-5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs leading-6 text-slate-500 transition-colors duration-[400ms] dark:text-slate-500">
                    با ادامه، قوانین استفاده و حریم خصوصی را می‌پذیرید.
                </p>
            </div>
        </section>
    </main>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

        function applyTheme(theme, savePreference = false) {
            const isDark = theme === 'dark';

            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            themeToggle.setAttribute('aria-pressed', String(isDark));
            themeToggle.setAttribute(
                'aria-label',
                isDark ? 'فعال کردن حالت روشن' : 'فعال کردن حالت تیره'
            );

            if (savePreference) {
                try {
                    localStorage.setItem('theme', theme);
                } catch (error) {
                    // The theme still works when browser storage is unavailable.
                }
            }
        }

        themeToggle.addEventListener('click', () => {
            const nextTheme = document.documentElement.classList.contains('dark')
                ? 'light'
                : 'dark';

            applyTheme(nextTheme, true);
        });

        systemTheme.addEventListener('change', (event) => {
            try {
                if (localStorage.getItem('theme')) return;
            } catch (error) {
                // Follow the system theme when storage cannot be read.
            }

            applyTheme(event.matches ? 'dark' : 'light');
        });

        applyTheme(
            document.documentElement.classList.contains('dark') ? 'dark' : 'light'
        );

        const form = document.getElementById('signupForm');

        const fields = {
            gmail: {
                input: document.getElementById('gmail'),
                message: document.getElementById('gmailMessage'),
                hint: 'آدرس باید به @gmail.com ختم شود.',
                required: 'وارد کردن آدرس جیمیل الزامی است.',
                invalid: 'یک آدرس معتبر با پسوند @gmail.com وارد کنید.'
            },
            phone: {
                input: document.getElementById('phone'),
                message: document.getElementById('phoneMessage'),
                hint: '۱۱ رقم و با 09 شروع شود.',
                required: 'وارد کردن شماره موبایل الزامی است.',
                invalid: 'شماره موبایل باید دقیقاً ۱۱ رقم و با 09 شروع شود.'
            },
            username: {
                input: document.getElementById('username'),
                message: document.getElementById('usernameMessage'),
                hint: 'نام کاربری باید بین ۵ تا ۱۰ کاراکتر باشد.',
                required: 'وارد کردن نام کاربری الزامی است.',
                invalid: 'نام کاربری باید حداقل ۵ و حداکثر ۱۰ کاراکتر داشته باشد.'
            }
        };

        const icons = {
            valid: '<svg class="h-5 w-5 text-emerald-600 dark:text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4 4L19 6"/></svg>',
            invalid: '<svg class="h-5 w-5 text-rose-600 dark:text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>'
        };

        function setFieldState(field, showEmptyError = false) {
            const { input, message, hint, required, invalid } = field;
            const icon = input.parentElement.querySelector('.status-icon');
            const hasValue = input.value.trim() !== '';

            input.classList.remove('is-valid', 'is-invalid');
            icon.classList.add('hidden');

            if (!hasValue && !showEmptyError) {
                message.textContent = hint;
                message.className = 'field-message';
                input.removeAttribute('aria-invalid');
                return;
            }

            if (input.checkValidity()) {
                input.classList.add('is-valid');
                message.textContent = 'مقدار واردشده معتبر است.';
                message.className = 'field-message text-emerald-700 dark:text-emerald-300';
                input.setAttribute('aria-invalid', 'false');
                icon.innerHTML = icons.valid;
            } else {
                input.classList.add('is-invalid');
                message.textContent = input.validity.valueMissing ? required : invalid;
                message.className = 'field-message text-rose-700 dark:text-rose-300';
                input.setAttribute('aria-invalid', 'true');
                icon.innerHTML = icons.invalid;
            }

            icon.classList.remove('hidden');
        }

        fields.phone.input.addEventListener('input', (event) => {
            event.target.value = event.target.value.replace(/\D/g, '').slice(0, 11);
        });

        Object.values(fields).forEach((field) => {
            field.input.addEventListener('input', () => setFieldState(field));
            field.input.addEventListener('blur', () => setFieldState(field, true));
        });

        form.addEventListener('submit', (event) => {
            Object.values(fields).forEach((field) => setFieldState(field, true));

            if (!form.checkValidity()) {
                event.preventDefault();
                form.querySelector(':invalid').focus();
            }
        });
    </script>
</body>
</html>
