<?php

use App\Models\IpcrSubmission;
use App\Services\IpcrApprovedFormExporter;
use Illuminate\Contracts\Console\Kernel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
$out = storage_path('app/test-ipcr-export.xlsx');
(new Xlsx($spreadsheet))->save($out);
echo "Saved: {$out}\n";

$sheet = IOFactory::load($out)->getActiveSheet();
echo 'A3: '.$sheet->getCell('A3')->getValue()."\n";
echo 'A6: '.$sheet->getCell('A6')->getValue()."\n";
echo 'A19: '.$sheet->getCell('A19')->getValue()."\n";
echo 'B20: '.$sheet->getCell('B20')->getValue()."\n";
echo 'E20: '.$sheet->getCell('E20')->getValue()."\n";

for ($r = 1; $r <= $sheet->getHighestRow(); $r++) {
    $a = (string) $sheet->getCell('A'.$r)->getValue();
    if (str_contains($a, 'COMMENTS AND RECOMMENDATIONS')) {
        echo "Comments row: {$r}\n";
    }
    if (str_contains($a, 'Legend')) {
        echo "Legend row {$r}: {$a}\n";
    }
    if ($a === 'TOTAL' || str_contains((string) $sheet->getCell('C'.$r)->getValue(), 'TOTAL')) {
        echo "Total row {$r}: E={$sheet->getCell('E'.$r)->getValue()} S={$sheet->getCell('S'.$r)->getValue()}\n";
    }
}

echo 'B20 wrap: '.($sheet->getStyle('B20')->getAlignment()->getWrapText() ? 'yes' : 'no')."\n";
echo 'B20 height: '.$sheet->getRowDimension(20)->getRowHeight()."\n";
echo 'A20 height: '.$sheet->getRowDimension(20)->getRowHeight()."\n";

for ($r = 44; $r <= 55; $r++) {
    $a = (string) $sheet->getCell('A'.$r)->getValue();
    $c = (string) $sheet->getCell('C'.$r)->getValue();
    if ($a !== '' || $c !== '') {
        echo "Scale/Legend R{$r} h=".$sheet->getRowDimension($r)->getRowHeight()." A=".substr($a, 0, 45)." | C=".substr($c, 0, 40)."\n";
    }
}
