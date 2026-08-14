<?php

namespace Modules\MarketplaceServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\MarketplaceServer\Model\License;

/**
 * شناسایی فروشگاه خریدار پشت یک درخواست API.
 *
 * دو چیز روی ریکوئست می‌گذارد: `license` (یا null) و `shop_domain`.
 *
 * عمداً هیچ درخواستی را رد نمی‌کند: کنترلر باید بتواند بین «توکن ندارد» و
 * «توکن دارد ولی دامنه‌اش ثبت نشده» فرق بگذارد، هم برای پیام و هم برای لاگ.
 */
class ResolveLicense
{
    public function handle(Request $request, Closure $next)
    {
        $license = License::findByToken($request->bearerToken());

        /**
         * دامنه را خود فروشگاه اعلام می‌کند. این «اثبات» نیست — هدر جعل‌شدنی
         * است — ولی کارش جلوگیری از نصب یک خرید روی ده سایت است، نه مقابله با
         * مهاجم؛ کسی که توکن را دارد از هر راهی بسته را می‌گیرد.
         */
        $domain = License::normalizeDomain(
            $request->header('X-Shop-Domain') ?: $request->input('domain') ?: $request->getHost()
        );

        $request->attributes->set('license', $license);
        $request->attributes->set('shop_domain', $domain);

        if ($license) {
            $license->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
