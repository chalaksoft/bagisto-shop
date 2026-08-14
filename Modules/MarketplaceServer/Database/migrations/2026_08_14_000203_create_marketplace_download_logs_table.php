<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هر دانلود — موفق یا ردشده — یک ردیف.
 *
 * ردشده‌ها به‌اندازهٔ موفق‌ها مهم‌اند: توکنی که مدام از دامنهٔ ثبت‌نشده تلاش
 * می‌کند یعنی لایسنس لو رفته است.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_download_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_license_id')
                ->nullable()
                ->constrained('marketplace_licenses')
                ->nullOnDelete();

            $table->foreignId('marketplace_module_version_id')
                ->nullable()
                ->constrained('marketplace_module_versions')
                ->nullOnDelete();

            $table->string('module_slug')->nullable();

            $table->string('domain')->nullable();

            $table->string('ip', 45)->nullable();

            $table->boolean('allowed')->default(false);

            /** دلیل رد شدن: توکن نامعتبر، دامنهٔ نامجاز، منقضی، خارج از لایسنس */
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['module_slug', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_download_logs');
    }
};
