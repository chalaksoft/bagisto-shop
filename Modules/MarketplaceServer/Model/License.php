<?php

namespace Modules\MarketplaceServer\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Webkul\Customer\Models\Customer;

class License extends Model
{
    protected $table = 'marketplace_licenses';

    protected $fillable = [
        'customer_id',
        'order_id',
        'token_hash',
        'token_hint',
        'label',
        'domains',
        'module_slugs',
        'expires_at',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'domains'      => 'array',
        'module_slugs' => 'array',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
        'active'       => 'boolean',
    ];

    /** خریدار همان مشتری خود بجیستوست */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /* ---------------------------------------------------------------------
     | توکن
     * -------------------------------------------------------------------*/

    /**
     * ساخت توکن خام. فقط همین یک بار قابل دیدن است؛ بعدش فقط هشش را داریم.
     */
    public static function newToken(): string
    {
        return 'mkt_'.Str::random(48);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * لایسنس متناظر با یک توکن خام.
     *
     * جست‌وجو روی هش انجام می‌شود، پس یک ایندکس یکتا کافی است و نیازی به
     * پیمایش همهٔ ردیف‌ها نیست.
     */
    public static function findByToken(?string $token): ?self
    {
        if (blank($token)) {
            return null;
        }

        return static::query()->where('token_hash', static::hash($token))->first();
    }

    /* ---------------------------------------------------------------------
     | اعتبارسنجی
     * -------------------------------------------------------------------*/

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * آیا این دامنه مجاز است؟
     *
     * مقایسه روی نامِ نرمال‌شده انجام می‌شود (بدون پروتکل، بدون `www.`، بدون
     * پورت) وگرنه یک `https://` اضافه در تنظیمات فروشگاه، لایسنس درست را رد
     * می‌کند. زیردامنه عمداً مجاز **نیست**: `shop.example.com` باید صریحاً ثبت شود.
     */
    public function allowsDomain(?string $domain): bool
    {
        $domain = static::normalizeDomain($domain);

        if (blank($domain)) {
            return false;
        }

        foreach ((array) $this->domains as $allowed) {
            if (static::normalizeDomain($allowed) === $domain) {
                return true;
            }
        }

        return false;
    }

    /** فهرست خالی یعنی «همهٔ ماژول‌ها» */
    public function covers(string $slug): bool
    {
        return blank($this->module_slugs) || in_array($slug, (array) $this->module_slugs, true);
    }

    /**
     * دلیل ردشدن، یا null وقتی لایسنس برای این دامنه معتبر است.
     */
    public function rejectionReason(?string $domain): ?string
    {
        return match (true) {
            ! $this->active                => 'لایسنس غیرفعال شده است.',
            $this->isExpired()             => 'اعتبار لایسنس تمام شده است.',
            ! $this->allowsDomain($domain) => 'این دامنه در لایسنس ثبت نشده است.',
            default                        => null,
        };
    }

    public static function normalizeDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));

        $domain = preg_replace('#^https?://#', '', $domain);

        $domain = explode('/', $domain)[0];

        $domain = explode(':', $domain)[0];

        return preg_replace('#^www\.#', '', $domain);
    }
}
