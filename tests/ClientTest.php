<?php

declare(strict_types=1);

namespace Numverify\Tests;

use Numverify\Api\ApiException;
use Numverify\Api\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var list<string> */
    private array $requestedUrls = [];

    /** transport وهمي: يسجّل الروابط ويعيد رداً ثابتاً، بلا إنترنت وبلا استهلاك حصة. */
    private function transport(array $payload): \Closure
    {
        return function (string $url) use ($payload): string {
            $this->requestedUrls[] = $url;

            return (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        };
    }

    private function validUsPayload(): array
    {
        return [
            'valid' => true,
            'number' => '14158586273',
            'local_format' => '4158586273',
            'international_format' => '+14158586273',
            'country_prefix' => '+1',
            'country_code' => 'US',
            'country_name' => 'United States of America',
            'location' => 'San Francisco',
            'carrier' => 'AT&T Mobility LLC',
            'line_type' => 'mobile',
        ];
    }

    public function testMapsFieldsFromProviderResponse(): void
    {
        $client = new Client('key', transport: $this->transport($this->validUsPayload()));
        $result = $client->validate('+1 415 858-6273');

        $this->assertTrue($result->valid);
        $this->assertSame('AT&T Mobility LLC', $result->carrier);
        $this->assertSame('mobile', $result->lineType);
        $this->assertFalse($result->fromCache);
    }

    public function testStripsFormattingBeforeSending(): void
    {
        $client = new Client('key', transport: $this->transport($this->validUsPayload()));
        $client->validate('+1 (415) 858-6273');

        $this->assertStringContainsString('number=14158586273', $this->requestedUrls[0]);
    }

    public function testUppercasesCountryCode(): void
    {
        $client = new Client('key', transport: $this->transport($this->validUsPayload()));
        $client->validate('14158586273', 'us');

        $this->assertStringContainsString('country_code=US', $this->requestedUrls[0]);
    }

    public function testIgnoresMalformedCountryCode(): void
    {
        $client = new Client('key', transport: $this->transport($this->validUsPayload()));
        $client->validate('14158586273', '966');

        $this->assertStringNotContainsString('country_code', $this->requestedUrls[0]);
    }

    public function testAppliesDefaultCountryCodeToLocalNumbers(): void
    {
        $client = new Client('key', transport: $this->transport($this->validUsPayload()), defaultCountryCode: 'SA');
        $client->validate('0501234567');

        $this->assertStringContainsString('country_code=SA', $this->requestedUrls[0]);
    }

    public function testDoesNotApplyDefaultCountryCodeToInternationalNumbers(): void
    {
        $client = new Client('key', transport: $this->transport($this->validUsPayload()), defaultCountryCode: 'SA');
        $client->validate('14158586273');

        $this->assertStringNotContainsString('country_code', $this->requestedUrls[0]);
    }

    public function testRetriesOnceWithDefaultCountryCode(): void
    {
        $attempts = 0;
        $client = new Client('key', transport: static function () use (&$attempts): string {
            $attempts++;

            return (string) json_encode($attempts === 1 ? ['valid' => false] : ['valid' => true, 'number' => '501234567']);
        }, defaultCountryCode: 'SA');

        $this->assertTrue($client->validate('501234567')->valid);
        $this->assertSame(2, $attempts);
    }

    public function testDoesNotRetryWhenDisabled(): void
    {
        $attempts = 0;
        $client = new Client('key', transport: static function () use (&$attempts): string {
            $attempts++;

            return (string) json_encode(['valid' => false]);
        }, defaultCountryCode: 'SA', autoRetry: false);

        $client->validate('501234567');

        $this->assertSame(1, $attempts);
    }

    public function testTreatsInvalidNumberAsResultNotError(): void
    {
        $client = new Client('key', transport: $this->transport(['valid' => false, 'number' => '00000']));
        $result = $client->validate('00000');

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->failureHints());
    }

    public function testThrowsOnInvalidAccessKey(): void
    {
        $client = new Client('bad', transport: $this->transport([
            'success' => false,
            'error' => ['code' => 101, 'type' => 'invalid_access_key', 'info' => 'invalid key'],
        ]));

        try {
            $client->validate('14158586273');
            $this->fail('كان يجب رمي ApiException');
        } catch (ApiException $e) {
            $this->assertSame('invalid_access_key', $e->type);
            $this->assertStringContainsString('مفتاح الوصول', $e->friendlyMessage());
        }
    }

    public function testTranslatesQuotaError(): void
    {
        $client = new Client('key', transport: $this->transport([
            'success' => false,
            'error' => ['code' => 104, 'type' => 'usage_limit_reached', 'info' => 'limit'],
        ]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/limit/');
        $client->validate('14158586273');
    }

    public function testRejectsEmptyNumberBeforeSpendingRequest(): void
    {
        $client = new Client('key', transport: $this->transport([]));

        try {
            $client->validate('   ');
            $this->fail('كان يجب رمي ApiException');
        } catch (ApiException $e) {
            $this->assertSame('no_phone_number_provided', $e->type);
            $this->assertSame([], $this->requestedUrls);
        }
    }

    public function testMasksAccessKeyInDiagnosticUrl(): void
    {
        $client = new Client('super-secret', transport: $this->transport($this->validUsPayload()));
        $client->validate('14158586273');

        $this->assertStringNotContainsString('super-secret', (string) $client->lastUrl());
    }
}
