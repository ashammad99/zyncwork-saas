<?php

namespace App\Features\Auth\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Services\ForgotPasswordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends BaseController
{
    public function __construct(
        protected ForgotPasswordService $forgotPasswordService
    ) {}

    public function index()
    {
        return view('pages.auth.forgot-password.page', [
            'title' => 'Reset Password - Laravel Feature Kit',
            'description' => 'Forget your password? No worries, reset it here.',
        ]);
    }

    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = $this->forgotPasswordService->sendResetLink($request->email);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('pages.auth.reset-password.page', [
            'token' => $token,
            'email' => $request->email,
            'title' => 'Reset Password - Laravel Feature Kit',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = $this->forgotPasswordService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
