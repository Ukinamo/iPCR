<?php

use App\Models\IpcrSubmission;
use App\Services\IpcrApprovedFormExporter;
use App\Services\IpcrSubmissionExportService;
use Illuminate\Contracts\Console\Kernel;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf as MpdfWriter;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$submission = IpcrSubmission::query()
    ->where('status', 'approved')
    ->with(['employee', 'commitments'])
    ->first();

if (! $submission) {
    echo "No approved submission found.\n";
    exit(1);
}

$spreadsheet = IpcrApprovedFormExporter::exportToSpreadsheet(collect([$submission]), $submission->employee);

$htmlWriter = new Html($spreadsheet);
$htmlWriter->setSheetIndex(0);
$htmlWriter->setGenerateSheetNavigationBlock(false);
$htmlWriter->setUseInlineCss(true);
$html = $htmlWriter->generateHtmlAll();
file_put_contents(storage_path('app/test-print.html'), $html);
echo 'HTML length: '.strlen($html)."\n";

$pdfPath = storage_path('app/test-print.pdf');
$pdfWriter = new MpdfWriter($spreadsheet);
$pdfWriter->save($pdfPath);
echo "PDF saved: {$pdfPath}\n";

$sheet = $spreadsheet->getActiveSheet();
echo 'Highest row: '.$sheet->getHighestRow()."\n";
echo 'FitToHeight: '.$sheet->getPageSetup()->getFitToHeight()."\n";
echo 'FitToPage: '.($sheet->getPageSetup()->getFitToPage() ? 'yes' : 'no')."\n";

$printHtml = IpcrSubmissionExportService::inlinePrint($submission)->getContent();
file_put_contents(storage_path('app/test-print-page.html'), $printHtml);
echo 'Print HTML saved: '.storage_path('app/test-print-page.html')."\n";
echo 'Has print root: '.(str_contains($printHtml, 'ipcr-print-root') ? 'yes' : 'no')."\n";
echo 'Has fit script: '.(str_contains($printHtml, 'fitToOnePage') ? 'yes' : 'no')."\n";
