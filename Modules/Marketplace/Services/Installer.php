<?php

namespace Modules\Marketplace\Services;

use App\Classes\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Marketplace\Model\InstalledModule;
use Modules\Marketplace\Model\ModuleInstallation;
use Throwable;
use Webkul\Core\Core;
use ZipArchive;

/**
 * نصب، به‌روزرسانی، فعال/غیرفعال‌سازی و حذف ماژول — بدون شل و بدون composer.
 *
 * ┄┄ چرا مرحله‌به‌مرحله ┄┄
 *
 * روی هاست اشتراکی تایم‌اوت PHP ۳۰ تا ۶۰ ثانیه است و دانلود + استخراج + مهاجرت
 * + پاک‌سازی کش در یک ریکوئست جا نمی‌شود. پس هر مرحله متد جداست، وضعیتش در
 * `module_installations` می‌نشیند و ریکوئست بعدی از همان‌جا ادامه می‌دهد —
 * همان کاری که به‌روزرسان وردپرس می‌کند. روی VPS هم همین `advance()` را
 * می‌شود داخل یک Job صف پشت سر هم صدا زد.
 *
 * ┄┄ چیزی که در همین ریکوئست اتفاق نمی‌افتد ┄┄
 *
 * پرووایدر ماژول تازه در ریکوئستِ نصب بوت **نمی‌شود**. هیچ‌جای این کلاس نباید
 * فرض کند کلاس‌های ماژول تازه در دسترس‌اند؛ به‌همین‌دلیل انتشار دارایی‌ها هم
 * به `vendor:publish` تکیه نمی‌کند و خودش از `resources/publishes` کپی می‌کند.
 */
class Installer
{
    /**
     * ترتیب مراحل نصب و برچسب فارسی‌شان (همان چیزی که در نوار پیشرفت دیده می‌شود).
     */
    public const STEPS = [
        'download' => 'دریافت بسته',
        'verify'   => 'بررسی امضا و چکسام',
        'check'    => 'بررسی سازگاری',
        'extract'  => 'باز کردن فایل‌ها',
        'record'   => 'ثبت در فهرست ماژول‌ها',
        'migrate'  => 'اجرای مهاجرت دیتابیس',
        'publish'  => 'انتشار دارایی‌ها',
        'flush'    => 'پاک‌سازی کش',
    ];

    /* ---------------------------------------------------------------------
     | شروع و پیش‌بردن یک نصب
     * -------------------------------------------------------------------*/

    /**
     * ساخت یک اجرای نصب. هنوز هیچ فایلی جابه‌جا نشده؛ با `advance()` جلو می‌رود.
     *
     * @param  string  $source  `manual` (zip آپلودشده) یا `repository`
     * @param  array  $payload  برای manual: `archive`؛ برای repository: `slug`
     */
    public function start(string $source, array $payload = [], array $admin = []): ModuleInstallation
    {
        return ModuleInstallation::create([
            'module'     => $payload['module'] ?? ($payload['slug'] ?? '—'),
            'source'     => $source,
            'action'     => 'install',
            'step'       => array_key_first(self::STEPS),
            'status'     => 'pending',
            'payload'    => $payload,
            'admin_id'   => $admin['id'] ?? null,
            'admin_name' => $admin['name'] ?? null,
        ]);
    }

    /**
     * اجرای مرحلهٔ جاری و رفتن به مرحلهٔ بعد.
     *
     * شکست هر مرحله، اجرا را `failed` می‌کند و اگر فایل‌ها جابه‌جا شده بودند
     * نسخهٔ قبلی برمی‌گردد؛ سایت نباید با ماژول نیمه‌کاره بماند.
     */
    public function advance(ModuleInstallation $run): ModuleInstallation
    {
        if ($run->isFinished()) {
            return $run;
        }

        $step = $run->step;

        if (! isset(self::STEPS[$step])) {
            return $this->finish($run);
        }

        $run->status = 'running';
        $run->save();

        try {
            $this->{$step}($run);
        } catch (Throwable $exception) {
            $this->rollback($run);

            $run->status = 'failed';
            $run->error  = $exception->getMessage();
            $run->save();

            return $run;
        }

        $steps = array_keys(self::STEPS);
        $next  = $steps[array_search($step, $steps, true) + 1] ?? null;

        if ($next === null) {
            return $this->finish($run);
        }

        $run->step   = $next;
        $run->status = 'pending';
        $run->save();

        return $run;
    }

