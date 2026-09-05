# البنية والمخططات

## المخطط العام

```mermaid
flowchart TD
    A["المُدخَل: رقم نصي"] --> B["Support/Digits<br/>تطبيع الخانات"]
    B --> C{"Local/Validator<br/>تحقق محلي"}
    C -->|"مرفوض"| D["نتيجة فورية<br/>صفر طلبات"]
    C -->|"مقبول"| E{"هل الإثراء مفعّل<br/>ويوجد مفتاح؟"}
    E -->|"لا"| F["نتيجة محلية فقط<br/>صفر طلبات"]
    E -->|"نعم"| G{"Support/FileCache"}
    G -->|"إصابة"| H["نتيجة مخزّنة<br/>صفر طلبات"]
    G -->|"فقدان"| I["Api/Client<br/>استدعاء Numverify"]
    I -->|"نجاح"| J["نتيجة مُثراة<br/>طلب واحد"]
    I -->|"فشل"| K["نتيجة محلية<br/>مع سبب التعذّر"]
```

النقطة الجوهرية في المخطط: ثلاثة من خمسة مسارات خروج تكلّف **صفر طلبات**.

## طبقات المشروع

```mermaid
flowchart TD
    subgraph entry["منافذ الدخول"]
        W["public/index.php<br/>الويب"]
        C1["bin/check.php<br/>سطر الأوامر"]
        D1["bin/diagnose.php<br/>التشخيص"]
    end

    subgraph orchestration["طبقة التنسيق"]
        H["Http/Controller"]
        P["Lookup/PhoneLookup"]
        R["Lookup/LookupResult"]
    end

    subgraph local["الطبقة المحلية"]
        V["Local/Validator<br/>واجهة"]
        B["Local/BuiltInValidator"]
        L["Local/LibPhoneNumberValidator"]
        FA["Local/ValidatorFactory"]
    end

    subgraph remote["الطبقة الخارجية"]
        CL["Api/Client"]
        RES["Api/Result"]
        EX["Api/ApiException"]
    end

    subgraph support["الدعم"]
        DG["Support/Digits"]
        EN["Support/Env"]
        FC["Support/FileCache"]
    end

    W --> H
    H --> P
    C1 --> P
    D1 --> V
    P --> V
    P --> CL
    P --> R
    FA --> B
    FA --> L
    B -.->|"ينفّذ"| V
    L -.->|"ينفّذ"| V
    B --> DG
    CL --> DG
    CL --> FC
    CL --> RES
    CL --> EX
```

## قواعد الاعتماد

هذه القواعد هي ما يجعل المشروع قابلاً للاختبار بلا إنترنت:

| الطبقة | تعرف عن | لا تعرف عن |
|---|---|---|
| `Local/` | التطبيع وخطط الترقيم | الشبكة، Numverify، HTTP |
| `Api/` | Numverify وحدها | HTML، الواجهة، التحقق المحلي |
| `Lookup/` | الطبقتين معاً | HTTP، طريقة العرض |
| `Http/` | `Lookup/` فقط | Numverify، تفاصيل التحقق |

السهم يتجه دائماً من الأعلى إلى الأسفل. لا تعرف طبقة سفلى شيئاً عمّن يستدعيها.

## قرار صرف الطلب

```mermaid
flowchart TD
    S["استُلم رقم"] --> N["تطبيع: إزالة الرموز<br/>وبادئة 00 وتحويل الخانات العربية"]
    N --> E{"فارغ؟"}
    E -->|"نعم"| X1["رفض: لم يُدخَل رقم"]
    E -->|"لا"| Z{"يبدأ بصفر؟"}
    Z -->|"نعم"| CC{"يوجد رمز دولة<br/>صريح أو افتراضي؟"}
    CC -->|"لا"| X2["رفض: صيغة محلية<br/>بلا رمز دولة"]
    CC -->|"نعم"| EXP["استبدال الصفر<br/>بمفتاح الدولة"]
    Z -->|"لا"| LEN
    EXP --> LEN{"الطول بين 8 و15؟"}
    LEN -->|"لا"| X3["رفض: خارج حدود E.164"]
    LEN -->|"نعم"| PFX{"مفتاح دولة معروف؟<br/>مطابقة أطول أولاً"}
    PFX -->|"لا"| X4["رفض: مفتاح غير معروف"]
    PFX -->|"نعم"| NAT{"طول الرقم الوطني<br/>صحيح لهذه الدولة؟"}
    NAT -->|"لا"| X5["رفض: طول وطني خاطئ"]
    NAT -->|"نعم"| OK["مقبول: يستحق طلباً"]
```

كل مسار رفض ينتهي برسالة محددة تشرح السبب، لا برسالة عامة واحدة.

## تسلسل استعلام كامل

```mermaid
sequenceDiagram
    participant U as المستخدم
    participant C as Controller
    participant P as PhoneLookup
    participant V as Validator
    participant K as FileCache
    participant A as Numverify

    U->>C: 0501234567
    C->>P: lookup
    P->>V: check
    V-->>P: مقبول، 966501234567، SA
    P->>A: validate عبر Client
    A->>K: هل النتيجة مخزّنة؟
    K-->>A: لا
    A->>A: طلب HTTP فعلي
    A->>K: خزّن النتيجة الصالحة
    A-->>P: المشغّل والموقع ونوع الخط
    P-->>C: LookupResult
    C-->>U: نتيجة مع مصدر كل حقل
```

## اختيار المحرك المحلي

```mermaid
flowchart LR
    F["ValidatorFactory::make"] --> Q{"هل libphonenumber<br/>مثبّتة؟"}
    Q -->|"نعم"| L["LibPhoneNumberValidator<br/>دقة أعلى"]
    Q -->|"لا"| B["BuiltInValidator<br/>صفر اعتماديات"]
```

القرار يحدث مرة واحدة عند الإقلاع، ولا تعرف بقية الطبقات أي محرك يعمل — تتعامل
مع واجهة `Validator` وحدها. اسم المحرك يظهر أسفل الواجهة لتتأكد بنفسك.

## شجرة الملفات

```
src/
  Local/                       الطبقة المحلية
    Validator.php              الواجهة المشتركة
    BuiltInValidator.php       جدول 46 دولة، بلا اعتماديات
    LibPhoneNumberValidator.php محوّل لمكتبة Google
    ValidatorFactory.php       يختار الأدق المتاح
    LocalResult.php            نتيجة التحقق المحلي
  Api/                         الطبقة الخارجية
    Client.php                 الصنف الوحيد الذي يعرف Numverify
    Result.php                 كائن نتيجة ثابت
    ApiException.php           أخطاء المزوّد مترجمة
  Lookup/
    PhoneLookup.php            قرار صرف الطلب
    LookupResult.php           نتيجة موحّدة للطبقتين
  Http/
    Controller.php             توجيه: صفحة + نقطة JSON
  Support/
    Digits.php                 التطبيع المشترك
    Env.php                    قراءة الإعدادات
    FileCache.php              كاش ملفات
```
