<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$path = __DIR__.'/../ipcr_form.xlsx';
$sheet = IOFactory::load($path)->getActiveSheet();

echo "=== COLUMN WIDTHS ===\n";
for ($c = 1; $c <= 19; $c++) {
    $col = Coordinate::stringFromColumnIndex($c);
    echo "{$col}: ".$sheet->getColumnDimension($col)->getWidth()."\n";
}

echo "\n=== ROW HEIGHTS (16-85) ===\n";
for ($r = 16; $r <= 85; $r++) {
    $h = $sheet->getRowDimension($r)->getRowHeight();
    if ($h > 0 && $h != -1) {
        echo "R{$r}: {$h}\n";
    }
}

echo "\n=== RATING SCALE ROWS ===\n";
for ($r = 72; $r <= 85; $r++) {
    $parts = [];
    for ($c = 1; $c <= 19; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $v = $sheet->getCell($col.$r)->getCalculatedValue();
        if ($v !== null && $v !== '') {
            $wrap = $sheet->getStyle($col.$r)->getAlignment()->getWrapText() ? 'wrap' : 'nowrap';
            $parts[] = "{$col}={$wrap}:".substr((string) $v, 0, 60);
        }
    }
    if ($parts) {
        echo "R{$r}: ".implode(' | ', $parts)."\n";
    }
}

echo "\n=== WRAP TEXT on headers ===\n";
foreach (['A16', 'B16', 'E16', 'F16', 'G16', 'H16'] as $addr) {
    $wrap = $sheet->getStyle($addr)->getAlignment()->getWrapText();
    echo "{$addr}: ".($wrap ? 'wrap' : 'no-wrap')."\n";
}