    /**
     * اجرای همهٔ مراحل پشت سر هم — برای دستورات artisan و صف روی VPS.
     */
    public function run(ModuleInstallation $run, ?callable $onStep = null): ModuleInstallation
    {
        while (! $run->isFinished()) {
            $step = $run->step;

            $run = $this->advance($run);

            if ($onStep) {
                $onStep($step, $run);
            }
        }

        return $run;
    }

    /* ---------------------------------------------------------------------
     | مراحل
     * -------------------------------------------------------------------*/

    /**
     * ۱ — رساندن zip به `storage/app/marketplace/tmp`.
     *
     * برای zip آپلودشده کاری جز بررسی وجود فایل نیست؛ کنترلر پیش از ساخت اجرا
     * آن را جابه‌جا کرده است.
     */
    protected function download(ModuleInstallation $run): void
    {
        if ($run->source !== 'repository') {
            if (! is_file((string) $run->payload('archive'))) {
                throw new PackageException('فایل بستهٔ آپلودشده پیدا نشد؛ احتمالاً پوشهٔ موقت پاک شده است.');
            }

            return;
        }

        $base = (string) config('marketplace.repository_url');

        if (blank($base)) {
            throw new PackageException('آدرس مخزن تنظیم نشده است (MARKETPLACE_URL).');
        }

        /**
         * مبدأ قفل است: فقط دامنهٔ تنظیم‌شده روی HTTPS. آدرس دلخواه از پنل
         * گرفته نمی‌شود، وگرنه این قابلیت به اجرای کد دلخواه تبدیل می‌شود.
         */
        if (! Str::startsWith($base, 'https://') && ! app()->isLocal()) {
            throw new PackageException('آدرس مخزن باید HTTPS باشد.');
        }

        $target = $this->temporaryPath($run).'/package.zip';

        File::ensureDirectoryExists(dirname($target));

        $response = Http::withToken((string) config('marketplace.token'))
            ->withHeaders(RepositoryClient::headers())
            ->timeout(120)
            ->sink($target)
            ->get($this->repositoryUrl('/modules/'.$run->payload('slug').'/download'), array_filter([
                'bagisto' => Core::BAGISTO_VERSION,
                'php'     => PHP_VERSION,
                /** خالی یعنی «جدیدترین سازگار» را خودِ مخزن انتخاب کند. */
                'version' => $run->payload('version'),
            ]));

        if (! $response->successful()) {
            /**
             * بدنهٔ خطا در فایل sink نوشته شده، نه در حافظه؛ پیام مخزن را از
             * همان‌جا درمی‌آوریم تا ادمین دلیل واقعی را ببیند («این دامنه در
             * لایسنس ثبت نشده است») نه فقط یک کد وضعیت.
             */
            $message = json_decode((string) @file_get_contents($target), true)['message'] ?? null;

            @unlink($target);

            throw new PackageException(
                $message ?: 'دریافت بسته از مخزن ناموفق بود (کد '.$response->status().').'
            );
        }

        $run->mergePayload([
            'archive'   => $target,
            'checksum'  => $response->header('X-Package-Checksum') ?: $run->payload('checksum'),
            'signature' => $response->header('X-Package-Signature') ?: $run->payload('signature'),
        ]);

        $run->save();
    }

    /**
     * ساخت آدرس یک مسیر روی مخزن — فقط از روی مبدأ و پیشوند تنظیم‌شده.
     */
    protected function repositoryUrl(string $path): string
    {
        return rtrim((string) config('marketplace.repository_url'), '/')
            .'/'.trim((string) config('marketplace.api_prefix', '/api/marketplace'), '/')
            .$path;
    }

