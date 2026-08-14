<?php

namespace Modules\Marketplace\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * توکن لایسنسِ گرفته‌شده از مخزن با ثبت‌نام از پنل.
 *
 * @see \Modules\Marketplace\Services\RepositoryClient::token()
 */
class RepositoryCredential extends Model
{
    protected $table = 'repository_credentials';

    protected $fillable = [
        'token',
        'domain',
        'email',
        'customer_name',
        'label',
        'expires_at',
        'registered_at',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'registered_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    /**
     * آخرین ثبت‌نام — همان توکنی که استفاده می‌شود.
     *
     * جدول ممکن است اصلاً وجود نداشته باشد (نصبی که هنوز migrate نشده)، و
     * صفحهٔ «ماژول‌های نصب‌شده» نباید به این خاطر ۵۰۰ بدهد.
     */
    public static function current(): ?self
    {
        try {
            return static::query()->latest('id')->first();
        } catch (\Throwable) {
            return null;
        }
    }
}
