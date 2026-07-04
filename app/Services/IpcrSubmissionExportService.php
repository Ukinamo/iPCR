<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\IpcrSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        abort_unless($submission->status === SubmissionStatus::Approved, 422);

        $submission->load(['employee', 'commitments', 'supervisor']);

        return $submission;
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
        $spreadsheet = self::spreadsheet($submission);

        return match ($format) {
            'csv' => self::streamCsv($spreadsheet, $submission),
            'pdf' => self::streamPdf($spreadsheet, $submission, true),
            default => self::streamXlsx($spreadsheet, $submission),
        };
    }

    public static function inlinePrint(IpcrSubmission $submission): Response
    {
        return response(self::htmlForSinglePagePrint(self::spreadsheet($submission)), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private static function htmlForSinglePagePrint(Spreadsheet $spreadsheet): string
    {
        $writer = new Html($spreadsheet);
        $writer->setSheetIndex(0);
        $writer->setGenerateSheetNavigationBlock(false);
        $writer->setUseInlineCss(true);

        return self::wrapHtmlForSinglePagePrint($writer->generateHtmlAll());
    }

    private static function wrapHtmlForSinglePagePrint(string $html): string
    {
        $printStyles = <<<'CSS'
<style type="text/css" id="ipcr-print-fit">
@page { size: legal landscape; margin: 0.2in; }
html, body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; background: #fff; }
.scrpgbrk, div + div { page-break-before: auto !important; }
.navigation { display: none !important; }
#ipcr-print-root { transform-origin: top left; }
@media print {
  html, body { overflow: visible !important; height: auto !important; }
}
</style>
CSS;

        $printScript = <<<'JS'
<script>
(function () {
    function fitToOnePage() {
        var root = document.getElementById('ipcr-print-root');
        if (!root) {
            return;
        }

        root.style.transform = 'none';
        root.style.zoom = '1';

        var pageW = 13.6 * 96;
        var pageH = 8.1 * 96;
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
JS;

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

    private static function streamXlsx(Spreadsheet $spreadsheet, IpcrSubmission $submission): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, self::filename($submission, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function streamCsv(Spreadsheet $spreadsheet, IpcrSubmission $submission): StreamedResponse
    {
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(0);
        $writer->setUseBOM(true);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, self::filename($submission, 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function streamPdf(Spreadsheet $spreadsheet, IpcrSubmission $submission, bool $download): StreamedResponse
    {
        $writer = new MpdfWriter($spreadsheet);
        $filename = self::filename($submission, 'pdf');
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"';

        return response()->stream(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }
}
