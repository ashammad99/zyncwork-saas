<?php

namespace App\Features\SystemSettings\Admin\Controllers;

use App\Core\BaseController;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\SystemSettings\Admin\Requests\UpdateSettingsRequest;
use App\Features\SystemSettings\Admin\Services\SettingsService;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends BaseController
{
    public function __construct(
        protected SettingsService $settingsService,
    ) {}

    /**
     * Display the settings page.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Setting::class);

        return Inertia::render('(portals)/admin/settings/page', [
            'groups' => $this->settingsService->grouped(),
            'values' => $this->settingsService->flat(),
        ]);
    }

    /**
     * Persist updated settings.
     */
    public function update(UpdateSettingsRequest $request)
    {
        $this->authorize('update', Setting::class);

        $this->settingsService->update($request->validated());

        return back()->with('success', 'Settings updated successfully.');
    }
}
