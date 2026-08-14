<x-admin::layouts>
    <x-slot:title>
        دانلودها
    </x-slot>

    <div class="flex items-center justify-between">
        <p class="text-xl font-bold text-gray-800 dark:text-white">دانلودها</p>

        <div class="flex items-center gap-x-2.5">
            <a href="{{ route('admin.marketplace_server.logs.index') }}"
               class="{{ $only ? 'secondary-button' : 'primary-button' }}">همه</a>

            <a href="{{ route('admin.marketplace_server.logs.index', ['only' => 'rejected']) }}"
               class="{{ $only === 'rejected' ? 'primary-button' : 'secondary-button' }}">فقط ردشده‌ها</a>
        </div>
    </div>

    <p class="mb-3.5 mt-1 text-xs leading-6 text-gray-500">
        تلاش‌های ردشده هم ثبت می‌شوند: توکنی که مدام از دامنهٔ ثبت‌نشده می‌آید یعنی لایسنس
        دست شخص دیگری است.
    </p>

    <div class="overflow-x-auto rounded bg-white dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="border-b text-xs text-gray-500 dark:border-gray-800">
                <tr>
                    <th class="p-4 text-start font-medium">زمان</th>
                    <th class="p-4 text-start font-medium">ماژول</th>
                    <th class="p-4 text-start font-medium">نسخه</th>
                    <th class="p-4 text-start font-medium">دامنه</th>
                    <th class="p-4 text-start font-medium">مشتری</th>
                    <th class="p-4 text-start font-medium">IP</th>
                    <th class="p-4 text-start font-medium">نتیجه</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b last:border-0 dark:border-gray-800">
                        <td class="p-4 text-xs text-gray-600 dark:text-gray-300">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td class="p-4 font-mono text-xs">{{ $log->module_slug ?: '—' }}</td>
                        <td class="p-4 font-mono text-xs">{{ $log->version?->version ?? '—' }}</td>
                        <td class="p-4 font-mono text-xs" dir="ltr">{{ $log->domain ?: '—' }}</td>
                        <td class="p-4 text-xs">
                            {{ $log->license?->customer
                                ? trim($log->license->customer->first_name.' '.$log->license->customer->last_name)
                                : '—' }}
                        </td>
                        <td class="p-4 font-mono text-xs" dir="ltr">{{ $log->ip ?: '—' }}</td>
                        <td class="p-4">
                            @if ($log->allowed)
                                <span class="label-active">موفق</span>
                            @else
                                <span class="label-canceled">رد شد</span>
                                <div class="mt-1 text-xs text-gray-500">{{ $log->reason }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-sm text-gray-500">چیزی ثبت نشده است.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin::layouts>
