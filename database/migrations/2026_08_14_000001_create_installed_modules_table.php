<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وضعیت ماژول‌های `Modules/` — منبع حقیقتِ «چه چیزی بوت شود».
 *
 * جایش ریشهٔ پروژه است نه ماژول Marketplace: `App\Classes\ModuleRegistry` که
 * در `bootstrap/providers.php` صدا زده می‌شود به این جدول نگاه می‌کند، پس
 * جدول باید حتی وقتی هیچ ماژولی فعال نیست هم ساخته شود.
 */
return new class extends Migration
{
    /**
     * ماژول‌هایی که پیش از این هم در `bootstrap/providers.php` نبودند و نباید
     * با اجرای این مهاجرت ناگهان بوت شوند.
     */
    protected array $disabled = ['Temp'];

    public function up(): void
    {
        Schema::create('installed_modules', function (Blueprint $table) {
            $table->id();

            /** نام پوشه داخل `Modules/`، مثل `Marketplace` */
            $table->string('name')->unique();

            $table->string('version')->default('0.0.0');

            $table->boolean('enabled')->default(true);

            /**
             * bundled   = همراه خود ریپو آمده
             * repository = از سایت مخزن نصب شده
             * manual    = آپلود دستی zip
             */
            $table->enum('source', ['bundled', 'repository', 'manual'])->default('bundled');

            /** چکسام بسته، شناسهٔ مخزن، لایسنس و هر چیز مخصوص منبع نصب */
            $table->json('meta')->nullable();

            $table->timestamp('installed_at')->nullable();

            $table->timestamps();
        });

        $this->seedExistingModules();
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
    }

    /**
     * ماژول‌های موجود روی دیسک را `bundled` ثبت می‌کند.
     *
     * بدون این کار، اولین ریکوئست بعد از مهاجرت جدولی خالی می‌بیند و — بسته به
     * سیاست رجیستری — ممکن است سایت بی‌ماژول بالا بیاید.
     */
    protected function seedExistingModules(): void
    {
        $now  = now();
        $rows = [];

        foreach (glob(base_path('Modules/*/module.json')) as $manifest) {
            $data = json_decode(file_get_contents($manifest), true);

            $name = $data['name'] ?? basename(dirname($manifest));

            $rows[] = [
                'name'         => $name,
                'version'      => $data['version'] ?? '1.0.0',
                'enabled'      => ! in_array($name, $this->disabled, true),
                'source'       => 'bundled',
                'meta'         => null,
                'installed_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        if ($rows) {
            DB::table('installed_modules')->insert($rows);
        }
    }
};
