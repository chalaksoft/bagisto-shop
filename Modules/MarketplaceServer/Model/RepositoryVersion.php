<?php

namespace Modules\MarketplaceServer\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Marketplace\Services\VersionConstraint;

class RepositoryVersion extends Model
{
    protected $table = 'marketplace_module_versions';

    protected $fillable = [
        'marketplace_module_id',
        'version',
        'archive_path',
        'archive_size',
        'checksum',
        'signature',
        'requires_bagisto',
        'requires_php',
        'requires',
        'changelog',
        'released_at',
    ];

    protected $casts = [
        'requires'     => 'array',
        'released_at'  => 'datetime',
        'archive_size' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(RepositoryModule::class, 'marketplace_module_id');
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * سازگاری با نسخهٔ بجیستو و PHP فروشگاه مقصد.
     *
     * قید با همان کلاسی سنجیده می‌شود که کلاینت موقع نصب استفاده می‌کند
     * (`Modules\Marketplace\Services\VersionConstraint`)؛ دو پیاده‌سازی جدا یعنی
     * بسته‌ای که مخزن «سازگار» می‌داند ممکن است سر نصب رد شود.
     *
     * وقتی فروشگاه نسخه‌ای اعلام نکرده، قید نادیده گرفته می‌شود: بهتر است فهرست
     * کامل برگردد و ناسازگاری سر نصب گرفته شود تا اینکه ماژول بی‌دلیل غیب شود.
     */
    public function isCompatibleWith(?string $bagisto, ?string $php): bool
    {
        if ($bagisto && ! VersionConstraint::satisfies($bagisto, $this->requires_bagisto)) {
            return false;
        }

        if ($php && ! VersionConstraint::satisfies($php, $this->requires_php)) {
            return false;
        }

        return true;
    }

    public function absolutePath(): string
    {
        return storage_path('app/'.$this->archive_path);
    }
}
