<?php

declare(strict_types=1);

use Numverify\Api\ApiException;
use Numverify\Api\Client;
use Numverify\Local\ValidatorFactory;
use Numverify\Support\Env;

$composer = __DIR__ . '/../vendor/autoload.php';
require is_file($composer) ? $composer : __DIR__ . '/../src/autoload.php';

/**
 * يعرض بالضبط ما أُرسل وما وصل — بدون كاش وبدون تفسير.
 * استخدمه عندما تكون النتيجة غير متوقعة.
 *
 * php bin/diagnose.php 0501234567 SA
 */

$root = dirname(__DIR__);
Env::load($root . '/.env');

$number = $argv[1] ?? null;

if ($number === null) {
    fwrite(STDERR, "الاستخدام: php bin/diagnose.php <number> [country_code]\n");
    exit(1);
}

$accessKey = Env::get('NUMVERIFY_ACCESS_KEY');

if ($accessKey === null) {
    fwrite(STDERR, "لم يتم ضبط NUMVERIFY_ACCESS_KEY في .env\n");
    exit(1);
}

// بلا كاش وبلا إعادة محاولة: نريد رؤية الطلب الأول كما هو.
$client = new Client(
    accessKey: $accessKey,
    useHttps: Env::bool('NUMVERIFY_HTTPS', false),
    cache: null,
    autoRetry: false,
);

$local = ValidatorFactory::make(Env::get('DEFAULT_COUNTRY_CODE'))->check($number, $argv[2] ?? null);

echo "المُدخَل:        {$number}\n";
echo "محرك محلي:      {$local->source}\n";
echo "تحقق محلي:      " . ($local->plausible ? 'مقبول' : 'مرفوض — ' . $local->reason) . "\n";

if (!$local->plausible) {
    echo "\nلم يُرسل أي طلب للمزوّد. لم تُستهلك أي حصة.\n";
    exit(2);
}


try {
    $result = $client->validate($number, $argv[2] ?? null);
} catch (ApiException $e) {
    echo "الرابط:         " . ($client->lastUrl() ?? '—') . "\n";
    echo "الرد الخام:     " . ($client->lastRawResponse() ?? '—') . "\n";
    echo "نوع الخطأ:      {$e->type}\n";
    echo "الرسالة:        {$e->friendlyMessage()}\n";
    exit(1);
}

echo "الرقم المُرسَل:  {$result->queriedNumber}\n";
echo "رمز الدولة:     " . ($result->countryCodeUsed ?? '—') . "\n";
echo "الرابط:         " . ($client->lastUrl() ?? '—') . "\n";
echo "الرد الخام:     " . ($client->lastRawResponse() ?? '—') . "\n";
echo "valid:          " . ($result->valid ? 'true' : 'false') . "\n";

if (!$result->valid) {
    echo "\nاقتراحات:\n";
    foreach ($result->failureHints() as $hint) {
        echo "  - {$hint}\n";
    }
}
