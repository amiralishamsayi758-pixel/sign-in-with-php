<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/check-auth.php';

startVerificationSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('dashboard.php');
}

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/config/database.php';
    $authenticatedUser = authenticatedUser($pdo, false);

    if ($authenticatedUser === null) {
        clearAuthenticationCookie();
        redirectTo('index.php');
    }

    if (!validAuthenticationCsrfToken($_POST['csrf_token'] ?? null)) {
        setAuthenticationFlash('درخواست معتبر نیست. لطفاً دوباره تلاش کنید.');
        redirectTo('dashboard.php');
    }

    $statement = $pdo->prepare(
        'UPDATE users
         SET auth_token_hash = NULL,
             token_expires_at = NULL,
             last_authenticated_at = NULL
         WHERE id = :id'
    );
    $statement->execute(['id' => (int) $authenticatedUser['id']]);
} catch (Throwable $exception) {
    setAuthenticationFlash('در انجام عملیات مشکلی رخ داد. لطفاً دوباره تلاش کنید.');
    redirectTo('dashboard.php');
}

clearAuthenticationCookie();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'],
        'samesite' => 'Lax',
    ]);
}

session_destroy();
redirectTo('index.php');
