<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Models\User;
use App\Services\IpcrApprovedFormExporter;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeRatingController extends Controller
{
    public function show(User $user): Response
    {
        abort_unless($user->role === UserRole::Employee, 404);

        $submissions = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->get();

        return Inertia::render('Admin/EmployeeRatings', [
            'employee' => $user->only(['id', 'name', 'email']),
            'submissions' => $submissions,
        ]);
    }

    public function export(User $user): StreamedResponse
    {
        abort_unless($user->role === UserRole::Employee, 404);

        $submissions = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->get();

        $spreadsheet = IpcrApprovedFormExporter::exportToSpreadsheet($submissions, $user);

        $writer = new Xlsx($spreadsheet);

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $user->name) ?? 'employee';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'ipcr-ratings-'.$safeName.'-'.$user->id.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
