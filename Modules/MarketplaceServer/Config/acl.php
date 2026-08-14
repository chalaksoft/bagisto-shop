<?php

/**
 * دسترسی‌ها ریزدانه‌اند: دیدن فهرست بی‌خطر است، ولی «انتشار نسخه» یعنی کدی که
 * روی فروشگاه‌های مشتری اجرا می‌شود، و «صدور لایسنس» یعنی دسترسی فروش.
 */
return [
    [
        'key'   => 'marketplace-server',
        'name'  => 'انتشار ماژول',
        'route' => 'admin.marketplace_server.modules.index',
        'sort'  => 23,
    ], [
        'key'   => 'marketplace-server.modules',
        'name'  => 'ماژول‌های منتشرشده',
        'route' => 'admin.marketplace_server.modules.index',
        'sort'  => 1,
    ], [
        'key'   => 'marketplace-server.modules.create',
        'name'  => 'admin::app.acl.create',
        'route' => 'admin.marketplace_server.modules.store',
        'sort'  => 1,
    ], [
        'key'   => 'marketplace-server.modules.edit',
        'name'  => 'admin::app.acl.edit',
        'route' => 'admin.marketplace_server.modules.update',
        'sort'  => 2,
    ], [
        'key'   => 'marketplace-server.modules.delete',
        'name'  => 'admin::app.acl.delete',
        'route' => 'admin.marketplace_server.modules.delete',
        'sort'  => 3,
    ], [
        'key'   => 'marketplace-server.versions.publish',
        'name'  => 'انتشار نسخهٔ تازه',
        'route' => 'admin.marketplace_server.versions.store',
        'sort'  => 4,
    ], [
        'key'   => 'marketplace-server.licenses',
        'name'  => 'لایسنس‌ها',
        'route' => 'admin.marketplace_server.licenses.index',
        'sort'  => 2,
    ], [
        'key'   => 'marketplace-server.licenses.create',
        'name'  => 'صدور لایسنس',
        'route' => 'admin.marketplace_server.licenses.store',
        'sort'  => 1,
    ], [
        'key'   => 'marketplace-server.licenses.edit',
        'name'  => 'admin::app.acl.edit',
        'route' => 'admin.marketplace_server.licenses.update',
        'sort'  => 2,
    ], [
        'key'   => 'marketplace-server.logs',
        'name'  => 'دانلودها',
        'route' => 'admin.marketplace_server.logs.index',
        'sort'  => 3,
    ],
];
