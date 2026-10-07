<?php

namespace App\Features\Dashboard\Controllers;

use App\Core\BaseController;
use App\Features\Dashboard\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends BaseController
{
    public function __construct(
        protected DashboardService $dashboardService

    ) {}

    public function index()
    {

        $user = auth()->user();

        return match ($user->role) {
            'admin' => $this->adminDashboard(),
            'manager' => $this->managerDashboard(),
            default => $this->employeeDashboard(),
        };
    }

    protected function adminDashboard()
    {
        $user = Auth::user();

        return inertia('(portals)/admin/dashboard/page', [
            'user' => $user,
            'metrics' => $this->dashboardService->getAdminMetrics(),
            'recent_activity' => $this->dashboardService->getRecentActivity(10),
        ]);
    }

    protected function managerDashboard()
    {
        $user = Auth::user();

        return inertia('(portals)/manager/dashboard/page', [
            'user' => $user,
            'metrics' => $this->dashboardService->getManagerMetrics($user),
            'recent_activity' => $this->dashboardService->getManagerRecentActivity($user, 10), // ← changed
            'stats' => $this->dashboardService->getUserStats($user),
            'team_capacity' => $this->dashboardService->getTeamCapacity($user),

        ])->withViewData(['title' => 'Manager Dashboard']);
    }
    //        return "Hello Manager";

    protected function employeeDashboard()
    {
        $user = Auth::user();

        return inertia('(portals)/employee/dashboard/page', [
            'user' => $user,
            'metrics' => $this->dashboardService->getEmployeeMetrics($user),
            'recent_activity' => $this->dashboardService->getEmployeeRecentActivity($user, 10),
            'stats' => $this->dashboardService->getUserStats($user),
        ])->withViewData([
            'title' => 'Employee Dashboard',
        ]);
    }
}
