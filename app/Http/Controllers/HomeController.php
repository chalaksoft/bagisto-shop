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

        return $this->renderDocument($embed);
    }

    /**
     * سند انتخاب‌شده به‌عنوان صفحهٔ اصلی، یا null.
     *
     * هر دو کلاس با `class_exists` چک می‌شوند چون ماژول صفحه‌ساز از مخزن نصب
     * می‌شود و ممکن است روی این نصب اصلاً نباشد.
     */
    /**
     * هر صفحهٔ دیگری که با صفحه‌ساز چیده شده — مثل «/modules».
     *
     * برخلاف صفحهٔ اصلی که سندش از تنظیمات می‌آید، اینجا سند با `slug` پیدا
     * می‌شود. اگر سند نبود ۴۰۴، نه صفحهٔ خالی.
     */
    public function document(string $slug)
    {
        $embed = $this->embedBySlug($slug);

        abort_if(! $embed, 404);

        return $this->renderDocument($embed);
    }

    /**
     * رندر سند همراه هدر و فوترِ صفحه‌ساز.
     *
     * `renderDocumentParts` هدر و فوتر انتخاب‌شده در تنظیمات صفحه‌ساز را
     * می‌سازد و آدرس CSS تولیدی‌شان را هم برمی‌گرداند؛ همان چیزی که بلاگ هم
     * استفاده می‌کند.
     */
    protected function renderDocument(array $embed)
    {
        $document = $embed['document'];

        $parts = (new \Modules\Elementor\Support\Renderer())->renderDocumentParts($document);

        return view('host::home.page-builder', [
            'document'     => $document,
            'html'         => $embed['html'],
            'header_html'  => $parts['header_html'] ?? '',
            'footer_html'  => $parts['footer_html'] ?? '',
            'css_links'    => array_merge((array) ($embed['css'] ?? []), (array) ($parts['css_links'] ?? [])),
            'channel'      => core()->getCurrentChannel(),
            'showAdminBar' => View::exists('Elementor::parts.admin-bar'),
        ]);
    }

    protected function embedBySlug(string $slug): ?array
    {
        $document = \Modules\Elementor\Model\ElementorDocument::class;
        $embed    = \Modules\ElementorBagisto\Support\DocumentEmbed::class;

        if (! class_exists($document) || ! class_exists($embed)) {
            return null;
        }

        try {
            $id = $document::query()->published()->where('slug', $slug)->value('id');

            return $id ? $embed::resolve((int) $id) : null;
        } catch (\Throwable) {
            return null;
        }
    }

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
