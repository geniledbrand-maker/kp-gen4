<?php
/**
 * Генератор Excel-документов
 * Файл: ExcelGenerator.php
 * Путь: /home/web/vm-1d0be1a6.na4u.ru/www/local/tools/kp_gen4/classes/ExcelGenerator.php
 *
 * ✅ ИСПРАВЛЕННАЯ ВЕРСИЯ:
 * - Корректная загрузка и валидация изображений
 * - Правильный расчёт скидки с фоллбэками
 * - Нормализация URL картинок
 * - Уникальные имена временных файлов
 * - Правильное формирование названия файла: "КП - Заказчик - Объект - Дата"
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Bitrix\Main\Web\HttpClient;

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/PhpSpreadsheet/vendor/autoload.php';

class ExcelGenerator
{
    private Spreadsheet $spreadsheet;
    private Worksheet $sheet;
    private array $priceTypeNames;

    public function __construct()
    {
        if (!file_exists(TEMPLATE_XLSX)) {
            throw new Exception('Шаблон Excel не найден: ' . TEMPLATE_XLSX);
        }

        $reader = IOFactory::createReader('Xlsx');
        $this->spreadsheet = $reader->load(TEMPLATE_XLSX);
        $this->sheet = $this->spreadsheet->getActiveSheet();

        $priceConfig = require __DIR__ . '/../config/price_types.php';
        $this->priceTypeNames = $priceConfig['ids_to_names'];

        // Ширина колонок
        $this->sheet->getColumnDimension('A')->setWidth(5);
        $this->sheet->getColumnDimension('B')->setWidth(15);
        $this->sheet->getColumnDimension('C')->setWidth(50);
        $this->sheet->getColumnDimension('D')->setWidth(8);
        $this->sheet->getColumnDimension('E')->setWidth(12);
        $this->sheet->getColumnDimension('F')->setWidth(10);
        $this->sheet->getColumnDimension('G')->setWidth(12);
        $this->sheet->getColumnDimension('H')->setWidth(12);
    }

    public function generate(array $products, array $info): void
    {
        $info = fillKpInfoDefaults(is_array($info) ? $info : []);
        $this->fillHeader($info);
        $headerRow = $this->findProductTableHeader();
        $lastRow = $this->fillProducts($products, $headerRow);
        $this->fillTotals($lastRow, $products);
        $commentEndRow = $this->fillComment($lastRow + 7, $info);
        $this->appendFooterBlock(null, $info);
        $this->save($info);
    }

    private function fillHeader(array $info): void
    {
        $this->replaceInSheet('#DATE#', (string)($info['date'] ?? date('d.m.Y')));
        $this->replaceInSheet('#CUSTOMER#', (string)($info['customer'] ?? ''));
        $this->replaceInSheet('#OBJECT#', (string)($info['object'] ?? ''));
        $this->replaceInSheet('#MANAGER_NAME#', (string)($info['manager'] ?? ''));
        $this->replaceInSheet('#MANAGER_PHONE#', (string)($info['phone'] ?? ''));
        $this->replaceInSheet('#MANAGER_EMAIL#', (string)($info['email'] ?? ''));
    }

    private function replaceInSheet(string $placeholder, string $value): void
    {
        foreach ($this->sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $cellValue = $cell->getValue();
                if (is_string($cellValue) && strpos($cellValue, $placeholder) !== false) {
                    $cell->setValue(str_replace($placeholder, $value, $cellValue));
                }
            }
        }
    }

    private function findProductTableHeader(): int
    {
        for ($row = 1; $row <= 50; $row++) {
            $cellValue = (string)$this->sheet->getCell("C{$row}")->getValue();
            if (stripos($cellValue, 'Товар') !== false && stripos($cellValue, 'параметр') !== false) {
                return $row;
            }
        }
        return 8;
    }

    private function fillProducts(array $products, int $headerRow): int
    {
        $firstDataRow = $headerRow + 1;
        $row = $firstDataRow;
        foreach ($products as $i => $product) {
            $this->fillProductRow($row, $i + 1, $product);
            $row++;
        }
        return $row;
    }

    private function fillProductRow(int $row, int $index, array $product): void
    {
        $name = str_replace('°', '', (string)($product['name'] ?? ''));
        $article = (string)($product['article'] ?? '');
        $measure = (string)($product['measure'] ?? 'шт');
        $quantity = max(1, (int)($product['quantity'] ?? 1));

        $basePrice = (float)($product['base_price'] ?? $product['mic_price'] ?? 0);
        $yourPrice = (float)($product['price'] ?? 0);

        if ($basePrice <= 0) {
            $basePrice = $yourPrice;
        }

        $discount = 0;
        if ($basePrice > 0 && $yourPrice < $basePrice) {
            $discount = (($basePrice - $yourPrice) / $basePrice) * 100;
        }
        $discount = round($discount, 1);
        $sum = $quantity * $yourPrice;

        $imageUrl = (string)($product['image'] ?? '');
        $priceTypeId = (int)($product['price_type_id'] ?? DEFAULT_PRICE_TYPE_ID);
        $priceTypeName = $this->priceTypeNames[$priceTypeId] ?? '';
        $props = is_array($product['props'] ?? null) ? $product['props'] : [];
        $url = (string)($product['url'] ?? '');

        $this->sheet->setCellValue("A{$row}", $index);
        $this->sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        if ($imageUrl !== '') {
            $this->insertImage($imageUrl, "B{$row}", $index);
        }

        $fullText = "{$name}\nАртикул: {$article}\n\nПараметры:\n";
        if (!empty($props)) {
            foreach ($props as $p) {
                $label = trim((string)($p['name'] ?? ''));
                $value = trim((string)($p['value'] ?? ''));
                if ($label !== '' && $value !== '') {
                    $fullText .= "{$label}: {$value}\n";
                }
            }
        } else {
            $fullText .= "—\n";
        }
// ✅ Блок с типом цены удалён

        $this->sheet->setCellValue("C{$row}", trim($fullText));
        $this->sheet->getStyle("C{$row}")->getAlignment()
            ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $this->sheet->getStyle("C{$row}")->getFont()->setSize(10);
        if ($url !== '') {
            $this->sheet->getCell("C{$row}")->getHyperlink()->setUrl($url);
        }

        $this->sheet->setCellValue("D{$row}", $quantity);
        $this->sheet->getStyle("D{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->sheet->setCellValue("E{$row}", $basePrice);
        $this->sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->sheet->getStyle("E{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
        if ($discount >= 0.1) {
            $discountValue = $discount / 100;
            $this->sheet->setCellValue("F{$row}", $discountValue);
            $this->sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('0,0%');
            if ($discount >= 1.0) {
                $this->sheet->getStyle("F{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFFFF9C');
                $this->sheet->getStyle("F{$row}")->getFont()->setBold(true);
            }
        } else {
            $this->sheet->setCellValue("F{$row}", '—');
        }
        $this->sheet->getStyle("F{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->sheet->setCellValue("G{$row}", $yourPrice);
        $this->sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->sheet->getStyle("G{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("G{$row}")->getFont()->setBold(true);

        $this->sheet->setCellValue("H{$row}", $sum);
        $this->sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->sheet->getStyle("H{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $this->sheet->getStyle("H{$row}")->getFont()->setBold(true);

        $lineCount = substr_count($fullText, "\n") + 1;
        $this->sheet->getRowDimension($row)->setRowHeight(max(100, $lineCount * 15));

        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $this->sheet->getStyle("A{$row}:H{$row}")->applyFromArray($borderStyle);
    }

    private function insertImage(string $url, string $cell, int $index): void
    {
        if (empty($url)) {
            return;
        }

        try {
            $url = $this->normalizeImageUrl($url);
            if (!$url) {
                return;
            }

            if (!preg_match('~\.(jpe?g|png|gif|webp|bmp)(\?|$)~i', $url)) {
                error_log("ExcelGenerator: Invalid image URL: {$url}");
                return;
            }

            $http = new HttpClient([
                'timeout' => 10,
                'streamTimeout' => 10,
                'redirect' => true,
                'redirectMax' => 5,
                'waitResponse' => true,
            ]);

            $http->setHeader('User-Agent', 'Mozilla/5.0 (compatible; KpGenerator/4.0)');

            if (!$http->get($url)) {
                error_log("ExcelGenerator: Failed to download {$url}");
                return;
            }

            $data = $http->getResult();
            $status = $http->getStatus();

            if ($status !== 200) {
                error_log("ExcelGenerator: HTTP {$status} for {$url}");
                return;
            }

            if (strlen($data) > 5 * 1024 * 1024) {
                error_log("ExcelGenerator: Image too large ({$url})");
                return;
            }

            if (strlen($data) < 100) {
                error_log("ExcelGenerator: Image too small ({$url})");
                return;
            }

            $ct = (string)$http->getHeaders()->get('Content-Type');
            $ext = 'jpg';
            if (preg_match('~image/(jpe?g|png|gif|webp|bmp)~i', $ct, $m)) {
                $ext = strtolower($m[1]);
                if ($ext === 'jpeg') $ext = 'jpg';
            }

            if (!file_exists(TEMP_DIR)) {
                if (!@mkdir(TEMP_DIR, 0755, true)) {
                    error_log("ExcelGenerator: Cannot create temp dir " . TEMP_DIR);
                    return;
                }
            }

            $path = TEMP_DIR . "img_{$index}_" . time() . ".{$ext}";

            if (file_put_contents($path, $data) === false) {
                error_log("ExcelGenerator: Cannot save image to {$path}");
                return;
            }

            if (!file_exists($path) || filesize($path) === 0) {
                error_log("ExcelGenerator: Saved image is empty at {$path}");
                @unlink($path);
                return;
            }

            $imgInfo = @getimagesize($path);
            if ($imgInfo === false) {
                error_log("ExcelGenerator: Invalid image format at {$path}");
                @unlink($path);
                return;
            }

            $img = new Drawing();
            $img->setPath($path);
            $img->setCoordinates($cell);
            $img->setHeight(90);
            $img->setOffsetX(5);
            $img->setOffsetY(5);
            $img->setWorksheet($this->sheet);

        } catch (\Throwable $e) {
            error_log("ExcelGenerator: Image error for {$url}: " . $e->getMessage());
        }
    }

    private function normalizeImageUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);

        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        if (strpos($url, '/') === 0) {
            $domain = $_SERVER['HTTP_HOST'] ?? 'geniled.ru';
            return 'https://' . $domain . $url;
        }

        $domain = $_SERVER['HTTP_HOST'] ?? 'geniled.ru';
        return 'https://' . $domain . '/' . ltrim($url, '/');
    }

    private function fillTotals(int $row, array $products): void
    {
        $totalBase = 0.0;
        $totalYour = 0.0;
        $count = 0;
        $discountPercentSum = 0.0;
        $discountPercentCount = 0;
        foreach ($products as $p) {
            $qty = max(1, (int)($p['quantity'] ?? 1));
            $basePrice = (float)($p['base_price'] ?? $p['mic_price'] ?? 0);
            $yourPrice = (float)($p['price'] ?? 0);

            if ($basePrice <= 0) {
                $basePrice = $yourPrice;
            }

            $itemDiscountPercent = 0.0;
            if ($basePrice > 0 && $yourPrice < $basePrice) {
                $itemDiscountPercent = (($basePrice - $yourPrice) / $basePrice) * 100;
            }
            $itemDiscountRounded = round($itemDiscountPercent, 1);
            if ($itemDiscountRounded >= 0.1) {
                $discountPercentSum += $itemDiscountRounded;
                $discountPercentCount++;
            }

            $totalBase += $qty * $basePrice;
            $totalYour += $qty * $yourPrice;
            $count += $qty;
        }
        $discount = $totalBase - $totalYour;
        $averageDiscountPercent = $discountPercentCount > 0 ? $discountPercentSum / $discountPercentCount : 0;
        $nds = $totalYour * 20 / 120;

        $row++;
        $this->sheet->setCellValue("E{$row}", 'ИТОГИ:');
        $this->sheet->getStyle("E{$row}")->getFont()->setBold(true)->setSize(12);
        $this->sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $row++;
        $this->sheet->setCellValue("E{$row}", 'Всего товаров:');
        $this->sheet->setCellValue("H{$row}", $count);

        $row++;
        $this->sheet->setCellValue("E{$row}", 'Сумма без скидки:');
        $this->sheet->setCellValue("H{$row}", $totalBase);
        $this->sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $row++;
        $economyText = number_format($discount, 2, ',', ' ') . ' руб (' . number_format($averageDiscountPercent, 0) . '%)';
        $this->sheet->setCellValue("E{$row}", '👉 ВЫ ЭКОНОМИТЕ:');
        $this->sheet->setCellValue("H{$row}", $economyText);
        $this->sheet->getStyle("D{$row}:H{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFF9C']],
        ]);

        $row++;
        $this->sheet->setCellValue("E{$row}", 'Итого к оплате:');
        $this->sheet->setCellValue("H{$row}", number_format($totalYour, 2, ',', ' ') . ' руб');
        $this->sheet->getStyle("D{$row}:H{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCCFFCC']],
        ]);

        $row++;
        $this->sheet->setCellValue("E{$row}", 'В том числе НДС 20%');
        $this->sheet->setCellValue("H{$row}", number_format($nds, 2, ',', ' ') . ' руб');
    }

    private function fillComment(int $row, array $info): int
    {
        $comment = trim((string)($info['comment'] ?? ''));
        if ($comment === '') {
            return $row;
        }

        $this->sheet->setCellValue("A{$row}", "Комментарий:");
        $this->sheet->mergeCells("A{$row}:H{$row}");
        $this->sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);

        $row++;
        $this->sheet->setCellValue("A{$row}", $comment);
        $this->sheet->mergeCells("A{$row}:H{$row}");
        $this->sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $lineCount = substr_count($comment, "\n") + 2;
        $this->sheet->getRowDimension($row)->setRowHeight($lineCount * 15);
        return $row;
    }

    private function appendFooterBlock(int $afterRow = null, array $info = []): void
    {
        if ($afterRow === null) {
            $highestRow = $this->sheet->getHighestRow();
            $afterRow = $highestRow > 0 ? $highestRow : 25;
        }

        $logosRow = $afterRow + 2;
        $titleRow = $logosRow + 1;

        $logos = [
            [
                'path' => $_SERVER['DOCUMENT_ROOT'] . '/local/tools/kp_gen4/assets/images/geniled.png',
                'cell' => 'B' . $logosRow,
                'offsetX' => 10,
                'link' => 'https://geniled.ru'
            ],
            [
                'path' => $_SERVER['DOCUMENT_ROOT'] . '/local/tools/kp_gen4/assets/images/geniled-art2.png',
                'cell' => 'D' . $logosRow,
                'offsetX' => 10,
                'link' => 'https://geniled.art'
            ],
            [
                'path' => $_SERVER['DOCUMENT_ROOT'] . '/local/tools/kp_gen4/assets/images/geniled-park2.png',
                'cell' => 'F' . $logosRow,
                'offsetX' => 10,
                'link' => 'https://geniledpark.ru'
            ],
        ];

        foreach ($logos as $logo) {
            if (file_exists($logo['path'])) {
                $img = new Drawing();
                $img->setPath($logo['path']);
                $img->setCoordinates($logo['cell']);
                $img->setHeight(34);
                $img->setOffsetX($logo['offsetX']);
                $img->setOffsetY(2);
                $img->setWorksheet($this->sheet);
            }
        }

        $this->sheet->getCell("B{$logosRow}")->getHyperlink()->setUrl('https://geniled.ru');
        $this->sheet->getCell("D{$logosRow}")->getHyperlink()->setUrl('https://geniled.art');
        $this->sheet->getCell("F{$logosRow}")->getHyperlink()->setUrl('https://geniledpark.ru');

        $this->sheet->getRowDimension($logosRow)->setRowHeight(40);

        // ✅ ИСПРАВЛЕНО: Используем поле 'company' вместо 'customer'
        $companyName = trim((string)($info['company'] ?? 'ООО "ИнПродакшн"'));
        $titleText = "Карта предприятия {$companyName}";

        $mergeRange = "B{$titleRow}:H{$titleRow}";
        $this->sheet->mergeCells($mergeRange);
        $this->sheet->setCellValue("B{$titleRow}", $titleText);

        $style = $this->sheet->getStyle("B{$titleRow}");
        $style->getFont()->setBold(true)->setSize(11);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $this->sheet->getRowDimension($titleRow)->setRowHeight(22);
        $row = $titleRow + 2;

        $companyInfo = [
            'Полное наименование организации (в соответствии с учредительными документами)' => 'Общество с ограниченной ответственностью «ИнПродакшн»',
            'Краткое наименование' => 'ООО «ИнПродакшн»',
            'Генеральный директор' => 'Вагин Константин Евгеньевич',
            'Действует на основании' => 'Устав',
            'Юридический адрес' => '620085 Свердловская область, г. Екатеринбург, ул. 8 Марта, д. 267, помещ. 11/1',
            'ИНН' => '6671026720',
            'КПП' => '667901001',
            'Расчетный счет' => '40702810900810005883',
            'Банк' => 'Филиал "Центральный" Банка ВТБ (ПАО)',
            'Корреспондентский счет' => '30101810145250000000',
            'БИК' => '44525411',
            'ОКПО' => '44138067',
            'ОКВЭД' => '27.40, 32.99, 46.15.3, 46.43.1, 46.47.2, 47.59',
            'ОГРН' => '1156658089587',
            'Телефон' => '+7(343) 344-44-00; 8(800)222-71-10',
            'Электронная почта' => 'info@in-prod.ru; info@geniled.ru',
            'Абонентский ящик для отправки корреспонденции' => '620016, г. Екатеринбург, а/я 155',
        ];

        foreach ($companyInfo as $label => $value) {
            $this->sheet->setCellValue("A{$row}", $label);
            if (mb_strlen($label) < 80) {
                $this->sheet->mergeCells("A{$row}:B{$row}");
            }
            $this->sheet->getStyle("A{$row}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $this->sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9);

            $this->sheet->setCellValue("C{$row}", $value);
            $this->sheet->mergeCells("C{$row}:H{$row}");
            $this->sheet->getStyle("C{$row}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $this->sheet->getStyle("C{$row}")->getFont()->setSize(9);

            $this->sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);

            $this->sheet->getRowDimension($row)->setRowHeight(-1);
            $row++;
        }
    }

    private function save(array $info): void
    {
        // Формируем части названия файла
        $parts = [];

        // Всегда начинаем с "КП"
        $parts[] = 'КП';

        // Добавляем заказчика, если есть
        $customer = trim((string)($info['customer'] ?? ''));
        if ($customer !== '') {
            // Очищаем от недопустимых символов для имени файла
            $customerClean = preg_replace('~[<>:"/\\|?*]~u', '', $customer);
            $customerClean = preg_replace('~\s+~u', ' ', $customerClean);
            $parts[] = mb_substr($customerClean, 0, 50); // Ограничиваем длину
        }

        // Добавляем объект, если есть
        $object = trim((string)($info['object'] ?? ''));
        if ($object !== '') {
            // Очищаем от недопустимых символов для имени файла
            $objectClean = preg_replace('~[<>:"/\\|?*]~u', '', $object);
            $objectClean = preg_replace('~\s+~u', ' ', $objectClean);
            $parts[] = mb_substr($objectClean, 0, 50); // Ограничиваем длину
        }

        // Добавляем дату КП
        $kpDate = trim((string)($info['date'] ?? ''));
        if ($kpDate !== '') {
            // Преобразуем дату в безопасный формат (заменяем точки на дефисы)
            $kpDate = str_replace('.', '-', $kpDate);
            $parts[] = $kpDate;
        } else {
            $parts[] = date('d-m-Y');
        }

        // Собираем название файла для скачивания
        $downloadFilename = implode(' - ', $parts) . '.xlsx';

        // Создаём безопасное имя для файловой системы (временный файл)
        $filesystemName = preg_replace('/[\\\\\\/\\:*?"<>|]+/u', '_', implode('_', $parts));
        $filesystemName = preg_replace('/_+/', '_', $filesystemName);
        $filesystemName = trim($filesystemName, '_ ');

        if ($filesystemName === '' || $filesystemName === 'КП') {
            $filesystemName = 'KP';
        }

        // Добавляем timestamp к временному файлу для уникальности
        $filesystemFilename = $filesystemName . '_' . date('Ymd_His') . '.xlsx';
        $filepath = TEMP_DIR . $filesystemFilename;

        // Сохраняем файл
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save($filepath);

        // Отправляем заголовки для скачивания
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // RFC 2231 для поддержки UTF-8 в именах файлов
        $asciiFallback = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $downloadFilename);
        if ($asciiFallback === false || $asciiFallback === '') {
            $asciiFallback = 'KP.xlsx';
        }

        header(
            'Content-Disposition: attachment; filename="' . $asciiFallback .
            '"; filename*=UTF-8\'\'' . rawurlencode($downloadFilename)
        );
        header('Cache-Control: max-age=0');

        // Отправляем файл
        readfile($filepath);

        // Очищаем временные файлы
        @unlink($filepath);
        foreach (glob(TEMP_DIR . 'img_*.*') as $f) {
            @unlink($f);
        }

        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
    }
}