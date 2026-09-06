# مرجع سريع — كل ملف، كل دالة

ملف شخصي للرجوع إليه، لا للنشر. الهدف: تفتحه بعد شهر وتتذكر أين كل شيء ولماذا.

---

## الفكرة في سطرين

الرقم يمرّ على طبقتين: **محلية** (تتحقق من الصيغة، مجانية) ثم **خارجية**
(Numverify، تعطي المشغّل والموقع، تستهلك من الحصة). المحلية تفلتر المستحيل
قبل أن يصل الخارجية.

```
مُدخَل → Digits::normalize → Validator::check → (مرفوض: توقف)
                                              → (مقبول: Client::validate → نتيجة)
```

---

## `src/Support/` — أدوات عامة، لا تعرف شيئاً عن الهاتف تحديداً

### `Digits.php`
تطبيع الأرقام. تُستخدم من `Local/` و`Api/` معاً.

- `normalize(string): string` — يزيل كل ما ليس رقماً، يحوّل الأرقام العربية/الفارسية
  إلى لاتينية، يزيل بادئة `00`. مثال: `+966 50-123` → `96650123`.
- `countryCode(?string): ?string` — يتحقق أن الرمز حرفان لاتينيان، يرفعه لحروف
  كبيرة. أي شيء آخر (`966`, `sa1`, فارغ) يرجع `null`.

### `Env.php`
قراءة `.env` بلا Composer. صنف ساكن بالكامل (static).

- `load(string $path): void` — يقرأ الملف مرة واحدة فقط (`$loaded` static). يتجاهل
  الأسطر الفارغة و`#`. يزيل علامات الاقتباس المحيطة بالقيمة.
- `get(string $key, ?default): ?string` — يرجع من الملف أو من `getenv()` كبديل.
- `bool(string $key, bool $default): bool` — `1/true/yes/on` (بأي حالة أحرف) = صح.
- `int(string $key, int $default): int` — يرجع الافتراضي إن لم تكن القيمة رقماً.

### `FileCache.php`
كاش بسيط على شكل ملفات JSON. اسم الملف = `sha1($key) . '.json'`.

- `__construct($directory, $ttlSeconds = 86400)` — ينشئ المجلد إن لم يوجد.
- `get(string $key): ?array` — يرجع `null` إن انتهت الصلاحية (ويحذف الملف)، أو لم
  يوجد أصلاً.
- `put(string $key, array $payload): void` — يكتب JSON بترميز عربي سليم
  (`JSON_UNESCAPED_UNICODE`).
- `flush(): int` — يحذف كل الملفات، يرجع العدد المحذوف. يستخدمه `bin/cache-clear.php`.

---

## `src/Local/` — التحقق المحلي، بلا شبكة إطلاقاً

### `Validator.php` (واجهة)
كل محرك تحقق يطبّقها. هذا هو ما يسمح بوجود محركين بدون أن تتغيّر بقية المشروع.

- `check(string $number, ?string $countryCode): LocalResult`
- `name(): string` — يظهر أسفل الواجهة (`builtin` أو `libphonenumber`).

### `LocalResult.php`
كائن نتيجة ثابت (readonly). لا منطق فيه، فقط حمل بيانات + تحويل لمصفوفة.

- `reject(string $e164, string $reason, $source): self` — مصنع ثابت لحالة الرفض.
- `toArray(): array` — للـ JSON.

### `BuiltInValidator.php`
المحرك الافتراضي. جدول 46 دولة `[مفتاح الاتصال، أقل طول وطني، أكبر طول وطني]`.
لا اعتماديات خارجية.

- `check()`: التسلسل الداخلي —
  1. `Digits::normalize` على المُدخَل.
  2. إن بدأ بصفر: يحتاج رمز دولة (صريح أو `$defaultCountryCode`) ليُترجَم إلى
     مفتاح دولي، وإلا رفض فوري.
  3. فحص الطول الكلي (8–15، حدود E.164).
  4. `matchCountry()` — يطابق أطول مفتاح أولاً (3 أرقام، ثم 2، ثم 1) حتى لا
     يُقرأ `971` كـ `97`.
  5. فحص طول الرقم الوطني مقابل حدود تلك الدولة تحديداً.
