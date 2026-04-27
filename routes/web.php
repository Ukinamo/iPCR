<?php

use App\Http\Controllers\Admin\EmployeeRatingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\AccomplishmentController;
use App\Http\Controllers\Employee\CommitmentController;
use App\Http\Controllers\Employee\RatingHistoryExportController;
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
        Route::get('commitments/{commitment}', [CommitmentController::class, 'show'])->name('commitments.show');
        Route::patch('commitments/{commitment}', [CommitmentController::class, 'update'])->name('commitments.update');
        Route::delete('commitments/{commitment}', [CommitmentController::class, 'destroy'])->name('commitments.destroy');
        Route::post('accomplishments', [AccomplishmentController::class, 'store'])->name('accomplishments.store');
        Route::delete('accomplishments/{accomplishment}', [AccomplishmentController::class, 'destroy'])->name('accomplishments.destroy');
        Route::get('ratings/history-export', RatingHistoryExportController::class)->name('ratings.history.export');
        Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    });

    Route::middleware('role:supervisor')->prefix('supervisor')->name('supervisor.')->group(function () {
        Route::get('submissions/{submission}', [SubmissionReviewController::class, 'show'])->name('submissions.show');
        Route::get('submissions/{submission}/export', [SubmissionReviewController::class, 'export'])->name('submissions.export');
        Route::patch('submissions/{submission}', [SubmissionReviewController::class, 'update'])->name('submissions.update');
    });

    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users/{user}/ratings/export', [EmployeeRatingController::class, 'export'])->name('users.ratings.export');
        Route::get('users/{user}/ratings', [EmployeeRatingController::class, 'show'])->name('users.ratings');
        Route::get('users', [UserAdminController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserAdminController::class, 'create'])->name('users.create');
        Route::get('users/{user}/edit', [UserAdminController::class, 'edit'])->name('users.edit');
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
