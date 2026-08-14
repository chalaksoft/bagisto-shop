<?php

use Illuminate\Support\Facades\Route;
use Modules\MarketplaceServer\Http\Controller\Api\DownloadController;
use Modules\MarketplaceServer\Http\Controller\Api\LicenseController;
use Modules\MarketplaceServer\Http\Controller\Api\ModuleController;

/**
 * API مخزن — چیزی که ماژول `Marketplace` روی فروشگاه‌های مشتری صدا می‌زند.
 *
 * احراز هویت با توکن لایسنس در `Authorization: Bearer …` و دامنهٔ فروشگاه در
 * `X-Shop-Domain`. فهرست ماژول‌ها بدون توکن هم جواب می‌دهد (فروشگاه باید بتواند
 * ویترین را ببیند)؛ دانلود نه.
 *
 * میان‌افزار عمداً حداقلی است: نه سشن، نه کوکی، نه CSRF. اینجا یک API بدون
 * وضعیت است و گروه `web` بجیستو فقط سربار و سطح حمله اضافه می‌کند.
 */
Route::prefix('api/marketplace')->middleware(['marketplace.license'])->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('modules', [ModuleController::class, 'index'])->name('marketplace.api.modules');

        Route::get('modules/{slug}', [ModuleController::class, 'show'])->name('marketplace.api.module');

        Route::post('license/check', [LicenseController::class, 'check'])->name('marketplace.api.license');
    });

    /**
     * دانلود سقف جداگانه دارد: هم سنگین‌تر است، هم تنها نقطه‌ای که ارزش
     * brute-force کردن توکن را دارد.
     */
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('modules/{slug}/download', DownloadController::class)
            ->name('marketplace.api.download');
    });
});
