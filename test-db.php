<?php

declare(strict_types=1);

try {
    $pdo = require __DIR__ . '/config/database.php';

    echo 'اتصال به دیتابیس با موفقیت انجام شد.';
} catch (Throwable $error) {
    echo '<pre>';
    echo 'نوع خطا: ' . get_class($error) . PHP_EOL;
    echo 'پیام خطا: ' . $error->getMessage();
    echo '</pre>';
}