<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Models\PlatformImpersonation;
use App\Features\Platform\Requests\StartImpersonationRequest;
use App\Features\Platform\Services\ImpersonationService;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ImpersonationController extends BaseController
{
    public function __construct(
        protected ImpersonationService $impersonations
    ) {}

    /** Platform host: create the session and send the browser to the tenant host. */
    public function start(StartImpersonationRequest $request, Tenant $tenant)
    {
        $data = $request->validated();
        $token = $this->impersonations->request($request->user('platform'), $tenant, (int) $data['user_id'], $data['reason']);

        // route() would build this on the platform host; the hand-off belongs on the tenant app.
        $url = rtrim(config('app.url'), '/').route('impersonation.consume', $token, false);

        return Inertia::location($url);
    }

    /** Tenant host: exchange the one-time token for a web-guard session. */
    public function consume(Request $request, string $token)
    {
        if (! $this->impersonations->consume($token, $request)) {
            return redirect()->route('login')->withErrors(['email' => 'This support link is invalid or has expired.']);
        }

        return redirect()->route('dashboard');
    }

    /** Tenant host: end the session and return to the platform console. */
    public function stop(Request $request)
    {
        $impersonation = PlatformImpersonation::find($request->session()->get('impersonation_id'));

        if (! $impersonation) {
            return redirect()->route('dashboard');
        }

        $this->impersonations->end($impersonation, PlatformImpersonation::END_STOPPED);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The console route is missing when PLATFORM_DOMAIN is unset (routes/web.php).
        return Inertia::location(Route::has('platform.tenants.show')
            ? route('platform.tenants.show', $impersonation->tenant_id)
            : route('login'));
    }
}
