<?php

declare(strict_types=1);

require_once __DIR__ . '/verification-helpers.php';

startVerificationSession();

$phone = verificationPhone();

if ($phone === null) {
    redirectTo('index.php');
}

function verificationEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pageError = null;
$user = null;

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';
    $statement = $pdo->prepare(
        'SELECT id, code_expires_at, is_verified, resend_count
         FROM users
         WHERE phone = :phone
         LIMIT 1'
    );
    $statement->execute(['phone' => $phone]);
    $user = $statement->fetch();
} catch (Throwable $exception) {
    $pageError = 'دریافت اطلاعات تأیید ممکن نشد. لطفاً دوباره تلاش کنید.';
}

if ($pageError === null && $user === false) {
    unset($_SESSION[VERIFICATION_PHONE_SESSION_KEY]);
    redirectTo('index.php');
}

$isVerified = is_array($user) && (int) $user['is_verified'] === 1;
$resendCount = is_array($user) ? (int) $user['resend_count'] : 0;
$resendLimitReached = $resendCount >= MAX_RESEND_ATTEMPTS;
$expiresAt = is_array($user)
    ? databaseUtcDateTime((string) $user['code_expires_at'])
    : null;
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$remainingSeconds = $expiresAt === null
    ? 0
    : max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
$isExpired = $remainingSeconds === 0;
$csrfToken = verificationCsrfToken();
$flash = pullVerificationFlash();

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="تأیید کد چهاررقمی حساب کاربری">
    <title>تأیید حساب کاربری</title>

    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const useDark = savedTheme ? savedTheme === 'dark' : prefersDark;
                document.documentElement.classList.toggle('dark', useDark);
                document.documentElement.style.colorScheme = useDark ? 'dark' : 'light';
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
                    colors: { ink: '#172033', brand: '#0f766e', coral: '#e76f51' }
                }
            }
        };
    </script>
    <style type="text/tailwindcss">
        @layer components {
            .code-input {
                @apply h-14 w-12 rounded-md border border-white/80 bg-white/60 text-center text-2xl font-bold text-ink shadow-[inset_0_1px_0_rgba(255,255,255,0.95),0_10px_24px_-18px_rgba(15,23,42,0.4)] outline-none backdrop-blur-lg transition duration-300 placeholder:text-slate-400 hover:border-teal-300/70 hover:bg-white/75 focus:border-teal-500/70 focus:bg-white/90 focus:ring-4 focus:ring-teal-500/10 disabled:cursor-not-allowed disabled:opacity-45 dark:border-white/10 dark:bg-white/[0.07] dark:text-slate-100 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_12px_26px_-18px_rgba(0,0,0,0.85)] dark:hover:border-teal-300/30 dark:hover:bg-white/[0.1] dark:focus:border-teal-300/70 dark:focus:bg-white/[0.12] dark:focus:ring-teal-400/10 sm:h-16 sm:w-14 sm:text-3xl;
            }
        }

        .theme-sun,
        .theme-moon {
            transform-origin: center;
            transition: opacity 400ms ease, transform 400ms ease;
        }
        .theme-sun { opacity: 1; transform: rotate(0deg) scale(1); }
        .theme-moon { opacity: 0; transform: rotate(-70deg) scale(.55); }
        .dark .theme-sun { opacity: 0; transform: rotate(90deg) scale(.55); }
        .dark .theme-moon { opacity: 1; transform: rotate(0deg) scale(1); }

        @keyframes logo-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .auth-logo { animation: logo-float 4.5s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .auth-logo, .theme-sun, .theme-moon { animation: none; transition: none; }
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-ink antialiased dark:bg-slate-950">
    <main class="grid min-h-screen lg:grid-cols-[minmax(360px,0.85fr)_minmax(520px,1.15fr)]">
        <section class="relative hidden min-h-screen overflow-hidden lg:block" aria-label="فضای کاری مدرن">
            <img src="https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1600&q=85" alt="فضای کاری روشن و مدرن" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-slate-950/60"></div>
            <div class="relative flex h-full max-w-xl flex-col justify-between p-12 xl:p-16">
                <a href="index.php" class="w-fit text-xl font-extrabold text-white" aria-label="صفحه اصلی هم‌مسیر">هم<span class="text-teal-300">‌مسیر</span></a>
                <div class="pb-10 text-white">
                    <p class="mb-4 inline-flex border-r-2 border-coral pr-3 text-sm font-semibold text-teal-100">یک قدم تا تکمیل حساب</p>
                    <h1 class="max-w-lg text-4xl font-extrabold leading-tight xl:text-5xl">تأیید سریع، شروعی مطمئن</h1>
                    <p class="mt-5 max-w-md text-base leading-8 text-slate-200">کد شما زمان محدودی دارد و پس از تأیید تنها یک‌بار قابل استفاده است.</p>
                </div>
            </div>
        </section>

        <section class="relative flex min-h-screen items-center justify-center bg-[linear-gradient(145deg,#f8fafc_0%,#edf7f5_52%,#e7eef2_100%)] px-4 py-20 transition-colors duration-500 dark:bg-[linear-gradient(145deg,#11161d_0%,#18242a_52%,#0d2021_100%)] sm:px-8 lg:px-10 xl:px-14">
            <button id="themeToggle" type="button" aria-label="فعال کردن حالت تیره" class="absolute left-4 top-4 grid h-11 w-11 place-items-center rounded-md border border-white/80 bg-white/[0.65] text-slate-700 shadow-sm backdrop-blur-lg transition duration-300 hover:bg-white/90 focus:outline-none focus:ring-4 focus:ring-teal-500/20 active:scale-95 dark:border-white/10 dark:bg-white/[0.07] dark:text-slate-300 dark:hover:bg-white/[0.11] sm:left-6 sm:top-6">
                <span class="relative h-5 w-5" aria-hidden="true">
                    <svg class="theme-sun absolute inset-0 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    <svg class="theme-moon absolute inset-0 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.2 15.2A8.5 8.5 0 0 1 8.8 3.8 8.5 8.5 0 1 0 20.2 15.2Z"/></svg>
                </span>
            </button>

            <div class="w-full max-w-lg">
                <a href="index.php" class="mb-8 block w-fit text-xl font-extrabold text-ink transition-colors dark:text-slate-100 lg:hidden">هم<span class="text-brand dark:text-teal-300">‌مسیر</span></a>

                <div class="rounded-lg border border-white/75 bg-white/[0.72] p-6 shadow-[0_22px_60px_-28px_rgba(15,23,42,0.35),0_0_38px_-30px_rgba(13,148,136,0.35)] backdrop-blur-xl transition duration-500 dark:border-white/[0.08] dark:bg-[#111b20]/[0.85] dark:shadow-[0_24px_70px_-28px_rgba(0,0,0,0.9),0_0_42px_-28px_rgba(45,212,191,0.55)] sm:p-9 lg:p-10">
                    <header class="mb-7 flex items-start justify-between gap-5">
                        <div class="min-w-0 flex-1">
                            <p class="mb-2 text-sm font-semibold text-brand dark:text-teal-300">تأیید حساب</p>
                            <h2 class="text-2xl font-extrabold leading-snug text-ink dark:text-slate-50 sm:text-3xl">کد تأیید را وارد کنید</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-500 dark:text-slate-400">کد چهاررقمی ارسال‌شده را وارد کنید.</p>
                        </div>
                        <div class="auth-logo relative h-14 w-14 shrink-0 sm:h-16 sm:w-16" role="img" aria-label="نشان هم‌مسیر">
                            <div class="absolute inset-1 rounded-full bg-teal-400/20 blur-lg"></div>
                            <svg class="relative h-full w-full drop-shadow-[0_8px_12px_rgba(15,118,110,0.2)]" viewBox="0 0 72 72" fill="none"><circle cx="36" cy="36" r="31" fill="rgba(255,255,255,.72)" stroke="rgba(20,184,166,.35)" stroke-width="2"/><path d="M22 38.5C22 30.49 28.27 24 36 24s14 6.49 14 14.5" stroke="#0f766e" stroke-width="5" stroke-linecap="round"/><path d="M25 46c3.25-3.33 6.92-5 11-5s7.75 1.67 11 5" stroke="#e76f51" stroke-width="5" stroke-linecap="round"/><circle cx="36" cy="35" r="4" fill="#0f766e"/></svg>
                        </div>
                    </header>

                    <?php if ($flash !== null): ?>
                        <?php $flashIsSuccess = $flash['type'] === 'success'; ?>
                        <div class="mb-6 flex items-start gap-3 rounded-md border px-4 py-3 text-sm leading-7 backdrop-blur-md <?= $flashIsSuccess ? 'border-emerald-500/30 bg-emerald-50/75 text-emerald-800 dark:border-emerald-400/25 dark:bg-emerald-400/[0.08] dark:text-emerald-200' : 'border-rose-500/35 bg-rose-50/80 text-rose-800 dark:border-rose-400/30 dark:bg-rose-500/[0.09] dark:text-rose-200' ?>" role="<?= $flashIsSuccess ? 'status' : 'alert' ?>">
                            <svg class="mt-1 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="<?= $flashIsSuccess ? 'm8 12 2.5 2.5L16 9' : 'M12 7v6M12 17h.01' ?>"/></svg>
                            <span><?= verificationEscape($flash['message']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($pageError !== null): ?>
                        <div class="rounded-md border border-rose-500/35 bg-rose-50/80 px-4 py-3 text-sm leading-7 text-rose-800 dark:border-rose-400/30 dark:bg-rose-500/[0.09] dark:text-rose-200" role="alert"><?= verificationEscape($pageError) ?></div>
                    <?php elseif ($isVerified): ?>
                        <div class="rounded-md border border-emerald-500/30 bg-emerald-50/75 px-5 py-5 text-center text-emerald-800 dark:border-emerald-400/25 dark:bg-emerald-400/[0.08] dark:text-emerald-200" role="status">
                            <svg class="mx-auto mb-3 h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                            <p class="font-bold">حساب کاربری شما با موفقیت تأیید شد.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-6 flex items-center justify-between rounded-md border border-teal-500/15 bg-teal-50/55 px-4 py-3 dark:border-teal-300/10 dark:bg-teal-300/[0.05]">
                            <span class="text-sm text-slate-600 dark:text-slate-400">زمان باقی‌مانده</span>
                            <span id="countdown" class="font-mono text-xl font-bold tabular-nums text-brand dark:text-teal-300" dir="ltr">00:00</span>
                        </div>

                        <p id="expirationMessage" class="mb-5 text-sm text-rose-700 dark:text-rose-300 <?= $isExpired ? '' : 'hidden' ?>" role="status">زمان اعتبار کد به پایان رسیده است.</p>

                        <form id="verificationForm" action="verify-process.php" method="post" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?= verificationEscape($csrfToken) ?>">
                            <fieldset>
                                <legend class="sr-only">چهار رقم کد تأیید</legend>
                                <div id="codeInputs" class="flex justify-center gap-2.5 sm:gap-4" dir="ltr">
                                    <?php for ($digit = 1; $digit <= 4; $digit++): ?>
                                        <input class="code-input" type="text" name="code[]" maxlength="1" inputmode="numeric" pattern="[0-9]" <?= $digit === 1 ? 'autocomplete="one-time-code" autofocus' : 'autocomplete="off"' ?> aria-label="رقم <?= $digit ?> کد تأیید" required <?= $isExpired ? 'disabled' : '' ?>>
                                    <?php endfor; ?>
                                </div>
                            </fieldset>

                            <button id="verifyButton" type="submit" class="flex w-full items-center justify-center gap-2 rounded-md bg-[linear-gradient(135deg,#0f766e,#115e59)] px-5 py-4 text-base font-bold text-white shadow-[0_12px_28px_-14px_rgba(20,184,166,.55)] transition duration-300 hover:brightness-110 focus:outline-none focus:ring-4 focus:ring-teal-500/20 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:brightness-100" <?= $isExpired ? 'disabled' : '' ?>>تأیید کد</button>
                        </form>

                        <div class="mt-6 border-t border-slate-200/70 pt-5 dark:border-white/10">
                            <form action="resend-code.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= verificationEscape($csrfToken) ?>">
                                <button id="resendButton" type="submit" class="w-full rounded-md border border-teal-600/25 bg-white/40 px-4 py-3 text-sm font-semibold text-brand transition duration-300 hover:bg-teal-50/80 focus:outline-none focus:ring-4 focus:ring-teal-500/10 disabled:cursor-not-allowed disabled:border-slate-300/50 disabled:text-slate-400 disabled:opacity-60 dark:border-teal-300/20 dark:bg-white/[0.04] dark:text-teal-300 dark:hover:bg-white/[0.08] dark:disabled:border-white/10 dark:disabled:text-slate-500" <?= (!$isExpired || $resendLimitReached) ? 'disabled' : '' ?>><?= $resendLimitReached ? 'تعداد ارسال مجدد به پایان رسیده است' : ($isExpired ? 'ارسال مجدد کد' : 'ارسال مجدد پس از پایان زمان') ?></button>
                            </form>
                            <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-500">ارسال مجدد: <?= $resendCount ?> از <?= MAX_RESEND_ATTEMPTS ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        const themeToggle = document.getElementById('themeToggle');

        function applyTheme(theme, save = false) {
            const isDark = theme === 'dark';
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            themeToggle.setAttribute('aria-pressed', String(isDark));
            themeToggle.setAttribute('aria-label', isDark ? 'فعال کردن حالت روشن' : 'فعال کردن حالت تیره');
            if (save) {
                try { localStorage.setItem('theme', theme); } catch (error) {}
            }
        }

        themeToggle.addEventListener('click', () => {
            applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark', true);
        });
        applyTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');

        const initialRemainingSeconds = <?= $remainingSeconds ?>;
        const resendLimitReached = <?= $resendLimitReached ? 'true' : 'false' ?>;
        const countdown = document.getElementById('countdown');
        const verifyButton = document.getElementById('verifyButton');
        const resendButton = document.getElementById('resendButton');
        const expirationMessage = document.getElementById('expirationMessage');
        const verificationForm = document.getElementById('verificationForm');
        const digitInputs = Array.from(document.querySelectorAll('.code-input'));

        if (countdown && verifyButton && resendButton && expirationMessage && verificationForm) {
            const endTime = Date.now() + initialRemainingSeconds * 1000;
            let timerId;

            function expireInterface() {
                verifyButton.disabled = true;
                digitInputs.forEach((input) => { input.disabled = true; });
                expirationMessage.classList.remove('hidden');
                if (!resendLimitReached) {
                    resendButton.disabled = false;
                    resendButton.textContent = 'ارسال مجدد کد';
                }
            }

            function updateCountdown() {
                const seconds = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
                const minutesPart = String(Math.floor(seconds / 60)).padStart(2, '0');
                const secondsPart = String(seconds % 60).padStart(2, '0');
                countdown.textContent = `${minutesPart}:${secondsPart}`;

                if (seconds === 0) {
                    clearInterval(timerId);
                    expireInterface();
                }
            }

            updateCountdown();
            timerId = setInterval(updateCountdown, 250);

            digitInputs.forEach((input, index) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(-1);
                    if (input.value && digitInputs[index + 1]) digitInputs[index + 1].focus();
                });
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Backspace' && !input.value && digitInputs[index - 1]) {
                        digitInputs[index - 1].focus();
                    }
                });
                input.addEventListener('paste', (event) => {
                    const pasted = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 4);
                    if (!pasted) return;
                    event.preventDefault();
                    pasted.split('').forEach((digit, digitIndex) => {
                        if (digitInputs[digitIndex]) digitInputs[digitIndex].value = digit;
                    });
                    digitInputs[Math.min(pasted.length, 4) - 1].focus();
                });
            });

            verificationForm.addEventListener('submit', (event) => {
                if (Date.now() >= endTime || digitInputs.some((input) => !/^[0-9]$/.test(input.value))) {
                    event.preventDefault();
                    if (Date.now() >= endTime) expireInterface();
                }
            });
        }
    </script>
</body>
</html>
