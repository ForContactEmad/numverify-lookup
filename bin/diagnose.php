<?php

declare(strict_types=1);

use Numverify\Api\ApiException;
use Numverify\Api\Client;
use Numverify\Local\ValidatorFactory;
use Numverify\Support\Env;

$composer = __DIR__ . '/../vendor/autoload.php';
require is_file($composer) ? $composer : __DIR__ . '/../src/autoload.php';

/**
 * يعرض بالضبط ما حدث في كل طبقة — بلا كاش وبلا إعادة محاولة.
 * الطبقة المحلية تُعرض دائماً، حتى بلا مفتاح.
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

// ── الطبقة المحلية ──────────────────────────────────────────────
$validator = ValidatorFactory::make(Env::get('DEFAULT_COUNTRY_CODE'));
$local = $validator->check($number, $argv[2] ?? null);

echo "المُدخَل:        {$number}\n";
echo "محرك محلي:      {$validator->name()}\n";
echo "بعد التطبيع:    " . ($local->e164 !== '' ? $local->e164 : '—') . "\n";
echo "تحقق محلي:      " . ($local->plausible ? 'مقبول' : 'مرفوض') . "\n";

if (!$local->plausible) {
    echo "السبب:          {$local->reason}\n";
    echo "\nلم يُرسل أي طلب للمزوّد. لم تُستهلك أي حصة.\n";
    exit(2);
}

echo "الدولة:         {$local->countryCode} (+{$local->callingCode})\n";
echo "الرقم الوطني:   {$local->nationalNumber}\n";

// ── الطبقة الخارجية ─────────────────────────────────────────────
$accessKey = Env::get('NUMVERIFY_ACCESS_KEY');

if ($accessKey === null) {
    echo "\nلا يوجد مفتاح في .env — توقف التشخيص عند الطبقة المحلية.\n";
    exit(0);
}

if (!Env::bool('ENRICH_WITH_API', true)) {
    echo "\nENRICH_WITH_API=false — الاستدعاء الخارجي معطّل بالإعدادات.\n";
    exit(0);
}

// بلا كاش وبلا إعادة محاولة: نريد رؤية الطلب الأول كما هو.
$client = new Client(
    accessKey: $accessKey,
    useHttps: Env::bool('NUMVERIFY_HTTPS', false),
    cache: null,
    autoRetry: false,
);

echo "\n-- المزوّد --\n";

try {
    $result = $client->validate($local->e164);
} catch (ApiException $e) {
    echo "الرابط:         " . ($client->lastUrl() ?? '—') . "\n";
    echo "الرد الخام:     " . ($client->lastRawResponse() ?? '—') . "\n";
    echo "نوع الخطأ:      {$e->type}\n";
    echo "الرسالة:        {$e->friendlyMessage()}\n";
    exit(1);
}

echo "الرابط:         " . ($client->lastUrl() ?? '—') . "\n";
echo "الرد الخام:     " . ($client->lastRawResponse() ?? '—') . "\n";
echo "valid:          " . ($result->valid ? 'true' : 'false') . "\n";

if ($result->valid) {
    echo "المشغّل:        " . ($result->carrier !== '' ? $result->carrier : '—') . "\n";
    echo "الموقع:         " . ($result->location !== '' ? $result->location : '—') . "\n";
    echo "نوع الخط:       " . ($result->lineType !== '' ? $result->lineType : '—') . "\n";
}

exit($result->valid ? 0 : 2);
