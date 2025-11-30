<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboardService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'platformOverview' => $this->dashboardService->getPlatformOverview(),
            'systemHealth' => $this->dashboardService->getSystemHealth(),
            'usageStats' => $this->dashboardService->getUsageStats(),
            'topTeams' => $this->dashboardService->getTopTeamsByTokenUsage(),
            'teamsApproachingLimit' => $this->dashboardService->getTeamsApproachingLimit(),
            'dailyTokenUsage' => $this->dashboardService->getDailyTokenUsage(),
            'recentRegistrations' => $this->dashboardService->getRecentRegistrations(),
        ]);
    }
}
