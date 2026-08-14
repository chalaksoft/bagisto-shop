<x-admin::layouts>
    <x-slot:title>
        ماژول‌ها
    </x-slot>

    <div class="flex items-center justify-between">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            ماژول‌های نصب‌شده
        </p>

        <div class="flex items-center gap-x-2.5">
            <a href="{{ route('admin.marketplace.repository') }}"
               class="{{ $updates ? 'primary-button' : 'secondary-button' }}">
                مخزن @if ($updates) ({{ count($updates) }} به‌روزرسانی) @endif
            </a>

            @if ($allowUpload)
                <form
                    method="POST"
                    action="{{ route('admin.marketplace.install') }}"
                    enctype="multipart/form-data"
                    class="flex items-center gap-x-2.5"
                >
                    @csrf

                    <input
                        type="file"
                        name="package"
                        accept=".zip"
                        required
                        class="text-xs text-gray-600 dark:text-gray-300"
                    >

                    <button type="submit" class="secondary-button">
                        نصب از فایل
                    </button>
                </form>
            @endif
        </div>
    </div>

    <p class="mb-3.5 mt-1 text-xs leading-6 text-gray-500">
        ماژول تازه‌نصب یا تازه‌فعال از <strong>ریکوئست بعدی</strong> بوت می‌شود؛ اگر بلافاصله
        اثری ندیدید یک‌بار صفحه را دوباره باز کنید. اگر روی سرور از کش روت استفاده می‌کنید،
        بعد از نصب یک‌بار <code>deploy/art route:cache</code> بزنید.
    </p>

    @if ($errors->any())
        <div class="mb-3.5 rounded bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="overflow-x-auto rounded bg-white dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                <tr>
                    <th class="p-4 text-start font-medium">ماژول</th>
                    <th class="p-4 text-start font-medium">نسخه</th>
                    <th class="p-4 text-start font-medium">منبع</th>
                    <th class="p-4 text-start font-medium">وضعیت</th>
                    <th class="p-4 text-start font-medium">اولویت بوت</th>
                    <th class="p-4 text-end font-medium">عملیات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($modules as $name => $module)
                    @php
                        $record      = $records[$name] ?? null;
                        $isProtected = in_array($name, $protected, true);
                        $isLocked    = in_array($name, $locked, true);
                    @endphp

                    <tr class="border-b last:border-0 dark:border-gray-800">
                        <td class="p-4 align-top">
                            <p class="font-semibold text-gray-800 dark:text-white">{{ $name }}</p>

                            <p class="mt-1 max-w-xl text-xs leading-5 text-gray-500">
                                {{ $module['description'] ?: '—' }}
                            </p>

                            @if ($module['requires'])
                                <p class="mt-1 text-xs text-gray-400">
                                    پیش‌نیاز: {{ implode('، ', $module['requires']) }}
                                </p>
                            @endif
                        </td>

                        <td class="p-4 align-top text-gray-600 dark:text-gray-300">
                            {{ $record->version ?? $module['version'] }}

                            @if ($update = ($updates[$name] ?? null))
                                <a href="{{ route('admin.marketplace.repository') }}"
                                   class="mt-1 block text-xs text-blue-600"
                                   title="{{ $update['changelog'] }}">
                                    ↑ {{ $update['version'] }} موجود است
                                </a>
                            @endif
                        </td>

                        <td class="p-4 align-top text-gray-600 dark:text-gray-300">
                            {{ ['bundled' => 'همراه پروژه', 'repository' => 'مخزن', 'manual' => 'دستی'][$record->source ?? ''] ?? '—' }}
                        </td>

                        <td class="p-4 align-top">
                            @if (! $module['enabled'])
                                <span class="label-pending">غیرفعال</span>
                            @elseif ($module['bootable'])
                                <span class="label-active">فعال</span>
                            @else
                                <span class="label-canceled">بوت نمی‌شود</span>

                                <p class="mt-1 text-xs text-red-600">
                                    @if ($module['missing'])
                                        پیش‌نیاز غایب: {{ implode('، ', $module['missing']) }}
                                    @else
                                        فایل ModuleServiceProvider پیدا نشد.
                                    @endif
                                </p>
                            @endif
                        </td>

                        <td class="p-4 align-top text-gray-600 dark:text-gray-300">
                            {{ $module['priority'] }}
                        </td>

                        <td class="p-4 align-top">
                            <div class="flex items-center justify-end gap-x-2.5">
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
                                @endif

                                @if (! $isProtected && ! $isLocked)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.marketplace.remove', $name) }}"
                                        onsubmit="return confirm('ماژول {{ $name }} و فایل‌هایش حذف می‌شوند. اگر گزینهٔ رول‌بک را زده باشید، دادهٔ جدول‌هایش هم پاک می‌شود. ادامه می‌دهید؟')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <label class="me-2 text-xs text-gray-500">
                                            <input type="checkbox" name="rollback_migrations" value="1">
                                            رول‌بک مهاجرت‌ها
                                        </label>

                                        <button type="submit" class="transparent-button text-red-600 hover:bg-red-50">
                                            حذف
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($history->isNotEmpty())
        <p class="mb-3.5 mt-8 text-xl font-bold text-gray-800 dark:text-white">
            آخرین نصب‌ها و حذف‌ها
        </p>

        <div class="overflow-x-auto rounded bg-white dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="p-4 text-start font-medium">زمان</th>
                        <th class="p-4 text-start font-medium">ماژول</th>
                        <th class="p-4 text-start font-medium">کار</th>
                        <th class="p-4 text-start font-medium">نسخه</th>
                        <th class="p-4 text-start font-medium">کاربر</th>
                        <th class="p-4 text-start font-medium">نتیجه</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($history as $entry)
                        <tr class="border-b last:border-0 dark:border-gray-800">
                            <td class="p-4 text-gray-600 dark:text-gray-300">{{ $entry->created_at }}</td>
                            <td class="p-4 text-gray-600 dark:text-gray-300">{{ $entry->module }}</td>
                            <td class="p-4 text-gray-600 dark:text-gray-300">
                                {{ ['install' => 'نصب', 'update' => 'به‌روزرسانی', 'remove' => 'حذف'][$entry->action] ?? $entry->action }}
                            </td>
                            <td class="p-4 text-gray-600 dark:text-gray-300">{{ $entry->version ?: '—' }}</td>
                            <td class="p-4 text-gray-600 dark:text-gray-300">{{ $entry->admin_name ?: 'خط فرمان' }}</td>
                            <td class="p-4">
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
    @endif
</x-admin::layouts>
