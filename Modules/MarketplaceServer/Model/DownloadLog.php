<?php

namespace Modules\MarketplaceServer\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadLog extends Model
{
    protected $table = 'marketplace_download_logs';

    protected $fillable = [
        'marketplace_license_id',
        'marketplace_module_version_id',
        'module_slug',
        'domain',
        'ip',
        'allowed',
        'reason',
    ];

    protected $casts = ['allowed' => 'boolean'];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'marketplace_license_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RepositoryVersion::class, 'marketplace_module_version_id');
    }
}
