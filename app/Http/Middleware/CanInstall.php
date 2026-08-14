<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webkul\Installer\Http\Middleware\CanInstall as BaseCanInstall;

/**
 * همان میان‌افزار نصب‌کنندهٔ بجیستو، فقط با تشخیص دقیق‌ترِ «آدرس نصب‌کننده».
 *
 * نسخهٔ اصلی `Str::contains($request->getPathInfo(), '/install')` را می‌زند،
 * یعنی *هر* آدرسی که رشتهٔ `/install` جایی در آن باشد. آدرس‌های خود ما مثل
 * `admin/marketplace/repository/install/{slug}` و `admin/marketplace/install/{run}`
 * هم در همین تور می‌افتند و — چون سایت قبلاً نصب شده — به صفحهٔ اصلی فروشگاه
 * ریدایرکت می‌شوند. یعنی دکمهٔ «نصب» بی‌سروصدا کاربر را به خانه می‌فرستاد.
 *
 * اینجا فقط مسیرهای واقعی نصب‌کننده گرفته می‌شوند: `install` و `install/*`.
 * بقیهٔ رفتار (ریدایرکت به نصب‌کننده وقتی سایت هنوز نصب نشده) دست‌نخورده است.
 */
class CanInstall extends BaseCanInstall
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('install', 'install/*')) {
            if ($this->isAlreadyInstalled() && ! $request->ajax()) {
                return redirect()->route('shop.home.index');
            }

            return $next($request);
        }

        if (! $this->isAlreadyInstalled()) {
            return redirect()->route('installer.index');
        }

        return $next($request);
    }
}
