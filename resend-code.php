<?php

declare(strict_types=1);

require_once __DIR__ . '/verification-helpers.php';

startVerificationSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('verify.php');
}

$phone = verificationPhone();

if ($phone === null) {
    redirectTo('index.php');
}

if (!validVerificationCsrfToken($_POST['csrf_token'] ?? null)) {
    setVerificationFlash('error', 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.');
    redirectTo('verify.php');
}

$pdo = null;

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';
    $pdo->beginTransaction();

    $selectStatement = $pdo->prepare(
        'SELECT id, code_expires_at, is_verified, resend_count
         FROM users
         WHERE phone = :phone
         LIMIT 1
         FOR UPDATE'
    );
    $selectStatement->execute(['phone' => $phone]);
    $user = $selectStatement->fetch();

    if ($user === false) {
        $pdo->rollBack();
        unset($_SESSION[VERIFICATION_PHONE_SESSION_KEY]);
        redirectTo('index.php');
    }

    if ((int) $user['is_verified'] === 1) {
        $pdo->commit();
        setVerificationFlash('success', 'حساب کاربری شما قبلاً تأیید شده است.');
        redirectTo('verify.php');
    }

    if ((int) $user['resend_count'] >= MAX_RESEND_ATTEMPTS) {
        $pdo->commit();
        setVerificationFlash('error', 'تعداد دفعات مجاز ارسال مجدد به پایان رسیده است.');
        redirectTo('verify.php');
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiresAt = databaseUtcDateTime((string) $user['code_expires_at']);

    if ($expiresAt !== null && $now < $expiresAt) {
        $pdo->commit();
        setVerificationFlash('error', 'کد فعلی هنوز معتبر است. تا پایان زمان منتظر بمانید.');
        redirectTo('verify.php');
    }

    $verificationCode = generateVerificationCode();
    $codeExpiresAt = createExpirationTime($now)->format('Y-m-d H:i:s');

    $updateStatement = $pdo->prepare(
        'UPDATE users
         SET verification_code = :verification_code,
             code_expires_at = :code_expires_at,
             resend_count = resend_count + 1,
             is_verified = 0,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStatement->execute([
        'verification_code' => $verificationCode,
        'code_expires_at' => $codeExpiresAt,
        'updated_at' => $now->format('Y-m-d H:i:s'),
        'id' => (int) $user['id'],
    ]);

    $pdo->commit();
    setVerificationFlash('success', 'کد تأیید جدید با اعتبار دو دقیقه ایجاد شد.');
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setVerificationFlash('error', 'ارسال مجدد کد انجام نشد. لطفاً دوباره تلاش کنید.');
}

redirectTo('verify.php');
