<?php
/**
 * Тестовая генерация PDF с подробной отладкой
 * Путь: /local/tools/kp_gen4/temp/Tests/test_pdf.php
 * Использование: https://geniled.ru/local/tools/kp_gen4/temp/Tests/test_pdf.php?ID=247327
 */

// Включаем отображение всех ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/lib/tcpdf/tcpdf.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/commercial/functions.php';

use Spipu\Html2Pdf\Html2Pdf;

if(!CModule::IncludeModule("iblock")){
    die("Module iblock not loaded");
}

$productId = (int)($_GET['ID'] ?? 0);

if (!$productId) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Тест генерации PDF</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
            h1 { color: #722e85; }
            input[type="number"] { padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
            button { padding: 10px 20px; background: #722e85; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #5a2369; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>🧪 Тест генерации PDF с отладкой</h1>
        <form method="GET">
            <label for="productId">Введите ID товара:</label><br><br>
            <input type="number" id="productId" name="ID" placeholder="Например: 247327" required>
            <button type="submit">Сгенерировать PDF</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Генерация PDF</title>";
echo "<style>body{font-family:Arial;margin:20px;background:#f5f5f5;}.log{background:white;padding:20px;margin:20px auto;max-width:1000px;border-radius:8px;}.success{color:green;}.error{color:red;}.info{color:#2196f3;}.step{margin:10px 0;padding:10px;background:#f9f9f9;border-left:3px solid #722e85;}</style>";
echo "</head><body><div class='log'>";

echo "<h1>🧪 Генерация PDF для товара ID={$productId}</h1><hr>";

// Шаг 1: Проверка товара
echo "<div class='step'><strong>Шаг 1:</strong> Получение данных товара...</div>";
$arProduct = CIBlockElement::GetList([], ['IBLOCK_ID' => 29, 'ID' => $productId], false, false, ['ID', 'NAME', 'PROPERTY_CML2_ARTICLE'])->Fetch();

if (!$arProduct) {
    echo "<p class='error'>❌ Товар не найден!</p></div></body></html>";
    exit;
}

echo "<p class='success'>✅ Товар найден: " . htmlspecialchars($arProduct['NAME']) . "</p>";
echo "<p>Артикул: " . htmlspecialchars($arProduct['PROPERTY_CML2_ARTICLE_VALUE'] ?? 'н/д') . "</p>";

// Шаг 2: Подготовка характеристик
echo "<div class='step'><strong>Шаг 2:</strong> Подготовка характеристик...</div>";

$chars = [
    'SVET' => [
        'NAME' => 'Световые и электрические параметры',
        'ITEMS' => [
            'SVETOVOY_POTOK_LM_DLYA_SAYTA' => [ 'MULIPLE' => 'N' ],
            'ELEKTROPOTREBLENIE_VT_DLYA_SAYTA_1' => [ 'MULIPLE' => 'N' ],
        ]
    ],
];

echo "<p class='success'>✅ Характеристики подготовлены</p>";

// Шаг 3: Получение данных через getProductData
echo "<div class='step'><strong>Шаг 3:</strong> Вызов getProductData()...</div>";

$productData = getProductData($productId, $chars, 'to');

if (!$productData) {
    echo "<p class='error'>❌ getProductData() вернул пустой результат!</p>";
    echo "<p>Проверьте логи PHP (error_log)</p>";
    echo "</div></body></html>";
    exit;
}

echo "<p class='success'>✅ Данные получены</p>";

// Шаг 4: Проверка изображения
echo "<div class='step'><strong>Шаг 4:</strong> Проверка изображения...</div>";

$imagePath = $productData['FIELDS']['PICTURE'] ?? '';

if (empty($imagePath)) {
    echo "<p class='error'>❌ PICTURE пустой!</p>";
} elseif (strpos($imagePath, 'data:image') === 0) {
    $imageSize = strlen($imagePath);
    echo "<p class='success'>✅ Изображение в формате Base64</p>";
    echo "<p class='info'>📊 Размер: " . number_format($imageSize) . " символов (" . number_format($imageSize/1024, 2) . " КБ)</p>";
    echo "<p class='info'>🔍 Начало: <code>" . htmlspecialchars(substr($imagePath, 0, 50)) . "...</code></p>";

    // Проверяем, что изображение валидное
    echo "<div style='border:1px solid #ccc;padding:10px;margin:10px 0;background:#fafafa;'>";
    echo "<p><strong>Превью изображения:</strong></p>";
    echo "<img src='{$imagePath}' style='max-width:200px;border:1px solid #ddd;'>";
    echo "</div>";
} else {
    echo "<p class='error'>❌ PICTURE не в формате Base64!</p>";
    echo "<p>Значение: <code>" . htmlspecialchars(substr($imagePath, 0, 100)) . "...</code></p>";
}

// Шаг 5: Подготовка HTML
echo "<div class='step'><strong>Шаг 5:</strong> Подготовка HTML для PDF...</div>";

$image = !empty($imagePath) && strpos($imagePath, 'data:image') === 0
    ? '<img style="width: 400px; height: auto;" src="' . $imagePath . '" alt="">'
    : '<div style="width:400px;height:400px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;">Нет изображения</div>';

$html = '<table style="width:720px; margin: 10px 15px; background: #f4f4f4; border-radius: 20px">
    <tr>
        <td style="padding: 30px 0 0 50px; font-family: Arial; font-size: 24px;">' . htmlspecialchars($arProduct['NAME']) . '</td>
    </tr>
    <tr>
        <td align="center" style="padding: 20px;">
            '.$image.'
        </td>
    </tr>
</table>';

echo "<p class='success'>✅ HTML подготовлен</p>";
echo "<details><summary>Показать HTML</summary><pre>" . htmlspecialchars($html) . "</pre></details>";

// Шаг 6: Инициализация Html2Pdf
echo "<div class='step'><strong>Шаг 6:</strong> Инициализация Html2Pdf...</div>";

try {
    $html2pdf = new HTML2PDF('P', 'A4', 'ru', 'UTF-8');

    $html2pdf->addFont('Montserrat-Light','', 'pdffonts/museosanscyrl300.php');
    $html2pdf->addFont('Montserrat-Medium','', 'pdffonts/museosanscyrl500.php');
    $html2pdf->addFont('Montserrat-SemiBold','', 'pdffonts/museosanscyrl700.php');
    $html2pdf->setDefaultFont("Montserrat-Light");

    echo "<p class='success'>✅ Html2Pdf инициализирован</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Ошибка инициализации: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></body></html>";
    exit;
}

// Шаг 7: Генерация PDF
echo "<div class='step'><strong>Шаг 7:</strong> Генерация PDF...</div>";

try {
    $html2pdf->writeHTML('<page>' . $html . '</page>');
    echo "<p class='success'>✅ HTML записан в PDF</p>";

    // Шаг 8: Вывод PDF
    echo "<div class='step'><strong>Шаг 8:</strong> Отправка PDF...</div>";

    $filename = 'test_product_' . $productId . '.pdf';

    echo "<p class='success'>✅ PDF сгенерирован успешно!</p>";
    echo "<p class='info'>📄 Имя файла: {$filename}</p>";
    echo "<p style='margin-top:20px;'><strong>Сейчас начнется скачивание PDF...</strong></p>";
    echo "<p><a href='?ID={$productId}' style='color:#722e85;'>🔄 Сгенерировать еще раз</a></p>";

    echo "</div></body></html>";

    // Отправляем PDF
    ob_end_clean(); // Очищаем буфер вывода
    $html2pdf->output($filename, 'D');

} catch (Exception $e) {
    echo "<p class='error'>❌ Ошибка генерации PDF: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div></body></html>";
}
?>