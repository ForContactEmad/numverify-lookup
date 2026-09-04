<?php

declare(strict_types=1);

namespace Numverify\Lookup;

use Numverify\Api\ApiException;
use Numverify\Api\Client;
use Numverify\Local\LocalResult;
use Numverify\Local\Validator;

/**
 * القاعدة الحاكمة للمشروع كله:
 *
 *   التحقق من الصيغة مجاني ومحلي. البيانات الإضافية (المشغّل، الموقع،
 *   نوع الخط) هي وحدها ما يستحق طلباً من الحصة الشهرية.
 *
 * لذلك لا يُستدعى المزوّد إلا بعد أن يعبر الرقم التحقق المحلي.
 * الرقم المستحيل يُرفض بصفر تكلفة.
 */
final class PhoneLookup
{
    public function __construct(
        private readonly Validator $local,
        private readonly ?Client $api = null,
        private readonly bool $enrich = true,
    ) {
    }

    public function lookup(string $number, ?string $countryCode = null): LookupResult
    {
        $local = $this->local->check($number, $countryCode);

        if (!$local->plausible) {
            return LookupResult::rejectedLocally($local);
        }

        if (!$this->enrich || $this->api === null) {
            return LookupResult::localOnly($local, 'الإثراء عبر المزوّد معطّل.');
        }

        try {
            // الرقم صار بصيغة E.164، ورمز الدولة لا يُرسل معها حسب توثيق المزوّد.
            $remote = $this->api->validate($local->e164);
        } catch (ApiException $e) {
            // فشل المزوّد لا يلغي ما عرفناه محلياً — نعيد ما لدينا مع سبب الفشل.
            return LookupResult::localOnly($local, $e->friendlyMessage());
        }

        return LookupResult::enriched($local, $remote);
    }

    /** كم طلباً كان سيُستهلك لو لم توجد الطبقة المحلية. */
    public function wouldHaveCost(string $number, ?string $countryCode = null): int
    {
        return $this->local->check($number, $countryCode)->plausible ? 1 : 0;
    }
}
