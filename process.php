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

function generateVerificationCode(): string
{
    return (string) random_int(1000, 9999);
}

function createExpirationTime(DateTimeImmutable $createdAt): DateTimeImmutable
{
    return $createdAt->modify('+2 minutes');
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

$gmail = $old['gmail'];
$phone = $old['phone'];
$username = $old['username'];

$timezone = new DateTimeZone('UTC');
$now = new DateTimeImmutable('now', $timezone);

$verificationCode = generateVerificationCode();
$codeCreatedAt = $now->format('Y-m-d H:i:s');
$codeExpiresAt = createExpirationTime($now)->format('Y-m-d H:i:s');
$updatedAt = $codeCreatedAt;

// Future database lookup by phone number using $phone.
//
// If the phone number is new:
// Future INSERT using $gmail, $phone, $username, $verificationCode,
// $codeCreatedAt, $codeExpiresAt, and $updatedAt.
//
// If the phone number already exists:
// Future UPDATE using $gmail, $username, $verificationCode,
// $codeExpiresAt, and $updatedAt while keeping created_at unchanged.
// Replacing verification_code and code_expires_at will make the old code unusable.
//
// Future verification rule:
// The stored code is valid while the current UTC time is earlier than code_expires_at.
// It is expired when the current UTC time is equal to or later than code_expires_at.

header('Location: index.php?status=prepared', true, 303);
exit;