- `matchCountry(digits, hint): ?array` — خاصة. `SHARED_PREFIX_OWNER` تحل تعارض
  المفاتيح المشتركة (`1` بين أمريكا وكندا → تفترض أمريكا).
- `isoForPrefix(prefix): ?string` — خاصة، بحث خطي في `COUNTRIES`.

### `LibPhoneNumberValidator.php`
غلاف حول مكتبة Google. يُستخدم فقط إن كانت مثبّتة عبر Composer.

- `isAvailable(): bool` — ساكنة، تفحص `class_exists(PhoneNumberUtil::class)`.
- `check()` — يفوّض لـ `PhoneNumberUtil::parse()` و`isValidNumber()`. أدق من
  المدمج لأنه يعرف نطاقات المشغّلين الفعلية لا حدود الطول فقط.

### `ValidatorFactory.php`
نقطة القرار الوحيدة بين المحركين.

- `make(?defaultCountryCode): Validator` — `LibPhoneNumberValidator` إن توفرت،
  وإلا `BuiltInValidator`. يُستدعى مرة واحدة في `public/index.php` و`bin/*.php`.

---

## `src/Api/` — الطبقة الوحيدة التي "تعرف" Numverify

### `Client.php`
الصنف الأهم في المشروع. كل تفاصيل المزوّد محصورة هنا.

- `__construct(accessKey, useHttps, cache, transport, timeout, defaultCountryCode, autoRetry)`
  — `$transport` قابل للحقن: هذا وحده ما يجعل كل الاختبارات تعمل بلا إنترنت.
- `validate(number, ?countryCode): Result` — المنطق العام:
  1. تطبيع + رفض الفارغ.
  2. إن بدأ بصفر ولا يوجد رمز صريح: يستخدم `defaultCountryCode`.
  3. `lookup()` (خاصة) — يفحص الكاش، ثم يطلب إن لزم.
  4. إن فشل ولم يكن هناك رمز دولة أصلاً وطول الرقم ≤10: يعيد المحاولة مرة واحدة
     بـ `defaultCountryCode` (`shouldRetry()`).
- `lookup(number, countryCode): Result` (خاصة) — الكاش يُكتب **فقط عند
  `valid === true`**. هذا قرار مقصود: تخزين رقم فاشل يجمّد الخطأ 24 ساعة.
- `countries(): array` — نقطة `/countries` لدى المزوّد، مخزّنة كاملة بمفتاح ثابت.
- `lastUrl()` / `lastRawResponse()`: للتشخيص فقط. الرابط يُخزَّن بالمفتاح مُقنَّعاً
  `***` منذ لحظة بنائه في `request()`.
- `request(endpoint, params): array` (خاصة) — يبني الرابط، يستدعي `fetch()` أو
  `$transport`، يفكّ JSON، يمرّ على `guardAgainstApiError()`.
- `guardAgainstApiError(data): void` (خاصة) — يرمي `ApiException` إن
  `success === false` أو وُجد مفتاح `error`. **يعتمد على `error.type` النصي لا
  على `error.code` الرقمي** — لأن توثيق المزوّد يعرض أرقاماً متضاربة لنفس الخطأ.
- `fetch(url): string` (خاصة) — يفضّل cURL، ويسقط إلى `file_get_contents` إن لم
  يكن الامتداد مثبّتاً (`fetchWithStream`). هذا يجعل المشروع يعمل على أي إعداد PHP.
- `normalizeNumber()` / `normalizeCountryCode()` — تفويض بسيط لـ `Digits`.

### `Result.php`
كائن نتيجة المزوّد. مطابق تماماً لحقول رد Numverify + حقلين إضافيين
(`queriedNumber`, `countryCodeUsed`) لم يأتيا من المزوّد بل مما أُرسل فعلاً —
بدونهما يستحيل تفسير سبب الفشل.

- `fromArray(data, queriedNumber, countryCodeUsed, fromCache): self` — مصنع ثابت.
- `failureHints(): array` — منطق استدلالي: يقارن ما أُرسل بأنماط الفشل الشائعة
  (صفر بلا رمز، رمز مع صيغة دولية، طول غير منطقي) ويرجع رسائل عربية جاهزة.

