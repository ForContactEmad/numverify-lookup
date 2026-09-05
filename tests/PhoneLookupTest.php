<?php

declare(strict_types=1);

namespace Numverify\Tests;

use Numverify\Api\Client;
use Numverify\Local\BuiltInValidator;
use Numverify\Lookup\PhoneLookup;
use PHPUnit\Framework\TestCase;

/**
 * أهم اختبارات المشروع: كل واحد منها يثبت أن طلباً لم يُصرف بلا داعٍ.
 */
final class PhoneLookupTest extends TestCase
{
    private int $apiCalls = 0;

    private function api(?array $payload = null): Client
    {
        $payload ??= [
            'valid' => true,
            'number' => '966501234567',
            'international_format' => '+966501234567',
            'country_code' => 'SA',
            'country_name' => 'Saudi Arabia',
            'location' => 'Riyadh',
            'carrier' => 'STC',
            'line_type' => 'mobile',
        ];

        return new Client('key', transport: function () use ($payload): string {
            $this->apiCalls++;

            return (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        });
    }

    public function testImpossibleNumberCostsZeroRequests(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api());
        $result = $lookup->lookup('12345');

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->apiCalled);
        $this->assertSame(0, $this->apiCalls);
        $this->assertSame(0, $result->requestsUsed());
    }

    public function testUnknownCallingCodeCostsZeroRequests(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator(), $this->api());
        $lookup->lookup('999123456789');

        $this->assertSame(0, $this->apiCalls);
    }

    public function testWrongNationalLengthCostsZeroRequests(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator(), $this->api());
        $lookup->lookup('96650123');

        $this->assertSame(0, $this->apiCalls);
    }

    public function testPlausibleNumberIsEnrichedByProvider(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api());
        $result = $lookup->lookup('0501234567');

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->apiCalled);
        $this->assertSame(1, $this->apiCalls);
        $this->assertSame('STC', $result->remote?->carrier);
    }

    public function testSendsNormalizedE164ToProvider(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api());
        $result = $lookup->lookup('٠٥٠ ١٢٣ ٤٥٦٧');

        $this->assertSame('966501234567', $result->local->e164);
        $this->assertSame(1, $this->apiCalls);
    }

    public function testEnrichmentCanBeDisabledEntirely(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api(), enrich: false);
        $result = $lookup->lookup('0501234567');

        $this->assertTrue($result->isValid());
        $this->assertSame(0, $this->apiCalls);
        $this->assertNotNull($result->apiSkippedReason);
    }

    public function testWorksWithoutAnyApiClient(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'));
        $result = $lookup->lookup('0501234567');

        $this->assertTrue($result->isValid());
        $this->assertNull($result->remote);
    }

    public function testProviderFailureDoesNotDiscardLocalKnowledge(): void
    {
        $failing = new Client('key', transport: function (): string {
            $this->apiCalls++;

            return (string) json_encode([
                'success' => false,
                'error' => ['code' => 104, 'type' => 'usage_limit_reached', 'info' => 'limit'],
            ]);
        });

        $result = (new PhoneLookup(new BuiltInValidator('SA'), $failing))->lookup('0501234567');

        $this->assertTrue($result->isValid());
        $this->assertSame('966501234567', $result->local->e164);
        $this->assertStringContainsString('الحصة', (string) $result->apiSkippedReason);
    }

    public function testProviderCanOverrideLocalOptimism(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api(['valid' => false]));
        $result = $lookup->lookup('0501234567');

        $this->assertFalse($result->isValid());
    }

    public function testDisplayFieldsLabelTheSourceOfEachValue(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api());
        $sources = array_column($lookup->lookup('0501234567')->displayFields(), 3);

        $this->assertContains('محلي', $sources);
        $this->assertContains('المزوّد', $sources);
    }

    public function testCostEstimateMatchesActualUsage(): void
    {
        $lookup = new PhoneLookup(new BuiltInValidator('SA'), $this->api());

        $this->assertSame(0, $lookup->wouldHaveCost('12345'));
        $this->assertSame(1, $lookup->wouldHaveCost('0501234567'));
    }
}
