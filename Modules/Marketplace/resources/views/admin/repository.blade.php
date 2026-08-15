<x-admin::layouts>
    <x-slot:title>
        مخزن ماژول‌ها
    </x-slot>

    @php
        $license      = $catalogue['license'] ?? [];
        $licenseValid = (bool) ($license['valid'] ?? false);
        $modules      = $catalogue['modules'] ?? [];
        $categories   = collect($modules)->pluck('category')->filter()->unique()->sort()->values();
        $installable  = collect($modules)->filter(fn ($module) => ($module['available'] ?? false) && ($module['latest_version']['version'] ?? null))->count();
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                مخزن ماژول‌ها
            </p>

            <p class="mt-1 text-xs leading-6 text-gray-500">
                دامنه‌ای که به مخزن اعلام می‌شود: <code>{{ $domain }}</code> —
                لایسنس مقید به همین دامنه است، پس اگر آدرس سایت عوض شد باید در مخزن هم به‌روز شود.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.marketplace.index') }}" class="secondary-button">
                ماژول‌های نصب‌شده
            </a>

            <a href="{{ route('admin.marketplace.repository', ['refresh' => 1]) }}" class="primary-button">
                تازه‌سازی فهرست
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-3.5 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mt-3.5 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-3.5 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $configured)
        <div class="mt-3.5 rounded border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
            آدرس مخزن تنظیم نشده است. در فایل <code>.env</code> کلید
            <code>MARKETPLACE_URL</code> را بگذارید؛ توکن لایسنس را می‌توانید با
            ثبت‌نام از همین صفحه بگیرید.
        </div>
    @elseif ($catalogue['error'])
        <div class="mt-3.5 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
            {{ $catalogue['error'] }}
        </div>
    @endif

    @if ($hasToken && $license)
        <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 rounded bg-white p-4 text-sm dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                @if ($licenseValid)
                    <span class="label-active">لایسنس معتبر</span>

                    <span class="text-gray-700 dark:text-gray-200">{{ $license['customer'] ?? '' }}</span>

                    <span class="text-xs text-gray-500">
                        @if (! empty($license['expires_at']))
                            · تا {{ \Illuminate\Support\Carbon::parse($license['expires_at'])->format('Y-m-d') }}
                        @endif
                        · {{ empty($license['modules']) ? 'همهٔ ماژول‌ها' : implode('، ', $license['modules']) }}
                        · {{ $installable }} ماژول قابل نصب
                    </span>
                @else
                    <span class="label-canceled">لایسنس معتبر نیست</span>

                    <span class="text-gray-600 dark:text-gray-300">{{ $license['reason'] ?? '' }}</span>
                @endif
            </div>

            <div class="flex items-center gap-x-3 text-xs text-gray-500">
                <span>
                    @if ($tokenFromEnv)
                        توکن از <code>.env</code> خوانده می‌شود
                    @else
                        ثبت‌نام‌شده با {{ $credential?->email }}
                        @if ($credential?->registered_at)
                            · {{ $credential->registered_at->format('Y-m-d') }}
                        @endif
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
              class="mt-3.5 rounded border border-blue-100 bg-white p-5 dark:border-blue-900/40 dark:bg-gray-900">
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
        {{-- جست‌وجو و فیلتر سمت مرورگر: فهرست چند ده‌تایی بدون آن‌ها اسکرول طولانی است. --}}
        <div class="mt-3.5 flex flex-wrap items-center gap-2.5" id="repository-filters">
            <input
                type="search"
                placeholder="جست‌وجو در نام، نامک یا توضیح…"
                class="w-full max-w-xs rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950"
                data-search
            >

            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" class="secondary-button !py-1 !text-xs" data-filter="all">همه</button>

                @foreach ($categories as $category)
                    <button type="button" class="secondary-button !py-1 !text-xs" data-filter="{{ $category }}">
                        {{ $category }}
                    </button>
                @endforeach

                <button type="button" class="secondary-button !py-1 !text-xs" data-filter="__updates">
                    به‌روزرسانی‌ها
                </button>
            </div>

            <span class="text-xs text-gray-500" data-count></span>
        </div>

        <div class="mt-3.5 grid gap-3.5 xl:grid-cols-2" id="repository-modules">
            @foreach ($modules as $module)
                @php
                    $current  = $installed[$module['package_name']] ?? null;
                    $latest   = $module['latest_version']['version'] ?? null;
                    $outdated = $current && $latest && version_compare($latest, $current, '>');
                @endphp

                <div
                    class="flex flex-col rounded bg-white p-5 dark:bg-gray-900"
                    data-module
                    data-category="{{ $module['category'] ?? '' }}"
                    data-updatable="{{ $outdated ? '1' : '0' }}"
                    data-haystack="{{ mb_strtolower($module['name'].' '.$module['package_name'].' '.$module['slug'].' '.($module['description'] ?? '')) }}"
                >
                    <div class="flex items-start justify-between gap-x-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-gray-800 dark:text-white">
                                {{ $module['name'] }}
                            </p>

                            <p class="mt-0.5 text-xs text-gray-400">
                                <code>{{ $module['package_name'] }}</code>
                                @if (! empty($module['category'])) · {{ $module['category'] }} @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            @if ($module['free'])
                                <span class="label-info">رایگان</span>
                            @elseif (! $module['available'])
                                <span class="label-pending">خارج از لایسنس</span>
                            @endif

                            @if ($outdated)
                                <span class="label-processing">به‌روزرسانی موجود</span>
                            @elseif ($current)
                                <span class="label-active">نصب‌شده</span>
                            @endif
                        </div>
                    </div>

                    <p class="mt-2 text-xs leading-6 text-gray-500">
                        {{ $module['description'] ?: '—' }}
                    </p>

                    @if ($latest)
                        <div class="mt-3 grid grid-cols-2 gap-2 rounded bg-gray-50 p-3 text-xs dark:bg-gray-950/50 sm:grid-cols-3">
                            <div>
                                <span class="block text-gray-400">آخرین نسخهٔ سازگار</span>
                                <span class="font-mono text-gray-700 dark:text-gray-200">{{ $latest }}</span>
                            </div>

                            <div>
                                <span class="block text-gray-400">نصب‌شده</span>
                                <span class="font-mono text-gray-700 dark:text-gray-200">{{ $current ?: '—' }}</span>
                            </div>

                            <div class="col-span-2 sm:col-span-1">
                                <span class="block text-gray-400">پیش‌نیاز</span>
                                <span class="text-gray-700 dark:text-gray-200">
                                    {{ empty($module['latest_version']['requires']) ? '—' : implode('، ', $module['latest_version']['requires']) }}
                                </span>
                            </div>
                        </div>

                        @if (! empty($module['latest_version']['changelog']))
                            <p class="mt-2 text-xs leading-5 text-gray-500">
                                {{ $module['latest_version']['changelog'] }}
                            </p>
                        @endif
                    @else
                        <p class="mt-3 rounded bg-amber-50 p-2.5 text-xs leading-5 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                            نسخهٔ سازگاری با بجیستو {{ \Webkul\Core\Core::BAGISTO_VERSION }} و
                            PHP {{ PHP_VERSION }} منتشر نشده است.
                        </p>
                    @endif

                    <div class="mt-auto flex items-center gap-x-2.5 pt-4">
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

        <div class="mt-3.5 hidden rounded bg-white p-8 text-center text-sm text-gray-500 dark:bg-gray-900" data-empty>
            ماژولی با این جست‌وجو پیدا نشد.
        </div>

        @push('scripts')
            <script>
                (function () {
                    const filters = document.getElementById('repository-filters');
                    const cards   = Array.from(document.querySelectorAll('[data-module]'));
                    const search  = filters.querySelector('[data-search]');
                    const counter = filters.querySelector('[data-count]');
                    const empty   = document.querySelector('[data-empty]');

                    let category = 'all';

                    function apply() {
                        const term = search.value.trim().toLowerCase();

                        let visible = 0;

                        cards.forEach(function (card) {
                            const matchesTerm = ! term || card.dataset.haystack.includes(term);

                            const matchesCategory = category === 'all'
                                || (category === '__updates' ? card.dataset.updatable === '1' : card.dataset.category === category);

                            const show = matchesTerm && matchesCategory;

                            card.classList.toggle('hidden', ! show);

                            visible += show ? 1 : 0;
                        });

                        counter.textContent = visible + ' از ' + cards.length + ' ماژول';

                        empty.classList.toggle('hidden', visible > 0);
                    }

                    filters.querySelectorAll('[data-filter]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            category = button.dataset.filter;

                            filters.querySelectorAll('[data-filter]').forEach(function (other) {
                                other.classList.toggle('primary-button', other === button);
                                other.classList.toggle('secondary-button', other !== button);
                            });

                            apply();
                        });
                    });

                    search.addEventListener('input', apply);

                    apply();
                })();
            </script>
        @endpush
    @endif
</x-admin::layouts>
