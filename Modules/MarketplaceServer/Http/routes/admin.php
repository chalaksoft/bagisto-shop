<?php

use Illuminate\Support\Facades\Route;
use Modules\MarketplaceServer\Http\Controller\Admin\DownloadLogController;
use Modules\MarketplaceServer\Http\Controller\Admin\LicenseController;
use Modules\MarketplaceServer\Http\Controller\Admin\RepositoryModuleController;
use Modules\MarketplaceServer\Http\Controller\Admin\VersionController;

Route::group(['middleware' => ['admin'], 'prefix' => config('app.admin_url')], function () {
    Route::prefix('marketplace-server')->group(function () {

        Route::controller(RepositoryModuleController::class)->prefix('modules')->group(function () {
            Route::get('', 'index')->name('admin.marketplace_server.modules.index');

            Route::get('create', 'create')->name('admin.marketplace_server.modules.create');

            Route::post('create', 'store')->name('admin.marketplace_server.modules.store');

            Route::get('{id}', 'show')->name('admin.marketplace_server.modules.show');

            Route::get('{id}/edit', 'edit')->name('admin.marketplace_server.modules.edit');

            Route::put('{id}/edit', 'update')->name('admin.marketplace_server.modules.update');

            Route::delete('{id}', 'destroy')->name('admin.marketplace_server.modules.delete');
        });

        Route::controller(VersionController::class)->prefix('modules/{module}/versions')->group(function () {
            Route::post('', 'store')->name('admin.marketplace_server.versions.store');

            Route::post('{version}/toggle', 'toggle')->name('admin.marketplace_server.versions.toggle');

            Route::delete('{version}', 'destroy')->name('admin.marketplace_server.versions.delete');
        });

        Route::controller(LicenseController::class)->prefix('licenses')->group(function () {
            Route::get('', 'index')->name('admin.marketplace_server.licenses.index');

            Route::get('create', 'create')->name('admin.marketplace_server.licenses.create');

            Route::post('create', 'store')->name('admin.marketplace_server.licenses.store');

            Route::get('{id}/edit', 'edit')->name('admin.marketplace_server.licenses.edit');

            Route::put('{id}/edit', 'update')->name('admin.marketplace_server.licenses.update');

            Route::post('{id}/rotate', 'rotate')->name('admin.marketplace_server.licenses.rotate');

            Route::delete('{id}', 'destroy')->name('admin.marketplace_server.licenses.delete');
        });

        Route::controller(DownloadLogController::class)->prefix('logs')->group(function () {
            Route::get('', 'index')->name('admin.marketplace_server.logs.index');
        });
    });
});
