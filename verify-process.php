<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/check-auth.php';

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

$submittedValue = $_POST['verification_code'] ?? '';
$submittedCode = is_string($submittedValue) ? $submittedValue : '';

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
        'SELECT id, gmail, phone, username, verification_code,
                code_expires_at, is_verified
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

    $authenticationToken = createAuthenticationToken($now);
    $updateStatement = $pdo->prepare(
        'UPDATE users
         SET is_verified = 1,
             resend_count = 0,
             auth_token_hash = :auth_token_hash,
             token_expires_at = :token_expires_at,
             last_authenticated_at = :last_authenticated_at,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStatement->execute([
        'auth_token_hash' => $authenticationToken['hash'],
        'token_expires_at' => $authenticationToken['expires_at']->format('Y-m-d H:i:s'),
        'last_authenticated_at' => $now->format('Y-m-d H:i:s'),
        'updated_at' => $now->format('Y-m-d H:i:s'),
        'id' => (int) $user['id'],
    ]);

    $pdo->commit();
    session_regenerate_id(true);
    unset(
        $_SESSION[VERIFICATION_PHONE_SESSION_KEY],
        $_SESSION[VERIFICATION_CSRF_SESSION_KEY]
    );
    setAuthenticationCookie($authenticationToken['raw'], $authenticationToken['expires_at']);
    setAuthenticationFlash('حساب کاربری شما با موفقیت تأیید شد.');
    redirectTo('dashboard.php');
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setVerificationFlash('error', 'بررسی کد انجام نشد. لطفاً دوباره تلاش کنید.');
}

redirectTo('verify.php');
