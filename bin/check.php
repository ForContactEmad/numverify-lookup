<?php

declare(strict_types=1);

use Numverify\Api\Client;
use Numverify\Local\ValidatorFactory;
use Numverify\Lookup\PhoneLookup;
use Numverify\Support\Env;
use Numverify\Support\FileCache;

$composer = __DIR__ . '/../vendor/autoload.php';
require is_file($composer) ? $composer : __DIR__ . '/../src/autoload.php';

$root = dirname(__DIR__);
Env::load($root . '/.env');

$number = $argv[1] ?? null;

if ($number === null) {
    fwrite(STDERR, "الاستخدام: php bin/check.php <number> [country_code]\n");
    exit(1);
}

$defaultCountry = Env::get('DEFAULT_COUNTRY_CODE');
$accessKey = Env::get('NUMVERIFY_ACCESS_KEY');

$api = $accessKey === null ? null : new Client(
    accessKey: $accessKey,
    useHttps: Env::bool('NUMVERIFY_HTTPS', false),
    cache: new FileCache($root . '/storage/cache', Env::int('CACHE_TTL', 86400)),
    defaultCountryCode: $defaultCountry,
    autoRetry: Env::bool('AUTO_RETRY', true),
);

$lookup = new PhoneLookup(ValidatorFactory::make($defaultCountry), $api, Env::bool('ENRICH_WITH_API', true));
$result = $lookup->lookup($number, $argv[2] ?? null);

echo json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
exit($result->isValid() ? 0 : 2);