### `ApiException.php`
- الخصائص العامة `type`, `apiCode`, `info` تُقرأ مباشرة في الاختبارات والتشخيص.
- `friendlyMessage(): string` — جدول `match()` يترجم كل `type` معروف إلى جملة
  عربية. `default` يرجع `info` الخام كحل أخير.

---

## `src/Lookup/` — التنسيق بين الطبقتين

### `PhoneLookup.php`
نقطة الدخول الموصى بها من أي كود يستخدم المشروع. تجسّد قاعدة "لا يصل المزوّد
إلا رقم عبر التحقق المحلي".

- `__construct(Validator $local, ?Client $api, bool $enrich = true)`
- `lookup(number, countryCode): LookupResult` — التسلسل:
  1. `$local->check()`. مرفوض → `LookupResult::rejectedLocally()` (توقف هنا).
  2. `$enrich` معطّل أو لا يوجد `$api` → `LookupResult::localOnly()`.
  3. يستدعي `$api->validate($local->e164)`. فشل (`ApiException`) → `localOnly()`
     مع سبب الفشل بدل رمي الخطأ — **لا تُفقَد المعرفة المحلية بسبب فشل المزوّد**.
  4. نجح → `LookupResult::enriched()`.
- `wouldHaveCost(number, countryCode): int` — `0` أو `1`، بلا تنفيذ فعلي. مفيد
  لعرض "هذا سيكلّفك طلباً" قبل الإرسال.

### `LookupResult.php`
النتيجة الموحّدة التي تراها الواجهة والـ JSON.

- ثلاثة مصانع ثابتة: `rejectedLocally()`, `localOnly()`, `enriched()` — كل حالة
  من حالات `PhoneLookup::lookup()` تقابل واحدة منها تماماً.
- `isValid(): bool` — محلي مقبول **و** (لا يوجد رد مزوّد **أو** المزوّد وافق).
- `requestsUsed(): int` — `1` فقط إن استُدعي المزوّد فعلاً ولم تكن النتيجة من
  الكاش. هذا هو الرقم الذي يهمّ فعلياً لتتبّع الحصة.
- `displayFields(): array` — يبني قائمة الحقول للعرض، كل حقل مع تسمية عربية/
  إنجليزية ومصدره ("محلي" أو "المزوّد"). يُصفّى الفارغ تلقائياً بـ `array_filter`.

---

## `src/Http/Controller.php`
موجّه + متحكم في ملف واحد. لا يعرف شيئاً عن Numverify — يكلّم `PhoneLookup` فقط.

- `handle(method, path, query, body): void` — التفرّع: `/api/validate` → JSON،
  POST → `page($body)`، غير ذلك (GET) → `page($query)`.
- `page(input): void` (خاصة) — يستدعي `lookup()` إن وُجد رقم، ثم `render('home', …)`.
- `json(query): void` (خاصة) — يرجع `422` إن كان الرقم فارغاً أو غير صالح، `200`
  غير ذلك.
- `render(view, data): void` (خاصة) — `extract()` ثم `require` لملف العرض. هذا
  هو الجسر إلى `views/home.php`.

---

## نقاط الدخول (لا تحوي منطقاً، فقط تركيب الأجزاء)

### `public/index.php`
يُشغَّل بـ `php -S localhost:8000 -t public`. يقرأ `.env`، يبني `ValidatorFactory`
ثم `Client` (أو `null` إن لم يوجد مفتاح) ثم `PhoneLookup` ثم `Controller`،
ويستدعي `handle()` بمعطيات `$_SERVER`/`$_GET`/`$_POST`.

### `api/index.php`
نفس الشيء لبيئة Vercel: يضبط `CACHE_DIR` إلى `/tmp` (نظام الملفات هناك للقراءة
فقط عدا `/tmp`) ثم `require` لـ `public/index.php` مباشرة — لا تكرار منطق.

### `src/autoload.php`
محمّل PSR-4 يدوي (`spl_autoload_register`). يجعل المشروع يعمل بلا
`composer install` إطلاقاً — تحسين متعمّد لتقليل الاحتكاك عند أول تشغيل.

