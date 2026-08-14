<?php

/**
 * دسترسی‌ها عمداً ریزدانه‌اند: دیدن فهرست ماژول‌ها بی‌خطر است، ولی نصب یعنی
 * «اجرای کد PHP تازه روی سرور» و حذف یعنی «رفتن داده‌ها». این سه نباید یک
 * مجوز مشترک داشته باشند.
 */
return [
    [
        'key'   => 'marketplace',
        'name'  => 'ماژول‌ها',
        'route' => 'admin.marketplace.index',
        'sort'  => 22,
    ], [
        'key'   => 'marketplace.repository',
        'name'  => 'دیدن مخزن',
        'route' => 'admin.marketplace.repository',
        'sort'  => 1,
    ], [
        'key'   => 'marketplace.install',
        'name'  => 'نصب و به‌روزرسانی',
        'route' => 'admin.marketplace.install',
        'sort'  => 2,
    ], [
        'key'   => 'marketplace.toggle',
        'name'  => 'فعال و غیرفعال کردن',
        'route' => 'admin.marketplace.toggle',
        'sort'  => 2,
    ], [
        'key'   => 'marketplace.remove',
        'name'  => 'حذف ماژول',
        'route' => 'admin.marketplace.remove',
        'sort'  => 3,
    ],
];
