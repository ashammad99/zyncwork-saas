<?php

namespace App\Features\Profile\User\Controllers;

use App\Core\BaseController;
use App\Features\Profile\User\Requests\UpdatePasswordRequest;
use App\Features\Profile\User\Requests\UpdateProfileRequest;
use App\Features\Profile\User\Services\ProfileService;
use Inertia\Inertia;

class ProfileController extends BaseController
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function index()
    {
        return Inertia::render($this->portalPage('profile/page'));
    }

    public function update(UpdateProfileRequest $request)
    {
        $this->profileService->updateProfile($request->user(), $request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function passwordIndex()
    {
        return Inertia::render($this->portalPage('profile/password/page'));
    }

    public function passwordUpdate(UpdatePasswordRequest $request)
    {
        $this->profileService->updatePassword($request->user(), $request->password);

        return back()->with('success', 'Password changed successfully.');
    }

    /**
     * Resolve the page path for the current user's portal.
     */
    protected function portalPage(string $suffix): string
    {
        $portal = match (auth()->user()->role) {
            'manager' => 'manager',
            'employee' => 'employee',
            default => 'user',
        };

        return "(portals)/{$portal}/{$suffix}";
    }
}
