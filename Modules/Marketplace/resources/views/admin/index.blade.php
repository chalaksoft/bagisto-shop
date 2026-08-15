<x-admin::layouts>
    <x-slot:title>
        ماژول‌ها
    </x-slot>

    @php
        $total    = count($modules);
        $active   = collect($modules)->filter(fn ($module) => $module['enabled'] && $module['bootable'])->count();
        $broken   = collect($modules)->filter(fn ($module) => $module['enabled'] && ! $module['bootable'])->count();
        $disabled = collect($modules)->filter(fn ($module) => ! $module['enabled'])->count();
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                ماژول‌های نصب‌شده
            </p>

            <p class="mt-1 text-xs leading-6 text-gray-500">
                ماژول تازه‌نصب یا تازه‌فعال از <strong>ریکوئست بعدی</strong> بوت می‌شود؛ اگر بلافاصله
                اثری ندیدید یک‌بار صفحه را دوباره باز کنید.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.marketplace.repository') }}"
               class="{{ $updates ? 'primary-button' : 'secondary-button' }}">
                مخزن ماژول‌ها

                @if ($updates)
                    <span class="ms-1 rounded-full bg-white/25 px-1.5 text-xs">{{ count($updates) }}</span>
                @endif
            </a>

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
    </div>

    @if (session('success'))
        <div class="mt-3.5 rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error') || $errors->any())
        <div class="mt-3.5 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') ?: $errors->first() }}
        </div>
    @endif

    {{-- نوار خلاصه: جواب سه سؤال اولِ هر ادمین بدون خواندن جدول. --}}
    <div class="mt-3.5 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['برچسب' => 'همهٔ ماژول‌ها', 'مقدار' => $total, 'رنگ' => 'text-gray-800 dark:text-white'],
            ['برچسب' => 'فعال', 'مقدار' => $active, 'رنگ' => 'text-green-600'],
            ['برچسب' => 'غیرفعال', 'مقدار' => $disabled, 'رنگ' => 'text-gray-500'],
            ['برچسب' => $broken ? 'بوت نمی‌شود' : 'به‌روزرسانی موجود', 'مقدار' => $broken ?: count($updates), 'رنگ' => $broken ? 'text-red-600' : 'text-blue-600'],
        ] as $card)
            <div class="rounded bg-white p-4 dark:bg-gray-900">
                <p class="text-xs text-gray-500">{{ $card['برچسب'] }}</p>

                <p class="mt-1 text-2xl font-bold {{ $card['رنگ'] }}">{{ $card['مقدار'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-3.5 grid gap-3.5 xl:grid-cols-2">
        @foreach ($modules as $name => $module)
            @php
                $record      = $records[$name] ?? null;
                $isProtected = in_array($name, $protected, true);
                $isLocked    = in_array($name, $locked, true);
                $update      = $updates[$name] ?? null;
                $source      = ['bundled' => 'همراه پروژه', 'repository' => 'مخزن', 'manual' => 'دستی'][$record->source ?? ''] ?? '—';
            @endphp

            <div class="flex flex-col rounded bg-white p-5 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-x-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-gray-800 dark:text-white">
                            {{ $name }}
                        </p>

                        <p class="mt-0.5 text-xs text-gray-400">
                            نسخهٔ <span class="font-mono">{{ $record->version ?? $module['version'] }}</span>
                            · {{ $source }}
                            · اولویت بوت {{ $module['priority'] }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                        @if (! $module['enabled'])
                            <span class="label-pending">غیرفعال</span>
                        @elseif ($module['bootable'])
                            <span class="label-active">فعال</span>
                        @else
                            <span class="label-canceled">بوت نمی‌شود</span>
                        @endif

                        @if ($isProtected)
                            <span class="label-info" title="این ماژول از پنل غیرفعال یا حذف نمی‌شود">محافظت‌شده</span>
                        @elseif ($isLocked)
                            <span class="label-info" title="فایل‌هایش مال این پروژه نیست؛ غیرفعال‌کردن آزاد است، حذف نه">قفل</span>
                        @endif
                    </div>
                </div>

                <p class="mt-2 text-xs leading-6 text-gray-500">
                    {{ $module['description'] ?: '—' }}
                </p>

                @if ($module['requires'])
                    <p class="mt-1 text-xs text-gray-400">
                        پیش‌نیاز: {{ implode('، ', $module['requires']) }}
                    </p>
                @endif

                @if ($module['enabled'] && ! $module['bootable'])
                    <p class="mt-2 rounded bg-red-50 p-2.5 text-xs leading-5 text-red-700 dark:bg-red-900/20 dark:text-red-300">
                        @if ($module['missing'])
                            پیش‌نیاز غایب: {{ implode('، ', $module['missing']) }}
                        @else
                            فایل ModuleServiceProvider پیدا نشد.
                        @endif
                    </p>
                @endif

                @if ($update)
                    <a href="{{ route('admin.marketplace.repository') }}"
                       class="mt-2 block rounded bg-blue-50 p-2.5 text-xs leading-5 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-300"
                       title="{{ $update['changelog'] }}">
                        نسخهٔ <span class="font-mono">{{ $update['version'] }}</span> در مخزن موجود است
                        @if ($update['changelog']) — {{ \Illuminate\Support\Str::limit($update['changelog'], 90) }} @endif
                    </a>
                @endif

                {{-- عملیات ته کارت می‌نشیند تا کارت‌های کنار هم یک‌اندازه دیده شوند. --}}
                <div class="mt-auto flex flex-wrap items-center justify-between gap-2.5 pt-4">
                    <div class="flex items-center gap-x-2.5">
                        @if (! $isProtected)
                            <form method="POST" action="{{ route('admin.marketplace.toggle', $name) }}">
                                @csrf

                                {{--
                                    غیرفعال‌کردن ماژولی که دیگری به آن وابسته است بدون
                                    force رد می‌شود؛ کاربر پیام دلیل را می‌بیند و در صورت
                                    نیاز اول وابسته‌ها را خاموش می‌کند.
                                --}}
                                <button type="submit" class="secondary-button">
                                    {{ $module['enabled'] ? 'غیرفعال کردن' : 'فعال کردن' }}
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-gray-400">این ماژول صفحهٔ فعلی را می‌سازد.</span>
                        @endif
                    </div>

                    @if (! $isProtected && ! $isLocked)
                        <form
                            method="POST"
                            action="{{ route('admin.marketplace.remove', $name) }}"
                            class="flex items-center gap-x-2"
                            onsubmit="return confirm('ماژول {{ $name }} و فایل‌هایش حذف می‌شوند. اگر گزینهٔ رول‌بک را زده باشید، دادهٔ جدول‌هایش هم پاک می‌شود. ادامه می‌دهید؟')"
                        >
                            @csrf
                            @method('DELETE')

                            <label class="flex items-center gap-x-1 text-xs text-gray-500">
                                <input type="checkbox" name="rollback_migrations" value="1">
                                رول‌بک مهاجرت‌ها
                            </label>

                            <button type="submit" class="transparent-button text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                حذف
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($history->isNotEmpty())
        <details class="mt-8 rounded bg-white p-5 dark:bg-gray-900">
            <summary class="cursor-pointer text-base font-semibold text-gray-800 dark:text-white">
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
</x-admin::layouts>
