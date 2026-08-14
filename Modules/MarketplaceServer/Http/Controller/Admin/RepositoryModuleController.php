<?php

namespace Modules\MarketplaceServer\Http\Controller\Admin;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Modules\MarketplaceServer\Services\PackageSigner;
use Webkul\Admin\Http\Controllers\Controller;

class RepositoryModuleController extends Controller
{
    public function index(PackageSigner $signer)
    {
        return view('MarketplaceServer::admin.modules.index', [
            'modules' => RepositoryModule::withCount([
                'versions',
                'versions as released_count' => fn ($query) => $query->whereNotNull('released_at'),
            ])->orderBy('name')->get(),
            /**
             * بدون کلید، انتشار نسخه ممکن نیست — مهم‌ترین چیزی که یک نصب تازه
             * ممکن است فراموشش کند، پس بالای همین صفحه هشدار داده می‌شود.
             */
            'hasKeys' => $signer->hasKeys(),
        ]);
    }

    public function create()
    {
        return view('MarketplaceServer::admin.modules.create', [
            'module' => new RepositoryModule(['published' => false, 'free' => false]),
        ]);
    }

    public function store(Request $request)
    {
        $module = RepositoryModule::create($this->validated($request));

        session()->flash('success', 'ماژول ساخته شد. حالا اولین نسخه‌اش را آپلود کنید.');

        return redirect()->route('admin.marketplace_server.modules.show', $module->id);
    }

    public function show(int $id)
    {
        return view('MarketplaceServer::admin.modules.show', [
            'module'    => RepositoryModule::with('versions')->findOrFail($id),
            'maxUpload' => (int) config('marketplace-server.max_upload_size'),
        ]);
    }

    public function edit(int $id)
    {
        return view('MarketplaceServer::admin.modules.edit', [
            'module' => RepositoryModule::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $module = RepositoryModule::findOrFail($id);

        $module->update($this->validated($request, $module));

        session()->flash('success', 'ماژول به‌روزرسانی شد.');

        return redirect()->route('admin.marketplace_server.modules.show', $module->id);
    }

    /**
     * حذف ماژول، نسخه‌ها و فایل‌هایشان.
     *
     * فروشگاه‌هایی که نصبش کرده‌اند دست‌نخورده می‌مانند؛ فقط دیگر به‌روزرسانی
     * نمی‌گیرند. برای «دیگر نفروش» بهتر است `published` را بردارید نه حذف.
     */
    public function destroy(int $id)
    {
        $module = RepositoryModule::with('versions')->findOrFail($id);

        foreach ($module->versions as $version) {
            if (is_file($path = $version->absolutePath())) {
                @unlink($path);
            }
        }

        $module->delete();

        return response()->json(['message' => 'ماژول و همهٔ نسخه‌هایش حذف شدند.']);
    }

    protected function validated(Request $request, ?RepositoryModule $module = null): array
    {
        $data = $request->validate([
            'slug' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('marketplace_modules', 'slug')->ignore($module),
            ],
            'package_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'name'         => 'required|string|max:190',
            'description'  => 'nullable|string',
            'icon'         => 'nullable|string|max:190',
            'category'     => 'nullable|string|max:100',
            'product_id'   => 'nullable|integer|exists:products,id',
        ], [
            'slug.regex'         => 'نامک فقط حروف کوچک انگلیسی، رقم و خط تیره.',
            'package_name.regex' => 'نام پوشه باید مثل نام کلاس باشد: با حرف شروع شود، بدون فاصله و خط تیره.',
        ], [
            'slug'         => 'نامک',
            'package_name' => 'نام پوشه',
            'name'         => 'نام',
        ]);

        $data['published'] = $request->boolean('published');
        $data['free']      = $request->boolean('free');

        return $data;
    }
}
