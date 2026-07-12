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

$submittedDigits = $_POST['code'] ?? null;
$submittedCode = '';

if (is_array($submittedDigits) && count($submittedDigits) === 4) {
    foreach ($submittedDigits as $digit) {
        if (!is_string($digit) || preg_match('/\A[0-9]\z/D', $digit) !== 1) {
            $submittedCode = '';
            break;
        }

        $submittedCode .= $digit;
    }
}

if (preg_match('/\A[0-9]{4}\z/D', $submittedCode) !== 1) {
    setVerificationFlash('error', 'کد تأیید باید دقیقاً چهار رقم باشد.');
    redirectTo('verify.php');
}

$pdo = null;

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';
    $pdo->beginTransaction();

    $selectStatement = $pdo->prepare(
        'SELECT id, phone, verification_code, code_expires_at, is_verified, resend_count
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

    $expiresAt = databaseUtcDateTime((string) $user['code_expires_at']);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    if ($expiresAt === null || $now >= $expiresAt) {
        $pdo->commit();
        setVerificationFlash('error', 'زمان اعتبار کد به پایان رسیده است.');
        redirectTo('verify.php');
    }

    if (!hash_equals((string) $user['verification_code'], $submittedCode)) {
        $pdo->commit();
        setVerificationFlash('error', 'کد واردشده صحیح نیست.');
        redirectTo('verify.php');
    }

    $updateStatement = $pdo->prepare(
        'UPDATE users
         SET is_verified = 1,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStatement->execute([
        'updated_at' => $now->format('Y-m-d H:i:s'),
        'id' => (int) $user['id'],
    ]);

    $pdo->commit();
    session_regenerate_id(true);
    setVerificationFlash('success', 'حساب کاربری شما با موفقیت تأیید شد.');
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setVerificationFlash('error', 'بررسی کد انجام نشد. لطفاً دوباره تلاش کنید.');
}

redirectTo('verify.php');
