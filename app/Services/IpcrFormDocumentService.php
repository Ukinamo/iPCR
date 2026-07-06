<?php

namespace App\Services;

use App\Models\IpcrSubmission;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @deprecated Use IpcrSubmissionExportService with IpcrApprovedFormExporter (ipcr_form.xlsx) instead.
 */
final class IpcrFormDocumentService
{
    public static function renderHtml(
        IpcrSubmission $submission,
        User $employee,
        bool $autoPrint = false,
        bool $showPrintButton = true,
        ?string $commitmentStatement = null,
    ): string {
        return IpcrSubmissionExportService::renderDocumentHtml(
            $submission,
            autoPrint: $autoPrint,
            showPrintButton: $showPrintButton,
        );
    }

    public static function streamPdf(
        IpcrSubmission $submission,
        User $employee,
        string $filename,
        ?string $commitmentStatement = null,
    ): StreamedResponse {
        return IpcrSubmissionExportService::download($submission, 'pdf');
    }
}
