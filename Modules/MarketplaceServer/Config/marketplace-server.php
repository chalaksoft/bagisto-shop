<?php

return [
    /**
     * مسیر کلید خصوصی امضا.
     *
     * ⚠️ این فایل نباید در مخزن کد باشد. اگر لو برود، هر کسی می‌تواند بسته‌ای
     * بسازد که همهٔ فروشگاه‌های مشتری بی‌چون‌وچرا نصبش می‌کنند.
     *
     * پیش‌فرض بیرون از `storage/app/public` است تا حتی با اشتباهِ `storage:link`
     * هم از وب قابل دانلود نباشد.
     */
    'private_key' => env('MARKETPLACE_PRIVATE_KEY') ?: storage_path('keys/repository.key'),

    'public_key' => env('MARKETPLACE_PUBLIC_KEY') ?: storage_path('keys/repository.pub'),

    /** رمز کلید خصوصی، اگر با passphrase ساخته شده باشد */
    'private_key_passphrase' => env('MARKETPLACE_PRIVATE_KEY_PASSPHRASE'),

    /** محل نگهداری بسته‌ها، نسبت به `storage/app` */
    'packages_path' => 'marketplace/packages',

    /** بیشینهٔ حجم بستهٔ آپلودی در پنل (کیلوبایت) */
    'max_upload_size' => (int) env('MARKETPLACE_SERVER_MAX_UPLOAD', 61440),

    /**
     * دانلود بدون توکن برای ماژول‌های رایگان.
     *
     * خاموشش کنید اگر می‌خواهید حتی ماژول رایگان هم فقط با لایسنس دانلود شود
     * (مثلاً برای اینکه بدانید چه کسی چه چیزی نصب کرده).
     */
    'anonymous_free_downloads' => (bool) env('MARKETPLACE_ANONYMOUS_FREE', true),
];
