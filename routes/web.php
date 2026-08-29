<?php

use App\Http\Controllers\Admin\EmployeeRatingController;
use App\Http\Controllers\Admin\FormTemplateController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SubmissionReviewController as AdminSubmissionReviewController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\AccomplishmentController;
use App\Http\Controllers\Employee\CommitmentController;
use App\Http\Controllers\Employee\FormPackageController;
use App\Http\Controllers\Employee\FormAnswerController;
use App\Http\Controllers\Employee\RatingHistoryExportController;
use App\Http\Controllers\Employee\SubmissionExportController;
use App\Http\Controllers\Employee\SubmissionController;
use App\Http\Controllers\IpcrFormPreviewController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolePortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/portal', RolePortalController::class)->name('portal.role');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('accomplishments/{accomplishment}/file', [AccomplishmentController::class, 'file'])->name('accomplishments.file');

    Route::middleware('role:employee')->prefix('employee')->name('employee.')->group(function () {
        Route::post('commitments', [CommitmentController::class, 'store'])->name('commitments.store');
        Route::get('commitments/{commitment}', [CommitmentController::class, 'show'])->name('commitments.show');
        Route::patch('commitments/{commitment}', [CommitmentController::class, 'update'])->name('commitments.update');
        Route::patch('commitments/{commitment}/package', [CommitmentController::class, 'updateBatch'])->name('commitments.updateBatch');
        Route::delete('commitments/{commitment}', [CommitmentController::class, 'destroy'])->name('commitments.destroy');
        Route::post('packages/from-template', [FormPackageController::class, 'fromTemplate'])->name('packages.from-template');
        Route::post('packages', [FormPackageController::class, 'store'])->name('packages.store');
        Route::get('packages/{submission}/edit', [FormPackageController::class, 'edit'])->name('packages.edit');
        Route::patch('packages/{submission}', [FormPackageController::class, 'update'])->name('packages.update');
        Route::delete('packages/{submission}', [FormPackageController::class, 'destroy'])->name('packages.destroy');
        Route::patch('form-answers', [FormAnswerController::class, 'update'])->name('form-answers.update');
        Route::post('accomplishments', [AccomplishmentController::class, 'store'])->name('accomplishments.store');
        Route::delete('accomplishments/{accomplishment}', [AccomplishmentController::class, 'destroy'])->name('accomplishments.destroy');
        Route::get('ratings/history-export', RatingHistoryExportController::class)->name('ratings.history.export');
        Route::get('submissions/{submission}/preview', [IpcrFormPreviewController::class, 'show'])->name('submissions.preview');
        Route::patch('submissions/{submission}/commitment-statement', [IpcrFormPreviewController::class, 'updateCommitment'])->name('submissions.commitment-statement');
        Route::get('submissions/{submission}/document', [IpcrFormPreviewController::class, 'document'])->name('submissions.document');
        Route::get('submissions/{submission}/export/{format?}', [SubmissionExportController::class, 'export'])
            ->where('format', 'xlsx|csv|pdf')
            ->name('submissions.export');
        Route::get('submissions/{submission}/print-pdf', [IpcrFormPreviewController::class, 'printPdf'])->name('submissions.print-pdf');
        Route::get('submissions/{submission}/print', [IpcrFormPreviewController::class, 'print'])->name('submissions.print');
        Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
        Route::post('submissions/{submission}/cancel', [SubmissionController::class, 'cancel'])->name('submissions.cancel');
    });

    Route::middleware('role:supervisor')->prefix('supervisor')->name('supervisor.')->group(function () {
        Route::get('submissions/{submission}/preview', [IpcrFormPreviewController::class, 'show'])->name('submissions.preview');
        Route::patch('submissions/{submission}/commitment-statement', [IpcrFormPreviewController::class, 'updateCommitment'])->name('submissions.commitment-statement');
        Route::get('submissions/{submission}/document', [IpcrFormPreviewController::class, 'document'])->name('submissions.document');
        Route::get('submissions/{submission}/export/{format?}', [IpcrFormPreviewController::class, 'export'])
            ->where('format', 'xlsx|csv|pdf')
            ->name('submissions.export');
        Route::get('submissions/{submission}/print-pdf', [IpcrFormPreviewController::class, 'printPdf'])->name('submissions.print-pdf');
        Route::get('submissions/{submission}/print', [IpcrFormPreviewController::class, 'print'])->name('submissions.print');
        Route::get('program-evaluations/create', [\App\Http\Controllers\Supervisor\ProgramEvaluationController::class, 'create'])->name('program-evaluations.create');
        Route::post('program-evaluations', [\App\Http\Controllers\Supervisor\ProgramEvaluationController::class, 'store'])->name('program-evaluations.store');
        Route::get('program-evaluations/{form}/edit', [\App\Http\Controllers\Supervisor\ProgramEvaluationController::class, 'edit'])->name('program-evaluations.edit');
        Route::patch('program-evaluations/{form}', [\App\Http\Controllers\Supervisor\ProgramEvaluationController::class, 'update'])->name('program-evaluations.update');
        Route::delete('program-evaluations/{form}', [\App\Http\Controllers\Supervisor\ProgramEvaluationController::class, 'destroy'])->name('program-evaluations.destroy');
        Route::get('sto-monitoring/create', [\App\Http\Controllers\Supervisor\StoMonitoringController::class, 'create'])->name('sto-monitoring.create');
        Route::post('sto-monitoring', [\App\Http\Controllers\Supervisor\StoMonitoringController::class, 'store'])->name('sto-monitoring.store');
        Route::get('sto-monitoring/{stoMonitoringForm}/edit', [\App\Http\Controllers\Supervisor\StoMonitoringController::class, 'edit'])->name('sto-monitoring.edit');
        Route::patch('sto-monitoring/{stoMonitoringForm}', [\App\Http\Controllers\Supervisor\StoMonitoringController::class, 'update'])->name('sto-monitoring.update');
        Route::delete('sto-monitoring/{stoMonitoringForm}', [\App\Http\Controllers\Supervisor\StoMonitoringController::class, 'destroy'])->name('sto-monitoring.destroy');
    });

    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('submissions/{submission}', [AdminSubmissionReviewController::class, 'show'])->name('submissions.show');
        Route::patch('submissions/{submission}', [AdminSubmissionReviewController::class, 'update'])->name('submissions.update');
        Route::get('submissions/{submission}/preview', [IpcrFormPreviewController::class, 'show'])->name('submissions.preview');
        Route::patch('submissions/{submission}/commitment-statement', [IpcrFormPreviewController::class, 'updateCommitment'])->name('submissions.commitment-statement');
        Route::get('submissions/{submission}/document', [IpcrFormPreviewController::class, 'document'])->name('submissions.document');
        Route::get('submissions/{submission}/export/{format?}', [EmployeeRatingController::class, 'exportSubmission'])
            ->where('format', 'xlsx|csv|pdf')
            ->name('submissions.export');
        Route::get('submissions/{submission}/print-pdf', [IpcrFormPreviewController::class, 'printPdf'])->name('submissions.print-pdf');
        Route::get('submissions/{submission}/print', [IpcrFormPreviewController::class, 'print'])->name('submissions.print');
        Route::get('users/{user}/ratings/export/{format?}', [EmployeeRatingController::class, 'export'])
            ->where('format', 'xlsx|csv|pdf')
            ->name('users.ratings.export');
        Route::get('users/{user}/ratings/print', [EmployeeRatingController::class, 'print'])->name('users.ratings.print');
        Route::get('users/{user}/ratings', [EmployeeRatingController::class, 'show'])->name('users.ratings');
        Route::get('users/pending', [UserAdminController::class, 'pending'])->name('users.pending');
        Route::patch('users/{user}/approve', [UserAdminController::class, 'approve'])->name('users.approve');
        Route::delete('users/{user}/reject', [UserAdminController::class, 'reject'])->name('users.reject');
        Route::get('users', [UserAdminController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserAdminController::class, 'create'])->name('users.create');
        Route::get('users/{user}/edit', [UserAdminController::class, 'edit'])->name('users.edit');
        Route::post('users', [UserAdminController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserAdminController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserAdminController::class, 'destroy'])->name('users.destroy');
        Route::get('forms', [FormTemplateController::class, 'index'])->name('forms.index');
        Route::get('forms/create', [FormTemplateController::class, 'create'])->name('forms.create');
        Route::post('forms', [FormTemplateController::class, 'store'])->name('forms.store');
        Route::get('forms/{form}', [FormTemplateController::class, 'show'])->name('forms.show');
        Route::get('forms/{form}/edit', [FormTemplateController::class, 'edit'])->name('forms.edit');
        Route::patch('forms/{form}', [FormTemplateController::class, 'update'])->name('forms.update');
        Route::delete('forms/{form}', [FormTemplateController::class, 'destroy'])->name('forms.destroy');
        Route::get('reports/ratings', [ReportController::class, 'ratings'])->name('reports.ratings');
        Route::get('reports/submissions/{submission}', [ReportController::class, 'showSubmission'])->name('reports.submissions.show');
        Route::get('reports/users.csv', [ReportController::class, 'usersCsv'])->name('reports.users');
        Route::get('program-evaluations/{form}', [\App\Http\Controllers\Admin\RegisterReportReviewController::class, 'showProgram'])->name('program-evaluations.show');
        Route::patch('program-evaluations/{form}', [\App\Http\Controllers\Admin\RegisterReportReviewController::class, 'updateProgram'])->name('program-evaluations.update');
        Route::get('sto-monitoring/{stoMonitoringForm}', [\App\Http\Controllers\Admin\RegisterReportReviewController::class, 'showSto'])->name('sto-monitoring.show');
        Route::patch('sto-monitoring/{stoMonitoringForm}', [\App\Http\Controllers\Admin\RegisterReportReviewController::class, 'updateSto'])->name('sto-monitoring.update');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::get('/users/{user}/profile-photo', [ProfileController::class, 'showPhoto'])->name('users.profile-photo');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
