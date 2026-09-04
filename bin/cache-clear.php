<?php

declare(strict_types=1);

use Numverify\Support\FileCache;

$composer = __DIR__ . "/../vendor/autoload.php";
require is_file($composer) ? $composer : __DIR__ . "/../src/autoload.php";

$removed = (new FileCache(dirname(__DIR__) . '/storage/cache'))->flush();

echo "تم حذف {$removed} ملف من الكاش.\n";
