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
        {{-- چیدمان ادمین بجیستو متای `csrf-token` ندارد، پس توکن از همین‌جا می‌آید. --}}
        data-token="{{ csrf_token() }}"
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

        <p class="mt-5 flex items-center gap-x-2 text-sm text-gray-500" data-message>
            <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600" data-spinner></span>
            <span data-message-text>در حال شروع…</span>
        </p>

        {{--
            نتیجه باید در نگاه اول دیده شود. قبلاً فقط یک خط خاکستری کم‌رنگ
            عوض می‌شد و کاربر نمی‌فهمید نصب تمام شده یا نه.
        --}}
        <div class="mt-5 hidden rounded border p-4" data-result>
            <p class="text-sm font-semibold" data-result-title></p>

            <p class="mt-1 text-xs leading-6" data-result-body></p>
        </div>

        <a href="{{ route('admin.marketplace.index') }}" class="primary-button mt-5 hidden" data-back>
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
                const root        = document.getElementById('marketplace-progress');
                const message     = root.querySelector('[data-message-text]');
                const spinner     = root.querySelector('[data-spinner]');
                const result      = root.querySelector('[data-result]');
                const resultTitle = root.querySelector('[data-result-title]');
                const resultBody  = root.querySelector('[data-result-body]');
                const back        = root.querySelector('[data-back]');
                const token       = root.dataset.token;

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

                function show(kind, title, body) {
                    spinner.classList.add('hidden');

                    result.className = 'mt-5 rounded border p-4 ' + (kind === 'success'
                        ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-900/20 dark:text-green-300'
                        : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300');

                    resultTitle.textContent = title;
                    resultBody.textContent  = body;

                    back.classList.remove('hidden');
                }

                function finish(payload) {
                    if (payload.status === 'failed') {
                        message.textContent = 'نصب متوقف شد.';

                        show(
                            'error',
                            '✕ ' + (payload.error || 'نصب ناموفق بود.'),
                            'فایل‌های نسخهٔ قبلی دست‌نخورده برگردانده شدند؛ چیزی روی سایت عوض نشده است.'
                        );

                        return;
                    }

                    message.textContent = 'تمام شد.';

                    show(
                        'success',
                        '✓ ماژول ' + payload.module + ' نسخهٔ ' + payload.version + ' نصب شد.',
                        'از ریکوئست بعدی بوت می‌شود؛ اگر بلافاصله اثری ندیدید یک‌بار صفحه را دوباره باز کنید.'
                    );
                }

                function stop(text) {
                    message.textContent = 'نصب نیمه‌کاره ماند.';

                    show('error', '✕ ارتباط با سرور قطع شد', text);
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
                        .then(function (response) {
                            /**
                             * بدون این بررسی، هر پاسخ خطا هم «مرحلهٔ بعدی» حساب
                             * می‌شد و حلقه تا ابد همان خطا را دوباره می‌گرفت.
                             */
                            if (! response.ok) {
                                return response.json().catch(function () { return {}; }).then(function (body) {
                                    throw new Error(
                                        body.description || body.error || body.message
                                            || ('سرور با کد ' + response.status + ' پاسخ داد.')
                                    );
                                });
                            }

                            return response.json();
                        })
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
                        .catch(function (exception) {
                            stop(
                                (exception && exception.message ? exception.message + ' ' : '')
                                + 'صفحه را دوباره باز کنید؛ نصب از همین مرحله ادامه پیدا می‌کند.'
                            );
                        });
                }

                advance();
            })();
        </script>
    @endpush
</x-admin::layouts>
