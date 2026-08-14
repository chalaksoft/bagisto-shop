<?php

namespace Modules\Marketplace\Services;

use ZipArchive;

/**
 * یک فایل zip ماژول، پیش از آنکه چیزی از آن روی دیسک باز شود.
 *
 * قرارداد بسته (بخش ۶ طرح): zip دقیقاً یک پوشه در ریشه دارد، هم‌نام ماژول، و
 * داخلش `module.json` و `ModuleServiceProvider.php`.
 *
 * همهٔ اعتبارسنجی‌ها روی فهرست ورودی‌های zip انجام می‌شود، نه بعد از استخراج:
 * یک ورودی با نام `../../.env` را باید قبل از نوشته‌شدن گرفت، نه بعدش.
 */
class Package
{
    /**
     * سقف حجم بازشده و نسبت فشرده‌سازی، برای zip bomb.
     *
     * ۵۱۲ مگابایت از هر ماژول واقعی (حتی با `vendor/` بسته‌بندی‌شده) خیلی
     * بزرگ‌تر است؛ نسبت ۲۰۰ به ۱ هم از هر آرشیو کد معمولی بالاتر است.
     */
    protected const MAX_EXTRACTED_BYTES = 536870912;

    protected const MAX_COMPRESSION_RATIO = 200;

    protected ?array $manifest = null;

    protected ?string $root = null;

    public function __construct(protected string $archive) {}

    public static function open(string $archive): self
    {
        if (! is_file($archive) || ! is_readable($archive)) {
            throw new PackageException('فایل بسته پیدا نشد یا خواندنی نیست.');
        }

        return new self($archive);
    }

    public function path(): string
    {
        return $this->archive;
    }

    /**
     * چکسام بسته — همان چیزی که مخزن هم برمی‌گرداند و در لاگ ثبت می‌شود.
     */
    public function checksum(): string
    {
        return hash_file('sha256', $this->archive);
    }

    /**
     * نام ماژول = نام تنها پوشهٔ ریشهٔ zip.
     */
    public function name(): string
    {
        return $this->root ??= $this->validateStructure();
    }

    /**
     * `module.json` داخل بسته، بدون استخراج.
     */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $zip = $this->archive();

        $contents = $zip->getFromName($this->name().'/module.json');

        $zip->close();

        $manifest = json_decode((string) $contents, true);

        if (! is_array($manifest)) {
            throw new PackageException('فایل module.json داخل بسته وجود ندارد یا JSON معتبری نیست.');
        }

        if (($manifest['name'] ?? null) !== $this->name()) {
            throw new PackageException(sprintf(
                'نام داخل module.json («%s») با نام پوشهٔ بسته («%s») یکی نیست.',
                $manifest['name'] ?? '—',
                $this->name()
            ));
        }

