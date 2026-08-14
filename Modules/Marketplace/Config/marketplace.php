<?php

return [
    /**
     * تنها مبدأ مجاز برای دانلود بسته‌ها. عمداً از env خوانده می‌شود و در پنل
     * قابل ویرایش نیست: اگر ادمین بتواند URL دلخواه وارد کند، این قابلیت به
     * «هر کد PHP دلخواهی را اجرا کن» تبدیل می‌شود.
     */
    'repository_url' => env('MARKETPLACE_URL'),

    /**
     * پیشوند API مخزن.
     *
     * مخزن یک ماژول داخل خود بجیستوست (`MarketplaceServer`)، پس روت‌هایش زیر
     * `/api/marketplace` می‌نشینند تا با API خود فروشگاه قاطی نشوند.
     */
    'api_prefix' => env('MARKETPLACE_API_PREFIX', '/api/marketplace'),

    /** توکن لایسنس که در هدر Authorization به مخزن فرستاده می‌شود */
    'token' => env('MARKETPLACE_TOKEN'),

    /**
     * آپلود دستی zip از پنل.
     *
     * رایج‌ترین راه هک‌شدن سایت‌های وردپرسی همین است، پس علاوه بر ACL جداگانهٔ
     * `marketplace.install` با یک سوییچ سراسری هم قابل بستن است. تا وقتی سایت
     * مخزن راه نیفتاده، تنها راه نصب همین است.
     */
    'allow_upload' => (bool) env('MARKETPLACE_ALLOW_UPLOAD', true),

    /**
     * بستهٔ بدون امضای معتبر نصب نشود.
     *
     * برای بسته‌های آمده از مخزن همیشه اجباری است. برای zip آپلودشده از پنل
     * می‌شود موقتاً خاموشش کرد (فاز ۲، پیش از راه‌افتادن مخزن)، ولی روی
     * پروداکشن باید روشن باشد.
     */
    'require_signature' => [
        'repository' => true,
        'manual'     => (bool) env('MARKETPLACE_REQUIRE_SIGNATURE', false),
    ],

    /** کلید عمومی مخزن؛ کلید خصوصی هرگز نباید در این ریپو باشد */
    'public_key' => __DIR__.'/../resources/keys/repository.pub',

    /** بیشینهٔ حجم بستهٔ آپلودی (کیلوبایت) */
    'max_upload_size' => (int) env('MARKETPLACE_MAX_UPLOAD', 61440),

    /**
     * ماژول‌هایی که از پنل نه غیرفعال می‌شوند نه حذف — غیرفعال‌کردنشان یعنی
     * بستن همین صفحه روی خودمان.
     */
    'protected_modules' => ['Marketplace'],

    /**
     * ماژول‌هایی که فایل‌هایشان مال این پروژه نیست (هستهٔ مشترک، `chmod a-w`).
     * غیرفعال‌کردن آزاد است، حذف نه.
     *
     * @see Modules/README.md
     */
    'locked_modules' => ['Elementor', 'Blog'],
];
