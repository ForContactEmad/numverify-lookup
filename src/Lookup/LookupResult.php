<?php

declare(strict_types=1);

namespace Numverify\Lookup;

use Numverify\Api\Result;
use Numverify\Local\LocalResult;

/**
 * نتيجة موحّدة تُظهر بوضوح: ماذا عرفنا محلياً، وماذا كلّفنا المزوّد.
 * الشفافية في مصدر كل حقل مقصودة — بها يفهم المستخدم أين تذهب حصته.
 */
final class LookupResult
{
    private function __construct(
        public readonly LocalResult $local,
        public readonly ?Result $remote,
        public readonly bool $apiCalled,
        public readonly ?string $apiSkippedReason,
    ) {
    }

    public static function rejectedLocally(LocalResult $local): self
    {
        return new self($local, null, false, 'رُفض محلياً قبل إرسال أي طلب.');
    }

    public static function localOnly(LocalResult $local, string $reason): self
    {
        return new self($local, null, false, $reason);
    }

    public static function enriched(LocalResult $local, Result $remote): self
    {
        return new self($local, $remote, true, null);
    }

    /** صالح = عبر التحقق المحلي، ولم يُكذّبه المزوّد إن سُئل. */
    public function isValid(): bool
    {
        return $this->local->plausible && ($this->remote === null || $this->remote->valid);
    }

    /** الطلبات المستهلكة من الحصة بسبب هذا الاستعلام. */
    public function requestsUsed(): int
    {
        return $this->apiCalled && $this->remote !== null && !$this->remote->fromCache ? 1 : 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'local' => $this->local->toArray(),
            'remote' => $this->remote?->toArray(),
            'api_called' => $this->apiCalled,
            'api_skipped_reason' => $this->apiSkippedReason,
            'requests_used' => $this->requestsUsed(),
        ];
    }

    /** الحقول المعروضة، مع مصدر كل حقل. */
    public function displayFields(): array
    {
        $fields = [
            ['الصيغة الدولية', 'International', '+' . $this->local->e164, 'محلي'],
            ['الدولة', 'Country', (string) $this->local->countryCode, 'محلي'],
            ['مفتاح الدولة', 'Prefix', '+' . $this->local->callingCode, 'محلي'],
            ['الرقم الوطني', 'National', $this->local->nationalNumber, 'محلي'],
        ];

        if ($this->remote !== null) {
            $fields[] = ['اسم الدولة', 'Country name', $this->remote->countryName, 'المزوّد'];
            $fields[] = ['الموقع', 'Location', $this->remote->location, 'المزوّد'];
            $fields[] = ['المشغّل', 'Carrier', $this->remote->carrier, 'المزوّد'];
            $fields[] = ['نوع الخط', 'Line type', $this->remote->lineType, 'المزوّد'];
        }

        return array_values(array_filter($fields, static fn (array $f): bool => trim((string) $f[2]) !== '' && $f[2] !== '+'));
    }
}
