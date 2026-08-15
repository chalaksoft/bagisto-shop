<?php

namespace App\Classes;

use PDO;
use Throwable;

/**
 * کشف ماژول‌های `Modules/` در زمان اجرا و ساختن فهرست سرویس‌پرووایدرها.
 *
 * جای لیست دستیِ قبلی در `bootstrap/providers.php` را می‌گیرد تا بشود ماژول را
 * از پنل نصب و فعال/غیرفعال کرد، بدون ویرایش هیچ فایل PHP.
 *
 * ┄┄ زمان‌بندی ┄┄
 *
 * `bootstrap/providers.php` را بوت‌استرپر `RegisterProviders` می‌خواند — یعنی
 * بعد از `LoadEnvironmentVariables` و `LoadConfiguration` ولی **قبل از** ثبت
 * `DatabaseServiceProvider`. پس اینجا `config()` در دسترس است اما `DB` هنوز نه؛
 * وضعیت فعال/غیرفعال با یک PDO مستقیم از روی `config('database')` خوانده می‌شود
 * و نتیجه در `bootstrap/cache/modules.php` کش می‌شود تا این کار یک‌بار بیفتد.
 *
 * اگر دیتابیس در دسترس نباشد (نصب اولیه، مهاجرت‌نرفته، هاست در حال بالا آمدن)،
 * همهٔ ماژول‌های روی دیسک فعال فرض می‌شوند و چیزی کش نمی‌شود — دقیقاً همان
 * رفتاری که قبل از این کلاس وجود داشت، پس هیچ حالتی سایت را بی‌ماژول نمی‌کند.
 */
class ModuleRegistry
{
    /** نام جدولی که وضعیت ماژول‌ها را نگه می‌دارد (بدون پیشوند دیتابیس) */
    public const TABLE = 'installed_modules';

    /** فهرست پرووایدرهای ساخته‌شده در همین ریکوئست */
    protected static ?array $resolved = null;

    /**
     * فهرست کلاس سرویس‌پرووایدرهای ماژول‌های قابل بوت، مرتب بر اساس `priority`.
     */
    public static function providers(): array
    {
        if (static::$resolved !== null) {
            return static::$resolved;
        }

        if (is_array($cached = static::readCache())) {
            return static::$resolved = $cached;
        }

        $enabled = static::enabledNames();

        $providers = static::resolveProviders(static::manifests(), $enabled);

        /**
         * وقتی وضعیت واقعی از دیتابیس خوانده نشده، آنچه ساخته‌ایم فقط یک حدسِ
         * «همه فعال» است؛ کش‌کردنش یعنی تثبیت همان حدس تا اولین پاک‌سازی کش.
         */
        if ($enabled !== null) {
            static::writeCache($providers);
        }

        return static::$resolved = $providers;
    }

    /**
     * همهٔ ماژول‌های روی دیسک با مانیفست و وضعیتشان — خوراک `module:list` و پنل.
     *
     * برخلاف `providers()` این متد کش را دور می‌زند و همیشه دیسک و دیتابیس را
     * می‌خواند، چون مصرف‌کننده‌اش صفحهٔ مدیریت است نه مسیر داغِ هر ریکوئست.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $manifests = static::manifests();
        $enabled   = static::enabledNames();
        $bootable  = static::bootable($manifests, $enabled);

        $modules = [];

        foreach ($manifests as $name => $manifest) {
            $isEnabled = $enabled === null || in_array($name, $enabled, true);

            $modules[$name] = $manifest + [
                'enabled'  => $isEnabled,
                'bootable' => isset($bootable[$name]),
                'missing'  => $isEnabled ? static::missingRequirements($name, $manifests, $enabled) : [],
            ];
        }

        uasort($modules, fn ($a, $b) => $a['priority'] <=> $b['priority'] ?: strcmp($a['name'], $b['name']));

        return $modules;
    }

    /**
     * مانیفست یک ماژول، یا null وقتی ماژول یا `module.json` اش نیست.
     */
    public static function manifest(string $name): ?array
    {
        return static::manifests()[$name] ?? null;
    }

    /**
     * باطل‌کردن کش — بعد از هر نصب، حذف، فعال یا غیرفعال‌سازی صدا زده شود.
     */
    public static function flush(): void
    {
        static::$resolved = null;

        /**
         * `services.php` مانیفست خود لاراول از پرووایدرهاست و تا وقتی هست،
         * فهرست تازهٔ ما خوانده نمی‌شود. بدون پاک‌کردنش، فعال‌کردن یک ماژول
         * ظاهراً انجام می‌شود ولی هیچ اثری ندارد. لاراول خودش دوباره می‌سازدش.
         */
        foreach ([static::cachePath(), base_path('bootstrap/cache/services.php')] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        }
    }

