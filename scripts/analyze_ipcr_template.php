<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = __DIR__.'/../ipcr_form.xlsx';
$ss = IOFactory::load($path);
$sheet = $ss->getActiveSheet();
$maxRow = $sheet->getHighestRow();
$maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

echo 'Sheet: '.$sheet->getTitle().PHP_EOL;
echo "Dimensions: {$maxRow} rows x {$maxCol} cols".PHP_EOL.PHP_EOL;

echo '=== COLUMN WIDTHS ==='.PHP_EOL;
for ($c = 1; $c <= $maxCol; $c++) {
    $col = Coordinate::stringFromColumnIndex($c);
    $w = $sheet->getColumnDimension($col)->getWidth();
    if ($w > 0) {
        echo "{$col}: {$w}".PHP_EOL;
    }
}

echo PHP_EOL.'=== MERGED CELLS ==='.PHP_EOL;
foreach ($sheet->getMergeCells() as $merge) {
    echo $merge.PHP_EOL;
}
  
echo PHP_EOL.'=== CELL VALUES (non-empty) ==='.PHP_EOL;
for ($r = 1; $r <= $maxRow; $r++) {
    $rowVals = [];
    for ($c = 1; $c <= $maxCol; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $val = $sheet->getCell($col.$r)->getCalculatedValue();
        if ($val !== null && $val !== '') {
            $text = str_replace(["\n", "\r"], ' | ', (string) $val);
            $rowVals[] = $col.$r.': '.$text;
        }
    }
    if ($rowVals) {
        echo 'R'.$r.': '.implode(' || ', $rowVals).PHP_EOL;
    }
}

echo PHP_EOL.'=== PAGE SETUP ==='.PHP_EOL;
$ps = $sheet->getPageSetup();
echo 'Orientation: '.$ps->getOrientation().PHP_EOL;
echo 'Paper: '.$ps->getPaperSize().PHP_EOL;
echo 'Fit width: '.$ps->getFitToWidth().PHP_EOL;
echo 'Fit height: '.$ps->getFitToHeight().PHP_EOL;
