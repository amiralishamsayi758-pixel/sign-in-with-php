<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php', true, 303);
    exit;
}

function postedString(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

$old = [
    'gmail' => postedString('gmail'),
    'phone' => postedString('phone'),
    'username' => postedString('username'),
];

$errors = [];

if ($old['gmail'] === '') {
    $errors['gmail'] = 'وارد کردن آدرس جیمیل الزامی است.';
} elseif (
    filter_var($old['gmail'], FILTER_VALIDATE_EMAIL) === false
    || preg_match('/@gmail\.com\z/i', $old['gmail']) !== 1
) {
    $errors['gmail'] = 'یک آدرس معتبر با پسوند @gmail.com وارد کنید.';
}

if ($old['phone'] === '') {
    $errors['phone'] = 'وارد کردن شماره موبایل الزامی است.';
} elseif (preg_match('/\A09[0-9]{9}\z/D', $old['phone']) !== 1) {
    $errors['phone'] = 'شماره موبایل باید دقیقاً ۱۱ رقم و با 09 شروع شود.';
}

$usernameLength = mb_strlen($old['username'], 'UTF-8');

if ($old['username'] === '') {
    $errors['username'] = 'وارد کردن نام کاربری الزامی است.';
} elseif ($usernameLength < 5 || $usernameLength > 10) {
    $errors['username'] = 'نام کاربری باید حداقل ۵ و حداکثر ۱۰ کاراکتر داشته باشد.';
}

if ($errors !== []) {
    http_response_code(422);
    require __DIR__ . '/index.php';
    exit;
}

$verificationCode = (string) random_int(1000, 9999);

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';

    $statement = $pdo->prepare(
        'INSERT INTO users (gmail, phone, username, verification_code)
         VALUES (:gmail, :phone, :username, :verification_code)
         ON DUPLICATE KEY UPDATE
            gmail = :updated_gmail,
            username = :updated_username,
            verification_code = :updated_verification_code,
            updated_at = CURRENT_TIMESTAMP'
    );

    $statement->execute([
        'gmail' => $old['gmail'],
        'phone' => $old['phone'],
        'username' => $old['username'],
        'verification_code' => $verificationCode,
        'updated_gmail' => $old['gmail'],
        'updated_username' => $old['username'],
        'updated_verification_code' => $verificationCode,
    ]);
} catch (Throwable $exception) {
    $errors['general'] = 'ذخیره اطلاعات انجام نشد. لطفاً اتصال پایگاه داده را بررسی و دوباره تلاش کنید.';
    http_response_code(500);
    require __DIR__ . '/index.php';
    exit;
}

header('Location: index.php?status=saved', true, 303);
exit;
