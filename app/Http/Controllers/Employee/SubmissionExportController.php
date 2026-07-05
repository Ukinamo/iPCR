<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\IpcrSubmission;
use App\Services\IpcrSubmissionExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    public function export(Request $request, IpcrSubmission $submission, string $format = 'xlsx'): StreamedResponse
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);

        $submission = IpcrSubmissionExportService::authorizeEmployeeExport($request, $submission);

        return IpcrSubmissionExportService::download($submission, $format);
    }

    public function print(Request $request, IpcrSubmission $submission): HttpResponse
    {
        $submission = IpcrSubmissionExportService::authorizeEmployeeExport($request, $submission);

        return IpcrSubmissionExportService::inlinePrint($submission);
    }
}
