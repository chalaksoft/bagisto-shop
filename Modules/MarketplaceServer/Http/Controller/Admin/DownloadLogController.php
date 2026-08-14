<?php

namespace Modules\MarketplaceServer\Http\Controller\Admin;

use Illuminate\Http\Request;
use Modules\MarketplaceServer\Model\DownloadLog;
use Webkul\Admin\Http\Controllers\Controller;

class DownloadLogController extends Controller
{
    public function index(Request $request)
    {
        return view('MarketplaceServer::admin.logs.index', [
            'logs' => DownloadLog::with(['license.customer', 'version'])
                ->when($request->query('only') === 'rejected', fn ($query) => $query->where('allowed', false))
                ->when($request->query('slug'), fn ($query, $slug) => $query->where('module_slug', $slug))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'only' => $request->query('only'),
        ]);
    }
}