    /**
     * مسیر پوشهٔ یک ماژول.
     */
    public static function path(string $name = ''): string
    {
        return rtrim(base_path('Modules/'.$name), '/');
    }

    /* ---------------------------------------------------------------------
     | خواندن دیسک
     * -------------------------------------------------------------------*/

    /**
     * `module.json` همهٔ ماژول‌ها، کلید‌خورده با نام ماژول.
     *
     * ماژولی که مانیفست ندارد یا مانیفستش JSON معتبر نیست نادیده گرفته می‌شود:
     * یک فایل نیمه‌نوشته وسط نصب نباید کل سایت را با خطای تجزیه پایین بیاورد.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function manifests(): array
    {
        $manifests = [];

        foreach (glob(static::path().'/*/module.json') ?: [] as $file) {
            $directory = basename(dirname($file));

            $data = json_decode((string) @file_get_contents($file), true);

            if (! is_array($data)) {
                continue;
            }

            $name = $data['name'] ?? $directory;

            $manifests[$name] = [
                'name'        => $name,
                'directory'   => $directory,
                'alias'       => $data['alias'] ?? strtolower($name),
                'description' => $data['description'] ?? '',
                'version'     => $data['version'] ?? '1.0.0',
                /** سازنده و صفحه‌اش — مثل «By …» در فهرست افزونه‌های وردپرس */
                'author'      => $data['author'] ?? null,
                'author_url'  => $data['author_url'] ?? null,
                'priority'    => (int) ($data['priority'] ?? 100),
                'requires'    => array_values((array) ($data['requires'] ?? [])),
                'providers'   => array_values((array) ($data['providers'] ?? ['Modules\\'.$directory.'\\ModuleServiceProvider'])),
                'assets_tag'  => $data['assets_tag'] ?? null,
                'path'        => dirname($file),
            ];
        }

        return $manifests;
    }

    /* ---------------------------------------------------------------------
     | چیدن فهرست
     * -------------------------------------------------------------------*/

    /**
     * فیلتر بر اساس وضعیت و پیش‌نیازها، مرتب‌سازی، و تبدیل به فهرست کلاس‌ها.
     *
     * @param  array<string, array<string, mixed>>  $manifests
     * @param  array<int, string>|null  $enabled  null یعنی «وضعیت نامعلوم، همه فعال»
     * @return array<int, string>
     */
    protected static function resolveProviders(array $manifests, ?array $enabled): array
    {
        $bootable = static::bootable($manifests, $enabled);

        uasort($bootable, fn ($a, $b) => $a['priority'] <=> $b['priority'] ?: strcmp($a['name'], $b['name']));

        $providers = [];

        foreach ($bootable as $manifest) {
            foreach ($manifest['providers'] as $provider) {
                /**
                 * ماژولی که فایل پرووایدرش نیست (استخراج ناتمام، حذف نصفه) از
                 * فهرست کنار می‌رود؛ وگرنه لاراول با «Class not found» بالا
                 * نمی‌آید و ادمین راهی برای رفع مشکل از پنل ندارد.
                 */
                if (class_exists($provider)) {
                    $providers[] = $provider;
                }
            }
        }

        return $providers;
    }

    /**
     * ماژول‌هایی که هم فعال‌اند و هم زنجیرهٔ پیش‌نیازهایشان کامل است.
     *
     * حل به نقطهٔ ثابت: هر دور ماژول‌هایی که پیش‌نیاز غایب دارند حذف می‌شوند و
     * حذفشان می‌تواند ماژول بعدی را هم بی‌پیش‌نیاز کند، پس تا وقتی چیزی حذف
     * می‌شود تکرار ادامه دارد.
     *
     * @param  array<string, array<string, mixed>>  $manifests
     * @return array<string, array<string, mixed>>
     */
    protected static function bootable(array $manifests, ?array $enabled): array
    {
        $bootable = $enabled === null
            ? $manifests
            : array_intersect_key($manifests, array_flip($enabled));

        do {
            $dropped = false;

            foreach ($bootable as $name => $manifest) {
                foreach ($manifest['requires'] as $requirement) {
                    if (! isset($bootable[$requirement])) {
                        unset($bootable[$name]);

                        $dropped = true;

                        break;
                    }
                }
            }
        } while ($dropped);

        return $bootable;
    }

    /**
     * پیش‌نیازهای غایبِ یک ماژول — برای نمایش دلیل بوت‌نشدن در پنل.
     *
     * @return array<int, string>
     */
    protected static function missingRequirements(string $name, array $manifests, ?array $enabled): array
    {
        $bootable = static::bootable($manifests, $enabled);

        if (isset($bootable[$name])) {
            return [];
        }

        return array_values(array_filter(
            $manifests[$name]['requires'] ?? [],
            fn ($requirement) => ! isset($bootable[$requirement])
        ));
    }

    /* ---------------------------------------------------------------------
     | وضعیت فعال/غیرفعال
     * -------------------------------------------------------------------*/

    /**
     * نام ماژول‌های فعال از جدول `installed_modules`.
     *
     * `null` یعنی نشد خواند (دیتابیس نیست، جدول هنوز ساخته نشده، اتصال قطع) و
     * صداکننده باید همه را فعال فرض کند.
     *
     * @return array<int, string>|null
     */
    protected static function enabledNames(): ?array
    {
        /**
         * وقتی از دستور artisan یا کنترلر صدا زده می‌شود، اتصال آمادهٔ لاراول
         * موجود است و ساختن PDO دوم بی‌دلیل است.
         */
        if (app()->resolved('db')) {
            try {
                return \Illuminate\Support\Facades\DB::table(static::TABLE)
                    ->where('enabled', true)
                    ->pluck('name')
                    ->all();
            } catch (Throwable) {
                return null;
            }
        }

        if (! $pdo = static::pdo()) {
            return null;
        }

        try {
            $table = config('database.connections.'.config('database.default').'.prefix', '').static::TABLE;

            $statement = $pdo->query('select name from '.$table.' where enabled = 1');

            return $statement === false ? null : $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * اتصال PDO ساخته‌شده مستقیم از `config('database')`.
     *
     * در زمان خواندن `bootstrap/providers.php` هنوز `DatabaseServiceProvider`
     * ثبت نشده، پس `DB` در دسترس نیست ولی کانفیگ هست.
     */
    protected static function pdo(): ?PDO
    {
        $config = config('database.connections.'.config('database.default'));

        if (! is_array($config)) {
            return null;
        }

        $dsn = match ($config['driver'] ?? null) {
            'mysql', 'mariadb' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 3306,
                $config['database'] ?? ''
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 5432,
                $config['database'] ?? ''
            ),
            'sqlite' => 'sqlite:'.($config['database'] ?? ''),
            default  => null,
        };

        if ($dsn === null) {
            return null;
        }

        try {
            return new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_TIMEOUT => 3,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /* ---------------------------------------------------------------------
     | کش
     * -------------------------------------------------------------------*/

    protected static function cachePath(): string
    {
        return base_path('bootstrap/cache/modules.php');
    }

    /**
     * فهرست کش‌شده، یا null وقتی کشی نیست یا دیگر معتبر نیست.
     */
    protected static function readCache(): ?array
    {
        if (! is_file($path = static::cachePath())) {
            return null;
        }

        $cache = @include $path;

        if (! is_array($cache) || ! isset($cache['providers'], $cache['stamp'])) {
            return null;
        }

        return $cache['stamp'] === static::stamp() ? $cache['providers'] : null;
    }

    protected static function writeCache(array $providers): void
    {
        $contents = '<?php return '.var_export([
            'stamp'     => static::stamp(),
            'providers' => $providers,
        ], true).';'.PHP_EOL;

        /**
         * نوشتن اتمیک: دو ریکوئست هم‌زمان نباید فایلی نیمه‌نوشته را `include` کنند.
         */
        $temporary = static::cachePath().'.'.getmypid();

        if (@file_put_contents($temporary, $contents, LOCK_EX) !== false) {
            @rename($temporary, static::cachePath());

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(static::cachePath(), true);
            }
        }
    }

    /**
     * اثر انگشتِ وضعیت دیسک — با تغییرش کش دور ریخته می‌شود.
     *
     * در حالت عادی فقط زمان تغییر پوشهٔ `Modules/` (اضافه/حذف شدن ماژول) نگاه
     * می‌شود که یک `stat` است. با `APP_DEBUG` روشن، زمان تغییر تک‌تک مانیفست‌ها
     * هم حساب می‌شود تا موقع توسعه، ویرایش `module.json` بلافاصله اثر کند.
     */
    protected static function stamp(): string
    {
        $parts = [(string) @filemtime(static::path())];

        if (config('app.debug')) {
            foreach (glob(static::path().'/*/module.json') ?: [] as $file) {
                $parts[] = $file.':'.@filemtime($file);
            }
        }

        return md5(implode('|', $parts));
    }
}
