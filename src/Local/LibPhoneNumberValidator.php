<?php

declare(strict_types=1);

namespace Numverify\Local;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Numverify\Support\Digits;

/**
 * تحقق محلي مدعوم بـ giggsey/libphonenumber-for-php (منفذ PHP لمكتبة Google).
 * يُستخدم تلقائياً إن كانت الحزمة مثبّتة — انظر ValidatorFactory.
 *
 * أدق بكثير من الجدول المدمج: يعرف نطاقات المشغّلين لكل دولة،
 * ويميّز الجوال من الثابت محلياً وبلا أي طلب خارجي.
 */
final class LibPhoneNumberValidator implements Validator
{
    public static function isAvailable(): bool
    {
        return class_exists(PhoneNumberUtil::class);
    }

    public function __construct(private readonly ?string $defaultCountryCode = null)
    {
    }

    public function name(): string
    {
        return 'libphonenumber';
    }

    public function check(string $number, ?string $countryCode = null): LocalResult
    {
        $digits = Digits::normalize($number);
        $region = Digits::countryCode($countryCode) ?? $this->defaultCountryCode;

        if ($digits === '') {
            return LocalResult::reject('', 'لم يُدخَل أي رقم.', $this->name());
        }

        $util = PhoneNumberUtil::getInstance();
        $input = str_starts_with($digits, '0') ? $digits : '+' . $digits;

        try {
            $parsed = $util->parse($input, $region);
        } catch (NumberParseException $e) {
            return LocalResult::reject($digits, 'تعذّر تحليل الرقم: ' . $e->getMessage(), $this->name());
        }

        if (!$util->isValidNumber($parsed)) {
            return LocalResult::reject($digits, 'الرقم لا يطابق خطة الترقيم لهذه الدولة.', $this->name());
        }

        return new LocalResult(
            plausible: true,
            e164: ltrim($util->format($parsed, \libphonenumber\PhoneNumberFormat::E164), '+'),
            countryCode: $util->getRegionCodeForNumber($parsed),
            callingCode: (string) $parsed->getCountryCode(),
            nationalNumber: (string) $parsed->getNationalNumber(),
            source: $this->name(),
        );
    }
}
