<?php

namespace Modules\Marketplace\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * وضعیت یک ماژول در جدول `installed_modules`.
 *
 * جدولش عمداً در `database/migrations` ریشهٔ پروژه است نه این ماژول:
 * `App\Classes\ModuleRegistry` که در `bootstrap/providers.php` صدا زده می‌شود
 * به آن نگاه می‌کند، پس باید حتی وقتی Marketplace غیرفعال است هم وجود داشته باشد.
 */
class InstalledModule extends Model
{
    protected $table = 'installed_modules';

    protected $fillable = [
        'name',
        'version',
        'enabled',
        'source',
        'meta',
        'installed_at',
    ];

    protected $casts = [
        'enabled'      => 'boolean',
        'meta'         => 'array',
        'installed_at' => 'datetime',
    ];
}
