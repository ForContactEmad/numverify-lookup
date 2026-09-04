<?php

declare(strict_types=1);

namespace Numverify\Local;

/**
 * نتيجة التحقق المحلي: هل الرقم *يستحق* استهلاك طلب من الحصة؟
 *
 * plausible = false تعني رفضاً قاطعاً (صيغة مستحيلة)، فلا يُرسل طلب.
 * plausible = true لا تعني أن الرقم مُفعَّل — هذا وحده ما يحتاج المزوّد.
 */
final class LocalResult
{
    public function __construct(
        public readonly bool $plausible,
        public readonly string $e164,
        public readonly ?string $countryCode = null,
        public readonly ?string $callingCode = null,
        public readonly string $nationalNumber = '',
        public readonly ?string $reason = null,
        public readonly string $source = 'builtin',
    ) {
    }

    public static function reject(string $e164, string $reason, string $source = 'builtin'): self
    {
        return new self(false, $e164, reason: $reason, source: $source);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plausible' => $this->plausible,
            'e164' => $this->e164,
            'country_code' => $this->countryCode,
            'calling_code' => $this->callingCode,
            'national_number' => $this->nationalNumber,
            'reason' => $this->reason,
            'source' => $this->source,
        ];
    }
}
