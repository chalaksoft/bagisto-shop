<?php

return [
    [
        'key'   => 'marketplace-server',
        'name'  => 'انتشار ماژول',
        'route' => 'admin.marketplace_server.modules.index',
        'sort'  => 11,
        'icon'  => 'icon-settings',
    ], [
        'key'   => 'marketplace-server.modules',
        'name'  => 'ماژول‌های منتشرشده',
        'route' => 'admin.marketplace_server.modules.index',
        'sort'  => 1,
        'icon'  => '',
    ], [
        'key'   => 'marketplace-server.licenses',
        'name'  => 'لایسنس‌ها',
        'route' => 'admin.marketplace_server.licenses.index',
        'sort'  => 2,
        'icon'  => '',
    ], [
        'key'   => 'marketplace-server.logs',
        'name'  => 'دانلودها',
        'route' => 'admin.marketplace_server.logs.index',
        'sort'  => 3,
        'icon'  => '',
    ],
];