    /**
     * ۲ — امضا و چکسام. بدون این مرحله، بقیهٔ مراحل یعنی اجرای کد ناشناس.
     */
    protected function verify(ModuleInstallation $run): void
    {
        $package = Package::open((string) $run->payload('archive'));

        $package->verifyChecksum($run->payload('checksum'));

        if (config('marketplace.require_signature.'.$run->source, true)) {
            $package->verifySignature($run->payload('signature'), (string) config('marketplace.public_key'));
        }

        $manifest = $package->manifest();

        $run->module  = $package->name();
        $run->version = $package->version();

        $run->mergePayload([
            'checksum' => $package->checksum(),
            'manifest' => $manifest,
        ]);

        $run->save();
    }

    /**
     * ۳ — نسخهٔ بجیستو، نسخهٔ PHP و پیش‌نیازها.
     */
    protected function check(ModuleInstallation $run): void
    {
        $manifest = (array) $run->payload('manifest', []);

        if (! VersionConstraint::satisfies(PHP_VERSION, $manifest['requires_php'] ?? null)) {
            throw new PackageException(sprintf(
                'این ماژول به PHP %s نیاز دارد و نسخهٔ این سرور %s است.',
                $manifest['requires_php'],
                PHP_VERSION
            ));
        }

        if (! VersionConstraint::satisfies(Core::BAGISTO_VERSION, $manifest['requires_bagisto'] ?? null)) {
            throw new PackageException(sprintf(
                'این ماژول به بجیستو %s نیاز دارد و نسخهٔ این فروشگاه %s است.',
                $manifest['requires_bagisto'],
                Core::BAGISTO_VERSION
            ));
        }

        $missing = [];

        foreach ((array) ($manifest['requires'] ?? []) as $requirement) {
            $installed = InstalledModule::query()->where('name', $requirement)->first();

            if (! $installed?->enabled) {
                $missing[] = $requirement;
            }
        }

        if ($missing) {
            throw new PackageException('این ماژول به ماژول‌های زیر نیاز دارد که نصب یا فعال نیستند: '.implode('، ', $missing));
        }

        /**
         * نصب روی نسخهٔ جدیدتر معمولاً اشتباه است (بازگرداندن نسخهٔ قدیمی روی
         * دیتابیسی که مهاجرت‌های جدیدتر خورده)، پس فقط با تأیید صریح.
         */
        $current = InstalledModule::query()->where('name', $run->module)->first();

        if ($current) {
            $run->action = 'update';
            $run->save();

            if (version_compare($run->version, $current->version, '<') && ! $run->payload('allow_downgrade')) {
                throw new PackageException(sprintf(
                    'نسخهٔ نصب‌شده (%s) جدیدتر از بستهٔ فعلی (%s) است. برای بازگرداندن نسخهٔ قدیمی‌تر باید صریحاً تأیید کنید.',
                    $current->version,
                    $run->version
                ));
            }
        }
    }

    /**
     * ۴ — استخراج در پوشهٔ موقت و جابه‌جایی اتمیک به `Modules/{Name}`.
     *
     * ترتیب عمداً این است: تا وقتی فایل‌های تازه سالم روی دیسک نیامده‌اند،
     * ماژول فعلی دست نمی‌خورد. اگر مقصد از قبل هست، اول یک zip بکاپ ساخته
     * می‌شود و بعد پوشهٔ قبلی به یک نام نقطه‌دار (نامرئی برای رجیستری) کنار
     * می‌رود تا در صورت شکست برگردد.
     */
    protected function extract(ModuleInstallation $run): void
    {
        $package = Package::open((string) $run->payload('archive'));

        $staged = $package->extractTo($this->temporaryPath($run).'/extracted');

        $target = ModuleRegistry::path($run->module);

        if (is_dir($target)) {
            $backup = $this->backup($run->module);

            $parked = dirname($target).'/.'.$run->module.'.old-'.$run->id;

            if (! @rename($target, $parked)) {
                throw new PackageException('پوشهٔ ماژول فعلی قابل جابه‌جایی نیست؛ دسترسی نوشتن روی Modules/ را بررسی کنید.');
            }

            $run->mergePayload(['backup' => $backup, 'parked' => $parked]);
        }

        if (! @rename($staged, $target)) {
            throw new PackageException('انتقال فایل‌های ماژول به Modules/ شکست خورد.');
        }

        /**
         * نسخهٔ کنارگذاشته‌شده تا **پایان کل نصب** می‌ماند، نه تا پایان همین
         * مرحله: مهاجرت یا انتشار دارایی‌ها هم می‌توانند شکست بخورند و آن‌وقت
         * تنها راه برگرداندن سایت همین پوشه است. پاک‌کردنش کار `finish()` است.
         */
        $run->save();
    }

