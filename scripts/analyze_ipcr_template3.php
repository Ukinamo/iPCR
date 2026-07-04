<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$sheet = IOFactory::load(__DIR__.'/../ipcr_form.xlsx')->getActiveSheet();
foreach ($sheet->getMergeCells() as $merge) {
    if (str_contains($merge, '59') || str_contains($merge, '51') || str_contains($merge, '19') || str_contains($merge, '35')) {
        echo $merge.PHP_EOL;
    }
}
