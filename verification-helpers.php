<?php

declare(strict_types=1);

const VERIFICATION_PHONE_SESSION_KEY = 'verification_phone';
const VERIFICATION_CSRF_SESSION_KEY = 'verification_csrf_token';
const VERIFICATION_FLASH_SESSION_KEY = 'verification_flash';
const MAX_RESEND_ATTEMPTS = 5;

function generateVerificationCode(): string
{
    return (string) random_int(1000, 9999);
}

function createExpirationTime(DateTimeImmutable $createdAt): DateTimeImmutable
{
    return $createdAt->modify('+2 minutes');
}

function startVerificationSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_name('sign_in_system_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function redirectTo(string $location): never
{
    header("Location: {$location}", true, 303);
    exit;
}

function verificationPhone(): ?string
{
    $phone = $_SESSION[VERIFICATION_PHONE_SESSION_KEY] ?? null;

    return is_string($phone) && preg_match('/\A09[0-9]{9}\z/D', $phone) === 1
        ? $phone
        : null;
}

function storeVerificationPhone(string $phone): void
{
    $_SESSION[VERIFICATION_PHONE_SESSION_KEY] = $phone;
}

function verificationCsrfToken(): string
{
    $token = $_SESSION[VERIFICATION_CSRF_SESSION_KEY] ?? null;

    if (!is_string($token) || strlen($token) < 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[VERIFICATION_CSRF_SESSION_KEY] = $token;
    }

    return $token;
}

function validVerificationCsrfToken(mixed $submittedToken): bool
{
    $storedToken = $_SESSION[VERIFICATION_CSRF_SESSION_KEY] ?? null;

    return is_string($submittedToken)
        && is_string($storedToken)
        && hash_equals($storedToken, $submittedToken);
}

function setVerificationFlash(string $type, string $message): void
{
    $_SESSION[VERIFICATION_FLASH_SESSION_KEY] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pullVerificationFlash(): ?array
{
    $flash = $_SESSION[VERIFICATION_FLASH_SESSION_KEY] ?? null;
    unset($_SESSION[VERIFICATION_FLASH_SESSION_KEY]);

    if (
        !is_array($flash)
        || !isset($flash['type'], $flash['message'])
        || !is_string($flash['type'])
        || !is_string($flash['message'])
    ) {
        return null;
    }

    return $flash;
}

function databaseUtcDateTime(string $value): ?DateTimeImmutable
{
    $dateTime = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $value,
        new DateTimeZone('UTC')
    );

    return $dateTime === false ? null : $dateTime;
}
