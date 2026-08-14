<x-admin::layouts>
    <x-slot:title>
        لایسنس‌ها
    </x-slot>

    <div class="flex items-center justify-between">
        <p class="text-xl font-bold text-gray-800 dark:text-white">لایسنس‌ها</p>

        <a href="{{ route('admin.marketplace_server.licenses.create') }}" class="primary-button">
            لایسنس جدید
        </a>
    </div>

    <p class="mb-3.5 mt-1 text-xs leading-6 text-gray-500">
        هر لایسنس مقید به دامنه است تا یک خرید روی ده سایت نصب نشود.
    </p>

    @if (session('new_token'))
        {{--
            تنها جایی که توکن خام دیده می‌شود. در دیتابیس فقط هشش هست، پس اگر
            اینجا کپی نشود دیگر قابل بازیابی نیست — فقط صدور توکن تازه.
        --}}
        <div class="mb-3.5 rounded bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/20">
            <p class="font-semibold">توکن را همین حالا کپی کنید — بعد از ترک این صفحه دیگر نمایش داده نمی‌شود.</p>

            <div class="mt-2 break-all rounded border border-dashed border-amber-300 bg-white/60 p-3 font-mono text-xs"
                 dir="ltr">
                {{ session('new_token') }}
            </div>

            <p class="mt-2 text-xs">
                در فایل <code>.env</code> فروشگاه مشتری: <code>MARKETPLACE_TOKEN=…</code>
            </p>
        </div>
    @endif

    <div class="overflow-x-auto rounded bg-white dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                <tr>
                    <th class="p-4 text-start font-medium">مشتری</th>
                    <th class="p-4 text-start font-medium">برچسب</th>
                    <th class="p-4 text-start font-medium">دامنه‌ها</th>
                    <th class="p-4 text-start font-medium">ماژول‌ها</th>
                    <th class="p-4 text-start font-medium">انقضا</th>
                    <th class="p-4 text-start font-medium">وضعیت</th>
                    <th class="p-4 text-end font-medium"></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($licenses as $license)
                    <tr class="border-b last:border-0 dark:border-gray-800">
                        <td class="p-4 align-top">
                            {{ $license->customer ? trim($license->customer->first_name.' '.$license->customer->last_name) : '—' }}
                            <div class="font-mono text-xs text-gray-400" dir="ltr">…{{ $license->token_hint }}</div>
                        </td>

                        <td class="p-4 align-top text-gray-600 dark:text-gray-300">{{ $license->label ?: '—' }}</td>

                        <td class="p-4 align-top font-mono text-xs text-gray-600 dark:text-gray-300" dir="ltr">
                            {{ implode('، ', (array) $license->domains) ?: '—' }}
                        </td>

                        <td class="p-4 align-top text-xs text-gray-600 dark:text-gray-300">
                            {{ $license->module_slugs ? implode('، ', $license->module_slugs) : 'همهٔ ماژول‌ها' }}
                        </td>

                        <td class="p-4 align-top text-xs text-gray-600 dark:text-gray-300">
                            {{ $license->expires_at?->format('Y-m-d') ?? 'بدون انقضا' }}
                        </td>

                        <td class="p-4 align-top">
                            @if (! $license->active)
                                <span class="label-canceled">غیرفعال</span>
                            @elseif ($license->isExpired())
                                <span class="label-canceled">منقضی</span>
                            @else
                                <span class="label-active">فعال</span>
                            @endif

                            @if ($license->last_used_at)
                                <div class="mt-1 text-xs text-gray-500">
                                    آخرین استفاده: {{ $license->last_used_at->diffForHumans() }}
                                </div>
                            @endif
                        </td>

                        <td class="p-4 align-top">
                            <div class="flex items-center justify-end gap-x-2.5">
                                <a href="{{ route('admin.marketplace_server.licenses.edit', $license->id) }}"
                                   class="secondary-button">ویرایش</a>

                                <form method="POST"
                                      action="{{ route('admin.marketplace_server.licenses.rotate', $license->id) }}"
                                      onsubmit="return confirm('توکن تازه صادر شود؟ توکن فعلی از همین لحظه کار نمی‌کند.')">
                                    @csrf
                                    <button type="submit" class="secondary-button">توکن تازه</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-sm text-gray-500">
                            هنوز لایسنسی صادر نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin::layouts>
