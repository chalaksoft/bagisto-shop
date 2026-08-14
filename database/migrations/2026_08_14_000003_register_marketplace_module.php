<?php

use App\Classes\ModuleRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ثبت ماژول‌های همراهِ خود ریپو در `installed_modules`.
 *
 * مهاجرت قبلی (`create_installed_modules_table`) ماژول‌های آن لحظه را ثبت کرد،
 * ولی روی نصب‌هایی که آن مهاجرت را زودتر اجرا کرده‌اند ماژول‌های تازه‌ای که با
 * به‌روزرسانی ریپو آمده‌اند ردیف ندارند. بدون ردیف، رجیستری آن‌ها را غیرفعال
 * می‌بیند و — در مورد `Marketplace` — حتی راهی برای فعال‌کردنشان از پنل نمی‌ماند.
 *
 * فقط ماژول‌هایی که با خود ریپو می‌آیند اینجا فعال می‌شوند. ماژولی که کسی با
 * FTP کپی کرده باید در پنل و به‌صورت آگاهانه فعال شود.
 */
return new class extends Migration
{
    /** ماژول‌های همراه ریپو */
    protected array $bundled = ['Marketplace', 'MarketplaceServer'];

    public function up(): void
    {
        $now = now();

        foreach ($this->bundled as $name) {
            if (! is_file($manifest = base_path("Modules/$name/module.json"))) {
                continue;
            }

            if (DB::table('installed_modules')->where('name', $name)->exists()) {
                continue;
            }

            $data = json_decode((string) file_get_contents($manifest), true) ?: [];

            DB::table('installed_modules')->insert([
                'name'         => $name,
                'version'      => $data['version'] ?? '1.0.0',
                'enabled'      => true,
                'source'       => 'bundled',
                'installed_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        /**
         * کش رجیستری بر اساس تغییر پوشهٔ `Modules/` باطل می‌شود، نه تغییر جدول؛
         * پس هر نوشتنِ مستقیم روی وضعیت باید خودش کش را دور بریزد.
         */
        ModuleRegistry::flush();
    }

    public function down(): void
    {
        DB::table('installed_modules')->whereIn('name', $this->bundled)->delete();

        ModuleRegistry::flush();
    }
};
