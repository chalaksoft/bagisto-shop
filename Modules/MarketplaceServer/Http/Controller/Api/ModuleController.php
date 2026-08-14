<?php

namespace Modules\MarketplaceServer\Http\Controller\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MarketplaceServer\Model\License;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Modules\MarketplaceServer\Model\RepositoryVersion;

/**
 * ویترین مخزن.
 *
 * فهرست بدون توکن هم برمی‌گردد — فروشگاه باید بتواند ببیند چه چیزی برای خرید
 * هست — ولی هر آیتم می‌گوید با لایسنس فعلی قابل دانلود هست یا نه.
 */
class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $license = $request->attributes->get('license');

        $modules = RepositoryModule::query()
            ->where('published', true)
            ->with('versions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $modules
                ->map(fn (RepositoryModule $module) => $this->summary(
                    $module,
                    /**
                     * ماژولی که هیچ نسخهٔ سازگاری ندارد از فهرست حذف نمی‌شود؛ با
                     * `latest_version: null` برمی‌گردد تا فروشگاه بتواند بگوید
                     * «هست ولی با نسخهٔ شما سازگار نیست» به‌جای اینکه ناپدید شود.
                     */
                    $module->latestCompatible($request->query('bagisto'), $request->query('php')),
                    $license
                ))
                ->values(),
            'meta' => ['license' => $this->licenseSummary($request)],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $module = RepositoryModule::query()
            ->where('slug', $slug)
            ->where('published', true)
            ->with('versions')
            ->firstOrFail();

        $latest = $module->latestCompatible($request->query('bagisto'), $request->query('php'));

        return response()->json([
            'data' => $this->summary($module, $latest, $request->attributes->get('license')) + [
                'versions' => $module->releasedVersions
                    ->sort(fn ($a, $b) => version_compare($b->version, $a->version))
                    ->map(fn (RepositoryVersion $version) => $this->versionPayload($version))
                    ->values(),
            ],
            'meta' => ['license' => $this->licenseSummary($request)],
        ]);
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    protected function summary(RepositoryModule $module, ?RepositoryVersion $version, ?License $license): array
    {
        return [
            'slug'           => $module->slug,
            'package_name'   => $module->package_name,
            'name'           => $module->name,
            'description'    => $module->description,
            'icon'           => $module->icon,
            'category'       => $module->category,
            'free'           => $module->free,
            'available'      => $module->isAvailableTo($license),
            'latest_version' => $version ? $this->versionPayload($version) : null,
        ];
    }

    protected function versionPayload(RepositoryVersion $version): array
    {
        return [
            'version'          => $version->version,
            'checksum'         => $version->checksum,
            'requires_bagisto' => $version->requires_bagisto,
            'requires_php'     => $version->requires_php,
            'requires'         => $version->requires ?: [],
            'changelog'        => $version->changelog,
            'released_at'      => $version->released_at?->toIso8601String(),
            'size'             => $version->archive_size,
        ];
    }

    protected function licenseSummary(Request $request): array
    {
        $license = $request->attributes->get('license');
        $domain  = $request->attributes->get('shop_domain');

        if (! $license) {
            return ['valid' => false, 'reason' => 'توکنی فرستاده نشده یا معتبر نیست.'];
        }

        $reason = $license->rejectionReason($domain);

        return [
            'valid'      => $reason === null,
            'reason'     => $reason,
            'domain'     => $domain,
            'customer'   => $license->customer?->name,
            'expires_at' => $license->expires_at?->toIso8601String(),
            /** null یعنی «همهٔ ماژول‌ها» */
            'modules'    => $license->module_slugs ?: null,
        ];
    }
}
