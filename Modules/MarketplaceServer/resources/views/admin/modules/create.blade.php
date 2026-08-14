<x-admin::layouts>
    <x-slot:title>
        ماژول جدید
    </x-slot>

    <form method="POST" action="{{ route('admin.marketplace_server.modules.store') }}">
        @csrf

        <div class="mb-3.5 flex items-center justify-between">
            <p class="text-xl font-bold text-gray-800 dark:text-white">ماژول جدید</p>
        </div>

        @include('MarketplaceServer::admin.modules.form')
    </form>
</x-admin::layouts>
