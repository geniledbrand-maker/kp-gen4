<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_once '/home/web/vm-1d0be1a6.na4u.ru/www/local/php_interface/lib/PhpSpreadsheet/vendor/autoload.php';

$templatePath = '/home/web/vm-1d0be1a6.na4u.ru/www/local/tools/kp_gen4/templates/excel/template-new2.xlsx';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Коммерческое предложение');

// ========== ШАПКА КП (логотип + заголовок) ==========
$sheet->mergeCells('C1:H1');
$sheet->setCellValue('C1', 'КОММЕРЧЕСКОЕ ПРЕДЛОЖЕНИЕ');
$sheet->getStyle('C1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('C1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(50);

// ========== ИНФОРМАЦИЯ О КП ==========
$sheet->setCellValue('A4', 'Дата составления:');
$sheet->setCellValue('C4', '#DATE#');

$sheet->setCellValue('A5', 'Заказчик:');
$sheet->setCellValue('C5', '#CUSTOMER#');

$sheet->setCellValue('A6', 'Объект:');
$sheet->setCellValue('C6', '#OBJECT#');

$sheet->setCellValue('E4', 'Менеджер:');
$sheet->setCellValue('G4', '#MANAGER_NAME#');

$sheet->setCellValue('E5', 'Телефон менеджера:');
$sheet->setCellValue('G5', '#MANAGER_PHONE#');

$sheet->setCellValue('E6', 'Почта менеджера:');
$sheet->setCellValue('G6', '#MANAGER_EMAIL#');

// Стили для шапки информации
foreach (['A4', 'A5', 'A6', 'E4', 'E5', 'E6'] as $cell) {
    $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(9);
}
foreach (['C4', 'C5', 'C6', 'G4', 'G5', 'G6'] as $cell) {
    $sheet->getStyle($cell)->getFont()->setSize(9);
}

// ========== ЗАГОЛОВОК ТАБЛИЦЫ ==========
$headerRow = 8;
$headers = [
    'A' => '№',
    'B' => 'Фото',
    'C' => 'Товар и параметры товара',
    'D' => 'Кол-во шт',
    'E' => 'Цена без скидки',
    'F' => 'Скидка',
    'G' => 'Ваша цена',
    'H' => 'Сумма, руб.'
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . $headerRow, $header);
}

// Стили заголовка таблицы
$headerStyle = $sheet->getStyle("A{$headerRow}:H{$headerRow}");
$headerStyle->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
$headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF722e85');
$headerStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$headerStyle->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);

// Высота строки заголовка
$sheet->getRowDimension($headerRow)->setRowHeight(35);

// ========== ШИРИНА КОЛОНОК ==========
$sheet->getColumnDimension('A')->setWidth(5);   // №
$sheet->getColumnDimension('B')->setWidth(15);  // Фото
$sheet->getColumnDimension('C')->setWidth(50);  // Товар и параметры
$sheet->getColumnDimension('D')->setWidth(10);  // Кол-во шт
$sheet->getColumnDimension('E')->setWidth(14);  // Цена без скидки
$sheet->getColumnDimension('F')->setWidth(10);  // Скидка
$sheet->getColumnDimension('G')->setWidth(14);  // Ваша цена
$sheet->getColumnDimension('H')->setWidth(14);  // Сумма, руб.

// ========== ПРИМЕЧАНИЯ (внизу) ==========
$footerRow = $headerRow + 30;

$sheet->setCellValue("A{$footerRow}", 'Комментарий:');
$sheet->mergeCells("A{$footerRow}:H{$footerRow}");
$sheet->getStyle("A{$footerRow}")->getFont()->setBold(true)->setSize(10);

$footerRow += 2;

$sheet->setCellValue("A{$footerRow}", 'Актуальное наличие продукции и срок гарантии уточняйте у менеджера.');
$sheet->mergeCells("A{$footerRow}:H{$footerRow}");
$sheet->getStyle("A{$footerRow}")->getFont()->setSize(8)->setItalic(true);

$footerRow++;
$sheet->setCellValue("A{$footerRow}", 'Итоговая цена будет указана в заказе клиента.');
$sheet->mergeCells("A{$footerRow}:H{$footerRow}");
$sheet->getStyle("A{$footerRow}")->getFont()->setSize(8)->setItalic(true);

$footerRow++;
$sheet->setCellValue("A{$footerRow}", 'Данная информация не является публичной офертой, цены являются предварительными и могут изменяться на этапе заказа как в большую, так и в меньшую сторону.');
$sheet->mergeCells("A{$footerRow}:H{$footerRow}");
$sheet->getStyle("A{$footerRow}")->getFont()->setSize(8)->setItalic(true);
$sheet->getStyle("A{$footerRow}")->getAlignment()->setWrapText(true);

// ========== ЛОГОТИПЫ (опционально) ==========
// Если у вас есть логотипы, можно добавить:
/*
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$logo1 = new Drawing();
$logo1->setPath('/путь/к/geniled-logo.png');
$logo1->setCoordinates('A1');
$logo1->setHeight(50);
$logo1->setWorksheet($sheet);
*/

// Сохраняем
$writer = new Xlsx($spreadsheet);
$writer->save($templatePath);

echo "✅ Шаблон создан успешно: {$templatePath}\n\n";
echo "Структура колонок:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "A: № (5)\n";
echo "B: Фото (15)\n";
echo "C: Товар и параметры товара (50)\n";
echo "D: Кол-во шт (10)\n";
echo "E: Цена без скидки (14)\n";
echo "F: Скидка (10)\n";
echo "G: Ваша цена (14)\n";
echo "H: Сумма, руб. (14)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";