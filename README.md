# Numverify Lookup

[![tests](https://github.com/USERNAME/numverify-lookup/actions/workflows/tests.yml/badge.svg)](https://github.com/USERNAME/numverify-lookup/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-8.1%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

التحقق من أرقام الهاتف الدولية بطبقتين: تحقق محلي مجاني يستبعد المستحيل،
ثم استعلام خارجي عن المشغّل والموقع للأرقام التي تستحقه وحدها.

> **الفكرة المركزية:** التحقق من *الصيغة* لا يحتاج واجهة خارجية إطلاقاً.
> المشغّل والموقع ونوع الخط هي وحدها ما يستحق طلباً من حصة محدودة.
> فصل الطبقتين قلّل استهلاك الحصة إلى الأرقام المعقولة فقط.

## لماذا هذا المشروع

واجهة Numverify تمنح 100 طلب شهرياً في خطتها المجانية. التنفيذ المباشر يستهلك
طلباً لكل إدخال، بما في ذلك أخطاء الطباعة والحقول الفارغة والأرقام المستحيلة.
هذا المشروع يضع تحققاً محلياً أمام الواجهة، فلا يصل إليها إلا ما يستحق.

```
المُدخَل ──► تطبيع ──► تحقق محلي ──┬── مرفوض ──► نتيجة (0 طلبات)
             (Digits)   (Local\*)  │
                                   └── مقبول ──► كاش ──┬── إصابة ──► نتيجة (0 طلبات)
                                                       └── فقدان ──► Numverify (طلب واحد)
```

## التشغيل

```bash
composer install          # اختياري — المشروع يعمل بدونه
cp .env.example .env      # ضع مفتاحك في NUMVERIFY_ACCESS_KEY
php -S localhost:8000 -t public
```

بدون مفتاح يعمل المشروع بالتحقق المحلي وحده — مفيد للتجربة والعرض.

```bash
php bin/check.php 0501234567      # استعلام واحد
php bin/diagnose.php 0501234567   # يعرض الطلب والرد الخام بلا كاش
php bin/cache-clear.php           # حذف الكاش
composer test                     # الاختبارات
```

نقطة JSON: `GET /api/validate?number=966501234567`

## البنية

```
src/
  Local/          التحقق المحلي — واجهة + تنفيذان
    Validator.php            الواجهة
    BuiltInValidator.php     جدول مدمج بلا اعتماديات
    LibPhoneNumberValidator.php  محرك أدق يُستخدم تلقائياً إن وُجد
    ValidatorFactory.php     يختار الأدق المتاح
  Api/            الطبقة الوحيدة التي تعرف Numverify
  Lookup/         التنسيق بين الطبقتين وقرار صرف الطلب
  Support/        التطبيع، الإعدادات، الكاش
```

القاعدة: `Api/` لا يعرف HTTP أو HTML، و`Local/` لا يعرف الشبكة أصلاً،
و`Http/` لا يعرف Numverify. لهذا يمكن اختبار كل شيء بلا إنترنت.

## قرارات تستحق الشرح

**واجهة للتحقق المحلي بتنفيذين.** الجدول المدمج يعمل بصفر اعتماديات، لكن
`giggsey/libphonenumber-for-php` أدق بكثير: يعرف نطاقات المشغّلين لكل دولة.
`ValidatorFactory` يختار الأدق المتاح تلقائياً، فالحزمة تحسين اختياري لا شرط تشغيل.

**الاعتماد على `error.type` لا على رقم الخطأ.** توثيق المزوّد يعرض رقمين مختلفين
لنفس الخطأ (101 مقابل 403)، والنص هو الثابت الوحيد بينهما.

**النتائج غير الصالحة لا تُخزَّن في الكاش.** تخزينها يجمّد رسالة الفشل 24 ساعة
حتى بعد أن يصحّح المستخدم رقمه.

**فشل المزوّد لا يلغي المعرفة المحلية.** عند نفاد الحصة تُعاد نتيجة التحقق المحلي
مع سبب تعذّر الإثراء، بدل رمي خطأ يمسح كل شيء.

**`transport` قابل للحقن في `Client`.** كل اختبار يعمل بلا إنترنت وبصفر استهلاك.

## حدود معروفة

الجدول المدمج يغطي 46 دولة ويتحقق من بنية E.164 وطول الرقم الوطني فقط — لا يعرف
نطاقات المشغّلين ولا الأرقام المحجوزة. لدقة أعلى ثبّت `giggsey/libphonenumber-for-php`
وسيُستخدم تلقائياً.

`Local\BuiltInValidator` يعتمد أشهر دولة للمفاتيح المشتركة (1 → US، 7 → RU)،
فتمييز كندا من الولايات المتحدة محلياً غير ممكن.

الخطة المجانية لا تدعم HTTPS، والاتصال يتم من الخادم فقط — المفتاح لا يظهر
في الواجهة الأمامية أبداً.

## الاختبارات

38 اختباراً في PHPUnit تغطي التحقق المحلي، تعامل المزوّد، وقرار صرف الطلب.
جميعها بلا إنترنت وبلا استهلاك حصة. أهمها مجموعة تثبت أن الأرقام المستحيلة
تكلّف صفر طلبات.

```bash
composer test
```

## Roadmap

- [ ] استعلام دفعات ورفع CSV مع تصدير النتائج
- [ ] تحديد معدل الطلبات لكل مستخدم
- [ ] مصادقة وسجل استعلامات مع عزل لكل مستخدم
- [ ] موفّر خدمة لـ Laravel يغلّف نفس الطبقات

---

## English

Two-layer international phone validation. A dependency-free local layer rejects
impossible numbers at zero cost; the Numverify API is called only for carrier,
location and line-type data on numbers that pass.

The free plan allows 100 requests per month, so a naive integration burns quota
on typos and empty fields. This one doesn't.

- `Local/` — validation engine behind an interface, with an optional
  libphonenumber-backed implementation selected automatically when installed
- `Api/` — the only class that knows Numverify exists; injectable transport
  makes every test run offline
- `Lookup/` — decides whether a request is worth spending

Requires PHP 8.1+. Composer is optional: a built-in PSR-4 autoloader keeps the
project runnable with zero installation.

```bash
cp .env.example .env
php -S localhost:8000 -t public
composer test
```

MIT licensed.
