<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__.'/../ipcr_form.xlsx';
$ss = IOFactory::load($path);
$sheet = $ss->getActiveSheet();

echo "=== HEADER AREA ===\n";
foreach ([1, 2, 3, 5, 6, 8, 10, 12, 14] as $r) {
    for ($c = 1; $c <= 19; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $cell = $sheet->getCell($col.$r);
        $val = $cell->getCalculatedValue();
        if ($val !== null && $val !== '') {
            echo "{$col}{$r}: {$val}\n";
        }
    }
}

echo "\n=== ROW 16-18 HEADER GRID ===\n";
for ($r = 16; $r <= 18; $r++) {
    $parts = [];
    for ($c = 1; $c <= 19; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $val = $sheet->getCell($col.$r)->getCalculatedValue();
        if ($val !== null && $val !== '') {
            $parts[] = "{$col}:{$val}";
        }
    }
    echo "R{$r}: ".implode(' | ', $parts)."\n";
}

echo "\n=== DATA ROWS 19-58 (column presence) ===\n";
for ($r = 19; $r <= 58; $r++) {
    $cols = [];
    for ($c = 1; $c <= 19; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $val = $sheet->getCell($col.$r)->getCalculatedValue();
        if ($val !== null && $val !== '') {
            $cols[] = $col;
        }
    }
    if ($cols) {
        $a = $sheet->getCell('A'.$r)->getCalculatedValue();
        $b = $sheet->getCell('B'.$r)->getCalculatedValue();
        echo "R{$r} cols=[".implode(',', $cols).'] A='.substr((string) $a, 0, 40).' B='.substr((string) $b, 0, 50)."\n";
    }
}

echo "\n=== ROW 20 STYLE SAMPLE ===\n";
$cell = $sheet->getCell('A20');
$style = $sheet->getStyle('A20');
echo 'A20 bold: '.($style->getFont()->getBold() ? 'yes' : 'no')."\n";
echo 'B16:D18 merge check - B20 value in merged: '.$sheet->getCell('B20')->getValue()."\n";

// Check if B:D are merged per row
echo "\n=== B:D MERGES in data area ===\n";
foreach ($sheet->getMergeCells() as $merge) {
    if (preg_match('/^B(\d+):D(\d+)$/', $merge, $m)) {
        if ((int) $m[1] >= 19 && (int) $m[1] <= 58) {
            echo $merge."\n";
        }
    }
}

echo "\n=== TOTAL / FINAL ROWS ===\n";
foreach ([56, 57, 58, 59, 60, 61] as $r) {
    echo "R{$r}: ";
    for ($c = 1; $c <= 19; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $v = $sheet->getCell($col.$r)->getCalculatedValue();
        if ($v !== null && $v !== '') {
            echo "{$col}={$v} ";
        }
    }
    echo "\n";
}
foreach ($sheet->getMergeCells() as $merge) {
    if (preg_match('/(\d+)/', $merge, $m) && (int) $m[1] >= 56 && (int) $m[1] <= 62) {
        echo "merge {$merge}\n";
    }
}
