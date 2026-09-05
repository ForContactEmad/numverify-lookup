# مرجع الواجهة والأصناف

## نقطة JSON

```
GET /api/validate?number=<الرقم>&country_code=<رمز اختياري>
```

| المعامل | مطلوب | الوصف |
|---|---|---|
| `number` | نعم | الرقم بأي صيغة — يُطبَّع تلقائياً |
| `country_code` | لا | رمزان بحسب ISO مثل `SA`، للأرقام المحلية |

### رد ناجح

```json
{
  "success": true,
  "data": {
    "valid": true,
    "local": {
      "plausible": true,
      "e164": "966501234567",
      "country_code": "SA",
      "calling_code": "966",
      "national_number": "501234567",
      "reason": null,
      "source": "builtin"
    },
    "remote": {
      "valid": true,
      "carrier": "STC",
      "line_type": "mobile",
      "location": "Riyadh",
      "from_cache": false
    },
    "api_called": true,
    "api_skipped_reason": null,
    "requests_used": 1
  }
}
```

### رد رفض محلي

رمز الحالة `422`، و`requests_used` تساوي صفراً:

```json
{
  "success": true,
  "data": {
    "valid": false,
    "local": {
      "plausible": false,
      "e164": "12345",
      "reason": "الرقم أقصر من الحد الأدنى (8 خانات).",
      "source": "builtin"
    },
    "remote": null,
    "api_called": false,
    "api_skipped_reason": "رُفض محلياً قبل إرسال أي طلب.",
    "requests_used": 0
  }
}
```

`success` تصف نجاح **العملية** لا صحة الرقم. صحة الرقم في `data.valid`.

### رموز الحالة

| الرمز | المعنى |
|---|---|
| `200` | الرقم صالح |
| `422` | الرقم غير صالح، أو معامل `number` مفقود |

## الأصناف العامة

### `Lookup\PhoneLookup`

نقطة الدخول الموصى بها.

```php
$lookup = new PhoneLookup(
    local: ValidatorFactory::make('SA'),
    api: $client,        // اختياري — بدونه تحقق محلي فقط
    enrich: true,        // false لتعطيل الاستدعاء الخارجي كلياً
);

$result = $lookup->lookup('0501234567');
$cost   = $lookup->wouldHaveCost('0501234567'); // 0 أو 1 قبل التنفيذ
```

### `Lookup\LookupResult`

| العضو | النوع | الوصف |
|---|---|---|
| `isValid()` | `bool` | عبر التحقق المحلي ولم يكذّبه المزوّد |
| `requestsUsed()` | `int` | ما استُهلك فعلاً من الحصة |
| `apiCalled` | `bool` | هل استُدعي المزوّد |
| `apiSkippedReason` | `?string` | سبب عدم الاستدعاء أو سبب فشله |
| `local` | `LocalResult` | نتيجة الطبقة المحلية |
| `remote` | `?Result` | نتيجة المزوّد إن وُجدت |
| `displayFields()` | `array` | حقول العرض مع مصدر كل حقل |
| `toArray()` | `array` | التمثيل الكامل بصيغة JSON |

### `Local\Validator`

```php
interface Validator
{
    public function check(string $number, ?string $countryCode = null): LocalResult;
    public function name(): string;
}
```

لإضافة محرك تحقق ثالث: نفّذ هذه الواجهة، وأضفه في `ValidatorFactory`. لن تتغيّر
أي طبقة أخرى.

### `Local\LocalResult`

| العضو | الوصف |
|---|---|
| `plausible` | هل يستحق الرقم استهلاك طلب |
| `e164` | الرقم بعد التطبيع والتوسيع، بلا `+` |
| `countryCode` | رمز ISO المستنتج |
| `callingCode` | مفتاح الاتصال |
| `nationalNumber` | الرقم بعد إزالة المفتاح |
| `reason` | سبب الرفض، أو `null` عند القبول |
| `source` | `builtin` أو `libphonenumber` |

### `Api\Client`

```php
$client = new Client(
    accessKey: '...',
    useHttps: false,           // true للخطط المدفوعة فقط
    cache: $fileCache,
    transport: null,           // دالة بديلة للاختبار بلا إنترنت
    timeout: 10,
    defaultCountryCode: 'SA',
    autoRetry: true,
);
```

`transport` هو ما يجعل كل اختبارات المشروع تعمل بلا شبكة وبصفر استهلاك:

```php
$client = new Client('key', transport: fn (string $url): string => '{"valid":true}');
```

### `Api\ApiException`

| العضو | الوصف |
|---|---|
| `type` | النص التقني من المزوّد مثل `invalid_access_key` |
| `apiCode` | الرمز الرقمي كما ورد |
| `friendlyMessage()` | رسالة عربية مفهومة للمستخدم |

المعالجة تعتمد على `type` لا على `apiCode`، لأن توثيق المزوّد يعرض رقمين مختلفين
لنفس الخطأ (`101` مقابل `403`) والنص هو الثابت الوحيد.

### أخطاء المزوّد المعروفة

| `type` | المعنى |
|---|---|
| `invalid_access_key` | مفتاح خاطئ أو مفقود |
| `inactive_user` | الحساب غير مفعّل |
| `usage_limit_reached` | نفدت الحصة الشهرية |
| `https_access_restricted` | HTTPS غير متاح في الخطة المجانية |
| `invalid_country_code` | رمز دولة غير صحيح |
| `network_error` | تعذّر الاتصال (من جانبنا لا من المزوّد) |

## سطر الأوامر

```bash
php bin/check.php <number> [country_code]   # استعلام، يخرج بالرمز 0 أو 2
php bin/diagnose.php <number> [cc]          # الطلب والرد الخام بلا كاش
php bin/cache-clear.php                     # حذف الكاش
```

`bin/check.php` يعيد رمز خروج `0` للرقم الصالح و`2` لغير الصالح، فيصلح للاستخدام
داخل سكربتات.
