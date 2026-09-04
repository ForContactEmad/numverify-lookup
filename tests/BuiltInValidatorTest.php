<?php

declare(strict_types=1);

namespace Numverify\Tests;

use Numverify\Local\BuiltInValidator;
use PHPUnit\Framework\TestCase;

final class BuiltInValidatorTest extends TestCase
{
    public function testAcceptsInternationalSaudiMobile(): void
    {
        $result = (new BuiltInValidator())->check('+966 50 123 4567');

        $this->assertTrue($result->plausible);
        $this->assertSame('966501234567', $result->e164);
        $this->assertSame('SA', $result->countryCode);
        $this->assertSame('966', $result->callingCode);
        $this->assertSame('501234567', $result->nationalNumber);
    }

    public function testExpandsLocalNumberUsingExplicitCountry(): void
    {
        $result = (new BuiltInValidator())->check('0501234567', 'SA');

        $this->assertTrue($result->plausible);
        $this->assertSame('966501234567', $result->e164);
    }

    public function testExpandsLocalNumberUsingDefaultCountry(): void
    {
        $result = (new BuiltInValidator('SA'))->check('0501234567');

        $this->assertTrue($result->plausible);
        $this->assertSame('966501234567', $result->e164);
    }

    public function testRejectsLocalNumberWithoutAnyCountry(): void
    {
        $result = (new BuiltInValidator())->check('0501234567');

        $this->assertFalse($result->plausible);
        $this->assertStringContainsString('رمز الدولة', (string) $result->reason);
    }

    public function testStripsInternationalDialPrefix(): void
    {
        $this->assertSame('966501234567', (new BuiltInValidator())->check('00966501234567')->e164);
    }

    public function testConvertsArabicIndicDigits(): void
    {
        $result = (new BuiltInValidator())->check('٩٦٦٥٠١٢٣٤٥٦٧');

        $this->assertTrue($result->plausible);
        $this->assertSame('966501234567', $result->e164);
    }

    public function testRejectsTooShortNumber(): void
    {
        $result = (new BuiltInValidator())->check('12345');

        $this->assertFalse($result->plausible);
        $this->assertStringContainsString('أقصر', (string) $result->reason);
    }

    public function testRejectsNumberLongerThanE164Limit(): void
    {
        $result = (new BuiltInValidator())->check('9665012345678901');

        $this->assertFalse($result->plausible);
        $this->assertStringContainsString('15', (string) $result->reason);
    }

    public function testRejectsUnknownCallingCode(): void
    {
        $result = (new BuiltInValidator())->check('999123456789');

        $this->assertFalse($result->plausible);
        $this->assertStringContainsString('مفتاح دولة', (string) $result->reason);
    }

    public function testRejectsWrongNationalLengthForCountry(): void
    {
        // 966 + خمس خانات فقط: مفتاح صحيح وطول وطني خاطئ.
        $result = (new BuiltInValidator())->check('96650123');

        $this->assertFalse($result->plausible);
        $this->assertStringContainsString('SA', (string) $result->reason);
    }

    public function testMatchesLongestPrefixFirst(): void
    {
        // 971 يجب ألا يُقرأ كـ 97 أو 9.
        $this->assertSame('AE', (new BuiltInValidator())->check('971501234567')->countryCode);
    }

    public function testResolvesSharedPrefixToMostCommonOwner(): void
    {
        $this->assertSame('US', (new BuiltInValidator())->check('14158586273')->countryCode);
    }

    public function testRejectsEmptyInput(): void
    {
        $this->assertFalse((new BuiltInValidator())->check('   ')->plausible);
    }

    public function testReportsItsEngineName(): void
    {
        $this->assertSame('builtin', (new BuiltInValidator())->name());
    }
}
