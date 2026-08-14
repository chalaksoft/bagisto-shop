<?php

namespace Modules\MarketplaceServer\Http\Controller\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * اعتبارسنجی توکن و دامنه — چیزی که پنل فروشگاه خریدار برای نمایش وضعیت
 * لایسنس صدا می‌زند، بدون اینکه چیزی دانلود کند.
 */
class LicenseController extends Controller
{
    public function check(Request $request)
    {
        $license = $request->attributes->get('license');
        $domain  = $request->attributes->get('shop_domain');

        if (! $license) {
            return response()->json(['valid' => false, 'reason' => 'توکن معتبر نیست.'], 401);
        }

        $reason = $license->rejectionReason($domain);

        return response()->json([
            'valid'      => $reason === null,
            'reason'     => $reason,
            'domain'     => $domain,
            'label'      => $license->label,
            'customer'   => $license->customer?->name,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'modules'    => $license->module_slugs ?: null,
            'domains'    => $license->domains,
        ], $reason === null ? 200 : 403);
    }
}
