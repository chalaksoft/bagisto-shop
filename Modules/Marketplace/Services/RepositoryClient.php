<?php

namespace Modules\Marketplace\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Marketplace\Model\RepositoryCredential;
use Throwable;
use Webkul\Core\Core;

/**
 * گفت‌وگو با سایت مخزن.
 *
 * مبدأ از `config('marketplace.repository_url')` می‌آید و در پنل قابل ویرایش
 * نیست — اگر ادمین بتواند آدرس دلخواه بدهد، این قابلیت به «هر کد PHP دلخواهی
 * را دانلود و اجرا کن» تبدیل می‌شود.
 *
 * هیچ متدی استثنا پرتاب نمی‌کند: مخزن ممکن است پایین باشد و صفحهٔ «ماژول‌های
 * نصب‌شده» نباید به‌خاطر آن ۵۰۰ بدهد. خطا به‌صورت `error` در خروجی برمی‌گردد.
 */
class RepositoryClient
{
    /** فهرست ماژول‌ها چند دقیقه کش می‌شود تا هر بازکردن صفحه یک ریکوئست بیرونی نباشد. */
    protected const CACHE_TTL = 300;

    protected const CACHE_KEY = 'marketplace.repository.modules';

    public function isConfigured(): bool
    {
        return filled(config('marketplace.repository_url'));
    }

    public function hasToken(): bool
    {
        return filled($this->token());
    }

    /**
     * توکنی که در هدر `Authorization` می‌رود.
     *
     * ثبت‌نام مقدم بر `.env` است: `MARKETPLACE_TOKEN` معمولاً از یک نصب قدیمی
     * یا فایل کپی‌شده مانده و ممکن است اصلاً روی این مخزن وجود نداشته باشد؛
     * اگر مقدم می‌شد، ادمین بعد از ثبت‌نام موفق باز هم «لایسنس معتبر نیست»
     * می‌دید و راهی هم برای درستش‌کردن بدون SSH نداشت.
     */
    public function token(): ?string
    {
        return $this->credential()?->token ?: ((string) config('marketplace.token') ?: null);
    }

    /** توکن از `.env` آمده یا از ثبت‌نام؟ */
    public function tokenFromEnv(): bool
    {
        return ! $this->credential() && filled(config('marketplace.token'));
    }

    public function credential(): ?RepositoryCredential
    {
        return RepositoryCredential::current();
    }