        return $this->manifest = $manifest;
    }

    public function version(): string
    {
        return $this->manifest()['version'] ?? '1.0.0';
    }

    /**
     * آیا فایلی با این مسیر داخل بسته هست؟
     *
     * سمت مخزن (`MarketplaceServer`) موقع انتشار با همین متد چک می‌کند که بسته
     * `ModuleServiceProvider.php` دارد یا نه — تا بستهٔ ناقص اصلاً منتشر نشود،
     * نه اینکه سر نصب روی فروشگاه مشتری رد شود.
     */
    public function hasEntry(string $path): bool
    {
        $zip = $this->archive();

        $found = $zip->locateName($this->name().'/'.ltrim($path, '/')) !== false;

        $zip->close();

        return $found;
    }

    /**
     * تأیید امضای دیجیتال بسته با کلید عمومی مخزن.
     *
     * بدون امضای معتبر نصب متوقف می‌شود — نه با هشدار، چون این قابلیت ذاتاً
     * یعنی «کد PHP را از اینترنت بگیر و اجرا کن» و امضا تنها چیزی است که
     * مبدأ را اثبات می‌کند.
     */
    public function verifySignature(?string $signature, string $publicKeyPath): void
    {
        if (blank($signature)) {
            throw new PackageException('بسته امضا ندارد و نصب بستهٔ بدون امضا مجاز نیست.');
        }

        if (! is_file($publicKeyPath)) {
            throw new PackageException('کلید عمومی مخزن روی این نصب موجود نیست؛ امضا قابل بررسی نیست.');
        }

        $key = openssl_pkey_get_public((string) file_get_contents($publicKeyPath));

        if ($key === false) {
            throw new PackageException('کلید عمومی مخزن خوانده نشد.');
        }

        $binary = base64_decode($signature, true);

        if ($binary === false) {
            throw new PackageException('امضای بسته قالب معتبری ندارد.');
        }

        $result = openssl_verify(
            (string) file_get_contents($this->archive),
            $binary,
            $key,
            OPENSSL_ALGO_SHA256
        );

        if ($result !== 1) {
            throw new PackageException('امضای بسته معتبر نیست؛ بسته دستکاری شده یا از مخزن دیگری آمده است.');
        }
    }

    public function verifyChecksum(?string $expected): void
    {
        if (blank($expected)) {
            return;
        }

        if (! hash_equals(strtolower($expected), $this->checksum())) {
            throw new PackageException('چکسام بسته با آنچه مخزن اعلام کرده یکی نیست؛ دانلود ناقص یا دستکاری‌شده است.');
        }
    }

    /**
     * باز کردن بسته در یک پوشهٔ **موقت** — نه مستقیم در `Modules/`.
     *
     * جابه‌جایی به مقصد کار `Installer` است تا اگر استخراج نصفه بماند، ماژول
     * فعلی دست‌نخورده سر جایش باشد.
     */
    public function extractTo(string $destination): string
    {
        $name = $this->name();

        if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
            throw new PackageException('پوشهٔ موقت استخراج ساخته نشد: '.$destination);
        }

        $zip = $this->archive();

        /**
         * فقط ورودی‌های واقعی استخراج می‌شوند. اگر فهرست را ندهیم،
         * `extractTo()` فراداده‌های مک (`__MACOSX/`، `._*`) را هم روی دیسک
         * می‌ریزد و پوشهٔ ماژول را با آشغال پر می‌کند.
         */
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);

            if ($entry !== false && ! static::isMetadata($entry)) {
                $entries[] = $entry;
            }
        }

        $extracted = $zip->extractTo($destination, $entries);

        $zip->close();

        if (! $extracted) {
            throw new PackageException('باز کردن بسته شکست خورد؛ فضای دیسک یا دسترسی نوشتن را بررسی کنید.');
        }

        $path = $destination.'/'.$name;

        foreach (['module.json', 'ModuleServiceProvider.php'] as $required) {
            if (! is_file($path.'/'.$required)) {
                throw new PackageException("بسته ناقص است: فایل $required داخلش نیست.");
            }
        }

        return $path;
    }

    /* ---------------------------------------------------------------------
     | اعتبارسنجی
     * -------------------------------------------------------------------*/

    /**
     * بررسی تک‌تک ورودی‌های zip و برگرداندن نام پوشهٔ ریشه.
     *
     * چیزهایی که رد می‌شوند:
     *   - مسیر مطلق (`/etc/passwd`) یا مسیر ویندوزی (`..\`)
     *   - هر جزء `..` — یعنی نوشتن بیرون از `Modules/{Name}`
     *   - بایت null در نام (کوتاه‌کردن مسیر در توابع سیستمی)
     *   - بیش از یک پوشه در ریشه، یا فایل لخت در ریشه
     *   - حجم بازشده یا نسبت فشرده‌سازی غیرعادی (zip bomb)
     */
    protected function validateStructure(): string
    {
        $zip = $this->archive();

        $root      = null;
        $extracted = 0;
        $compressed = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                $zip->close();

                throw new PackageException('فهرست فایل‌های بسته خوانده نشد؛ آرشیو خراب است.');
            }

            $name = $stat['name'];

            /**
             * فراداده‌های مک نه شمرده می‌شوند نه استخراج. بدون این، هر zip
             * ساخته‌شده با «Compress» فایندر رد می‌شد، چون `__MACOSX/` را
             * پوشهٔ دوم ریشه می‌دیدیم.
             */
            if (static::isMetadata($name)) {
                continue;
            }

            if (str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')) {
                $zip->close();

                throw new PackageException("بسته ورودی با مسیر غیرمجاز دارد: $name");
            }

            $segments = explode('/', trim($name, '/'));

            if (in_array('..', $segments, true)) {
                $zip->close();

                throw new PackageException("بسته تلاش می‌کند بیرون از پوشهٔ خودش بنویسد: $name");
            }

            if (count($segments) < 2 && ! str_ends_with($name, '/')) {
                $zip->close();

                throw new PackageException("بسته باید دقیقاً یک پوشه در ریشه داشته باشد؛ فایل «{$name}» در ریشه است.");
            }

            $root ??= $segments[0];

            if ($segments[0] !== $root) {
                $zip->close();

                throw new PackageException('بسته بیش از یک پوشه در ریشه دارد؛ باید فقط پوشهٔ هم‌نام ماژول باشد.');
            }

            $extracted  += (int) $stat['size'];
            $compressed += (int) $stat['comp_size'];
        }

        $zip->close();

        if ($root === null) {
            throw new PackageException('بسته خالی است.');
        }

        if ($extracted > self::MAX_EXTRACTED_BYTES) {
            throw new PackageException('حجم بازشدهٔ بسته غیرعادی است و نصب نمی‌شود.');
        }

        if ($compressed > 0 && $extracted / $compressed > self::MAX_COMPRESSION_RATIO) {
            throw new PackageException('نسبت فشرده‌سازی بسته غیرعادی است و نصب نمی‌شود.');
        }

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $root)) {
            throw new PackageException("نام ماژول «{$root}» معتبر نیست؛ فقط حروف انگلیسی، رقم و زیرخط مجاز است.");
        }

        return $root;
    }

    /**
     * ورودی‌هایی که فراداده‌اند، نه کد.
     *
     * فایندر مک کنار هر فایل یک «AppleDouble» می‌گذارد: پوشهٔ `__MACOSX/` در
     * ریشه و فایل‌های `._نام`. ویندوز و بعضی ابزارها هم `.DS_Store` و
     * `Thumbs.db` را با خودشان می‌آورند. هیچ‌کدام بخشی از ماژول نیستند و نه
     * باید اعتبارسنجی را بشکنند نه روی دیسک بنشینند.
     */
    protected static function isMetadata(string $entry): bool
    {
        $segments = explode('/', trim($entry, '/'));

        if (($segments[0] ?? null) === '__MACOSX') {
            return true;
        }

        $basename = end($segments) ?: '';

        return str_starts_with($basename, '._')
            || in_array($basename, ['.DS_Store', 'Thumbs.db', 'desktop.ini'], true);
    }

    protected function archive(): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($this->archive, ZipArchive::RDONLY) !== true) {
            throw new PackageException('فایل بسته یک zip معتبر نیست.');
        }

        return $zip;
    }
}