    /**
     * ۵ — ثبت وضعیت. از این لحظه رجیستری ماژول را می‌بیند.
     */
    protected function record(ModuleInstallation $run): void
    {
        /**
         * وضعیت قبلی نگه داشته می‌شود تا اگر مرحله‌ای بعد از این شکست خورد،
         * `rollback()` بتواند ردیف را هم مثل فایل‌ها برگرداند.
         */
        $previous = InstalledModule::query()->where('name', $run->module)->first();

        $run->mergePayload([
            'recorded' => true,
            'previous' => $previous?->only(['version', 'enabled', 'source', 'meta']),
        ]);

        $run->save();

        InstalledModule::query()->updateOrCreate(['name' => $run->module], [
            'version'      => $run->version,
            'enabled'      => true,
            'source'       => $run->source,
            'installed_at' => now(),
            'meta'         => [
                'checksum'  => $run->payload('checksum'),
                'slug'      => $run->payload('slug'),
                'license'   => $run->payload('license'),
                'backup'    => $run->payload('backup'),
            ],
        ]);

        ModuleRegistry::flush();
    }

    /**
     * ۶ — مهاجرت‌های خود ماژول.
     *
     * `Artisan::call` داخل همان ریکوئست اجرا می‌شود و به شل نیاز ندارد؛ همین
     * چیزی است که پشتیبانی از هاست اشتراکی را ممکن می‌کند.
     */
    protected function migrate(ModuleInstallation $run): void
    {
        $path = 'Modules/'.$run->module.'/Database/migrations';

        if (! is_dir(base_path($path))) {
            return;
        }

        Artisan::call('migrate', [
            '--force' => true,
            '--path'  => $path,
        ]);

        $run->mergePayload(['migrate_output' => trim(Artisan::output())]);
        $run->save();
    }

