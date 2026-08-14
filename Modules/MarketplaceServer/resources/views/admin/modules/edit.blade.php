<x-admin::layouts>
    <x-slot:title>
        ویرایش {{ $module->name }}
    </x-slot>

    <form method="POST" action="{{ route('admin.marketplace_server.modules.update', $module->id) }}">
        @csrf
        @method('PUT')

        <div class="mb-3.5 flex items-center justify-between">
            <p class="text-xl font-bold text-gray-800 dark:text-white">ویرایش {{ $module->name }}</p>

            <a href="{{ route('admin.marketplace_server.modules.show', $module->id) }}" class="secondary-button">
                نسخه‌ها
            </a>
        </div>

        @include('MarketplaceServer::admin.modules.form')
    </form>

    <div class="mt-5 rounded bg-white p-5 dark:bg-gray-900">
        <p class="text-base font-semibold text-gray-800 dark:text-white">حذف ماژول</p>

        <p class="mt-1 text-xs leading-6 text-gray-500">
            ماژول و همهٔ نسخه‌ها و فایل‌هایشان حذف می‌شوند. فروشگاه‌هایی که نصبش کرده‌اند
            دست‌نخورده می‌مانند ولی دیگر به‌روزرسانی نمی‌گیرند. برای «دیگر نفروش» بهتر است
            فقط تیک «منتشرشده» را بردارید.
        </p>

        <form
            method="POST"
            action="{{ route('admin.marketplace_server.modules.delete', $module->id) }}"
            onsubmit="return confirm('ماژول {{ $module->name }} و همهٔ نسخه‌هایش حذف شوند؟')"
            class="mt-4"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="transparent-button text-red-600 hover:bg-red-50">حذف ماژول</button>
        </form>
    </div>
</x-admin::layouts>
