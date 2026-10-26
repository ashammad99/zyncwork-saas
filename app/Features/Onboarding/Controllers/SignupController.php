<?php

namespace App\Features\Onboarding\Controllers;

use App\Core\BaseController;
use App\Features\Onboarding\Requests\SignupRequest;
use App\Features\Onboarding\Services\SignupService;
use Illuminate\Support\Facades\Auth;

class SignupController extends BaseController
{
    public function __construct(
        protected SignupService $signups
    ) {}

    public function index()
    {
        return view('pages.auth.register.page');
    }

    public function store(SignupRequest $request)
    {
        // Honeypot (hidden field): only bots fill it. Look like success, create nothing.
        if ($request->filled('website')) {
            return redirect()->route('login');
        }

        $user = $this->signups->register($request->validated());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
