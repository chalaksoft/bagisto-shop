<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توکن لایسنسی که این فروشگاه با «ثبت‌نام» از مخزن گرفته است.
 *
 * تا پیش از این تنها راه، گذاشتن `MARKETPLACE_TOKEN` در `.env` بود؛ روی هاست
 * اشتراکی و برای ادمینی که SSH ندارد یعنی هیچ راهی. توکن اینجا خام ذخیره
 * می‌شود چون باید در هر درخواست فرستاده شود — دقیقاً هم‌رده با `.env`، نه
 * بیشتر و نه کمتر.
 *
 * فقط یک ردیف معنا دارد (آخرین ثبت‌نام)؛ ردیف‌های قدیمی‌تر تاریخچه‌اند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_credentials', function (Blueprint $table) {
            $table->id();

            $table->string('token');

            /** دامنه‌ای که لایسنس رویش صادر شده؛ اگر آدرس سایت عوض شود دیگر نمی‌خورد */
            $table->string('domain')->nullable();

            $table->string('email')->nullable();

            $table->string('customer_name')->nullable();

            $table->string('label')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamp('registered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_credentials');
    }
};