    /**
     * ۷ — بردن دارایی‌ها به `public/`.
     *
     * `vendor:publish` فقط تگ‌هایی را می‌شناسد که پرووایدرِ **بوت‌شده** ثبت
     * کرده باشد و پرووایدر ماژول تازه هنوز بوت نشده، پس مسیر اصلی کپی مستقیم
     * از `resources/publishes` است. `vendor:publish` فقط به‌عنوان مسیر دوم و
     * برای به‌روزرسانی ماژولی که از قبل بوت شده اجرا می‌شود.
     */
    protected function publish(ModuleInstallation $run): void
    {
        $manifest = (array) $run->payload('manifest', []);

        $source = ModuleRegistry::path($run->module).'/resources/publishes';

        if (is_dir($source)) {
            $destination = public_path('vendor/'.($manifest['alias'] ?? Str::kebab($run->module)));

            File::ensureDirectoryExists($destination);

            File::copyDirectory($source, $destination);
        }

        if ($tag = ($manifest['assets_tag'] ?? null)) {
            try {
                Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);
            } catch (Throwable) {
                // تگ هنوز ثبت نشده — طبیعی است و کپی بالا کار را کرده.
            }
        }
    }

    /**
     * ۸ — کش‌ها.
     *
     * `route:cache` عمداً اجرا نمی‌شود: روت‌های ماژول تازه تنها وقتی ثبت
     * می‌شوند که پرووایدرش بوت شده باشد، و آن ریکوئستِ بعدی است. کش روت را
     * `optimize:clear` پاک می‌کند و اگر روی سرور از آن استفاده می‌کنید، بعد از
     * نصب یک‌بار `deploy/art route:cache` بزنید.
     */
    protected function flush(ModuleInstallation $run): void
    {
        $hadConfigCache = is_file(base_path('bootstrap/cache/config.php'));

        Artisan::call('optimize:clear');

        ModuleRegistry::flush();

        /**
         * کش کانفیگ/ویو فقط اگر از قبل روشن بوده دوباره ساخته می‌شود؛ روشن‌کردنش
         * روی نصبی که کش نداشته یعنی از این به بعد `.env` خوانده نمی‌شود و این
         * تغییر رفتار نباید عارضهٔ جانبی نصب یک ماژول باشد.
         */
        if ($hadConfigCache) {
            Artisan::call('config:cache');
            Artisan::call('view:cache');
        }

        if (function_exists('opcache_reset') && ini_get('opcache.enable')) {
            @opcache_reset();
        }
    }

    /* ---------------------------------------------------------------------
     | فعال، غیرفعال، حذف
     * -------------------------------------------------------------------*/

    /**
     * فعال‌سازی — برگشت‌پذیر و بی‌خطر؛ فقط یک بیت و باطل‌کردن کش.
     */
    public function enable(string $name): void
    {
        $module = $this->requireInstalled($name);

        foreach ((array) (ModuleRegistry::manifest($name)['requires'] ?? []) as $requirement) {
            $dependency = InstalledModule::query()->where('name', $requirement)->first();

            if (! $dependency?->enabled) {
                throw new PackageException("ماژول «{$name}» به «{$requirement}» نیاز دارد که فعال نیست.");
            }
        }

        $module->update(['enabled' => true]);

        ModuleRegistry::flush();
    }

    /**
     * غیرفعال‌سازی. ماژول‌هایی که چیز دیگری به آن‌ها وابسته است بدون تأیید
     * غیرفعال نمی‌شوند، وگرنه رجیستری وابسته‌ها را هم بی‌صدا کنار می‌گذارد.
     */
    public function disable(string $name, bool $force = false): array
    {
        $module = $this->requireInstalled($name);

        if (in_array($name, (array) config('marketplace.protected_modules', []), true)) {
            throw new PackageException("ماژول «{$name}» محافظت‌شده است و غیرفعال نمی‌شود.");
        }

        $dependents = $this->dependents($name);

        if ($dependents && ! $force) {
            throw new PackageException(
                'این ماژول‌ها به «'.$name.'» وابسته‌اند و با غیرفعال‌شدنش بوت نمی‌شوند: '.implode('، ', $dependents)
            );
        }

        $module->update(['enabled' => false]);

        ModuleRegistry::flush();

        return $dependents;
    }

    /**
     * حذف کامل — پوشه، ردیف و (در صورت درخواست) مهاجرت‌ها.
     *
     * رول‌بک مهاجرت یعنی رفتن داده‌ها، پس پیش‌فرض خاموش است و باید صریحاً
     * خواسته شود.
     */
    public function remove(string $name, bool $rollbackMigrations = false, array $admin = []): ModuleInstallation
    {
        $this->requireInstalled($name);

        if (in_array($name, (array) config('marketplace.protected_modules', []), true)) {
            throw new PackageException("ماژول «{$name}» محافظت‌شده است و حذف نمی‌شود.");
        }

        if (in_array($name, (array) config('marketplace.locked_modules', []), true)) {
            throw new PackageException(
                "ماژول «{$name}» هستهٔ مشترک با پروژهٔ دیگری است و فایل‌هایش مال این پروژه نیست؛ از پنل حذف نمی‌شود."
            );
        }

        if ($dependents = $this->dependents($name)) {
            throw new PackageException(
                'اول این ماژول‌ها را غیرفعال کنید؛ به «'.$name.'» وابسته‌اند: '.implode('، ', $dependents)
            );
        }

        $log = ModuleInstallation::create([
            'module'     => $name,
            'version'    => InstalledModule::query()->where('name', $name)->value('version'),
            'source'     => InstalledModule::query()->where('name', $name)->value('source') ?: 'manual',
            'action'     => 'remove',
            'status'     => 'running',
            'admin_id'   => $admin['id'] ?? null,
            'admin_name' => $admin['name'] ?? null,
        ]);

        try {
            $backup = $this->backup($name);

            if ($rollbackMigrations && is_dir(base_path('Modules/'.$name.'/Database/migrations'))) {
                Artisan::call('migrate:rollback', [
                    '--force' => true,
                    '--path'  => 'Modules/'.$name.'/Database/migrations',
                ]);
            }

            File::deleteDirectory(ModuleRegistry::path($name));

            InstalledModule::query()->where('name', $name)->delete();

            ModuleRegistry::flush();

            Artisan::call('optimize:clear');

            $log->update([
                'status'  => 'completed',
                'payload' => ['backup' => $backup, 'rolled_back' => $rollbackMigrations],
            ]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error' => $exception->getMessage()]);

            throw $exception;
        }

        return $log;
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    /**
     * ماژول‌های فعالی که این ماژول را در `requires` خود دارند.
     *
     * @return array<int, string>
     */
    public function dependents(string $name): array
    {
        $dependents = [];

        foreach (ModuleRegistry::all() as $module) {
            if ($module['name'] !== $name && $module['enabled'] && in_array($name, $module['requires'], true)) {
                $dependents[] = $module['name'];
            }
        }

        return $dependents;
    }

    /**
     * برگرداندن وضعیت قبلی وقتی نصب وسط کار شکست خورده است.
     *
     * فایل‌ها از پوشهٔ کنارگذاشته‌شده و ردیف وضعیت از `payload.previous` برمی‌گردند.
     * چیزی که برنمی‌گردد مهاجرت‌های نیمه‌اجراشده است — رول‌بک خودکارشان یعنی
     * حذف داده، پس ترجیح این است که سایت با نسخهٔ قبلی سرپا بماند و خطا به
     * ادمین گزارش شود تا خودش تصمیم بگیرد.
     */
    protected function rollback(ModuleInstallation $run): void
    {
        if (($parked = $run->payload('parked')) && is_dir($parked)) {
            $target = ModuleRegistry::path($run->module);

            File::deleteDirectory($target);

            @rename($parked, $target);

            $run->mergePayload(['parked' => null, 'restored' => true]);
        }

        if ($run->payload('recorded')) {
            $module = InstalledModule::query()->where('name', $run->module)->first();

            if ($previous = $run->payload('previous')) {
                $module?->fill($previous)->save();
            } else {
                /** ماژول قبلاً نبود، پس ردیفی هم که همین اجرا ساخته باید برود. */
                $module?->delete();
            }
        }

        ModuleRegistry::flush();
    }

    /**
     * بستن zip از وضعیت فعلی یک ماژول، پیش از هر تغییر برگشت‌ناپذیر.
     */
    protected function backup(string $name): ?string
    {
        $source = ModuleRegistry::path($name);

        if (! is_dir($source)) {
            return null;
        }

        $version = InstalledModule::query()->where('name', $name)->value('version') ?: '0.0.0';

        $path = storage_path('app/marketplace/backups/'.$name.'-'.$version.'-'.now()->format('Ymd_His').'.zip');

        File::ensureDirectoryExists(dirname($path));

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PackageException('ساخت بکاپ ماژول ممکن نشد؛ دسترسی نوشتن روی storage را بررسی کنید.');
        }

        foreach (File::allFiles($source, true) as $file) {
            $zip->addFile($file->getPathname(), $name.'/'.$file->getRelativePathname());
        }

        $zip->close();

        return $path;
    }

    protected function temporaryPath(ModuleInstallation $run): string
    {
        return storage_path('app/marketplace/tmp/'.$run->id);
    }

    /**
     * پایان موفق: پاک‌کردن فایل‌های موقت این اجرا.
     */
    protected function finish(ModuleInstallation $run): ModuleInstallation
    {
        File::deleteDirectory($this->temporaryPath($run));

        /** حالا که همهٔ مراحل رد شده‌اند، نسخهٔ قبلی دیگر لازم نیست. */
        if ($parked = $run->payload('parked')) {
            File::deleteDirectory($parked);

            $run->mergePayload(['parked' => null]);
        }

        if (($archive = $run->payload('archive')) && str_starts_with((string) $archive, storage_path('app/marketplace'))) {
            @unlink($archive);
        }

        $run->step   = null;
        $run->status = 'completed';
        $run->save();

        return $run;
    }

    protected function requireInstalled(string $name): InstalledModule
    {
        $module = InstalledModule::query()->where('name', $name)->first();

        if (! $module) {
            throw new PackageException("ماژول «{$name}» در فهرست ماژول‌های نصب‌شده نیست.");
        }

        return $module;
    }
}
