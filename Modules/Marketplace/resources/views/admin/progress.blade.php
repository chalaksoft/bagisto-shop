<x-admin::layouts>
    <x-slot:title>
        نصب ماژول
    </x-slot>

    <p class="text-xl font-bold text-gray-800 dark:text-white">
        نصب ماژول
    </p>

    <p class="mb-3.5 mt-1 text-xs leading-6 text-gray-500">
        هر مرحله یک درخواست جداست تا روی هاست‌هایی که تایم‌اوت کوتاه دارند نصب نیمه‌کاره نماند.
        این صفحه را نبندید.
    </p>

    <div
        class="rounded bg-white p-5 dark:bg-gray-900"
        id="marketplace-progress"
        data-advance="{{ route('admin.marketplace.advance', $run->id) }}"
        data-status="{{ $run->status }}"
    >
        <ol class="grid gap-3">
            @foreach ($steps as $key => $label)
                <li class="flex items-center gap-x-3 text-sm" data-step="{{ $key }}">
                    <span
                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[10px] text-gray-400 dark:border-gray-700"
                        data-marker
                    >•</span>

                    <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        <p class="mt-5 text-sm text-gray-500" data-message>در حال شروع…</p>

        <p class="mt-2 hidden text-sm text-red-600" data-error></p>

        <a href="{{ route('admin.marketplace.index') }}" class="secondary-button mt-5 hidden" data-back>
            بازگشت به فهرست ماژول‌ها
        </a>
    </div>

    @push('scripts')
        <script>
            /**
             * حلقهٔ مرحله‌به‌مرحله — هر پاسخ می‌گوید مرحلهٔ بعدی چیست. سرور
             * وضعیت را نگه می‌دارد، پس اگر کاربر صفحه را دوباره باز کند نصب از
             * همان‌جا ادامه پیدا می‌کند و مرحله‌ای دوباره اجرا نمی‌شود.
             */
            (function () {
                const root    = document.getElementById('marketplace-progress');
                const message = root.querySelector('[data-message]');
                const error   = root.querySelector('[data-error]');
                const back    = root.querySelector('[data-back]');
                const token   = document.querySelector('meta[name="csrf-token"]')?.content;

                const items = Array.from(root.querySelectorAll('[data-step]'));

                /**
                 * مرحلهٔ جاری همان است که سرور برای *دفعهٔ بعد* برگردانده، پس هر
                 * چه قبل از آن است تمام شده. با `null` (پایان کار) همه تیک می‌خورند.
                 */
                function paint(currentStep) {
                    const boundary = currentStep === null ? items.length : items.findIndex(
                        item => item.dataset.step === currentStep
                    );

                    items.forEach(function (item, index) {
                        const marker = item.querySelector('[data-marker]');

                        if (index < boundary) {
                            marker.textContent = '✓';
                            marker.className = 'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-green-500 text-[10px] text-green-600';
                        } else if (index === boundary) {
                            marker.textContent = '…';
                            marker.className = 'inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-blue-500 text-[10px] text-blue-600';
                        }
                    });
                }

                function finish(payload) {
                    if (payload.status === 'failed') {
                        error.textContent = payload.error || 'نصب ناموفق بود.';
                        error.classList.remove('hidden');
                        message.textContent = 'نصب متوقف شد؛ فایل‌های قبلی دست‌نخورده برگردانده شدند.';
                    } else {
                        message.textContent = 'ماژول ' + payload.module + ' نسخهٔ ' + payload.version
                            + ' نصب شد. از ریکوئست بعدی بوت می‌شود.';
                    }

                    back.classList.remove('hidden');
                }

                function advance() {
                    fetch(root.dataset.advance, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (payload) {
                            if (payload.finished) {
                                paint(null);
                                finish(payload);

                                return;
                            }

                            message.textContent = payload.label || '…';

                            paint(payload.step);

                            advance();
                        })
                        .catch(function () {
                            error.textContent = 'ارتباط با سرور قطع شد. صفحه را دوباره باز کنید؛ نصب از همین مرحله ادامه پیدا می‌کند.';
                            error.classList.remove('hidden');
                            back.classList.remove('hidden');
                        });
                }

                advance();
            })();
        </script>
    @endpush
</x-admin::layouts>
