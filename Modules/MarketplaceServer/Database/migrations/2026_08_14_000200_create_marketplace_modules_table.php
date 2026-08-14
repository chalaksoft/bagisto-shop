<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ماژول‌هایی که این فروشگاه **منتشر می‌کند** (نه آن‌هایی که نصب کرده).
 *
 * پیشوند `marketplace_` عمدی است: این فروشگاه هم‌زمان یک فروشگاه بجیستوی
 * معمولی است و جدول‌هایی مثل `customers` و `products` مال خود بجیستو هستند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_modules', function (Blueprint $table) {
            $table->id();

            /** شناسهٔ پایدار در API و آدرس‌ها، مثل `invoice-pro` */
            $table->string('slug')->unique();

            /** نام پوشه در `Modules/` فروشگاه مقصد، مثل `InvoicePro` */
            $table->string('package_name');

            $table->string('name');

            $table->text('description')->nullable();

            $table->string('icon')->nullable();

            $table->string('category')->nullable();

            /** فقط ماژول منتشرشده در API دیده می‌شود */
            $table->boolean('published')->default(false);

            /** ماژول رایگان بدون لایسنس هم دانلود می‌شود */
            $table->boolean('free')->default(false);

            /**
             * محصول متناظر در کاتالوگ بجیستو، اگر ماژول فروشی است.
             *
             * `nullOnDelete` چون حذف یک محصول از کاتالوگ نباید ماژول منتشرشده و
             * لایسنس‌های صادرشده‌اش را از بین ببرد.
             */
            $table->unsignedInteger('product_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_modules');
    }
};
