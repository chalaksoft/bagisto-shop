<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\View;
use Webkul\Shop\Http\Controllers\HomeController as BagistoHomeController;

/**
 * صفحهٔ اصلی — اگر با صفحه‌ساز چیده شده باشد، همان رندر می‌شود.
 *
 * ┄┄ چرا روت جدا و نه بازنویسی ویو ┄┄
 *
 * راه معمول در بجیستو این است که ویوی تم بازنویسی شود، ولی بجیستو ۲.۳
 * فضای‌نام `shop::` را به ویوهای خود پکیج قفل می‌کند: `views_path` تم برای
 * سیستم «سفارشی‌سازی ظاهر» است، نه برای جایگزینی ویوهای پکیج. عملاً هم
 * `View::prependNamespace('shop', …)` — چه در `boot()` چه در `booted()` — اثری
 * نداشت و `shop::home.index` همچنان به فایل پکیج می‌رسید.
 *
 * پس نقطهٔ درست مداخله خودِ روت است، نه ویو. این‌طور هیچ فایلی از
 * `packages/Webkul` دست نمی‌خورد.
 *
 * وقتی سندی انتخاب نشده باشد — یا اصلاً ماژول صفحه‌ساز نصب نباشد — کار به
 * کنترلر خود بجیستو سپرده می‌شود و رفتار دقیقاً همان قبل است.
 */
class HomeController extends BagistoHomeController
{
    public function index()
    {
        $embed = $this->pageBuilderEmbed();

        if (! $embed) {
            return parent::index();
        }

        return view('host::home.page-builder', [
            'embed'   => $embed,
            'channel' => core()->getCurrentChannel(),
            /**
             * نوار ادمین «ویرایش این صفحه» را فقط وقتی نشان می‌دهیم که ویوی
             * ماژول واقعاً موجود باشد؛ نبودش نباید صفحهٔ اصلی را بشکند.
             */
            'showAdminBar' => View::exists('Elementor::parts.admin-bar'),
        ]);
    }

    /**
     * سند انتخاب‌شده به‌عنوان صفحهٔ اصلی، یا null.
     *
     * هر دو کلاس با `class_exists` چک می‌شوند چون ماژول صفحه‌ساز از مخزن نصب
     * می‌شود و ممکن است روی این نصب اصلاً نباشد.
     */
    protected function pageBuilderEmbed(): ?array
    {
        $setting = \Modules\Elementor\Model\Setting::class;
        $embed   = \Modules\ElementorBagisto\Support\DocumentEmbed::class;

        if (! class_exists($setting) || ! class_exists($embed)) {
            return null;
        }

        try {
            $documentId = (int) $setting::optionCache('elementor_home_doc');

            return $documentId ? $embed::resolve($documentId) : null;
        } catch (\Throwable) {
            /** جدول‌های صفحه‌ساز هنوز مهاجرت نشده‌اند — صفحهٔ عادی رندر شود. */
            return null;
        }
    }
}
