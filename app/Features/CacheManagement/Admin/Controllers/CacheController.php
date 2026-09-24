<?php

namespace App\Features\CacheManagement\Admin\Controllers;

use App\Core\BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Features\CacheManagement\Admin\Requests\ClearCacheRequest;
use App\Features\CacheManagement\Admin\Services\CacheService;

class CacheController extends BaseController
{
    public function __construct(
        protected CacheService $cacheService
    ) {}

    /**
     * Display the cache management page.
     */
    public function index()
    {
        return Inertia::render('(portals)/admin/cache/page');
    }

    /**
     * Clear specific or all caches based on request.
     */
    public function clear(ClearCacheRequest $request)
    {
        try {
            $msg = $this->cacheService->clearCaches($request->validated()['type']);
            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }
}
