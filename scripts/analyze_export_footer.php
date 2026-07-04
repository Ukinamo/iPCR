<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__.'/../storage/app/test-ipcr-export.xlsx';
if (! is_file($path)) {
    echo "Run test_ipcr_export.php first\n";
    exit(1);
}

$sheet = IOFactory::load($path)->getActiveSheet();
$max = $sheet->getHighestRow();

echo "Rows {$max} total\n\n";
for ($r = 40; $r <= $max; $r++) {
    $parts = [];
    foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
        $v = $sheet->getCell($col.$r)->getCalculatedValue();
        if ($v !== null && $v !== '') {
            $parts[] = "{$col}=".substr((string) $v, 0, 70);
        }
    }
    if ($parts) {
        echo "R{$r}: ".implode(' | ', $parts)."\n";
    }
}

echo "\n=== MERGES from row 40 ===\n";
foreach ($sheet->getMergeCells() as $merge) {
    if (preg_match('/(\d+)/', $merge, $m) && (int) $m[0] >= 40) {
        echo $merge."\n";
    }
}
