<?php

namespace Modules\Marketplace\Http\Controller\Admin;

use App\Classes\ModuleRegistry;
use Illuminate\Http\Request;
use Modules\Marketplace\Model\InstalledModule;
use Modules\Marketplace\Model\ModuleInstallation;
use Modules\Marketplace\Services\Installer;
use Modules\Marketplace\Services\PackageException;
use Modules\Marketplace\Services\RepositoryClient;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * صفحهٔ «ماژول‌ها» در پنل.
 *
 * فهرست عمداً DataGrid نیست: چیزی که باید دیده شود ترکیب دیسک و دیتابیس است
 * (نسخهٔ روی دیسک، وضعیت در جدول، پیش‌نیاز غایب) و DataGrid روی یک کوئری
 * دیتابیس ساخته می‌شود؛ ماژولی که با FTP آمده و ردیف ندارد در آن دیده نمی‌شد.
 *
 * دسترسی‌ها دو لایه‌اند: میان‌افزار `admin` بر اساس `Config/acl.php` کلید هر
 * روت را چک می‌کند، و نصب/حذف علاوه بر آن نقش سوپرادمین می‌خواهد.
 */
class ModuleController extends Controller
{
    public function __construct(
        protected Installer $installer,
        protected RepositoryClient $repository,
    ) {}

    public function index()
    {
        $this->sync();

        $records = InstalledModule::query()->get()->keyBy('name');

        return view('Marketplace::admin.index', [
            'modules'      => ModuleRegistry::all(),
            'records'      => $records,
            'history'      => ModuleInstallation::query()->latest()->limit(10)->get(),
            'allowUpload'  => (bool) config('marketplace.allow_upload'),
            'protected'    => (array) config('marketplace.protected_modules', []),
            'locked'       => (array) config('marketplace.locked_modules', []),
            /**
             * فهرست مخزن کش می‌شود و خطای شبکه‌اش هم بلعیده؛ این صفحه باید
             * حتی وقتی مخزن پایین است کار کند، چون تنها راه غیرفعال‌کردن یک
             * ماژول خراب همین‌جاست.
             */
            'updates'      => $this->repository->isConfigured()
                ? $this->repository->updatesFor($records->pluck('version', 'name'))
                : [],
        ]);
    }

    /**
     * فعال یا غیرفعال کردن. برگشت‌پذیر است، پس تأیید اضافه نمی‌خواهد.
     */
    public function toggle(Request $request, string $name)
    {
        $wasEnabled = InstalledModule::query()->where('name', $name)->firstOrFail()->enabled;

        try {
            $wasEnabled
                ? $this->installer->disable($name, $request->boolean('force'))
                : $this->installer->enable($name);
        } catch (PackageException $exception) {
            session()->flash('error', $exception->getMessage());

            return redirect()->route('admin.marketplace.index');
        }

        session()->flash('success', $wasEnabled
            ? "ماژول $name غیرفعال شد."
            : "ماژول $name فعال شد؛ از ریکوئست بعدی بوت می‌شود.");

        return redirect()->route('admin.marketplace.index');
    }

    public function remove(Request $request, string $name)
    {
        $this->assertSuperAdmin();

        try {
            $log = $this->installer->remove(
                $name,
                $request->boolean('rollback_migrations'),
                $this->admin()
            );
        } catch (PackageException $exception) {
            session()->flash('error', $exception->getMessage());

            return redirect()->route('admin.marketplace.index');
        }

        session()->flash('success', "ماژول $name حذف شد. بکاپ: ".($log->payload('backup') ?: '—'));

        return redirect()->route('admin.marketplace.index');
    }

    /**
     * آپلود zip و شروع نصب.
     *
     * خودِ نصب اینجا اجرا نمی‌شود: فقط یک اجرا ساخته و به صفحهٔ پیشرفت
     * ریدایرکت می‌شود، چون روی هاست اشتراکی همهٔ مراحل در یک ریکوئست جا نمی‌شود.
     */
    public function install(Request $request)
    {
        $this->assertSuperAdmin();

        if (! config('marketplace.allow_upload')) {
            session()->flash('error', 'آپلود دستی بسته روی این نصب بسته است.');

            return redirect()->route('admin.marketplace.index');
        }

        $request->validate([
            'package' => 'required|file|mimes:zip|max:'.(int) config('marketplace.max_upload_size'),
        ], [], ['package' => 'بستهٔ ماژول']);

        $archive = storage_path('app/marketplace/tmp/upload-'.uniqid().'.zip');

        $request->file('package')->move(dirname($archive), basename($archive));

        $run = $this->installer->start('manual', [
            'archive'         => $archive,
            'allow_downgrade' => $request->boolean('allow_downgrade'),
        ], $this->admin());

        return redirect()->route('admin.marketplace.progress', $run->id);
    }

    public function progress(int $run)
    {
        $this->assertSuperAdmin();

        return view('Marketplace::admin.progress', [
            'run'   => ModuleInstallation::query()->findOrFail($run),
            'steps' => Installer::STEPS,
        ]);
    }

    /**
     * اجرای یک مرحله و برگرداندن وضعیت — صفحهٔ پیشرفت این را در حلقه صدا می‌زند.
     */
    public function advance(int $run)
    {
        $this->assertSuperAdmin();

        $state = $this->installer->advance(ModuleInstallation::query()->findOrFail($run));

        return response()->json([
            'step'     => $state->step,
            'label'    => Installer::STEPS[$state->step] ?? null,
            'status'   => $state->status,
            'module'   => $state->module,
            'version'  => $state->version,
            'error'    => $state->error,
            'finished' => $state->isFinished(),
            'redirect' => $state->isFinished() ? route('admin.marketplace.index') : null,
        ]);
    }

    /* ---------------------------------------------------------------------
     | کمکی‌ها
     * -------------------------------------------------------------------*/

    /**
     * ماژول‌هایی که روی دیسک هستند ولی ردیف وضعیت ندارند (کپی با FTP، ریستور
     * ناقص) به‌صورت **غیرفعال** ثبت می‌شوند.
     *
     * فعال‌کردن خودکارشان یعنی هر کسی که به FTP دسترسی دارد می‌تواند کد را
     * بی‌سروصدا به اجرا برساند؛ این‌طوری ادمین آن‌ها را در فهرست می‌بیند و
     * خودش تصمیم می‌گیرد.
     */
    protected function sync(): void
    {
        $known = InstalledModule::query()->pluck('name')->all();

        foreach (ModuleRegistry::all() as $name => $module) {
            if (in_array($name, $known, true)) {
                continue;
            }

            InstalledModule::create([
                'name'         => $name,
                'version'      => $module['version'],
                'enabled'      => false,
                'source'       => 'manual',
                'installed_at' => now(),
            ]);
        }
    }

    protected function admin(): array
    {
        $user = auth()->guard('admin')->user();

        return ['id' => $user?->id, 'name' => $user?->name];
    }

    /**
     * نصب و حذف یعنی «کد PHP تازه روی سرور» و «رفتن داده‌ها»؛ کلید ACL لازم است
     * ولی کافی نیست.
     */
    protected function assertSuperAdmin(): void
    {
        abort_unless(
            auth()->guard('admin')->user()?->role?->permission_type === 'all',
            401,
            'این کار فقط از نقش سوپرادمین انجام می‌شود.'
        );
    }
}
