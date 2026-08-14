<?php

namespace Modules\MarketplaceServer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * ساخت جفت‌کلید امضای مخزن.
 *
 * یک‌بار روی این نصب اجرا می‌شود. کلید عمومی به ریپوی هر فروشگاه خریدار می‌رود
 * (`Modules/Marketplace/resources/keys/repository.pub`) و کلید خصوصی همین‌جا
 * می‌ماند.
 */
class GenerateKeysCommand extends Command
{
    protected $signature = 'marketplace:keys {--force : بازنویسی کلید موجود}';

    protected $description = 'ساخت جفت‌کلید RSA برای امضای بسته‌های مخزن';

    public function handle(): int
    {
        $private = config('marketplace-server.private_key');
        $public  = config('marketplace-server.public_key');

        if (is_file($private) && ! $this->option('force')) {
            $this->error('کلید خصوصی از قبل وجود دارد: '.$private);
            $this->line('  بازنویسی‌اش یعنی همهٔ بسته‌های منتشرشده با کلید قبلی روی فروشگاه‌های مشتری رد می‌شوند.');
            $this->line('  اگر واقعاً می‌خواهید، --force بزنید و بعد همهٔ نسخه‌ها را دوباره منتشر کنید.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($private));

        $resource = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->error('ساخت کلید شکست خورد: '.openssl_error_string());

            return self::FAILURE;
        }

        openssl_pkey_export($resource, $privateKey, config('marketplace-server.private_key_passphrase') ?: null);

        file_put_contents($private, $privateKey);
        file_put_contents($public, openssl_pkey_get_details($resource)['key']);

        /** کلید خصوصی نباید حتی برای گروه خواندنی باشد. */
        chmod($private, 0600);
        chmod($public, 0644);

        $this->info('جفت‌کلید ساخته شد.');
        $this->line('  خصوصی: '.$private.'  (روی همین سرور بماند)');
        $this->line('  عمومی: '.$public);
        $this->newLine();
        $this->line('کلید عمومی را در ریپوی هر فروشگاه خریدار اینجا بگذارید:');
        $this->line('  Modules/Marketplace/resources/keys/repository.pub');

        return self::SUCCESS;
    }
}
