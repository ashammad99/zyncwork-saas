<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Requests\ClearCacheRequest;
use App\Features\Platform\Services\CacheService;
use Inertia\Inertia;

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
        return Inertia::render('(platform)/cache/page');
    }

    /**
     * Clear specific or all caches based on request.
     */
    public function clear(ClearCacheRequest $request)
    {
        try {
            $msg = $this->cacheService->clearCaches($request->validated()['type'], $request->user('platform'));
            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }
}
