<?php

namespace Modules\MarketplaceServer\Services;

use Illuminate\Support\Facades\File;
use Modules\Marketplace\Services\Package;
use Modules\Marketplace\Services\PackageException;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Modules\MarketplaceServer\Model\RepositoryVersion;

/**
 * افزودن یک نسخهٔ تازه به یک ماژول: بررسی بسته، ذخیره، چکسام و امضا.
 *
 * اعتبارسنجی بسته با **همان کلاسی** انجام می‌شود که خریدار موقع نصب استفاده
 * می‌کند (`Modules\Marketplace\Services\Package`). چون مخزن و فروشگاه یک اپ‌اند،
 * دیگر دو پیاده‌سازی جدا از قواعد بسته وجود ندارد — یعنی محال است چیزی اینجا
 * منتشر شود که سر نصب رد شود.
 *
 * هم پنل و هم `marketplace:publish` از همین مسیر می‌گذرند؛ منطق دومی نیست.
 */
class VersionPublisher
{
    public function __construct(protected PackageSigner $signer) {}

    /**
     * @param  string  $archive  مسیر فایل zip روی دیسک (آپلودشده یا محلی)
     * @param  array{changelog?: string, release?: bool}  $options
     */
    public function publish(RepositoryModule $module, string $archive, array $options = []): RepositoryVersion
    {
        $package = Package::open($archive);

        /**
         * نام پوشهٔ داخل zip باید با نام ماژول در مخزن یکی باشد، وگرنه بسته روی
         * فروشگاه خریدار در پوشهٔ دیگری باز می‌شود و ماژول اشتباهی را بازنویسی
         * می‌کند.
         */
        if ($package->name() !== $module->package_name) {
            throw new PackageException(sprintf(
                'بسته پوشهٔ «%s» دارد ولی این ماژول در مخزن «%s» است.',
                $package->name(),
                $module->package_name
            ));
        }

        if (! $package->hasEntry('ModuleServiceProvider.php')) {
            throw new PackageException('بسته ناقص است: فایل ModuleServiceProvider.php داخلش نیست.');
        }

        $manifest = $package->manifest();

        $version = (string) ($manifest['version'] ?? '');

        if (blank($version)) {
            throw new PackageException('فیلد version در module.json خالی است.');
        }

        if ($module->versions()->where('version', $version)->exists()) {
            throw new PackageException("نسخهٔ $version از این ماژول قبلاً منتشر شده است.");
        }

        $stored = $this->store($module, $version, $archive);

        return $module->versions()->create([
            'version'          => $version,
            'archive_path'     => $stored,
            'archive_size'     => filesize(storage_path('app/'.$stored)),
            'checksum'         => hash_file('sha256', storage_path('app/'.$stored)),
            'signature'        => $this->signer->sign(storage_path('app/'.$stored)),
            'requires_bagisto' => $manifest['requires_bagisto'] ?? null,
            'requires_php'     => $manifest['requires_php'] ?? null,
            'requires'         => $manifest['requires'] ?? [],
            'changelog'        => $options['changelog'] ?? null,
            'released_at'      => ($options['release'] ?? true) ? now() : null,
        ]);
    }

    /**
     * انتشار یا برگرداندن به حالت پیش‌نویس.
     *
     * نسخهٔ منتشرنشده در API دیده نمی‌شود ولی فایلش سر جایش می‌ماند، پس
     * برگرداندن یک انتشار اشتباه یک کلیک است نه یک آپلود دوباره.
     */
    public function toggleRelease(RepositoryVersion $version): RepositoryVersion
    {
        $version->update(['released_at' => $version->isReleased() ? null : now()]);

        return $version;
    }

    public function delete(RepositoryVersion $version): void
    {
        $path = $version->absolutePath();

        $version->delete();

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * جابه‌جایی بسته به محل دائمی‌اش.
     *
     * نام فایل از slug و نسخه ساخته می‌شود، نه از نام فایل آپلودی: نام آپلودی
     * را کاربر تعیین می‌کند و نباید تعیین‌کنندهٔ مسیر روی دیسک باشد.
     */
    protected function store(RepositoryModule $module, string $version, string $archive): string
    {
        $relative = config('marketplace-server.packages_path').'/'.$module->slug;

        File::ensureDirectoryExists(storage_path('app/'.$relative));

        $path = $relative.'/'.$module->slug.'-'.$version.'.zip';

        $absolute = storage_path('app/'.$path);

        if (! @rename($archive, $absolute) && ! @copy($archive, $absolute)) {
            throw new PackageException('انتقال بسته به محل نگهداری شکست خورد.');
        }

        return $path;
    }
}
