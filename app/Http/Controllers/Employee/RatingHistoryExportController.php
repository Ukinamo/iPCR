<?php

namespace App\Http\Controllers\Employee;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Services\IpcrApprovedFormExporter;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RatingHistoryExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();

        $submissions = IpcrSubmission::query()
            ->where('employee_id', $user->id)
            ->where('status', SubmissionStatus::Approved)
            ->with(['commitments', 'supervisor'])
            ->orderByDesc('evaluation_year')
            ->orderByDesc('evaluation_quarter')
            ->get();

        $spreadsheet = IpcrApprovedFormExporter::exportToSpreadsheet($submissions, $user);

        $writer = new Xlsx($spreadsheet);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $user->name) ?: 'employee';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'commitment-history-'.$safeName.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
