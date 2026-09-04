<?php

declare(strict_types=1);

namespace Numverify\Api;

use Closure;
use Numverify\Support\Digits;
use Numverify\Support\FileCache;

/**
 * الطبقة الوحيدة التي تعرف شيئاً عن Numverify.
 * كل ما عداها (الواجهة، سطر الأوامر) يتعامل مع Result و ApiException فقط.
 *
 * $transport قابل للحقن ليصبح اختبار الصنف ممكناً بدون إنترنت
 * وبدون استهلاك الحصة الشهرية.
 */
final class Client
{
    private const HOST = 'apilayer.net/api';

    private ?string $lastUrl = null;

    private ?string $lastRawResponse = null;

    public function __construct(
        private readonly string $accessKey,
        private readonly bool $useHttps = false,
        private readonly ?FileCache $cache = null,
        private readonly ?Closure $transport = null,
        private readonly int $timeout = 10,
        private readonly ?string $defaultCountryCode = null,
        private readonly bool $autoRetry = true,
    ) {
    }

    public function validate(string $number, ?string $countryCode = null): Result
    {
        $normalized = $this->normalizeNumber($number);

        if ($normalized === '') {
            throw new ApiException('رقم فارغ', 'no_phone_number_provided');
        }

        $countryCode = $this->normalizeCountryCode($countryCode);

        // رقم محلي يبدأ بصفر لا معنى له بدون رمز دولة — نضيف الافتراضي بدل الفشل.
        if ($countryCode === null && str_starts_with($normalized, '0')) {
            $countryCode = $this->defaultCountryCode;
        }

        $result = $this->lookup($normalized, $countryCode);

        // محاولة أخيرة: رقم قصير بلا رمز دولة غالباً محلي منقوص.
        if (!$result->valid && $this->shouldRetry($normalized, $countryCode)) {
            $retry = $this->lookup($normalized, $this->defaultCountryCode);

            if ($retry->valid) {
                return $retry;
            }
        }

        return $result;
    }

    /** @return array<string, array{country_name: string, dialling_code: string}> */
    public function countries(): array
    {
        $cacheKey = 'countries';

        if ($this->cache !== null && ($cached = $this->cache->get($cacheKey)) !== null) {
            return $cached;
        }

        $data = $this->request('countries', []);
        $this->cache?->put($cacheKey, $data);

        return $data;
    }

    /** الرابط المستخدم في آخر طلب، بمفتاح مُقنَّع — للتشخيص فقط. */
    public function lastUrl(): ?string
    {
        return $this->lastUrl;
    }

    /** الرد الخام لآخر طلب — للتشخيص فقط. */
    public function lastRawResponse(): ?string
    {
        return $this->lastRawResponse;
    }

    private function lookup(string $number, ?string $countryCode): Result
    {
        $params = ['number' => $number];

        if ($countryCode !== null) {
            $params['country_code'] = $countryCode;
        }

        $cacheKey = 'validate:' . json_encode($params);

        if ($this->cache !== null && ($cached = $this->cache->get($cacheKey)) !== null) {
            return Result::fromArray($cached, $number, $countryCode, fromCache: true);
        }

        $data = $this->request('validate', $params);

        // رقم غير صالح يعيده المزوّد كمصفوفة شبه فارغة، وليس كخطأ.
        if (!array_key_exists('valid', $data)) {
            $data['valid'] = false;
            $data['number'] = $number;
        }

        // النتائج غير الصالحة لا تُخزَّن: غالباً خطأ إدخال، وتخزينها يجمّد
        // الرسالة الخاطئة ٢٤ ساعة حتى بعد ما يصحح المستخدم الرقم.
        if ($data['valid'] === true) {
            $this->cache?->put($cacheKey, $data);
        }

        return Result::fromArray($data, $number, $countryCode);
    }

    private function shouldRetry(string $number, ?string $countryCode): bool
    {
        return $this->autoRetry
            && $countryCode === null
            && $this->defaultCountryCode !== null
            && strlen($number) <= 10;
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $params): array
    {
        $query = http_build_query($params + ['access_key' => $this->accessKey]);

        $url = sprintf(
            '%s://%s/%s?%s',
            $this->useHttps ? 'https' : 'http',
            self::HOST,
            $endpoint,
            $query
        );

        $this->lastUrl = str_replace($this->accessKey, '***', $url);

        $body = $this->transport !== null
            ? ($this->transport)($url)
            : $this->fetch($url);

        $this->lastRawResponse = $body;

        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new ApiException('رد غير صالح من المزوّد', 'invalid_response');
        }

        $this->guardAgainstApiError($data);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function guardAgainstApiError(array $data): void
    {
        if (($data['success'] ?? true) !== false && !isset($data['error'])) {
            return;
        }

        $error = is_array($data['error'] ?? null) ? $data['error'] : [];

        throw new ApiException(
            message: (string) ($error['info'] ?? 'طلب مرفوض من Numverify'),
            type: (string) ($error['type'] ?? 'unknown_error'),
            apiCode: (int) ($error['code'] ?? 0),
            info: isset($error['info']) ? (string) $error['info'] : null,
        );
    }

    /** يستخدم cURL إن وُجد، وإلا يسقط إلى الـ streams حتى يعمل المشروع على أي إعداد PHP. */
    private function fetch(string $url): string
    {
        return function_exists('curl_init')
            ? $this->fetchWithCurl($url)
            : $this->fetchWithStream($url);
    }

    private function fetchWithStream(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'user_agent' => 'numverify-mvp/1.0',
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            throw new ApiException('فشل الاتصال بخادم Numverify', 'network_error');
        }

        return $body;
    }

    private function fetchWithCurl(string $url): string
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'numverify-mvp/1.0',
        ]);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        // curl_close($handle);

        if ($body === false) {
            throw new ApiException($error !== '' ? $error : 'فشل الاتصال', 'network_error');
        }

        return (string) $body;
    }

    private function normalizeNumber(string $number): string
    {
        return Digits::normalize($number);
    }

    private function normalizeCountryCode(?string $countryCode): ?string
    {
        return Digits::countryCode($countryCode);
    }
}
