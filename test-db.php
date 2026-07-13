<?php

declare(strict_types=1);

try {
    $pdo = require __DIR__ . '/config/database.php';

    echo 'اتصال به دیتابیس با موفقیت انجام شد.';
} catch (Throwable $error) {
    http_response_code(500);
    echo 'اتصال به پایگاه داده انجام نشد.';
}
