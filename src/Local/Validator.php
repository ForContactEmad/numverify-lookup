<?php

declare(strict_types=1);

namespace Numverify\Local;

/**
 * واجهة التحقق المحلي. وجودها يسمح بتبديل التنفيذ المدمج
 * بـ libphonenumber دون أن تتغيّر أي طبقة أخرى.
 */
interface Validator
{
    public function check(string $number, ?string $countryCode = null): LocalResult;

    /** اسم يُعرض للمستخدم ليعرف أي محرك يعمل. */
    public function name(): string;
}
