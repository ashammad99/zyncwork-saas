<?php

namespace App\Features\Profile\Admin\Controllers;

use App\Core\BaseController;

use Inertia\Inertia;
use App\Features\Profile\Admin\Services\ProfileService;
use App\Features\Profile\Admin\Requests\UpdateProfileRequest;
use App\Features\Profile\Admin\Requests\UpdatePasswordRequest;
use App\Features\Profile\Admin\Models\User;

class ProfileController extends BaseController
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function index()
    {
        return Inertia::render('(portals)/admin/profile/page');
    }

    public function update(UpdateProfileRequest $request)
    {
        $this->profileService->updateProfile($request->user(), $request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function passwordIndex()
    {
        return Inertia::render('(portals)/admin/profile/password/page');
    }

    public function passwordUpdate(UpdatePasswordRequest $request)
    {
        $this->profileService->updatePassword($request->user(), $request->password);

        return back()->with('success', 'Password changed successfully.');
    }
}