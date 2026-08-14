<?php

namespace Modules\MarketplaceServer\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ماژولی که این فروشگاه منتشر می‌کند.
 *
 * نامش `RepositoryModule` است نه `Module`، تا با `Modules\Marketplace\Model\InstalledModule`
 * (ماژول‌هایی که همین فروشگاه نصب کرده) اشتباه نشود. این دو مفهوم متفاوت‌اند و
 * یک فروشگاه می‌تواند هر دو را داشته باشد.
 */
class RepositoryModule extends Model
{
    protected $table = 'marketplace_modules';

    protected $fillable = [
        'slug',
        'package_name',
        'name',
        'description',
        'icon',
        'category',
        'published',
        'free',
        'product_id',
    ];

    protected $casts = [
        'published' => 'boolean',
        'free'      => 'boolean',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(RepositoryVersion::class, 'marketplace_module_id')->orderByDesc('id');
    }

    public function releasedVersions(): HasMany
    {
        return $this->versions()->whereNotNull('released_at');
    }

    /**
     * جدیدترین نسخهٔ منتشرشده‌ای که با فروشگاه مقصد سازگار است.
     *
     * «جدیدترین» با `version_compare` تعیین می‌شود نه با تاریخ انتشار: انتشار
     * وصلهٔ ۱.۲.۴ بعد از ۱.۳.۰ اتفاق طبیعی است و نباید ۱.۳.۰ را عقب بزند.
     */
    public function latestCompatible(?string $bagisto = null, ?string $php = null): ?RepositoryVersion
    {
        return $this->releasedVersions
            ->filter(fn (RepositoryVersion $version) => $version->isCompatibleWith($bagisto, $php))
            ->sort(fn ($a, $b) => version_compare($a->version, $b->version))
            ->last();
    }

    /**
     * آیا این لایسنس اجازهٔ دانلود این ماژول را دارد؟
     */
    public function isAvailableTo(?License $license): bool
    {
        if ($this->free) {
            return true;
        }

        return (bool) $license?->covers($this->slug);
    }
}
