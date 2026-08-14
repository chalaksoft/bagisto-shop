<x-admin::layouts>
    <x-slot:title>
        لایسنس جدید
    </x-slot>

    <form method="POST" action="{{ route('admin.marketplace_server.licenses.store') }}">
        @csrf

        <div class="mb-3.5">
            <p class="text-xl font-bold text-gray-800 dark:text-white">لایسنس جدید</p>

            <p class="mt-1 text-xs leading-6 text-gray-500">
                توکن بعد از ذخیره یک بار نشان داده می‌شود؛ در دیتابیس فقط هشش می‌ماند.
            </p>
        </div>

        @include('MarketplaceServer::admin.licenses.form')
    </form>
</x-admin::layouts>
