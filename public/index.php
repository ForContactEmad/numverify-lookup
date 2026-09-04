<?php

declare(strict_types=1);

use Numverify\Api\Client;
use Numverify\Http\Controller;
use Numverify\Local\ValidatorFactory;
use Numverify\Lookup\PhoneLookup;
use Numverify\Support\Env;
use Numverify\Support\FileCache;

$composer = __DIR__ . '/../vendor/autoload.php';
require is_file($composer) ? $composer : __DIR__ . '/../src/autoload.php';

$root = dirname(__DIR__);

Env::load($root . '/.env');

$defaultCountry = Env::get('DEFAULT_COUNTRY_CODE');
$validator = ValidatorFactory::make($defaultCountry);

$accessKey = Env::get('NUMVERIFY_ACCESS_KEY');

// بلا مفتاح، المشروع يظل يعمل بالتحقق المحلي وحده — مفيد للعرض التجريبي.
$api = $accessKey === null ? null : new Client(
    accessKey: $accessKey,
    useHttps: Env::bool('NUMVERIFY_HTTPS', false),
    cache: Env::bool('CACHE_ENABLED', true)
        ? new FileCache($root . '/storage/cache', Env::int('CACHE_TTL', 86400))
        : null,
    defaultCountryCode: $defaultCountry,
    autoRetry: Env::bool('AUTO_RETRY', true),
);

$lookup = new PhoneLookup($validator, $api, Env::bool('ENRICH_WITH_API', true));

(new Controller($lookup, $root . '/views', $validator->name()))->handle(
    method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
    path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    query: $_GET,
    body: $_POST,
);
