<?php

namespace Modules\MarketplaceServer\Http\Controller\Admin;

use Illuminate\Http\Request;
use Modules\MarketplaceServer\Model\License;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Customer\Models\Customer;

/**
 * صدور و مدیریت لایسنس.
 *
 * توکن خام فقط یک بار — بلافاصله بعد از صدور — نشان داده می‌شود؛ در دیتابیس
 * هشش می‌نشیند. اگر مشتری گمش کرد، توکن تازه صادر می‌شود، نه اینکه قبلی
 * بازیابی شود.
 */
class LicenseController extends Controller
{
    public function index()
    {
        return view('MarketplaceServer::admin.licenses.index', [
            'licenses' => License::with('customer')->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('MarketplaceServer::admin.licenses.create', [
            'license' => new License(['active' => true, 'domains' => []]),
        ] + $this->formData());
    }

    public function store(Request $request)
    {
        $token = License::newToken();

        $license = License::create($this->validated($request) + [
            'token_hash' => License::hash($token),
            'token_hint' => substr($token, -4),
        ]);

        /**
         * توکن با فلش سشن منتقل می‌شود، نه با query string: آدرس در تاریخچهٔ
         * مرورگر و لاگ وب‌سرور می‌ماند و توکن نباید آنجا باشد.
         */
        return redirect()
            ->route('admin.marketplace_server.licenses.index')
            ->with('new_token', $token)
            ->with('new_token_license', $license->id);
    }

    public function edit(int $id)
    {
        return view('MarketplaceServer::admin.licenses.edit', [
            'license' => License::findOrFail($id),
        ] + $this->formData());
    }

    public function update(Request $request, int $id)
    {
        License::findOrFail($id)->update($this->validated($request));

        session()->flash('success', 'لایسنس به‌روزرسانی شد.');

        return redirect()->route('admin.marketplace_server.licenses.index');
    }

    /**
     * صدور توکن تازه برای همان لایسنس.
     *
     * توکن قبلی از همان لحظه بی‌اثر است — همین کار را وقتی می‌کنید که توکن لو
     * رفته باشد.
     */
    public function rotate(int $id)
    {
        $license = License::findOrFail($id);

        $token = License::newToken();

        $license->update([
            'token_hash' => License::hash($token),
            'token_hint' => substr($token, -4),
        ]);

        return redirect()
            ->route('admin.marketplace_server.licenses.index')
            ->with('new_token', $token)
            ->with('new_token_license', $license->id)
            ->with('success', 'توکن تازه صادر شد؛ توکن قبلی دیگر کار نمی‌کند.');
    }

    public function destroy(int $id)
    {
        License::findOrFail($id)->delete();

        return response()->json(['message' => 'لایسنس حذف شد.']);
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    protected function formData(): array
    {
        return [
            'customers' => Customer::query()
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(fn (Customer $customer) => [
                    $customer->id => trim($customer->first_name.' '.$customer->last_name).' — '.$customer->email,
                ]),
            'modules' => RepositoryModule::orderBy('name')->pluck('name', 'slug'),
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id'    => 'nullable|integer|exists:customers,id',
            'label'          => 'nullable|string|max:190',
            'domains'        => 'required|string',
            'module_slugs'   => 'nullable|array',
            'module_slugs.*' => 'string|exists:marketplace_modules,slug',
            'expires_at'     => 'nullable|date',
        ], [], [
            'customer_id' => 'مشتری',
            'domains'     => 'دامنه‌ها',
        ]);

        /**
         * دامنه‌ها به شکل نرمال‌شده ذخیره می‌شوند تا مقایسه سر درخواست API یک
         * مقایسهٔ سادهٔ رشته‌ای بماند.
         */
        $data['domains'] = collect(preg_split('/[\s,]+/', $data['domains']))
            ->map(fn ($domain) => License::normalizeDomain($domain))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data['module_slugs'] = $request->input('module_slugs') ?: null;
        $data['active']       = $request->boolean('active');

        return $data;
    }
}
