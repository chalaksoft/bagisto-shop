{{--
    صفحهٔ اصلیِ چیده‌شده با صفحه‌ساز، داخل قالب فروشگاه.

    عمداً در `resources/views` است نه در پوشهٔ تم: بجیستو ویوهای تم را برای
    جایگزینی ویوهای پکیج نمی‌خواند، ولی فضای‌نام پیش‌فرض اپ همیشه در دسترس است.
--}}
@push('meta')
    <meta name="title" content="{{ $channel->home_seo['meta_title'] ?? '' }}" />

    <meta name="description" content="{{ $channel->home_seo['meta_description'] ?? '' }}" />

    <meta name="keywords" content="{{ $channel->home_seo['meta_keywords'] ?? '' }}" />

    {{--
        CSS اختصاصی سند. ساختارش آرایه است نه رشته، پس با پارشال خود ماژول
        رندر می‌شود — همان چیزی که قالب‌های صفحه‌ساز هم استفاده می‌کنند.
    --}}
    @if (! empty($embed['css']) && view()->exists('ElementorBagisto::parts.embed-head'))
        @include('ElementorBagisto::parts.embed-head', ['eeCss' => $embed['css']])
    @endif

    {{--
        پایهٔ ظاهری صفحه‌ساز (`elementor.extra_styles`) را همان پارشال بالا
        بار می‌کند؛ تکرارش اینجا فقط یک لینک اضافه در head می‌ساخت.
    --}}
@endpush

<x-shop::layouts>
    <x-slot:title>
        {{ $channel->home_seo['meta_title'] ?? $channel->name }}
    </x-slot>

    @if ($showAdminBar)
        @include('Elementor::parts.admin-bar', [
            'barDocumentId' => $embed['document']->id,
            'barDocument'   => $embed['document'],
        ])
    @endif

    {!! $embed['html'] !!}
</x-shop::layouts>
