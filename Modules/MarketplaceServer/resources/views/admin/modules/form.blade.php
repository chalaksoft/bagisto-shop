{{--
    فرم مشترک ساخت و ویرایش ماژول.

    «نامک» شناسهٔ ماژول در API است و «نام پوشه» همان چیزی که در `Modules/`
    فروشگاه خریدار ساخته می‌شود — و باید دقیقاً با نام پوشهٔ داخل zip و فیلد
    `name` در module.json یکی باشد، وگرنه انتشار رد می‌شود.
--}}
<div class="rounded bg-white p-5 dark:bg-gray-900">
    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">نام</label>
            <input type="text" name="name" value="{{ old('name', $module->name) }}" required
                   class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">دستهٔ نمایش</label>
            <input type="text" name="category" value="{{ old('category', $module->category) }}"
                   class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">نامک (در API و آدرس‌ها)</label>
            <input type="text" name="slug" value="{{ old('slug', $module->slug) }}" required
                   placeholder="invoice-pro" dir="ltr"
                   class="w-full rounded border px-3 py-2 font-mono text-sm dark:border-gray-800 dark:bg-gray-950">
            <p class="mt-1 text-xs text-gray-500">حروف کوچک انگلیسی، رقم و خط تیره. بعد از انتشار عوضش نکنید.</p>
        </div>

        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">نام پوشه در Modules/</label>
            <input type="text" name="package_name" value="{{ old('package_name', $module->package_name) }}" required
                   placeholder="InvoicePro" dir="ltr"
                   class="w-full rounded border px-3 py-2 font-mono text-sm dark:border-gray-800 dark:bg-gray-950">
            <p class="mt-1 text-xs text-gray-500">
                باید دقیقاً با نام پوشهٔ داخل zip و فیلد <code>name</code> در module.json یکی باشد.
            </p>
        </div>
    </div>

    <div class="mt-4">
        <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">توضیح</label>
        <textarea name="description" rows="3"
                  class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">{{ old('description', $module->description) }}</textarea>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">آیکون (نام یا آدرس)</label>
            <input type="text" name="icon" value="{{ old('icon', $module->icon) }}"
                   class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
        </div>

        <div>
            <label class="mb-1.5 block text-xs text-gray-600 dark:text-gray-300">شناسهٔ محصول در کاتالوگ</label>
            <input type="number" name="product_id" value="{{ old('product_id', $module->product_id) }}" dir="ltr"
                   class="w-full rounded border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-950">
            <p class="mt-1 text-xs text-gray-500">
                اختیاری — محصولی که مشتری برای خرید این ماژول سفارش می‌دهد.
            </p>
        </div>
    </div>

    <label class="mt-4 flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="published" value="1" @checked(old('published', $module->published))>
        منتشرشده — در API فروشگاه‌های مشتری دیده شود
    </label>

    <label class="mt-2 flex items-center gap-x-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="free" value="1" @checked(old('free', $module->free))>
        رایگان — بدون لایسنس هم قابل دانلود باشد
    </label>

    <div class="mt-5 flex items-center gap-x-2.5">
        <button type="submit" class="primary-button">ذخیره</button>

        <a href="{{ route('admin.marketplace_server.modules.index') }}" class="secondary-button">انصراف</a>
    </div>
</div>
