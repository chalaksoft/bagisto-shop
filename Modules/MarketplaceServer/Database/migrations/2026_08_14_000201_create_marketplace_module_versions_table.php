<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * یک نسخهٔ منتشرشده از یک ماژول.
 *
 * `checksum` و `signature` موقع انتشار محاسبه و ذخیره می‌شوند، نه موقع دانلود:
 * کلید خصوصی نباید در مسیر داغِ هر ریکوئست باز شود، و امضای ذخیره‌شده یعنی
 * دانلود حتی وقتی کلید موقتاً در دسترس نیست هم کار می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_module_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_module_id')
                ->constrained('marketplace_modules')
                ->cascadeOnDelete();

            $table->string('version');

            /** مسیر فایل نسبت به `storage/app` */
            $table->string('archive_path');

            $table->unsignedBigInteger('archive_size')->default(0);

            $table->string('checksum', 64);

            /** امضای base64 با کلید خصوصی مخزن */
            $table->text('signature');

            $table->string('requires_bagisto')->nullable();

            $table->string('requires_php')->nullable();

            $table->json('requires')->nullable();

            $table->text('changelog')->nullable();

            /** نسخهٔ منتشرنشده فقط در پنل دیده می‌شود، نه در API */
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->unique(['marketplace_module_id', 'version'], 'marketplace_versions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_module_versions');
    }
};
