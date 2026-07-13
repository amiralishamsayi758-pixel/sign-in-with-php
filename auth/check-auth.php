<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/verification-helpers.php';

const AUTH_COOKIE_NAME = 'sign_in_auth';
const AUTH_TOKEN_LIFETIME_SECONDS = 86400;
const AUTH_REFRESH_AFTER_SECONDS = 3600;
const AUTH_CSRF_SESSION_KEY = 'auth_csrf_token';
const AUTH_FLASH_SESSION_KEY = 'auth_flash';

function requestUsesHttps(): bool
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function createAuthenticationToken(DateTimeImmutable $now): array
{
    $rawToken = bin2hex(random_bytes(32));

    return [
        'raw' => $rawToken,
        'hash' => hash('sha256', $rawToken),
        'expires_at' => $now->modify('+24 hours'),
    ];
}

function setAuthenticationCookie(string $rawToken, DateTimeImmutable $expiresAt): void
{
    setcookie(AUTH_COOKIE_NAME, $rawToken, [
        'expires' => $expiresAt->getTimestamp(),
        'path' => '/',
        'secure' => requestUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearAuthenticationCookie(): void
{
    setcookie(AUTH_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => requestUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_COOKIE[AUTH_COOKIE_NAME]);
}

function authenticationCsrfToken(): string
{
    $token = $_SESSION[AUTH_CSRF_SESSION_KEY] ?? null;

    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[AUTH_CSRF_SESSION_KEY] = $token;
    }

    return $token;
}

function validAuthenticationCsrfToken(mixed $submittedToken): bool
{
    $storedToken = $_SESSION[AUTH_CSRF_SESSION_KEY] ?? null;

    return is_string($submittedToken)
        && is_string($storedToken)
        && hash_equals($storedToken, $submittedToken);
}

function setAuthenticationFlash(string $message): void
{
    $_SESSION[AUTH_FLASH_SESSION_KEY] = $message;
}

function pullAuthenticationFlash(): ?string
{
    $message = $_SESSION[AUTH_FLASH_SESSION_KEY] ?? null;
    unset($_SESSION[AUTH_FLASH_SESSION_KEY]);

    return is_string($message) ? $message : null;
}

function authenticatedUser(PDO $pdo, bool $refreshExpiration = true): ?array
{
    $rawToken = $_COOKIE[AUTH_COOKIE_NAME] ?? null;

    if (
        !is_string($rawToken)
        || strlen($rawToken) !== 64
        || ctype_xdigit($rawToken) === false
    ) {
        if ($rawToken !== null) {
            clearAuthenticationCookie();
        }

        return null;
    }

    $tokenHash = hash('sha256', $rawToken);
    $statement = $pdo->prepare(
        'SELECT id, gmail, phone, username, is_verified,
                token_expires_at, last_authenticated_at
         FROM users
         WHERE auth_token_hash = :auth_token_hash
         LIMIT 1'
    );
    $statement->execute(['auth_token_hash' => $tokenHash]);
    $user = $statement->fetch();

    if ($user === false || (int) $user['is_verified'] !== 1) {
        clearAuthenticationCookie();

        return null;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiresAt = databaseUtcDateTime((string) $user['token_expires_at']);

    if ($expiresAt === null || $now >= $expiresAt) {
        $clearStatement = $pdo->prepare(
            'UPDATE users
             SET auth_token_hash = NULL,
                 token_expires_at = NULL
             WHERE id = :id AND auth_token_hash = :auth_token_hash'
        );
        $clearStatement->execute([
            'id' => (int) $user['id'],
            'auth_token_hash' => $tokenHash,
        ]);
        clearAuthenticationCookie();

        return null;
    }

    $lastAuthenticatedAt = is_string($user['last_authenticated_at'])
        ? databaseUtcDateTime($user['last_authenticated_at'])
        : null;

    if (
        $refreshExpiration
        && ($lastAuthenticatedAt === null
            || $now->getTimestamp() - $lastAuthenticatedAt->getTimestamp() >= AUTH_REFRESH_AFTER_SECONDS)
    ) {
        $newExpiresAt = $now->modify('+24 hours');
        $refreshStatement = $pdo->prepare(
            'UPDATE users
             SET token_expires_at = :token_expires_at,
                 last_authenticated_at = :last_authenticated_at
             WHERE id = :id
               AND auth_token_hash = :auth_token_hash
               AND is_verified = 1
               AND token_expires_at > :current_time'
        );
        $refreshStatement->execute([
            'token_expires_at' => $newExpiresAt->format('Y-m-d H:i:s'),
            'last_authenticated_at' => $now->format('Y-m-d H:i:s'),
            'id' => (int) $user['id'],
            'auth_token_hash' => $tokenHash,
            'current_time' => $now->format('Y-m-d H:i:s'),
        ]);

        if ($refreshStatement->rowCount() === 1) {
            setAuthenticationCookie($rawToken, $newExpiresAt);
            $user['token_expires_at'] = $newExpiresAt->format('Y-m-d H:i:s');
            $user['last_authenticated_at'] = $now->format('Y-m-d H:i:s');
        }
    }

    unset($user['token_expires_at'], $user['last_authenticated_at']);

    return $user;
}
