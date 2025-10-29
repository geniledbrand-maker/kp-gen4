<?php
// Удаляет все объединения ячеек из указанного XLSX-файла

if ($argc < 2) {
    echo "Usage: php8.2 fix_merges.php /path/to/template-new2.xlsx\n";
    exit(1);
}

$file = $argv[1];
if (!file_exists($file)) {
    echo "File not found: $file\n";
    exit(1);
}

$tmpDir = sys_get_temp_dir() . '/fix_xlsx_' . uniqid();
mkdir($tmpDir);
copy($file, "$tmpDir/orig.xlsx");

// XLSX — это ZIP-архив, распакуем
$zip = new ZipArchive();
if ($zip->open("$tmpDir/orig.xlsx") === true) {
    $zip->extractTo($tmpDir);
    $zip->close();
} else {
    echo "Failed to unzip XLSX\n";
    exit(1);
}

// Удаляем строки <mergeCells>...</mergeCells> из всех листов
$sheetFiles = glob("$tmpDir/xl/worksheets/sheet*.xml");
$removed = 0;
foreach ($sheetFiles as $sheet) {
    $xml = file_get_contents($sheet);
    $new = preg_replace('/<mergeCells[^>]*>.*?<\/mergeCells>/s', '', $xml, -1, $count);
    if ($count > 0) {
        $removed += $count;
        file_put_contents($sheet, $new);
    }
}

// Пересобираем XLSX
$zip = new ZipArchive();
if ($zip->open("$tmpDir/fixed.xlsx", ZipArchive::CREATE) === true) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $name => $f) {
        $local = substr($name, strlen($tmpDir) + 1);
        if (in_array(basename($local), ['orig.xlsx', 'fixed.xlsx'])) continue;
        $zip->addFile($name, $local);
    }
    $zip->close();
}

rename("$tmpDir/fixed.xlsx", $file);
echo "✅ Done. Removed merge blocks: $removed\n";
