<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\AccomplishmentController;
use App\Http\Controllers\Employee\CommitmentController;
use App\Http\Controllers\Employee\SubmissionController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolePortalController;
use App\Http\Controllers\Supervisor\SubmissionReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/portal', RolePortalController::class)->name('portal.role');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:employee')->prefix('employee')->name('employee.')->group(function () {
        Route::post('commitments', [CommitmentController::class, 'store'])->name('commitments.store');
        Route::patch('commitments/{commitment}', [CommitmentController::class, 'update'])->name('commitments.update');
        Route::delete('commitments/{commitment}', [CommitmentController::class, 'destroy'])->name('commitments.destroy');
        Route::post('accomplishments', [AccomplishmentController::class, 'store'])->name('accomplishments.store');
        Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    });

    Route::middleware('role:supervisor')->prefix('supervisor')->name('supervisor.')->group(function () {
        Route::patch('submissions/{submission}', [SubmissionReviewController::class, 'update'])->name('submissions.update');
    });

    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::post('users', [UserAdminController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserAdminController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
        Route::get('reports/users.csv', [ReportController::class, 'usersCsv'])->name('reports.users');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
