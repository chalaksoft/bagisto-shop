<div class="rounded bg-white p-5 dark:bg-gray-900">
    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">مشتری</label>

            <select name="customer_id"
                    class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                <option value="">— بدون مشتری —</option>

                @foreach ($customers as $id => $label)
                    <option value="{{ $id }}" @selected(old('customer_id', $license->customer_id) == $id)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <p class="mt-1 text-xs text-gray-500">مشتری از همان دفتر مشتریان فروشگاه می‌آید.</p>
        </div>

        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">برچسب</label>
            <input type="text" name="label" value="{{ old('label', $license->label) }}"
                   placeholder="مثلاً: فروشگاه اصلی"
                   class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
        </div>
    </div>

    <div class="mt-4">
        <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">دامنه‌های مجاز</label>

        <textarea name="domains" rows="3" required dir="ltr"
                  class="w-full rounded border px-3 py-2 font-mono text-sm dark:border-gray-800 dark:bg-gray-950">{{ old('domains', implode("\n", (array) $license->domains)) }}</textarea>

        <p class="mt-1 text-xs text-gray-500">
            هر خط یک دامنه. پروتکل و <code>www.</code> خودکار حذف می‌شوند.
            زیردامنه مجاز نیست مگر صریحاً نوشته شود: <code>shop.example.com</code>
        </p>
    </div>

    <div class="mt-4">
        <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">ماژول‌های مجاز</label>

        @forelse ($modules as $slug => $name)
            <label class="flex items-center gap-x-2 py-0.5 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" name="module_slugs[]" value="{{ $slug }}"
                       @checked(in_array($slug, old('module_slugs', (array) $license->module_slugs), true))>
                {{ $name }}
                <span class="font-mono text-xs text-gray-400">{{ $slug }}</span>
            </label>
        @empty
            <p class="text-xs text-gray-500">هنوز ماژولی برای فروش ساخته نشده است.</p>
        @endforelse

        <p class="mt-1 text-xs text-gray-500">هیچ‌کدام را نزنید یعنی «همهٔ ماژول‌ها».</p>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">تاریخ انقضا</label>
            <input type="date" name="expires_at" dir="ltr"
                   value="{{ old('expires_at', $license->expires_at?->format('Y-m-d')) }}"
                   class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
            <p class="mt-1 text-xs text-gray-500">خالی یعنی بدون انقضا.</p>
        </div>

        <label class="flex items-end gap-x-2 pb-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" name="active" value="1"
                   @checked(old('active', $license->exists ? $license->active : true))>
            فعال
        </label>
    </div>

    <div class="mt-5 flex items-center gap-x-2.5">
        <button type="submit" class="primary-button">
            {{ $license->exists ? 'ذخیره' : 'صدور لایسنس' }}
        </button>

        <a href="{{ route('admin.marketplace_server.licenses.index') }}" class="secondary-button">انصراف</a>
    </div>
</div>
