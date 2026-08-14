<x-admin::layouts>
    <x-slot:title>
        مخزن ماژول‌ها
    </x-slot>

    <div class="flex items-center justify-between">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            مخزن ماژول‌ها
        </p>

        <div class="flex items-center gap-x-2.5">
            <a href="{{ route('admin.marketplace.index') }}" class="secondary-button">
                ماژول‌های نصب‌شده
            </a>

            <a href="{{ route('admin.marketplace.repository', ['refresh' => 1]) }}" class="primary-button">
                تازه‌سازی فهرست
            </a>
        </div>
    </div>

    <p class="mb-3.5 mt-1 text-xs leading-6 text-gray-500">
        دامنه‌ای که به مخزن اعلام می‌شود: <code>{{ $domain }}</code> —
        لایسنس مقید به همین دامنه است، پس اگر آدرس سایت عوض شد باید در مخزن هم به‌روز شود.
    </p>

    @if (! $configured)
        <div class="mb-3.5 rounded bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20">
            آدرس مخزن تنظیم نشده است. در فایل <code>.env</code> این دو کلید را بگذارید:
            <code>MARKETPLACE_URL</code> و <code>MARKETPLACE_TOKEN</code>.
        </div>
    @elseif ($catalogue['error'])
        <div class="mb-3.5 rounded bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20">
            {{ $catalogue['error'] }}
        </div>
    @endif

    @php
        $license = $catalogue['license'] ?? [];
    @endphp

    @if ($license)
        <div class="mb-3.5 rounded bg-white p-4 text-sm dark:bg-gray-900">
            @if ($license['valid'] ?? false)
                <span class="label-active">لایسنس معتبر</span>

                <span class="text-gray-600 dark:text-gray-300">
                    {{ $license['customer'] ?? '' }}
                    @if (! empty($license['expires_at']))
                        — تا {{ \Illuminate\Support\Carbon::parse($license['expires_at'])->format('Y-m-d') }}
                    @endif
                    @if (empty($license['modules']))
                        — همهٔ ماژول‌ها
                    @else
                        — {{ implode('، ', $license['modules']) }}
                    @endif
                </span>
            @else
                <span class="label-canceled">لایسنس معتبر نیست</span>

                <span class="text-gray-600 dark:text-gray-300">{{ $license['reason'] ?? '' }}</span>
            @endif
        </div>
    @endif

    @if (empty($catalogue['modules']))
        <div class="rounded bg-white p-8 text-center text-sm text-gray-500 dark:bg-gray-900">
            فهرست ماژول‌های مخزن خالی است.
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($catalogue['modules'] as $module)
                @php
                    $current  = $installed[$module['package_name']] ?? null;
                    $latest   = $module['latest_version']['version'] ?? null;
                    $outdated = $current && $latest && version_compare($latest, $current, '>');
                @endphp

                <div class="rounded bg-white p-5 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-x-3">
                        <div>
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                {{ $module['name'] }}
                            </p>

                            <p class="mt-0.5 text-xs text-gray-400">
                                <code>{{ $module['package_name'] }}</code>
                                @if (! empty($module['category'])) · {{ $module['category'] }} @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-x-1.5">
                            @if ($module['free'])
                                <span class="label-info">رایگان</span>
                            @elseif (! $module['available'])
                                <span class="label-pending">خارج از لایسنس</span>
                            @endif

                            @if ($outdated)
                                <span class="label-pending">به‌روزرسانی موجود</span>
                            @elseif ($current)
                                <span class="label-active">نصب‌شده</span>
                            @endif
                        </div>
                    </div>

                    <p class="mt-2 text-xs leading-6 text-gray-500">
                        {{ $module['description'] ?: '—' }}
                    </p>

                    @if ($latest)
                        <p class="mt-2 text-xs text-gray-400">
                            آخرین نسخهٔ سازگار: <span class="font-mono">{{ $latest }}</span>
                            @if ($current)
                                — نصب‌شده: <span class="font-mono">{{ $current }}</span>
                            @endif
                            @if (! empty($module['latest_version']['requires']))
                                — پیش‌نیاز: {{ implode('، ', $module['latest_version']['requires']) }}
                            @endif
                        </p>

                        @if (! empty($module['latest_version']['changelog']))
                            <p class="mt-1 text-xs leading-5 text-gray-500">
                                {{ $module['latest_version']['changelog'] }}
                            </p>
                        @endif
                    @else
                        <p class="mt-2 text-xs text-amber-600">
                            نسخهٔ سازگاری با بجیستو {{ \Webkul\Core\Core::BAGISTO_VERSION }} و
                            PHP {{ PHP_VERSION }} منتشر نشده است.
                        </p>
                    @endif

                    <div class="mt-4 flex items-center gap-x-2.5">
                        @if ($latest && $module['available'])
                            <form method="POST" action="{{ route('admin.marketplace.repository.install', $module['slug']) }}">
                                @csrf

                                <button type="submit" class="{{ $outdated || ! $current ? 'primary-button' : 'secondary-button' }}">
                                    @if ($outdated)
                                        به‌روزرسانی به {{ $latest }}
                                    @elseif ($current)
                                        نصب دوباره
                                    @else
                                        نصب
                                    @endif
                                </button>
                            </form>
                        @elseif (! $module['available'])
                            <span class="text-xs text-gray-500">
                                برای نصب این ماژول باید در لایسنس شما باشد.
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin::layouts>
