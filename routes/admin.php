<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');

    Route::get('/system', [SystemHealthController::class, 'index'])->name('system.index');
    Route::get('/jobs', [SystemHealthController::class, 'jobs'])->name('system.jobs');
    Route::post('/jobs/{id}/retry', [SystemHealthController::class, 'retryJob'])->name('system.jobs.retry');
    Route::delete('/jobs/{id}', [SystemHealthController::class, 'deleteFailedJob'])->name('system.jobs.delete');
    Route::delete('/jobs', [SystemHealthController::class, 'flushFailedJobs'])->name('system.jobs.flush');
    Route::get('/sources', [SystemHealthController::class, 'sources'])->name('system.sources');
    Route::get('/errors', [SystemHealthController::class, 'errors'])->name('system.errors');
});

// Stop impersonation route - accessible when impersonating (authenticated but NOT requiring admin middleware)
Route::middleware(['auth'])->post('/admin/impersonate/stop', [ImpersonationController::class, 'stop'])->name('admin.impersonation.stop');
