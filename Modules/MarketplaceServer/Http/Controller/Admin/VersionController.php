<?php

namespace Modules\MarketplaceServer\Http\Controller\Admin;

use Illuminate\Http\Request;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Modules\MarketplaceServer\Model\RepositoryVersion;
use Modules\MarketplaceServer\Services\VersionPublisher;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * آپلود و مدیریت نسخه‌های یک ماژول.
 *
 * شمارهٔ نسخه از فرم گرفته نمی‌شود؛ از `module.json` داخل خود بسته خوانده
 * می‌شود. دو منبع حقیقت برای نسخه یعنی روزی که با هم نمی‌خوانند و فروشگاه
 * خریدار چیزی نصب می‌کند که فکر می‌کند چیز دیگری است.
 */
class VersionController extends Controller
{
    public function __construct(protected VersionPublisher $publisher) {}

    public function store(Request $request, int $module)
    {
        $module = RepositoryModule::findOrFail($module);

        $request->validate([
            'package'   => 'required|file|mimes:zip|max:'.(int) config('marketplace-server.max_upload_size'),
            'changelog' => 'nullable|string',
        ], [], ['package' => 'بستهٔ ماژول']);

        try {
            $version = $this->publisher->publish(
                $module,
                $request->file('package')->getRealPath(),
                [
                    'changelog' => $request->input('changelog'),
                    'release'   => $request->boolean('release'),
                ]
            );
        } catch (Throwable $exception) {
            session()->flash('error', $exception->getMessage());

            return redirect()->route('admin.marketplace_server.modules.show', $module->id);
        }

        session()->flash('success', "نسخهٔ {$version->version} آپلود و امضا شد.".
            ($version->isReleased() ? '' : ' هنوز منتشر نشده و در API دیده نمی‌شود.'));

        return redirect()->route('admin.marketplace_server.modules.show', $module->id);
    }

    public function toggle(int $module, int $version)
    {
        $version = $this->version($module, $version);

        $this->publisher->toggleRelease($version);

        session()->flash('success', $version->isReleased()
            ? "نسخهٔ {$version->version} منتشر شد."
            : "نسخهٔ {$version->version} به پیش‌نویس برگشت و دیگر در API نیست.");

        return redirect()->route('admin.marketplace_server.modules.show', $module);
    }

    public function destroy(int $module, int $version)
    {
        $version = $this->version($module, $version);

        $number = $version->version;

        $this->publisher->delete($version);

        return response()->json(['message' => "نسخهٔ $number حذف شد."]);
    }

    /**
     * محافظت در برابر دستکاری شناسه در آدرس — نسخهٔ ماژول دیگری نباید از این
     * مسیر قابل حذف باشد.
     */
    protected function version(int $module, int $version): RepositoryVersion
    {
        $version = RepositoryVersion::findOrFail($version);

        abort_unless($version->marketplace_module_id === $module, 404);

        return $version;
    }
}
