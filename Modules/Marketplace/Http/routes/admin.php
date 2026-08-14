<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controller\Admin\ModuleController;
use Modules\Marketplace\Http\Controller\Admin\RepositoryController;

Route::group(['middleware' => ['admin'], 'prefix' => config('app.admin_url')], function () {
    Route::controller(RepositoryController::class)->prefix('marketplace/repository')->group(function () {
        Route::get('', 'index')->name('admin.marketplace.repository');

        Route::get('license', 'license')->name('admin.marketplace.license');

        Route::post('install/{slug}', 'install')->name('admin.marketplace.repository.install');
    });

    Route::controller(ModuleController::class)->prefix('marketplace')->group(function () {
        Route::get('', 'index')->name('admin.marketplace.index');

        Route::post('toggle/{name}', 'toggle')->name('admin.marketplace.toggle');

        Route::delete('remove/{name}', 'remove')->name('admin.marketplace.remove');

        /** آپلود zip فقط نصب را «شروع» می‌کند؛ اجرا مرحله‌به‌مرحله است. */
        Route::post('install', 'install')->name('admin.marketplace.install');

        Route::get('install/{run}', 'progress')->name('admin.marketplace.progress');

        Route::post('install/{run}/advance', 'advance')->name('admin.marketplace.advance');
    });
});
