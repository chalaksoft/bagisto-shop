<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توکنی که فروشگاه خریدار در هدر `Authorization` می‌فرستد.
 *
 * خریدار همان **مشتری خود بجیستو**ست (`customers`)، چون این فروشگاه ماژول‌ها
 * را می‌فروشد؛ دفتر مشتریِ جداگانه یعنی دو جای نگهداری یک آدم.
 *
 * توکن به‌صورت هش ذخیره می‌شود و مقدار خام فقط یک بار موقع صدور نشان داده
 * می‌شود: نشتی دیتابیس نباید یعنی نشتی همهٔ لایسنس‌ها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_licenses', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('customer_id')->nullable();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            /** سفارشی که این لایسنس بابتش صادر شده، اگر از فروشگاه خریده شده */
            $table->unsignedInteger('order_id')->nullable();

            /** هشِ sha256 توکن؛ مقدار خام ذخیره نمی‌شود */
            $table->string('token_hash', 64)->unique();

            /** چهار کاراکتر آخر توکن، فقط برای تشخیص در پنل */
            $table->string('token_hint', 8)->nullable();

            $table->string('label')->nullable();

            /** دامنه‌های مجاز، بدون پروتکل و بدون www */
            $table->json('domains');

            /** null یعنی همهٔ ماژول‌ها */
            $table->json('module_slugs')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_licenses');
    }
};
