<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هر نصب/به‌روزرسانی/حذف یک ردیف اینجا دارد.
 *
 * این جدول هم‌زمان دو کار می‌کند:
 *
 *   ۱. لاگ ممیزی — چه کسی، چه ماژولی، چه نسخه‌ای، با چه چکسامی، کی.
 *   ۲. وضعیت ادامه‌پذیر — روی هاست اشتراکی تایم‌اوت ۳۰ تا ۶۰ ثانیه است و نصب
 *      در یک ریکوئست جا نمی‌شود، پس هر مرحله وضعیتش را اینجا می‌نویسد و
 *      ریکوئست بعدی از همان‌جا ادامه می‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_installations', function (Blueprint $table) {
            $table->id();

            $table->string('module');

            $table->string('version')->nullable();

            $table->enum('source', ['bundled', 'repository', 'manual'])->default('manual');

            $table->enum('action', ['install', 'update', 'remove'])->default('install');

            /** کلید مرحلهٔ بعدی؛ خالی یعنی کاری نمانده */
            $table->string('step')->nullable();

            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');

            /** مسیر آرشیو، چکسام، مسیر بکاپ نسخهٔ قبلی، نسخهٔ قبلی */
            $table->json('payload')->nullable();

            $table->text('error')->nullable();

            $table->unsignedInteger('admin_id')->nullable();

            $table->string('admin_name')->nullable();

            $table->timestamps();

            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_installations');
    }
};
