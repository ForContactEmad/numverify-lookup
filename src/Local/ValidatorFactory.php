<?php

declare(strict_types=1);

namespace Numverify\Local;

/**
 * يختار أدق محرك متاح: libphonenumber إن كانت مثبّتة، وإلا الجدول المدمج.
 * المشروع يعمل في الحالتين — الحزمة تحسين اختياري لا شرط تشغيل.
 */
final class ValidatorFactory
{
    public static function make(?string $defaultCountryCode = null): Validator
    {
        return LibPhoneNumberValidator::isAvailable()
            ? new LibPhoneNumberValidator($defaultCountryCode)
            : new BuiltInValidator($defaultCountryCode);
    }
}