---

## `bin/` — سطر الأوامر

### `check.php`
استعلام واحد من الطرفية. يبني نفس السلسلة (`ValidatorFactory` → `Client` →
`PhoneLookup`) ويطبع `toArray()` بصيغة JSON. **رمز الخروج**: `0` صالح، `2` غير
صالح — يصلح للاستخدام داخل سكربتات (`if php bin/check.php $n; then …`).

### `diagnose.php`
تشخيص بلا كاش وبلا إعادة محاولة، بلا `PhoneLookup` — يستدعي `Validator` ثم
`Client` مباشرة ليعرض كل خطوة على حدة: المُدخَل، بعد التطبيع، قرار الطبقة
المحلية، ثم (إن وُجد مفتاح) الرابط المُرسَل والرد الخام. يتوقف بأدب عند كل نقطة
لا يمكن تجاوزها (لا مفتاح، الإثراء معطّل، رقم مرفوض محلياً).

### `cache-clear.php`
سطر واحد فعلياً: `(new FileCache(...))->flush()`.

---

## `views/home.php`
عرض HTML خام (لا محرك قوالب). يستقبل عبر `extract()`: `$number`, `$countryCode`,
`$result` (كائن `LookupResult|null`), `$engine`. كل نص من المستخدم يمرّ عبر
دالة `$e()` المحلية (`htmlspecialchars`) لمنع XSS. يعرض `displayFields()` مع
وسم `<span class="src">` لكل مصدر، وسطر يوضّح `requestsUsed()`.

---

## `tests/` — كل واحد يثبت شيئاً محدداً

### `bootstrap.php`
يفضّل `vendor/autoload.php` (Composer) إن وُجد، وإلا يسقط لمحمّل المشروع
المدمج + محمّل صغير لمجلد `Tests\`.

### `BuiltInValidatorTest.php` (14 اختباراً)
كل قاعدة في `BuiltInValidator::check()` لها اختبار مقابل: التوسيع من صفر،
بادئة `00`، الأرقام العربية، أطوال حدّية (أقصر من 8، أطول من 15)، مفتاح غير
معروف، طول وطني خاطئ، أولوية أطول مفتاح، حل التعارض بالمفتاح المشترك.

### `ClientTest.php` (13 اختباراً)
يحقن `transport` مغلق (Closure) بدل شبكة حقيقية. يغطي: التطبيع قبل الإرسال،
رفع رمز الدولة لحروف كبيرة، تجاهل رمز غير صالح، الرمز الافتراضي وإعادة
المحاولة (مع تعطيلها)، ترجمة أخطاء المزوّد، إخفاء المفتاح في `lastUrl()`.

### `PhoneLookupTest.php` (11 اختباراً) — الأهم في المشروع
كل اختبار يعدّ استدعاءات `$this->apiCalls` ليثبت **رقمياً** أن الرقم المرفوض
محلياً لم يكلّف شيئاً. أيضاً: فشل المزوّد لا يمسح المعرفة المحلية، تعطيل
الإثراء كلياً، العمل بلا `Client` مطلقاً.

---

## قواعد لا تُكسَر عند التعديل لاحقاً

1. **لا تستدعِ `Client` مباشرة من الواجهة أو `bin/`.** مرّ دائماً عبر
   `PhoneLookup` — هذا هو ضامن "لا رقم مرفوض يصل المزوّد".
2. **لا تُخزِّن نتيجة `valid: false` في الكاش.** أُصلح هذا الخطأ مرة، وسبب
   عودته سهل: نسيان الشرط في `Client::lookup()`.
3. **أي حقل جديد من المزوّد يُضاف في `Result::fromArray()` فقط.** لا تقرأ
   مصفوفة الرد الخام من مكان آخر.
4. **أي محرك تحقق محلي جديد ينفّذ `Validator` ويُسجَّل في `ValidatorFactory`
   فقط.** لا تُغيّر `PhoneLookup` أو `Controller`.
5. **الاعتماد في معالجة الأخطاء على `error.type` النصي، لا `error.code`
   الرقمي** — راجع التعليق في `guardAgainstApiError()` إن نسيت السبب.
