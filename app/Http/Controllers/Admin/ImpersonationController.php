<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $admin = Auth::user();

        if (! $admin?->isAdmin()) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to impersonate users.');
        }

        if ($user->id === $admin->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot impersonate yourself.');
        }

        if ($user->is_admin) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot impersonate admin users.');
        }

        // Log impersonation start
        ActivityLog::create([
            'user_id' => $admin->id,
            'event_type' => 'admin.impersonation_started',
            'level' => 'info',
            'description' => "Admin {$admin->name} started impersonating {$user->name} ({$user->email})",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'admin_id' => $admin->id,
                'target_user_id' => $user->id,
            ],
        ]);

        // Store admin session data
        session()->put('impersonator_id', $admin->id);
        session()->put('impersonation_started_at', now()->toIso8601String());

        // Login as target user
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', "You are now impersonating {$user->name}.");
    }

    public function stop(): RedirectResponse
    {
        $impersonatorId = session()->get('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('dashboard')
                ->with('error', 'You are not impersonating anyone.');
        }

        $admin = User::find($impersonatorId);
        $impersonatedUser = Auth::user();
        $startedAt = session()->get('impersonation_started_at');

        // Calculate duration
        $duration = $startedAt ? now()->diffInMinutes($startedAt) : null;

        // Log impersonation end
        if ($admin) {
            ActivityLog::create([
                'user_id' => $admin->id,
                'event_type' => 'admin.impersonation_ended',
                'level' => 'info',
                'description' => "Admin {$admin->name} stopped impersonating {$impersonatedUser->name} ({$impersonatedUser->email})",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'admin_id' => $admin->id,
                    'target_user_id' => $impersonatedUser->id,
                    'duration_minutes' => $duration,
                ],
            ]);
        }

        // Clear impersonation session data
        session()->forget('impersonator_id');
        session()->forget('impersonation_started_at');

        // Login back as admin
        if ($admin) {
            Auth::login($admin);

            return redirect()->route('admin.users.index')
                ->with('success', 'Impersonation session ended.');
        }

        return redirect()->route('login');
    }
}
