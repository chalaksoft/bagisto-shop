<x-admin::layouts>
    <x-slot:title>
        مخزن ماژول‌ها
    </x-slot>

    @php
        $license      = $catalogue['license'] ?? [];
        $licenseValid = (bool) ($license['valid'] ?? false);
        $modules      = $catalogue['modules'] ?? [];
        $categories   = collect($modules)->pluck('category')->filter()->unique()->sort()->values();

        /** رنگ آیکون از نام ماژول می‌آید تا هر ماژول همیشه همان رنگ را داشته باشد. */
        $palette = ['bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500', 'bg-rose-500', 'bg-cyan-600', 'bg-indigo-500'];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-3">
            <p class="text-xl font-bold text-gray-800 dark:text-white">افزودن ماژول</p>

            <a href="{{ route('admin.marketplace.index') }}" class="secondary-button !py-1 !text-xs">
                ماژول‌های نصب‌شده
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-xs text-gray-500">
                دامنهٔ اعلام‌شده: <code>{{ $domain }}</code>
            </span>

            <a href="{{ route('admin.marketplace.repository', ['refresh' => 1]) }}" class="secondary-button !py-1 !text-xs">
                تازه‌سازی فهرست
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-3.5 rounded border-s-4 border-green-500 bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mt-3.5 rounded border-s-4 border-red-500 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-3.5 rounded border-s-4 border-red-500 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $configured)
        <div class="mt-3.5 rounded border-s-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
            آدرس مخزن تنظیم نشده است. در فایل <code>.env</code> کلید
            <code>MARKETPLACE_URL</code> را بگذارید؛ توکن لایسنس را می‌توانید با
            ثبت‌نام از همین صفحه بگیرید.
        </div>
    @elseif ($catalogue['error'])
        <div class="mt-3.5 rounded border-s-4 border-red-500 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
            {{ $catalogue['error'] }}
        </div>
    @endif

    @if ($hasToken && $license)
        <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 rounded bg-white px-4 py-3 text-sm dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                @if ($licenseValid)
                    <span class="label-active">لایسنس معتبر</span>

                    <span class="text-gray-700 dark:text-gray-200">{{ $license['customer'] ?? '' }}</span>

                    <span class="text-xs text-gray-500">
                        @if (! empty($license['expires_at']))
                            · تا {{ \Illuminate\Support\Carbon::parse($license['expires_at'])->format('Y-m-d') }}
                        @endif
                        · {{ empty($license['modules']) ? 'همهٔ ماژول‌ها' : implode('، ', $license['modules']) }}
                    </span>
                @else
                    <span class="label-canceled">لایسنس معتبر نیست</span>

                    <span class="text-gray-600 dark:text-gray-300">{{ $license['reason'] ?? '' }}</span>
                @endif
            </div>

            <div class="flex items-center gap-x-3 text-xs text-gray-500">
                <span>
                    @if ($tokenFromEnv)
                        توکن از <code>.env</code>
                    @else
                        ثبت‌نام‌شده با {{ $credential?->email }}
                        @if ($credential?->domain && $credential->domain !== $domain)
                            · ⚠️ لایسنس روی <code>{{ $credential->domain }}</code> صادر شده، نه دامنهٔ فعلی
                        @endif
                    @endif
                </span>

                @if (! $tokenFromEnv)
                    <form method="POST"
                          action="{{ route('admin.marketplace.disconnect') }}"
                          onsubmit="return confirm('توکن ثبت‌نام پاک شود؟ برای همین دامنه دوباره ثبت‌نام ممکن نیست و باید از مخزن توکن تازه بگیرید.')">
                        @csrf

                        <button type="submit" class="text-red-600 hover:underline">پاک‌کردن توکن</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{--
        فرم ثبت‌نام تا وقتی لایسنس معتبر نشده سر جایش می‌ماند: «توکن دارم ولی
        مخزن نمی‌شناسدش» (مثلاً .env کپی‌شده از نصب دیگر) همان‌قدر بن‌بست است که
        «توکن ندارم»، و راه بیرون‌آمدن از هر دو یکی است.
    --}}
    @if ($configured && ! $licenseValid)
        <form method="POST"
              action="{{ route('admin.marketplace.register') }}"
              class="mt-3.5 rounded border-s-4 border-blue-500 bg-white p-5 dark:bg-gray-900">
            @csrf

            <p class="text-base font-semibold text-gray-800 dark:text-white">
                ثبت‌نام فروشگاه در مخزن
            </p>

            <p class="mt-1 text-xs leading-6 text-gray-500">
                این فروشگاه لایسنس معتبری ندارد. با ثبت‌نام، یک لایسنس مقید به دامنهٔ
                <code>{{ $domain }}</code> صادر می‌شود و توکنش همین‌جا ذخیره می‌گردد —
                نیازی به ویرایش <code>.env</code> نیست. برای هر دامنه فقط یک بار
                ممکن است، پس اگر بعداً توکن را پاک کردید باید از پشتیبانی توکن تازه بگیرید.
            </p>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">نام</label>

                    <input type="text" name="first_name" required value="{{ old('first_name') }}"
                           class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">نام خانوادگی</label>

                    <input type="text" name="last_name" value="{{ old('last_name') }}"
                           class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">ایمیل</label>

                    <input type="email" name="email" required dir="ltr" value="{{ old('email') }}"
                           class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">

                    <p class="mt-1 text-xs text-gray-500">
                        اگر با این ایمیل در مخزن حساب دارید، لایسنس به همان حساب وصل می‌شود.
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="primary-button">ثبت‌نام و دریافت لایسنس</button>
            </div>
        </form>
    @endif

    @if (empty($modules))
        <div class="mt-3.5 rounded bg-white p-8 text-center text-sm text-gray-500 dark:bg-gray-900">
            فهرست ماژول‌های مخزن خالی است.
        </div>
    @else
        <div class="mt-4 flex flex-wrap items-end justify-between gap-3" id="repository-toolbar">
            <div class="flex flex-wrap items-center gap-x-1 text-sm text-gray-500">
                <button type="button" data-filter="all"
                        class="rounded px-2 py-1 font-semibold text-gray-800 hover:text-gray-800 dark:text-white">
                    همه <span class="font-normal text-gray-400">({{ count($modules) }})</span>
                </button>

                @foreach ($categories as $category)
                    <span class="text-gray-300 dark:text-gray-700">|</span>

                    <button type="button" data-filter="{{ $category }}"
                            class="rounded px-2 py-1 hover:text-gray-800 dark:hover:text-white">
                        {{ $category }}
                    </button>
                @endforeach

                <span class="text-gray-300 dark:text-gray-700">|</span>

                <button type="button" data-filter="__updates" class="rounded px-2 py-1 hover:text-gray-800 dark:hover:text-white">
                    به‌روزرسانی‌ها
                </button>
            </div>

            <input
                type="search"
                placeholder="جست‌وجوی ماژول…"
                class="w-full max-w-xs rounded border px-3 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-950"
                data-search
            >
        </div>

        <div class="mt-2.5 grid gap-4 lg:grid-cols-2 2xl:grid-cols-3" id="repository-modules">
            @foreach ($modules as $module)
                @php
                    $current  = $installed[$module['package_name']] ?? null;
                    $latest   = $module['latest_version']['version'] ?? null;
                    $outdated = $current && $latest && version_compare($latest, $current, '>');
                    $color    = $palette[crc32($module['slug']) % count($palette)];
                @endphp

                <div
                    class="flex flex-col rounded border bg-white dark:border-gray-800 dark:bg-gray-900"
                    data-module
                    data-category="{{ $module['category'] ?? '' }}"
                    data-updatable="{{ $outdated ? '1' : '0' }}"
                    data-haystack="{{ mb_strtolower($module['name'].' '.$module['package_name'].' '.$module['slug'].' '.($module['description'] ?? '')) }}"
                >
                    <div class="flex items-start gap-x-3 p-4">
                        @include('Marketplace::admin._logo', [
                            'src'   => $module['icon'] ?? null,
                            'name'  => $module['name'],
                            'size'  => 'h-12 w-12',
                            'color' => $color,
                        ])

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-gray-800 dark:text-white">
                                {{ $module['name'] }}
                            </p>

                            <p class="mt-0.5 truncate text-xs text-gray-400">
                                @if (! empty($module['author']))
                                    توسط
                                    @if (! empty($module['author_url']))
                                        <a href="{{ $module['author_url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="text-blue-600 hover:underline">{{ $module['author'] }}</a>
                                    @else
                                        {{ $module['author'] }}
                                    @endif
                                    ·
                                @endif

                                <code>{{ $module['package_name'] }}</code>
                                @if (! empty($module['category'])) · {{ $module['category'] }} @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-y-1.5">
                            @if ($latest && $module['available'])
                                <form method="POST" action="{{ route('admin.marketplace.repository.install', $module['slug']) }}">
                                    @csrf

                                    <button type="submit" class="{{ $outdated || ! $current ? 'primary-button' : 'secondary-button' }} !py-1 !text-xs">
                                        @if ($outdated)
                                            به‌روزرسانی
                                        @elseif ($current)
                                            نصب دوباره
                                        @else
                                            نصب
                                        @endif
                                    </button>
                                </form>
                            @elseif (! $module['available'])
                                <span class="label-pending">خارج از لایسنس</span>
                            @endif

                            @if ($outdated)
                                <span class="text-[11px] text-blue-600">نسخهٔ {{ $latest }}</span>
                            @elseif ($current)
                                <span class="text-[11px] text-green-600">نصب‌شده</span>
                            @elseif ($module['free'])
                                <span class="text-[11px] text-gray-400">رایگان</span>
                            @endif
                        </div>
                    </div>

                    <p
                        class="px-4 text-xs leading-6 text-gray-500"
                        style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"
                        title="{{ $module['description'] }}"
                    >
                        {{ $module['description'] ?: '—' }}
                    </p>

                    @if (! empty($module['latest_version']['changelog']))
                        <p class="mt-2 px-4 text-xs leading-5 text-gray-400"
                           style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                            تغییرات: {{ $module['latest_version']['changelog'] }}
                        </p>
                    @endif

                    {{-- نوار اطلاعات ته کارت، همان‌جایی که وردپرس «آخرین به‌روزرسانی» را می‌گذارد. --}}
                    <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t px-4 py-2.5 text-[11px] text-gray-500 dark:border-gray-800">
                        @if ($latest)
                            <span>آخرین نسخه: <span class="font-mono text-gray-700 dark:text-gray-200">{{ $latest }}</span></span>

                            @if ($current)
                                <span class="text-gray-300 dark:text-gray-700">|</span>
                                <span>نصب‌شده: <span class="font-mono text-gray-700 dark:text-gray-200">{{ $current }}</span></span>
                            @endif

                            @if (! empty($module['latest_version']['requires']))
                                <span class="text-gray-300 dark:text-gray-700">|</span>
                                <span>پیش‌نیاز: {{ implode('، ', $module['latest_version']['requires']) }}</span>
                            @endif
                        @else
                            <span class="text-amber-600">
                                نسخهٔ سازگار با بجیستو {{ \Webkul\Core\Core::BAGISTO_VERSION }} و PHP {{ PHP_VERSION }} ندارد
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3.5 hidden rounded bg-white p-8 text-center text-sm text-gray-500 dark:bg-gray-900" data-empty>
            ماژولی با این جست‌وجو پیدا نشد.
        </div>

        @push('scripts')
            <script>
                (function () {
                    const toolbar = document.getElementById('repository-toolbar');
                    const cards   = Array.from(document.querySelectorAll('[data-module]'));
                    const search  = toolbar.querySelector('[data-search]');
                    const empty   = document.querySelector('[data-empty]');
                    const tabs    = Array.from(toolbar.querySelectorAll('[data-filter]'));

                    let category = 'all';

                    function apply() {
                        const term = search.value.trim().toLowerCase();

                        let visible = 0;

                        cards.forEach(function (card) {
                            const matchesCategory = category === 'all'
                                || (category === '__updates' ? card.dataset.updatable === '1' : card.dataset.category === category);

                            const show = matchesCategory && (! term || card.dataset.haystack.includes(term));

                            card.classList.toggle('hidden', ! show);

                            visible += show ? 1 : 0;
                        });

                        empty.classList.toggle('hidden', visible > 0);
                    }

                    tabs.forEach(function (tab) {
                        tab.addEventListener('click', function () {
                            category = tab.dataset.filter;

                            tabs.forEach(function (other) {
                                other.classList.toggle('font-semibold', other === tab);
                                other.classList.toggle('text-gray-800', other === tab);
                                other.classList.toggle('dark:text-white', other === tab);
                            });

                            apply();
                        });
                    });

                    search.addEventListener('input', apply);
                })();
            </script>
        @endpush
    @endif
</x-admin::layouts>
