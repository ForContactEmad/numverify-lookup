<?php

declare(strict_types=1);

namespace Numverify\Api;

use RuntimeException;

/**
 * خطأ قادم من Numverify أو من طبقة الشبكة.
 *
 * ملاحظة مهمة: توثيق APILayer يعرض أرقام أخطاء بصيغتين مختلفتين
 * (101/104/105 في توثيق numverify.com، و403/429/601 في docs.apilayer.com).
 * لذلك نعتمد على الحقل النصي `type` وليس على الرقم — أثبت وأقل عرضة للكسر.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $type = 'unknown_error',
        public readonly int $apiCode = 0,
        public readonly ?string $info = null,
    ) {
        parent::__construct($message);
    }

    /** رسالة عربية مفهومة للمستخدم بدل النص التقني. */
    public function friendlyMessage(): string
    {
        return match ($this->type) {
            'missing_access_key', 'invalid_access_key' =>
                'مفتاح الوصول غير صحيح أو مفقود. تحقق من NUMVERIFY_ACCESS_KEY في ملف .env',
            'inactive_user' =>
                'الحساب غير مفعّل لدى Numverify. راجع لوحة التحكم أو الدعم.',
            'usage_limit_reached' =>
                'انتهت الحصة الشهرية للطلبات. الكاش يقلل الاستهلاك، لكن تحتاج ترقية الخطة أو الانتظار للشهر القادم.',
            'https_access_restricted' =>
                'الاتصال المشفّر (HTTPS) غير متاح في الخطة المجانية. اضبط NUMVERIFY_HTTPS=false أو رقّ الخطة.',
            'no_phone_number_provided', 'no_phone_number_specified' =>
                'لم يتم إرسال رقم هاتف.',
            'non_numeric_phone_number_provided', 'non_numeric_phone_number' =>
                'الرقم يحتوي على محارف غير رقمية.',
            'invalid_country_code' =>
                'رمز الدولة غير صحيح. استخدم رمزين حسب ISO مثل SA أو US.',
            'invalid_api_function', '404_not_found' =>
                'المسار المطلوب غير موجود لدى المزوّد.',
            'network_error' =>
                'تعذّر الاتصال بخادم Numverify. تحقق من الشبكة ثم أعد المحاولة.',
            'invalid_response' =>
                'وصل رد غير مفهوم من المزوّد.',
            default => $this->info ?? $this->getMessage(),
        };
    }
}
