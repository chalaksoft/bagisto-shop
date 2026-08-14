<?php

namespace Modules\MarketplaceServer\Http\Controller\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MarketplaceServer\Model\DownloadLog;
use Modules\MarketplaceServer\Model\License;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Modules\MarketplaceServer\Model\RepositoryVersion;

/**
 * تحویل بستهٔ zip به فروشگاه خریدار.
 *
 * چکسام و امضا در هدر می‌آیند، نه در بدنه: بدنه خودِ فایل است و کلاینت آن را
 * مستقیم روی دیسک می‌ریزد (`Http::sink()`).
 */
class DownloadController extends Controller
{
    public function __invoke(Request $request, string $slug)
    {
        $license = $request->attributes->get('license');
        $domain  = $request->attributes->get('shop_domain');

        $module = RepositoryModule::query()->where('slug', $slug)->where('published', true)->first();

        if (! $module) {
            return $this->deny($request, null, $slug, 'ماژول در مخزن نیست یا منتشر نشده است.', 404);
        }

        $version = $this->resolveVersion($request, $module);

        if (! $version) {
            return $this->deny($request, null, $slug, 'نسخهٔ سازگاری برای این فروشگاه منتشر نشده است.', 404);
        }

        if ($reason = $this->rejection($module, $license, $domain)) {
            return $this->deny($request, $version, $slug, $reason, 403);
        }

        if (! is_file($path = $version->absolutePath())) {
            return $this->deny($request, $version, $slug, 'فایل بسته روی مخزن پیدا نشد.', 500);
        }

        DownloadLog::create([
            'marketplace_license_id'        => $license?->id,
            'marketplace_module_version_id' => $version->id,
            'module_slug'                   => $slug,
            'domain'                        => $domain,
            'ip'                            => $request->ip(),
            'allowed'                       => true,
        ]);

        /** `download()` خودش Content-Disposition: attachment می‌گذارد. */
        return response()->download($path, $module->slug.'-'.$version->version.'.zip', [
            'X-Package-Checksum'  => $version->checksum,
            'X-Package-Signature' => $version->signature,
            'X-Package-Version'   => $version->version,
        ]);
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    /**
     * نسخهٔ خواسته‌شده، یا جدیدترین سازگار.
     *
     * وقتی فروشگاه `?version=` می‌دهد یعنی عمداً نسخهٔ مشخصی می‌خواهد (نصب
     * دوبارهٔ نسخهٔ قبلی)، پس سازگاری همان‌جا سنجیده نمی‌شود — کلاینت خودش موقع
     * نصب می‌سنجد و پیام بهتری می‌دهد.
     */
    protected function resolveVersion(Request $request, RepositoryModule $module): ?RepositoryVersion
    {
        if ($requested = $request->query('version')) {
            return $module->releasedVersions->firstWhere('version', $requested);
        }

        return $module->latestCompatible($request->query('bagisto'), $request->query('php'));
    }

    /**
     * دلیل رد شدن، یا null وقتی دانلود مجاز است.
     */
    protected function rejection(RepositoryModule $module, ?License $license, ?string $domain): ?string
    {
        if ($module->free && config('marketplace-server.anonymous_free_downloads')) {
            return null;
        }

        if (! $license) {
            return 'برای دانلود این ماژول توکن لایسنس لازم است.';
        }

        if ($reason = $license->rejectionReason($domain)) {
            return $reason;
        }

        if (! $license->covers($module->slug)) {
            return 'این ماژول در لایسنس شما نیست.';
        }

        return null;
    }

    /**
     * ردکردن + لاگ.
     *
     * تلاش‌های ردشده هم ثبت می‌شوند: توکنی که مدام از دامنهٔ ثبت‌نشده می‌آید
     * یعنی لایسنس دست شخص دیگری است.
     */
    protected function deny(Request $request, ?RepositoryVersion $version, ?string $slug, string $reason, int $status)
    {
        DownloadLog::create([
            'marketplace_license_id'        => $request->attributes->get('license')?->id,
            'marketplace_module_version_id' => $version?->id,
            'module_slug'                   => $slug,
            'domain'                        => $request->attributes->get('shop_domain'),
            'ip'                            => $request->ip(),
            'allowed'                       => false,
            'reason'                        => $reason,
        ]);

        return response()->json(['message' => $reason], $status);
    }
}
