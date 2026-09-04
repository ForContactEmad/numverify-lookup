<?php

declare(strict_types=1);

/**
 * محمّل تلقائي بسيط بنمط PSR-4 — يغني عن Composer في هذه النسخة التجريبية.
 * Minimal PSR-4 autoloader, so the project runs without `composer install`.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Numverify\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