    /**
     * ثبت‌نام این فروشگاه در مخزن و گرفتن لایسنس مقید به همین دامنه.
     *
     * توکن خام فقط در همین یک پاسخ برمی‌گردد، پس همان‌جا ذخیره می‌شود؛ اگر گم
     * شود، ثبت‌نام دوباره جوابی نمی‌دهد و باید از مخزن توکن تازه صادر شود.
     *
     * @return array{ok: bool, message: string}
     */
    public function register(array $data): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'آدرس مخزن تنظیم نشده است (MARKETPLACE_URL).'];
        }

        $domain = $this->domain();

        if (blank($domain)) {
            return ['ok' => false, 'message' => 'دامنهٔ فروشگاه از APP_URL خوانده نشد؛ اول آن را درست کنید.'];
        }

        try {
            $response = Http::withHeaders(static::headers())
                ->timeout(15)
                ->post($this->url($this->prefix().'/register'), [
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'] ?? '',
                    'email'      => $data['email'],
                    'domain'     => $domain,
                ]);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'ارتباط با مخزن برقرار نشد: '.$exception->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'ok'      => false,
                'message' => $response->json('message')
                    ?: 'ثبت‌نام ناموفق بود؛ مخزن با کد '.$response->status().' پاسخ داد.',
            ];
        }

        $body = (array) $response->json();

        if (blank($body['token'] ?? null)) {
            return ['ok' => false, 'message' => 'مخزن توکنی برنگرداند.'];
        }

        RepositoryCredential::create([
            'token'         => $body['token'],
            'domain'        => $body['domain'] ?? $domain,
            'email'         => $body['email'] ?? $data['email'],
            'customer_name' => $body['customer'] ?? trim($data['first_name'].' '.($data['last_name'] ?? '')),
            'label'         => $body['label'] ?? null,
            'expires_at'    => $body['expires_at'] ?? null,
            'registered_at' => now(),
        ]);

        /** فهرست کش‌شده با توکن قبلی (یا بدون توکن) گرفته شده بود. */
        $this->flush();

        return ['ok' => true, 'message' => $body['message'] ?? 'فروشگاه در مخزن ثبت شد.'];
    }

    /**
     * دور انداختن توکن ثبت‌نام — لایسنس در مخزن دست‌نخورده می‌ماند.
     */
    public function forgetCredential(): void
    {
        RepositoryCredential::query()->delete();

        $this->flush();
    }

    /**
     * فهرست ماژول‌های مخزن.
     *
     * @return array{modules: array, license: array, error: ?string}
     */
    public function modules(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $response = $this->get($this->prefix().'/modules', [
                'bagisto' => Core::BAGISTO_VERSION,
                'php'     => PHP_VERSION,
            ]);

            if ($response['error']) {
                return ['modules' => [], 'license' => [], 'error' => $response['error']];
            }

            return [
                'modules' => $response['body']['data'] ?? [],
                'license' => $response['body']['meta']['license'] ?? [],
                'error'   => null,
            ];
        });
    }

    /**
     * جزئیات و تاریخچهٔ نسخه‌های یک ماژول.
     */
    public function module(string $slug): array
    {
        $response = $this->get($this->prefix().'/modules/'.$slug, [
            'bagisto' => Core::BAGISTO_VERSION,
            'php'     => PHP_VERSION,
        ]);

        return [
            'module' => $response['body']['data'] ?? null,
            'error'  => $response['error'],
        ];
    }

    /**
     * وضعیت لایسنس این نصب.
     */
    public function license(): array
    {
        if (! $this->hasToken()) {
            return ['valid' => false, 'reason' => 'این فروشگاه هنوز در مخزن ثبت نشده است.'];
        }

        $response = $this->request()->post($this->url($this->prefix().'/license/check'), [
            'domain' => $this->domain(),
        ]);

        try {
            return $response->json() ?: ['valid' => false, 'reason' => 'پاسخ مخزن قابل خواندن نبود.'];
        } catch (Throwable $exception) {
            return ['valid' => false, 'reason' => $exception->getMessage()];
        }
    }

    /**
     * ماژول‌های نصب‌شده‌ای که مخزن نسخهٔ جدیدتری برایشان دارد.
     *
     * کلید خروجی نام پوشه است (`package_name`)، چون همان چیزی است که در
     * `installed_modules.name` نشسته.
     *
     * @return array<string, array{slug: string, version: string, changelog: ?string, available: bool}>
     */
    public function updatesFor(iterable $installed): array
    {
        $catalogue = $this->modules();

        if ($catalogue['error']) {
            return [];
        }

        $byPackage = collect($catalogue['modules'])->keyBy('package_name');

        $updates = [];

        foreach ($installed as $name => $version) {
            $module = $byPackage->get($name);

            $latest = $module['latest_version']['version'] ?? null;

            if ($latest && version_compare($latest, (string) $version, '>')) {
                $updates[$name] = [
                    'slug'      => $module['slug'],
                    'version'   => $latest,
                    'changelog' => $module['latest_version']['changelog'] ?? null,
                    /** ماژولی که لایسنسش را ندارید به‌روزرسانی هم نمی‌گیرد. */
                    'available' => (bool) ($module['available'] ?? false),
                ];
            }
        }

        return $updates;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * دامنه‌ای که به مخزن اعلام می‌شود — پایهٔ قید دامنهٔ لایسنس.
     */
    public function domain(): string
    {
        return Str::of((string) config('app.url'))
            ->replaceMatches('#^https?://#', '')
            ->before('/')
            ->before(':')
            ->replaceMatches('#^www\.#', '')
            ->lower()
            ->toString();
    }

    /**
     * هدرهای مشترک هر درخواست — همان‌هایی که `Installer::download()` هم
     * می‌فرستد.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return array_filter([
            'X-Shop-Domain'     => app(self::class)->domain(),
            'X-Bagisto-Version' => Core::BAGISTO_VERSION,
            'X-PHP-Version'     => PHP_VERSION,
            'Accept'            => 'application/json',
        ]);
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    /**
     * @return array{body: ?array, error: ?string}
     */
    protected function get(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            return ['body' => null, 'error' => 'آدرس مخزن تنظیم نشده است (MARKETPLACE_URL).'];
        }

        try {
            $response = $this->request()->get($this->url($path), $query);
        } catch (Throwable $exception) {
            return ['body' => null, 'error' => 'ارتباط با مخزن برقرار نشد: '.$exception->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'body'  => null,
                'error' => $response->json('message') ?: 'مخزن با کد '.$response->status().' پاسخ داد.',
            ];
        }

        return ['body' => $response->json(), 'error' => null];
    }

    /**
     * پیشوند API مخزن، بدون اسلش انتهایی.
     */
    protected function prefix(): string
    {
        return '/'.trim((string) config('marketplace.api_prefix', '/api/marketplace'), '/');
    }

    protected function request(): PendingRequest
    {
        return Http::withToken((string) $this->token())
            ->withHeaders(static::headers())
            ->timeout(15);
    }

    /**
     * ساخت آدرس فقط از روی مبدأ تنظیم‌شده.
     *
     * روی محیط غیرمحلی HTTPS اجباری است: بستهٔ کد روی HTTP یعنی هر کسی وسط راه
     * می‌تواند جایش را عوض کند و امضا هم تنها وقتی کمک می‌کند که واقعاً بررسی
     * شود.
     */
    protected function url(string $path): string
    {
        $base = rtrim((string) config('marketplace.repository_url'), '/');

        if (! Str::startsWith($base, 'https://') && ! app()->isLocal()) {
            throw new PackageException('آدرس مخزن باید HTTPS باشد.');
        }

        return $base.$path;
    }
}
