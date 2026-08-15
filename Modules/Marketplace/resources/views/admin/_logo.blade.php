{{--
    لوگوی ماژول.

    ورودی‌ها: $src (آدرس تصویر یا خالی)، $name (برای alt و حرف پیش‌فرض)،
    $size (کلاس اندازه) و $color (کلاس رنگ زمینهٔ پیش‌فرض).

    ماژولی که لوگو ندارد یک مربع رنگی با نشان قطعه می‌گیرد، نه جای خالی —
    مثل آیکون پیش‌فرض افزونه‌های وردپرس. رنگ از روی نام ساخته می‌شود، پس هر
    ماژول همیشه همان رنگ را دارد و در فهرست قابل تشخیص می‌ماند.
--}}
@php
    $size  = $size ?? 'h-12 w-12';
    $name  = $name ?? '';
    $color = $color ?? 'bg-slate-500';
@endphp

@if (filled($src ?? null))
    <img
        src="{{ $src }}"
        alt="{{ $name }}"
        loading="lazy"
        class="{{ $size }} shrink-0 rounded border bg-white object-contain p-1 dark:border-gray-800 dark:bg-gray-950"
    >
@else
    <span class="{{ $size }} {{ $color }} flex shrink-0 items-center justify-center rounded text-white" title="{{ $name }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" class="h-1/2 w-1/2" aria-hidden="true">
            <path d="M10 3.5a1.5 1.5 0 0 1 3 0V5h3a1 1 0 0 1 1 1v3h1.5a1.5 1.5 0 0 1 0 3H17v3a1 1 0 0 1-1 1h-3v1.5a1.5 1.5 0 0 1-3 0V16H7a1 1 0 0 1-1-1v-3H4.5a1.5 1.5 0 0 1 0-3H6V6a1 1 0 0 1 1-1h3V3.5Z"/>
        </svg>
    </span>
@endif
