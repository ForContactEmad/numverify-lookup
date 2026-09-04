<?php

declare(strict_types=1);

/**
 * يفضّل محمّل Composer إن وُجد، وإلا يسقط إلى المحمّل المدمج،
 * حتى تعمل الاختبارات مع أو بدون composer install.
 */
$composer = __DIR__ . '/../vendor/autoload.php';

if (is_file($composer)) {
    require $composer;

    return;
}

require __DIR__ . '/../src/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Numverify\\Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
