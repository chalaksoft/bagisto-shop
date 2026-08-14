<x-admin::layouts>
    <x-slot:title>
        ماژول‌های منتشرشده
    </x-slot>

    <div class="flex items-center justify-between">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            ماژول‌های منتشرشده
        </p>

        <a href="{{ route('admin.marketplace_server.modules.create') }}" class="primary-button">
            ماژول جدید
        </a>
    </div>

    <p class="mb-3.5 mt-1 text-xs leading-6 text-gray-500">
        این‌ها ماژول‌هایی هستند که <strong>این فروشگاه می‌فروشد</strong> — نه آن‌هایی که خودش نصب کرده.
        فقط ماژول «منتشرشده» در API فروشگاه‌های مشتری دیده می‌شود.
    </p>

    @unless ($hasKeys)
        {{--
            بدون جفت‌کلید هیچ نسخه‌ای امضا و منتشر نمی‌شود. تنها کاری است که یک
            نصب تازه حتماً باید انجام دهد، پس بالاتر از هر چیز دیگری می‌آید.
        --}}
        <div class="mb-3.5 rounded bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20">
            کلید امضای مخزن ساخته نشده است. تا وقتی این کار انجام نشود هیچ نسخه‌ای منتشر نمی‌شود:
            <code>php artisan marketplace:keys</code>
        </div>
    @endunless

    <div class="overflow-x-auto rounded bg-white dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                <tr>
                    <th class="p-4 text-start font-medium">نام</th>
                    <th class="p-4 text-start font-medium">نامک</th>
                    <th class="p-4 text-start font-medium">پوشه</th>
                    <th class="p-4 text-start font-medium">نسخه‌ها</th>
                    <th class="p-4 text-start font-medium">وضعیت</th>
                    <th class="p-4 text-end font-medium"></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($modules as $module)
                    <tr class="border-b last:border-0 dark:border-gray-800">
                        <td class="p-4">
                            <a href="{{ route('admin.marketplace_server.modules.show', $module->id) }}"
                               class="font-semibold text-blue-600">
                                {{ $module->name }}
                            </a>

                            @if ($module->category)
                                <div class="text-xs text-gray-500">{{ $module->category }}</div>
                            @endif
                        </td>

                        <td class="p-4 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $module->slug }}</td>

                        <td class="p-4 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $module->package_name }}</td>

                        <td class="p-4 text-gray-600 dark:text-gray-300">
                            {{ $module->released_count }} منتشرشده
                            @if ($module->versions_count > $module->released_count)
                                <span class="text-xs text-gray-400">
                                    (+{{ $module->versions_count - $module->released_count }} پیش‌نویس)
                                </span>
                            @endif
                        </td>

                        <td class="p-4">
                            @if ($module->published)
                                <span class="label-active">منتشرشده</span>
                            @else
                                <span class="label-pending">پیش‌نویس</span>
                            @endif

                            @if ($module->free)
                                <span class="label-info">رایگان</span>
                            @endif
                        </td>

                        <td class="p-4 text-end">
                            <a href="{{ route('admin.marketplace_server.modules.edit', $module->id) }}"
                               class="secondary-button">
                                ویرایش
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-sm text-gray-500">
                            هنوز ماژولی برای فروش ساخته نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts>
