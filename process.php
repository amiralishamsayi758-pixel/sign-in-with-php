<?php

declare(strict_types=1);

require_once __DIR__ . '/verification-helpers.php';

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

$gmail = $old['gmail'];
$phone = $old['phone'];
$username = $old['username'];

$timezone = new DateTimeZone('UTC');
$now = new DateTimeImmutable('now', $timezone);

$verificationCode = generateVerificationCode();
$codeCreatedAt = $now->format('Y-m-d H:i:s');
$codeExpiresAt = createExpirationTime($now)->format('Y-m-d H:i:s');
$updatedAt = $codeCreatedAt;

$pdo = null;
$resendLimitReached = false;

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';
    $pdo->beginTransaction();

    $selectStatement = $pdo->prepare(
        'SELECT id, resend_count
         FROM users
         WHERE phone = :phone
         LIMIT 1
         FOR UPDATE'
    );
    $selectStatement->execute(['phone' => $phone]);
    $existingUser = $selectStatement->fetch();

    if ($existingUser === false) {
        $insertStatement = $pdo->prepare(
            'INSERT INTO users (
                gmail,
                phone,
                username,
                verification_code,
                code_expires_at,
                resend_count,
                is_verified,
                created_at,
                updated_at
             ) VALUES (
                :gmail,
                :phone,
                :username,
                :verification_code,
                :code_expires_at,
                0,
                0,
                :created_at,
                :updated_at
             )'
        );
        $insertStatement->execute([
            'gmail' => $gmail,
            'phone' => $phone,
            'username' => $username,
            'verification_code' => $verificationCode,
            'code_expires_at' => $codeExpiresAt,
            'created_at' => $codeCreatedAt,
            'updated_at' => $updatedAt,
        ]);
    } elseif ((int) $existingUser['resend_count'] >= MAX_RESEND_ATTEMPTS) {
        $resendLimitReached = true;
    } else {
        $updateStatement = $pdo->prepare(
            'UPDATE users
             SET gmail = :gmail,
                 username = :username,
                 verification_code = :verification_code,
                 code_expires_at = :code_expires_at,
                 resend_count = resend_count + 1,
                 is_verified = 0,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $updateStatement->execute([
            'gmail' => $gmail,
            'username' => $username,
            'verification_code' => $verificationCode,
            'code_expires_at' => $codeExpiresAt,
            'updated_at' => $updatedAt,
            'id' => (int) $existingUser['id'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: index.php?status=database-error', true, 303);
    exit;
}

startVerificationSession();
session_regenerate_id(true);
storeVerificationPhone($phone);

if ($resendLimitReached) {
    setVerificationFlash(
        'error',
        'تعداد دفعات مجاز ارسال مجدد به پایان رسیده است.'
    );
}

redirectTo('verify.php');
