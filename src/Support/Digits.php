<?php

declare(strict_types=1);

namespace Numverify\Support;

/**
 * تطبيع الأرقام في مكان واحد. كان هذا المنطق مكرراً داخل Client،
 * وصار الآن مشتركاً بينه وبين طبقة التحقق المحلية.
 */
final class Digits
{
    /** الأرقام العربية والفارسية → لاتينية، لأن المزوّد لا يقبل غيرها. */
    private const MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * ‎+966 50 123 4567 → 966501234567
     * ‎00966501234567   → 966501234567   (بادئة الاتصال الدولي تُزال)
     * ‎٠٥٠١٢٣٤٥٦٧      → 0501234567     (أرقام عربية تُحوَّل)
     */
    public static function normalize(string $number): string
    {
        $number = strtr(trim($number), self::MAP);
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function countryCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
    }
}
