import { existsSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

/**
 * ورودی‌های ماژول‌هایی که ویوهایشان `@vite(...)` صدا می‌زنند.
 *
 * این «قرارداد میزبان» است: ماژول در زمان اجرا از مخزن نصب می‌شود، ولی
 * دارایی‌هایش باید در بیلدِ همین اپ باشند. خروجی بیلد در `public/build` کامیت
 * می‌شود، پس کلید مانیفست حتی وقتی ماژول هنوز نصب نشده هم وجود دارد و بعد از
 * نصبش بدون هیچ بیلد تازه‌ای کار می‌کند — کاربر نهایی به Node نیاز ندارد.
 *
 * `existsSync` برای وقتی است که این ریپو را بدون آن ماژول‌ها بیلد می‌کنید؛
 * نبودشان نباید بیلد را بشکند.
 */
const moduleInputs = [
    'Modules/Elementor/resources/js/front.js',
    'Modules/Elementor/resources/js/editor/main.jsx',
].filter((path) => existsSync(path));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...moduleInputs],
            refresh: true,
        }),

        react(),
    ],
});
