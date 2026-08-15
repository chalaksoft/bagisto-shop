<x-admin::layouts>
    <x-slot:title>
        ماژول‌ها
    </x-slot>

    @php
        $rows = collect($modules)->map(function ($module, $name) use ($records, $updates, $protected, $locked) {
            $record = $records[$name] ?? null;

            return [
                'name'        => $name,
                'module'      => $module,
                'record'      => $record,
                'update'      => $updates[$name] ?? null,
                'isProtected' => in_array($name, $protected, true),
                'isLocked'    => in_array($name, $locked, true),
                'broken'      => $module['enabled'] && ! $module['bootable'],
                'source'      => ['bundled' => 'همراه پروژه', 'repository' => 'مخزن', 'manual' => 'دستی'][$record->source ?? ''] ?? '—',
            ];
        });

        $counts = [
            'all'      => $rows->count(),
            'active'   => $rows->where('module.enabled', true)->count(),
            'inactive' => $rows->where('module.enabled', false)->count(),
            'update'   => $rows->filter(fn ($row) => $row['update'])->count(),
            'broken'   => $rows->where('broken', true)->count(),
        ];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <p class="text-xl font-bold text-gray-800 dark:text-white">ماژول‌ها</p>

            <a href="{{ route('admin.marketplace.repository') }}" class="secondary-button !py-1 !text-xs">
                افزودن ماژول
            </a>

            @if ($counts['update'])
                <a href="{{ route('admin.marketplace.repository') }}" class="text-xs text-blue-600 hover:underline">
                    {{ $counts['update'] }} به‌روزرسانی در مخزن هست
                </a>
            @endif
        </div>

        @if ($allowUpload)
            <form
                method="POST"
                action="{{ route('admin.marketplace.install') }}"
                enctype="multipart/form-data"
                class="flex items-center gap-x-2"
            >
                @csrf

                <input
                    type="file"
                    name="package"
                    accept=".zip"
                    required
                    class="max-w-52 text-xs text-gray-600 file:me-2 file:rounded file:border-0 file:bg-gray-100 file:px-2 file:py-1.5 file:text-xs dark:text-gray-300 dark:file:bg-gray-800 dark:file:text-gray-200"
                >

                <button type="submit" class="secondary-button">نصب از فایل</button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="mt-3.5 rounded border-s-4 border-green-500 bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error') || $errors->any())
        <div class="mt-3.5 rounded border-s-4 border-red-500 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') ?: $errors->first() }}
        </div>
    @endif

    {{--
        تب‌های فیلتر و جست‌وجو، مثل صفحهٔ افزونه‌های وردپرس: شمارش هر وضعیت
        همان‌جا دیده می‌شود و فیلتر بدون رفت‌وبرگشت به سرور انجام می‌گیرد.
    --}}
    <div class="mt-4 flex flex-wrap items-end justify-between gap-3" id="modules-toolbar">
        <div class="flex flex-wrap items-center gap-x-1 text-sm text-gray-500">
            @foreach ([
                'all'      => ['همه', $counts['all']],
                'active'   => ['فعال', $counts['active']],
                'inactive' => ['غیرفعال', $counts['inactive']],
                'update'   => ['به‌روزرسانی موجود', $counts['update']],
                'broken'   => ['بوت نمی‌شود', $counts['broken']],
            ] as $key => [$label, $count])
                @continue(in_array($key, ['update', 'broken'], true) && ! $count)

                <button
                    type="button"
                    data-filter="{{ $key }}"
                    class="rounded px-2 py-1 hover:text-gray-800 dark:hover:text-white {{ $key === 'all' ? 'font-semibold text-gray-800 dark:text-white' : '' }}"
                >
                    {{ $label }} <span class="text-gray-400">({{ $count }})</span>
                </button>

                @if (! $loop->last)
                    <span class="text-gray-300 dark:text-gray-700">|</span>
                @endif
            @endforeach
        </div>

        <input
            type="search"
            placeholder="جست‌وجوی ماژول…"
            class="w-full max-w-xs rounded border px-3 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-950"
            data-search
        >
    </div>

    <div class="mt-2.5 overflow-hidden rounded bg-white dark:bg-gray-900">
        <table class="w-full text-sm" id="modules-table">
            <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                <tr>
                    <th class="w-72 p-4 text-start font-medium">ماژول</th>
                    <th class="p-4 text-start font-medium">توضیح</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    @php
                        $name   = $row['name'];
                        $module = $row['module'];
                        $states = collect([
                            'all',
                            $module['enabled'] ? 'active' : 'inactive',
                            $row['update'] ? 'update' : null,
                            $row['broken'] ? 'broken' : null,
                        ])->filter()->implode(' ');
                    @endphp

                    <tr
                        data-module
                        data-states="{{ $states }}"
                        data-haystack="{{ mb_strtolower($name.' '.($module['description'] ?? '')) }}"
                        class="border-b border-s-4 dark:border-gray-800 {{ $module['enabled']
                            ? ($row['broken'] ? 'border-s-red-500' : 'border-s-green-500')
                            : 'border-s-transparent bg-gray-50 dark:bg-gray-950/40' }} {{ $row['update'] ? '!border-b-0' : '' }}"
                    >
                        <td class="p-4 align-top">
                            <p class="font-semibold text-gray-800 dark:text-white">{{ $name }}</p>

                            {{-- لینک‌های عملیات زیر نام، دقیقاً جایی که وردپرس گذاشته. --}}
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs">
                                @if (! $row['isProtected'])
                                    <form method="POST" action="{{ route('admin.marketplace.toggle', $name) }}">
                                        @csrf

                                        {{--
                                            غیرفعال‌کردن ماژولی که دیگری به آن وابسته است بدون
                                            force رد می‌شود؛ کاربر پیام دلیل را می‌بیند و در صورت
                                            نیاز اول وابسته‌ها را خاموش می‌کند.
                                        --}}
                                        <button type="submit" class="text-blue-600 hover:underline">
                                            {{ $module['enabled'] ? 'غیرفعال کردن' : 'فعال کردن' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400" title="این ماژول همین صفحه را می‌سازد">محافظت‌شده</span>
                                @endif

                                @if (! $row['isProtected'] && ! $row['isLocked'])
                                    <span class="text-gray-300 dark:text-gray-700">|</span>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.marketplace.remove', $name) }}"
                                        class="flex items-center gap-x-1.5"
                                        onsubmit="return confirm('ماژول {{ $name }} و فایل‌هایش حذف می‌شوند. اگر گزینهٔ رول‌بک را زده باشید، دادهٔ جدول‌هایش هم پاک می‌شود. ادامه می‌دهید؟')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="text-red-600 hover:underline">حذف</button>

                                        <label class="flex items-center gap-x-1 text-gray-400" title="جدول‌های این ماژول هم برگردانده شوند">
                                            <input type="checkbox" name="rollback_migrations" value="1">
                                            با رول‌بک
                                        </label>
                                    </form>
                                @elseif ($row['isLocked'])
                                    <span class="text-gray-300 dark:text-gray-700">|</span>
                                    <span class="text-gray-400" title="فایل‌هایش مال این پروژه نیست">قفل</span>
                                @endif
                            </div>
                        </td>

                        <td class="p-4 align-top">
                            <p class="max-w-3xl leading-6 text-gray-600 dark:text-gray-300">
                                {{ $module['description'] ?: '—' }}
                            </p>

                            <p class="mt-1.5 text-xs text-gray-400">
                                نسخهٔ <span class="font-mono">{{ $row['record']->version ?? $module['version'] }}</span>

                                @if (! empty($module['author']))
                                    <span class="text-gray-300 dark:text-gray-700">|</span>
                                    توسط
                                    @if (! empty($module['author_url']))
                                        <a href="{{ $module['author_url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="text-blue-600 hover:underline">{{ $module['author'] }}</a>
                                    @else
                                        {{ $module['author'] }}
                                    @endif
                                @endif

                                <span class="text-gray-300 dark:text-gray-700">|</span> {{ $row['source'] }}
                                <span class="text-gray-300 dark:text-gray-700">|</span> اولویت بوت {{ $module['priority'] }}
                                @if ($module['requires'])
                                    <span class="text-gray-300 dark:text-gray-700">|</span> پیش‌نیاز: {{ implode('، ', $module['requires']) }}
                                @endif
                            </p>

                            @if ($row['broken'])
                                <p class="mt-2 text-xs text-red-600">
                                    @if ($module['missing'])
                                        بوت نمی‌شود — پیش‌نیاز غایب: {{ implode('، ', $module['missing']) }}
                                    @else
                                        بوت نمی‌شود — فایل ModuleServiceProvider پیدا نشد.
                                    @endif
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- ردیف خبر به‌روزرسانی، چسبیده به ردیف ماژول — همان الگوی وردپرس. --}}
                    @if ($row['update'])
                        <tr
                            data-module
                            data-states="{{ $states }}"
                            data-haystack="{{ mb_strtolower($name) }}"
                            class="border-b border-s-4 border-s-blue-500 bg-blue-50 dark:border-gray-800 dark:bg-blue-900/20"
                        >
                            <td colspan="2" class="px-4 py-2.5 text-xs leading-6 text-blue-800 dark:text-blue-300">
                                نسخهٔ <span class="font-mono">{{ $row['update']['version'] }}</span> این ماژول در مخزن هست.
                                @if ($row['update']['changelog'])
                                    {{ \Illuminate\Support\Str::limit($row['update']['changelog'], 120) }}
                                @endif

                                <a href="{{ route('admin.marketplace.repository') }}" class="font-semibold hover:underline">
                                    همین حالا به‌روزرسانی کنید
                                </a>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <p class="hidden p-8 text-center text-sm text-gray-500" data-empty>
            ماژولی با این فیلتر پیدا نشد.
        </p>
    </div>

    @if ($history->isNotEmpty())
        <details class="mt-6 rounded bg-white p-5 dark:bg-gray-900">
            <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-white">
                آخرین نصب‌ها و حذف‌ها ({{ $history->count() }})
            </summary>

            <div class="mt-3.5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                        <tr>
                            <th class="p-3 text-start font-medium">زمان</th>
                            <th class="p-3 text-start font-medium">ماژول</th>
                            <th class="p-3 text-start font-medium">کار</th>
                            <th class="p-3 text-start font-medium">نسخه</th>
                            <th class="p-3 text-start font-medium">کاربر</th>
                            <th class="p-3 text-start font-medium">نتیجه</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($history as $entry)
                            <tr class="border-b last:border-0 dark:border-gray-800">
                                <td class="whitespace-nowrap p-3 text-gray-600 dark:text-gray-300">{{ $entry->created_at }}</td>
                                <td class="p-3 text-gray-600 dark:text-gray-300">{{ $entry->module }}</td>
                                <td class="p-3 text-gray-600 dark:text-gray-300">
                                    {{ ['install' => 'نصب', 'update' => 'به‌روزرسانی', 'remove' => 'حذف'][$entry->action] ?? $entry->action }}
                                </td>
                                <td class="p-3 font-mono text-gray-600 dark:text-gray-300">{{ $entry->version ?: '—' }}</td>
                                <td class="p-3 text-gray-600 dark:text-gray-300">{{ $entry->admin_name ?: 'خط فرمان' }}</td>
                                <td class="p-3">
                                    @if ($entry->status === 'completed')
                                        <span class="label-active">موفق</span>
                                    @elseif ($entry->status === 'failed')
                                        <span class="label-canceled" title="{{ $entry->error }}">ناموفق</span>
                                    @else
                                        <span class="label-pending">ناتمام</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @push('scripts')
        <script>
            (function () {
                const toolbar = document.getElementById('modules-toolbar');
                const rows    = Array.from(document.querySelectorAll('#modules-table [data-module]'));
                const search  = toolbar.querySelector('[data-search]');
                const empty   = document.querySelector('[data-empty]');
                const tabs    = Array.from(toolbar.querySelectorAll('[data-filter]'));

                let state = 'all';

                function apply() {
                    const term = search.value.trim().toLowerCase();

                    let visible = 0;

                    rows.forEach(function (row) {
                        const show = (state === 'all' || row.dataset.states.split(' ').includes(state))
                            && (! term || row.dataset.haystack.includes(term));

                        row.classList.toggle('hidden', ! show);

                        visible += show ? 1 : 0;
                    });

                    empty.classList.toggle('hidden', visible > 0);
                }

                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        state = tab.dataset.filter;

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
</x-admin::layouts>
