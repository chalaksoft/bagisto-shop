<x-admin::layouts>
    <x-slot:title>
        {{ $module->name }}
    </x-slot>

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white">{{ $module->name }}</p>

            <p class="mt-0.5 font-mono text-xs text-gray-400">
                {{ $module->slug }} · Modules/{{ $module->package_name }}
            </p>
        </div>

        <div class="flex items-center gap-x-2.5">
            @if ($module->published)
                <span class="label-active">منتشرشده</span>
            @else
                <span class="label-pending">پیش‌نویس</span>
            @endif

            @if ($module->free)
                <span class="label-info">رایگان</span>
            @endif

            <a href="{{ route('admin.marketplace_server.modules.edit', $module->id) }}" class="secondary-button">
                ویرایش
            </a>
        </div>
    </div>

    <div class="mt-4 rounded bg-white p-5 dark:bg-gray-900">
        <p class="text-base font-semibold text-gray-800 dark:text-white">آپلود نسخهٔ تازه</p>

        <p class="mb-4 mt-1 text-xs leading-6 text-gray-500">
            شمارهٔ نسخه از <code>module.json</code> داخل خود بسته خوانده می‌شود، نه از این فرم.
            بسته با همان قواعدی بررسی می‌شود که فروشگاه خریدار موقع نصب اعمال می‌کند، و
            بلافاصله با کلید خصوصی مخزن امضا می‌شود.
        </p>

        <form
            method="POST"
            action="{{ route('admin.marketplace_server.versions.store', $module->id) }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">
                        فایل zip (حداکثر {{ number_format($maxUpload / 1024) }} مگابایت)
                    </label>

                    <input type="file" name="package" accept=".zip" required
                           class="w-full text-xs text-gray-600 dark:text-gray-300">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">تغییرات این نسخه</label>

                    <textarea name="changelog" rows="2"
                              class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">{{ old('changelog') }}</textarea>
                </div>
            </div>

            <label class="mt-3 flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="release" value="1" checked>
                بلافاصله منتشر شود
            </label>

            <button type="submit" class="primary-button mt-4">آپلود و امضا</button>
        </form>
    </div>

    <p class="mb-3.5 mt-8 text-xl font-bold text-gray-800 dark:text-white">نسخه‌ها</p>

    <div class="overflow-x-auto rounded bg-white dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                <tr>
                    <th class="p-4 text-start font-medium">نسخه</th>
                    <th class="p-4 text-start font-medium">نیازمندی‌ها</th>
                    <th class="p-4 text-start font-medium">حجم</th>
                    <th class="p-4 text-start font-medium">چکسام</th>
                    <th class="p-4 text-start font-medium">وضعیت</th>
                    <th class="p-4 text-end font-medium"></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($module->versions->sort(fn ($a, $b) => version_compare($b->version, $a->version)) as $version)
                    <tr class="border-b last:border-0 dark:border-gray-800">
                        <td class="p-4 align-top">
                            <span class="font-mono">{{ $version->version }}</span>

                            @if ($version->changelog)
                                <div class="mt-1 max-w-sm text-xs leading-5 text-gray-500">
                                    {{ $version->changelog }}
                                </div>
                            @endif
                        </td>

                        <td class="p-4 align-top text-xs text-gray-600 dark:text-gray-300">
                            <div>بجیستو: <span class="font-mono">{{ $version->requires_bagisto ?: '*' }}</span></div>
                            <div>PHP: <span class="font-mono">{{ $version->requires_php ?: '*' }}</span></div>
                            @if ($version->requires)
                                <div>ماژول: {{ implode('، ', $version->requires) }}</div>
                            @endif
                        </td>

                        <td class="p-4 align-top text-xs text-gray-600 dark:text-gray-300">
                            {{ number_format($version->archive_size / 1024) }} KB
                        </td>

                        <td class="p-4 align-top font-mono text-xs text-gray-500" title="{{ $version->checksum }}">
                            {{ substr($version->checksum, 0, 12) }}…
                        </td>

                        <td class="p-4 align-top">
                            @if ($version->isReleased())
                                <span class="label-active">منتشرشده</span>
                                <div class="mt-1 text-xs text-gray-500">{{ $version->released_at->format('Y-m-d') }}</div>
                            @else
                                <span class="label-pending">پیش‌نویس</span>
                            @endif
                        </td>

                        <td class="p-4 align-top">
                            <div class="flex items-center justify-end gap-x-2.5">
                                <form method="POST"
                                      action="{{ route('admin.marketplace_server.versions.toggle', [$module->id, $version->id]) }}">
                                    @csrf

                                    <button type="submit" class="secondary-button">
                                        {{ $version->isReleased() ? 'برگرداندن به پیش‌نویس' : 'انتشار' }}
                                    </button>
                                </form>

                                <form method="POST"
                                      action="{{ route('admin.marketplace_server.versions.delete', [$module->id, $version->id]) }}"
                                      onsubmit="return confirm('نسخهٔ {{ $version->version }} و فایلش حذف شوند؟')">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="transparent-button text-red-600 hover:bg-red-50">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-sm text-gray-500">
                            هنوز نسخه‌ای آپلود نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts>
