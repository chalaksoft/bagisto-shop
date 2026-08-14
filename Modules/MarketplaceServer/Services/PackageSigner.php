<?php

namespace Modules\MarketplaceServer\Services;

use Modules\Marketplace\Services\PackageException;
use OpenSSLAsymmetricKey;

/**
 * امضای بسته با کلید خصوصی مخزن.
 *
 * امضا **موقع انتشار** ساخته و ذخیره می‌شود، نه موقع دانلود: کلید خصوصی نباید
 * در مسیر داغِ هر ریکوئست باز شود، و امضای ذخیره‌شده یعنی دانلود حتی وقتی کلید
 * موقتاً در دسترس نیست هم کار می‌کند.
 *
 * الگوریتم `sha256WithRSA` روی **بایت‌های خام فایل zip** — دقیقاً همان چیزی که
 * `Modules\Marketplace\Services\Package::verifySignature()` سمت خریدار بررسی
 * می‌کند.
 */
class PackageSigner
{
    public function sign(string $archivePath): string
    {
        if (! is_file($archivePath)) {
            throw new PackageException('فایل بسته برای امضا پیدا نشد.');
        }

        $signature = '';

        if (! openssl_sign(file_get_contents($archivePath), $signature, $this->privateKey(), OPENSSL_ALGO_SHA256)) {
            throw new PackageException('امضای بسته انجام نشد: '.openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * بررسی اینکه امضای ذخیره‌شده هنوز با فایل می‌خواند.
     *
     * خوراک `marketplace:verify` است: اگر فایلی روی دیسک عوض شده باشد، بهتر
     * است خودمان بفهمیم تا اینکه فروشگاه مشتری با «امضا معتبر نیست» روبه‌رو شود.
     */
    public function verify(string $archivePath, string $signature): bool
    {
        $key = openssl_pkey_get_public((string) @file_get_contents(config('marketplace-server.public_key')));

        if ($key === false) {
            throw new PackageException('کلید عمومی مخزن خوانده نشد.');
        }

        $binary = base64_decode($signature, true);

        return $binary !== false && openssl_verify(
            file_get_contents($archivePath),
            $binary,
            $key,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    public function hasKeys(): bool
    {
        return is_file(config('marketplace-server.private_key'))
            && is_file(config('marketplace-server.public_key'));
    }

    protected function privateKey(): OpenSSLAsymmetricKey
    {
        $path = config('marketplace-server.private_key');

        if (! is_file($path)) {
            throw new PackageException(
                'کلید خصوصی مخزن پیدا نشد. یک‌بار `php artisan marketplace:keys` را اجرا کنید.'
            );
        }

        $key = openssl_pkey_get_private(
            (string) file_get_contents($path),
            config('marketplace-server.private_key_passphrase') ?: ''
        );

        if ($key === false) {
            throw new PackageException('کلید خصوصی مخزن خوانده نشد؛ رمزش را بررسی کنید.');
        }

        return $key;
    }
}
