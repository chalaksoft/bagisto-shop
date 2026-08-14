<?php

namespace Modules\Marketplace\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * یک اجرای نصب/به‌روزرسانی/حذف — هم لاگ ممیزی، هم وضعیت ادامه‌پذیر.
 */
class ModuleInstallation extends Model
{
    protected $table = 'module_installations';

    protected $fillable = [
        'module',
        'version',
        'source',
        'action',
        'step',
        'status',
        'payload',
        'error',
        'admin_id',
        'admin_name',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    /**
     * خواندن یک کلید از payload — که همیشه آرایه است، حتی وقتی null ذخیره شده.
     */
    public function payload(string $key, $default = null)
    {
        return data_get($this->payload ?? [], $key, $default);
    }

    /**
     * نوشتن چند کلید در payload بدون از دست دادن بقیه.
     */
    public function mergePayload(array $values): void
    {
        $this->payload = array_merge($this->payload ?? [], $values);
    }
}
