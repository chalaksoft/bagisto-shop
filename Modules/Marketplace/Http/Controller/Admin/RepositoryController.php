<?php

namespace Modules\Marketplace\Http\Controller\Admin;

use Illuminate\Http\Request;
use Modules\Marketplace\Model\InstalledModule;
use Modules\Marketplace\Services\Installer;
use Modules\Marketplace\Services\RepositoryClient;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * صفحهٔ «مخزن» — ویترین ماژول‌های قابل نصب.
 *
 * برخلاف صفحهٔ «ماژول‌های نصب‌شده» که فقط دیسک و دیتابیس محلی را می‌خواند،
 * این صفحه به یک سرویس بیرونی وابسته است؛ پس هر خطای شبکه به‌جای ۵۰۰ به یک
 * پیام در همان صفحه تبدیل می‌شود.
 */
class RepositoryController extends Controller
{
    public function __construct(
        protected RepositoryClient $repository,
        protected Installer $installer,
    ) {}

    public function index(Request $request)
    {
        if ($request->boolean('refresh')) {
            $this->repository->flush();
        }

        $catalogue = $this->repository->modules();

        $installed = InstalledModule::query()->pluck('version', 'name');

        return view('Marketplace::admin.repository', [
            'catalogue' => $catalogue,
            'installed' => $installed,
            'domain'    => $this->repository->domain(),
            'configured' => $this->repository->isConfigured(),
        ]);
    }

    /**
     * شروع نصب از مخزن. مثل نصب از فایل، فقط یک اجرا ساخته می‌شود و مراحل در
     * صفحهٔ پیشرفت جلو می‌روند.
     */
    public function install(Request $request, string $slug)
    {
        $this->assertSuperAdmin();

        $run = $this->installer->start('repository', array_filter([
            'slug'            => $slug,
            'version'         => $request->input('version'),
            'allow_downgrade' => $request->boolean('allow_downgrade'),
        ]), $this->admin());

        return redirect()->route('admin.marketplace.progress', $run->id);
    }

    /**
     * وضعیت لایسنس این نصب — جدا از فهرست، چون فهرست کش می‌شود و وضعیت لایسنس
     * چیزی است که ادمین معمولاً همین حالا می‌خواهد بداند.
     */
    public function license()
    {
        return response()->json($this->repository->license());
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    protected function admin(): array
    {
        $user = auth()->guard('admin')->user();

        return ['id' => $user?->id, 'name' => $user?->name];
    }

    protected function assertSuperAdmin(): void
    {
        abort_unless(
            auth()->guard('admin')->user()?->role?->permission_type === 'all',
            401,
            'این کار فقط از نقش سوپرادمین انجام می‌شود.'
        );
    }
}
