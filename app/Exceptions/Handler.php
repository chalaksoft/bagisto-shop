<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Webkul\Core\Exceptions\Handler as BaseHandler;

/**
 * همان هندلر بجیستو، با دو اصلاح.
 *
 * **یک: تم پیش از رندر صفحهٔ خطا.** صفحهٔ خطا داخل قالب رندر می‌شود و قالب
 * `themes()->url(...)` صدا می‌زند. تم را میان‌افزار
 * `Webkul\Shop\Http\Middleware\Theme` ست می‌کند و آن میان‌افزار روی درخواستی که
 * به هیچ روتی نخورده اصلاً اجرا نمی‌شود. نتیجه: `Themes::url()` می‌ترکید
 * («Call to a member function url() on null») و یک ۴۰۴ ساده به ۵۰۰ تبدیل
 * می‌شد — یعنی دلیل واقعی خطا پنهان می‌ماند. روی مخزن همین باعث شد «این روت
 * وجود ندارد» شبیه «سرور خراب است» به نظر برسد.
 *
 * **دو: کد وضعیت واقعی.** بجیستو فقط ۴۰۱ و ۴۰۳ و ۴۰۴ و ۵۰۳ را نگه می‌دارد و
 * بقیه را ۵۰۰ می‌کند؛ پس POST به آدرسی که فقط GET دارد (۴۰۵) یا نشست منقضی
 * (۴۱۹) هم شبیه خرابی سرور دیده می‌شد.
 *
 * کال‌بک‌ها پیش از `parent::register()` ثبت می‌شوند تا اول اجرا شوند؛
 * برگرداندن `null` یعنی «تصمیم با هندلرهای بجیستو».
 */
class Handler extends BaseHandler
{
    /** کدهایی که خود بجیستو برایشان صفحه و ترجمه دارد */
    protected const HANDLED_BY_BAGISTO = [401, 403, 404, 503];

    public function register(): void
    {
        $this->ensureThemeIsSet();

        $this->handleExpiredSession();

        $this->keepClientErrorStatus();

        parent::register();
    }

    /**
     * نشست منقضی (۴۱۹) خطای صفحه نیست، یک «دوباره بفرست» است.
     *
     * پیام «۴۰۴ صفحه پیدا نشد» برای این حالت گمراه‌کننده است: آدرس درست بوده و
     * فقط توکن فرم کهنه شده — معمولاً چون تب ساعت‌ها باز مانده.
     */
    protected function handleExpiredSession(): void
    {
        $this->renderable(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            $message = 'نشست شما منقضی شده بود؛ صفحه را تازه کنید و دوباره تلاش کنید.';

            if ($request->wantsJson()) {
                return response()->json(['error' => 'نشست منقضی', 'description' => $message], 419);
            }

            return redirect()->back()->with('error', $message);
        });
    }

    /**
     * تم فعال باید پیش از رندر هر صفحهٔ خطا موجود باشد.
     */
    protected function ensureThemeIsSet(): void
    {
        $this->renderable(function (Throwable $exception, Request $request) {
            if (! themes()->current()) {
                themes()->set(
                    $this->isAdminRequest($request)
                        ? config('themes.admin-default')
                        : config('themes.shop-default')
                );
            }

            return null;
        });
    }

    /**
     * خطای سمت کلاینت با کد خودش پاسخ داده شود، نه ۵۰۰.
     *
     * صفحهٔ نمایشی همان ۴۰۴ است، چون بجیستو برای ۴۰۵ و ۴۱۹ نه ویو دارد نه
     * ترجمه؛ ولی کد وضعیت راست را می‌گوید و لاگ و مانیتورینگ گمراه نمی‌شوند.
     */
    protected function keepClientErrorStatus(): void
    {
        $this->renderable(function (HttpException $exception, Request $request) {
            $status = $exception->getStatusCode();

            if (
                $status < 400
                || $status >= 500
                || in_array($status, self::HANDLED_BY_BAGISTO, true)
            ) {
                return null;
            }

            $namespace = $this->isAdminRequest($request) ? 'admin' : 'shop';

            if ($request->wantsJson()) {
                return response()->json([
                    'error'       => trans("{$namespace}::app.errors.404.title"),
                    'description' => $exception->getMessage() ?: trans("{$namespace}::app.errors.404.description"),
                ], $status);
            }

            $viewPath = view()->exists("{$namespace}::errors.404")
                ? "{$namespace}::errors.404"
                : "{$namespace}::errors.index";

            return response()->view($viewPath, ['errorCode' => 404], $status);
        });
    }

    protected function isAdminRequest(Request $request): bool
    {
        return $request->is(config('app.admin_url').'/*');
    }
}
