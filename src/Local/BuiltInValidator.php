<?php

declare(strict_types=1);

namespace Numverify\Local;

use Numverify\Support\Digits;

/**
 * تحقق محلي بلا أي اعتمادية خارجية.
 *
 * حدوده معروفة ومقصودة: يتحقق من بنية E.164، ومن أن مفتاح الدولة معروف،
 * ومن أن طول الرقم الوطني ضمن المدى المتوقع لتلك الدولة. لا يعرف نطاقات
 * المشغّلين ولا الأرقام المحجوزة — هذا ما تفعله libphonenumber، وهي
 * البديل التلقائي إن كانت مثبّتة (انظر LibPhoneNumberValidator).
 *
 * الهدف ليس دقة مطلقة، بل استبعاد المستحيل قبل صرف طلب من الحصة.
 */
final class BuiltInValidator implements Validator
{
    /** حدود معيار E.164 */
    private const MIN_LENGTH = 8;
    private const MAX_LENGTH = 15;

    /** iso => [مفتاح الاتصال، أقل طول وطني، أكبر طول وطني] */
    private const COUNTRIES = [
        'SA' => ['966', 8, 9],  'AE' => ['971', 8, 9],  'KW' => ['965', 8, 8],
        'QA' => ['974', 8, 8],  'BH' => ['973', 8, 8],  'OM' => ['968', 8, 8],
        'YE' => ['967', 9, 9],  'EG' => ['20', 9, 10],  'JO' => ['962', 8, 9],
        'LB' => ['961', 7, 8],  'SY' => ['963', 8, 9],  'IQ' => ['964', 10, 10],
        'PS' => ['970', 8, 9],  'SD' => ['249', 9, 9],  'LY' => ['218', 9, 9],
        'TN' => ['216', 8, 8],  'DZ' => ['213', 8, 9],  'MA' => ['212', 9, 9],
        'TR' => ['90', 10, 10], 'US' => ['1', 10, 10],  'CA' => ['1', 10, 10],
        'GB' => ['44', 9, 10],  'FR' => ['33', 9, 9],   'DE' => ['49', 6, 11],
        'ES' => ['34', 9, 9],   'IT' => ['39', 9, 11],  'NL' => ['31', 9, 9],
        'SE' => ['46', 7, 9],   'RU' => ['7', 10, 10],  'IN' => ['91', 10, 10],
        'PK' => ['92', 10, 10], 'BD' => ['880', 10, 10],'ID' => ['62', 9, 12],
        'MY' => ['60', 9, 10],  'PH' => ['63', 10, 10], 'CN' => ['86', 11, 11],
        'JP' => ['81', 9, 10],  'KR' => ['82', 9, 10],  'AU' => ['61', 9, 9],
        'NZ' => ['64', 8, 10],  'BR' => ['55', 10, 11], 'MX' => ['52', 10, 10],
        'ZA' => ['27', 9, 9],   'NG' => ['234', 10, 10],'KE' => ['254', 9, 9],
        'ET' => ['251', 9, 9],
    ];

    /** مفاتيح مشتركة بين عدة دول — نختار الأكثر شيوعاً للاستدلال العكسي. */
    private const SHARED_PREFIX_OWNER = ['1' => 'US', '7' => 'RU'];

    public function __construct(private readonly ?string $defaultCountryCode = null)
    {
    }

    public function name(): string
    {
        return 'builtin';
    }

    public function check(string $number, ?string $countryCode = null): LocalResult
    {
        $digits = Digits::normalize($number);
        $countryCode = Digits::countryCode($countryCode);

        if ($digits === '') {
            return LocalResult::reject('', 'لم يُدخَل أي رقم.');
        }

        // رقم محلي يبدأ بصفر: يحتاج دولة لتفسيره.
        if (str_starts_with($digits, '0')) {
            $iso = $countryCode ?? $this->defaultCountryCode;

            if ($iso === null || !isset(self::COUNTRIES[$iso])) {
                return LocalResult::reject(
                    $digits,
                    'الرقم بصيغة محلية (يبدأ بصفر) ولم يُحدَّد رمز الدولة.'
                );
            }

            $digits = self::COUNTRIES[$iso][0] . ltrim($digits, '0');
        }

        if (strlen($digits) < self::MIN_LENGTH) {
            return LocalResult::reject($digits, 'الرقم أقصر من الحد الأدنى (8 خانات).');
        }

        if (strlen($digits) > self::MAX_LENGTH) {
            return LocalResult::reject($digits, 'الرقم أطول من 15 خانة، وهو الحد الأقصى في E.164.');
        }

        $match = $this->matchCountry($digits, $countryCode);

        if ($match === null) {
            return LocalResult::reject($digits, 'لا يبدأ الرقم بأي مفتاح دولة معروف في الجدول المحلي.');
        }

        [$iso, $calling, $min, $max] = $match;
        $national = substr($digits, strlen($calling));
        $length = strlen($national);

        if ($length < $min || $length > $max) {
            $expected = $min === $max ? "{$min}" : "{$min}–{$max}";

            return LocalResult::reject(
                $digits,
                "الرقم الوطني في {$iso} يتكوّن من {$expected} خانة، والمُدخَل {$length}."
            );
        }

        return new LocalResult(
            plausible: true,
            e164: $digits,
            countryCode: $iso,
            callingCode: $calling,
            nationalNumber: $national,
            source: $this->name(),
        );
    }

    /**
     * يطابق أطول مفتاح أولاً (966 قبل 96 قبل 9) لتفادي الالتباس.
     *
     * @return array{0: string, 1: string, 2: int, 3: int}|null
     */
    private function matchCountry(string $digits, ?string $hint): ?array
    {
        // رمز الدولة المُصرَّح به يفوز إن كان متسقاً مع الرقم.
        if ($hint !== null && isset(self::COUNTRIES[$hint])) {
            [$calling, $min, $max] = self::COUNTRIES[$hint];

            if (str_starts_with($digits, $calling)) {
                return [$hint, $calling, $min, $max];
            }
        }

        foreach ([3, 2, 1] as $length) {
            $prefix = substr($digits, 0, $length);
            $iso = self::SHARED_PREFIX_OWNER[$prefix] ?? $this->isoForPrefix($prefix);

            if ($iso !== null) {
                [$calling, $min, $max] = self::COUNTRIES[$iso];

                return [$iso, $calling, $min, $max];
            }
        }

        return null;
    }

    private function isoForPrefix(string $prefix): ?string
    {
        foreach (self::COUNTRIES as $iso => [$calling]) {
            if ($calling === $prefix) {
                return $iso;
            }
        }

        return null;
    }
}
