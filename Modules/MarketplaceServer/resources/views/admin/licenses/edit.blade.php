<x-admin::layouts>
    <x-slot:title>
        ویرایش لایسنس
    </x-slot>

    <form method="POST" action="{{ route('admin.marketplace_server.licenses.update', $license->id) }}">
        @csrf
        @method('PUT')

        <div class="mb-3.5">
            <p class="text-xl font-bold text-gray-800 dark:text-white">ویرایش لایسنس</p>
        </div>

        @include('MarketplaceServer::admin.licenses.form')
    </form>

    <div class="mt-5 rounded bg-white p-5 dark:bg-gray-900">
        <p class="text-base font-semibold text-gray-800 dark:text-white">حذف لایسنس</p>

        <p class="mt-1 text-xs leading-6 text-gray-500">
            فروشگاهی که با این توکن کار می‌کند از همان لحظه دیگر به‌روزرسانی نمی‌گیرد.
            ماژول‌های نصب‌شده‌اش دست‌نخورده می‌مانند.
        </p>

        <form
            method="POST"
            action="{{ route('admin.marketplace_server.licenses.delete', $license->id) }}"
            onsubmit="return confirm('این لایسنس حذف شود؟')"
            class="mt-4"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="transparent-button text-red-600 hover:bg-red-50">حذف لایسنس</button>
        </form>
    </div>
</x-admin::layouts>
