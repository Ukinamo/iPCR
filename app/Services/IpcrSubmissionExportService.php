<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf as MpdfWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IpcrSubmissionExportService
{
    public static function authorizeApprovedExport(Request $request, IpcrSubmission $submission): IpcrSubmission
    {
        abort_unless($submission->supervisor_id === $request->user()->id, 403);

        return self::loadApprovedSubmission($submission);
    }

    public static function authorizeAdminExport(Request $request, IpcrSubmission $submission): IpcrSubmission
    {
        abort_unless($request->user()->role === UserRole::Administrator, 403);

        return self::loadApprovedSubmission($submission);
    }

    public static function authorizeEmployeeExport(Request $request, IpcrSubmission $submission): IpcrSubmission
    {
        abort_unless($submission->employee_id === $request->user()->id, 403);

        return self::loadApprovedSubmission($submission);
    }

    private static function loadApprovedSubmission(IpcrSubmission $submission): IpcrSubmission
    {
        abort_unless($submission->status === SubmissionStatus::Approved, 422);

        $submission->load(['employee', 'commitments', 'supervisor']);

        return $submission;
    }

    /**
     * @param  Collection<int, IpcrSubmission>  $submissions
     */
    public static function spreadsheetForEmployee(Collection $submissions, User $employee): Spreadsheet
    {
        return IpcrApprovedFormExporter::exportToSpreadsheet($submissions, $employee);
    }

    public static function employeeHistoryFilename(User $employee, string $extension): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $employee->name) ?: 'employee';

        return "ipcr-ratings-{$safeName}-{$employee->id}.{$extension}";
    }

    public static function spreadsheet(IpcrSubmission $submission): Spreadsheet
    {
        return IpcrApprovedFormExporter::exportToSpreadsheet(
            collect([$submission]),
            $submission->employee,
        );
    }

    public static function filename(IpcrSubmission $submission, string $extension): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $submission->employee->name) ?: 'employee';
        $period = 'Q'.$submission->evaluation_quarter.'-'.$submission->evaluation_year;

        return "ipcr-{$safeName}-{$period}.{$extension}";
    }

    public static function download(IpcrSubmission $submission, string $format): StreamedResponse
    {
        return self::downloadSpreadsheet(
            self::spreadsheet($submission),
            self::filename($submission, self::extensionForFormat($format)),
            $format,
            allowPdf: true,
        );
    }

    /**
     * @param  Collection<int, IpcrSubmission>  $submissions
     */
    public static function downloadEmployeeHistory(Collection $submissions, User $employee, string $format): StreamedResponse
    {
        $spreadsheet = self::spreadsheetForEmployee($submissions, $employee);

        return self::downloadSpreadsheet(
            $spreadsheet,
            self::employeeHistoryFilename($employee, self::extensionForFormat($format)),
            $format,
            allowPdf: true,
        );
    }

    public static function inlinePrint(IpcrSubmission $submission): Response
    {
        return response(
            self::renderDocumentHtml($submission, autoPrint: true, showPrintButton: true),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public static function renderDocumentHtml(
        IpcrSubmission $submission,
        bool $autoPrint = false,
        bool $showPrintButton = false,
    ): string {
        $html = self::htmlFromSpreadsheet(self::spreadsheet($submission));

        if ($showPrintButton) {
            $html = self::wrapHtmlWithPrintButton($html);
        }

        if ($autoPrint) {
            $html = self::wrapHtmlForSinglePagePrint($html, autoPrint: true);
        }

        return $html;
    }

    public static function htmlFromSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $writer = new Html($spreadsheet);
        $writer->setSheetIndex(0);
        $writer->setGenerateSheetNavigationBlock(false);
        $writer->setUseInlineCss(true);

        return $writer->generateHtmlAll();
    }

    /**
     * @param  Collection<int, IpcrSubmission>  $submissions
     */
    public static function inlinePrintEmployeeHistory(Collection $submissions, User $employee): Response
    {
        return self::inlinePrintSpreadsheet(self::spreadsheetForEmployee($submissions, $employee));
    }

    public static function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename, string $format, bool $allowPdf = false): StreamedResponse
    {
        return match ($format) {
            'csv' => self::streamCsv($spreadsheet, $filename),
            'pdf' => $allowPdf
                ? self::streamPdf($spreadsheet, $filename, true)
                : throw new \InvalidArgumentException('PDF export is not supported for this spreadsheet.'),
            default => self::streamXlsx($spreadsheet, $filename),
        };
    }

    public static function inlinePrintSpreadsheet(Spreadsheet $spreadsheet): Response
    {
        $html = self::htmlFromSpreadsheet($spreadsheet);

        return response(self::wrapHtmlForSinglePagePrint($html, autoPrint: true), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private static function extensionForFormat(string $format): string
    {
        return match ($format) {
            'csv' => 'csv',
            'pdf' => 'pdf',
            default => 'xlsx',
        };
    }

    private static function htmlForSinglePagePrint(Spreadsheet $spreadsheet): string
    {
        return self::wrapHtmlForSinglePagePrint(self::htmlFromSpreadsheet($spreadsheet), autoPrint: true);
    }

    private static function wrapHtmlWithPrintButton(string $html): string
    {
        $button = <<<'HTML'
<div class="no-print" style="text-align:right;margin:12px;">
<button type="button" onclick="window.print()" style="background:#2c3e50;color:#fff;border:none;padding:8px 18px;border-radius:4px;cursor:pointer;font-size:14px;">Print / Save as PDF</button>
</div>
HTML;

        if (preg_match('/<body[^>]*>/i', $html)) {
            return preg_replace('/<body([^>]*)>/i', '<body$1>'.$button, $html, 1);
        }

        return $button.$html;
    }

    private static function wrapHtmlForSinglePagePrint(string $html, bool $autoPrint = true): string
    {
        $printStyles = <<<'CSS'
<style type="text/css" id="ipcr-print-fit">
@page { size: letter portrait; margin: 0.2in; }
html, body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; background: #fff; }
.scrpgbrk, div + div { page-break-before: auto !important; }
.navigation { display: none !important; }
.no-print { display: block; }
#ipcr-print-root { transform-origin: top left; }
@media print {
  html, body { overflow: visible !important; height: auto !important; }
  .no-print { display: none !important; }
}
</style>
CSS;

        $printScript = $autoPrint ? <<<'JS'
<script>
(function () {
    function fitToOnePage() {
        var root = document.getElementById('ipcr-print-root');
        if (!root) {
            return;
        }

        root.style.transform = 'none';
        root.style.zoom = '1';

        var pageW = 8.1 * 96;
        var pageH = 10.6 * 96;
        var w = root.scrollWidth || root.offsetWidth;
        var h = root.scrollHeight || root.offsetHeight;

        if (!w || !h) {
            return;
        }

        var scale = Math.min(pageW / w, pageH / h, 1);
        root.style.transform = 'scale(' + scale + ')';
        root.style.zoom = scale;
        document.body.style.width = (w * scale) + 'px';
        document.body.style.height = (h * scale) + 'px';
    }

    window.addEventListener('load', function () {
        fitToOnePage();
        setTimeout(function () {
            window.print();
        }, 350);
    });
    window.addEventListener('beforeprint', fitToOnePage);
})();
</script>
JS : '';

        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', $printStyles."\n</head>", $html);
        } else {
            $html = $printStyles.$html;
        }

        if (preg_match('/<body[^>]*>/i', $html)) {
            $html = preg_replace(
                '/<body([^>]*)>/i',
                '<body$1><div id="ipcr-print-root">',
                $html,
                1,
            );
            $html = str_replace('</body>', '</div>'.$printScript.'</body>', $html);
        }

        return $html;
    }

    private static function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function streamCsv(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(0);
        $writer->setUseBOM(true);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function streamPdf(Spreadsheet $spreadsheet, string $filename, bool $download): StreamedResponse
    {
        $writer = new MpdfWriter($spreadsheet);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"';

        return response()->stream(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }
}
