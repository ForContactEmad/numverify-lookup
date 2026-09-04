<?php

declare(strict_types=1);

namespace Numverify\Api;

/**
 * كائن نتيجة ثابت (immutable) بدل تمرير مصفوفات بلا شكل معروف.
 * أي تغيير في حقول المزوّد يُعالَج هنا في مكان واحد فقط.
 *
 * queriedNumber و countryCodeUsed ليسا من رد المزوّد: هما ما أُرسل فعلاً
 * بعد التنظيف — بدونهما يستحيل معرفة سبب فشل التحقق.
 */
final class Result
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $number,
        public readonly string $localFormat,
        public readonly string $internationalFormat,
        public readonly string $countryPrefix,
        public readonly string $countryCode,
        public readonly string $countryName,
        public readonly string $location,
        public readonly string $carrier,
        public readonly string $lineType,
        public readonly string $queriedNumber = '',
        public readonly ?string $countryCodeUsed = null,
        public readonly bool $fromCache = false,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(
        array $data,
        string $queriedNumber = '',
        ?string $countryCodeUsed = null,
        bool $fromCache = false,
    ): self {
        return new self(
            valid: (bool) ($data['valid'] ?? false),
            number: (string) ($data['number'] ?? ''),
            localFormat: (string) ($data['local_format'] ?? ''),
            internationalFormat: (string) ($data['international_format'] ?? ''),
            countryPrefix: (string) ($data['country_prefix'] ?? ''),
            countryCode: (string) ($data['country_code'] ?? ''),
            countryName: (string) ($data['country_name'] ?? ''),
            location: (string) ($data['location'] ?? ''),
            carrier: (string) ($data['carrier'] ?? ''),
            lineType: (string) ($data['line_type'] ?? ''),
            queriedNumber: $queriedNumber,
            countryCodeUsed: $countryCodeUsed,
            fromCache: $fromCache,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'number' => $this->number,
            'local_format' => $this->localFormat,
            'international_format' => $this->internationalFormat,
            'country_prefix' => $this->countryPrefix,
            'country_code' => $this->countryCode,
            'country_name' => $this->countryName,
            'location' => $this->location,
            'carrier' => $this->carrier,
            'line_type' => $this->lineType,
            'query' => [
                'number_sent' => $this->queriedNumber,
                'country_code_sent' => $this->countryCodeUsed,
            ],
            'from_cache' => $this->fromCache,
        ];
    }

    /** الحقول المعروضة في الواجهة، بعناوين ثنائية اللغة. */
    public function displayFields(): array
    {
        return [
            ['الصيغة الدولية', 'International', $this->internationalFormat],
            ['الصيغة المحلية', 'Local', $this->localFormat],
            ['الدولة', 'Country', trim($this->countryName . ' ' . ($this->countryCode !== '' ? "({$this->countryCode})" : ''))],
            ['مفتاح الدولة', 'Prefix', $this->countryPrefix],
            ['الموقع', 'Location', $this->location],
            ['المشغّل', 'Carrier', $this->carrier],
            ['نوع الخط', 'Line type', $this->lineType],
        ];
    }

    /** اقتراحات عملية تُعرض عند فشل التحقق، مبنية على ما أُرسل فعلاً. */
    public function failureHints(): array
    {
        $hints = [];

        if ($this->countryCodeUsed === null && str_starts_with($this->queriedNumber, '0')) {
            $hints[] = 'الرقم يبدأ بصفر، وهذه صيغة محلية. أضف رمز الدولة (SA للسعودية) أو اكتبه بالصيغة الدولية.';
        }

        if ($this->countryCodeUsed !== null && !str_starts_with($this->queriedNumber, '0')) {
            $hints[] = "أُرسل رمز الدولة {$this->countryCodeUsed} مع رقم بصيغة دولية. احذف رمز الدولة وجرّب مرة أخرى.";
        }

        $length = strlen($this->queriedNumber);

        if ($length < 8) {
            $hints[] = "الرقم المُرسَل مكوّن من {$length} خانات فقط — يبدو ناقصاً.";
        }

        if ($length > 15) {
            $hints[] = 'الرقم أطول من 15 خانة، وهو الحد الأقصى في معيار E.164.';
        }

        if ($hints === []) {
            $hints[] = 'تأكد من مفتاح الدولة (966 للسعودية) ومن أن الرقم مستخدم فعلاً.';
        }

        return $hints;
    }
}
