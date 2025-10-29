<?php
/**
 * Минимальный тест генерации PDF с изображением
 * Путь: /local/tools/kp_gen4/temp/Tests/minimal_test.php?ID=247327
 */

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/commercial/functions.php';

use Spipu\Html2Pdf\Html2Pdf;

$productId = (int)($_GET['ID'] ?? 247327);

// Получаем товар
$arProduct = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 29, 'ID' => $productId],
    false,
    false,
    ['ID', 'NAME', 'DETAIL_PICTURE', 'PREVIEW_PICTURE', 'PROPERTY_CML2_ARTICLE']
)->Fetch();

if (!$arProduct) {
    die("Товар не найден");
}

$imageId = (int)($arProduct['DETAIL_PICTURE'] ?: $arProduct['PREVIEW_PICTURE']);

if ($imageId <= 0) {
    die("У товара нет изображения");
}

// Получаем путь к изображению
$resized = CFile::ResizeImageGet(
    $imageId,
    ['width' => 300, 'height' => 300],
    BX_RESIZE_IMAGE_PROPORTIONAL,
    false
);

if (!$resized || empty($resized['src'])) {
    die("Не удалось создать ресайз");
}

$imagePath = $_SERVER['DOCUMENT_ROOT'] . $resized['src'];

if (!file_exists($imagePath)) {
    die("Файл изображения не найден: " . $imagePath);
}

// Конвертируем в путь к временному файлу (не Base64!)
$imageTempPath = getImageAsBase64($imagePath, 300);

if (empty($imageTempPath) || !file_exists($imageTempPath)) {
    die("Не удалось создать временный файл изображения");
}

// Создаём минимальный HTML с использованием file:// протокола
$html = '
<page>
    <h1 style="text-align:center; color:#722e85;">Тест изображения в PDF</h1>
    
    <p style="text-align:center; margin:20px 0;">
        <strong>Товар:</strong> ' . htmlspecialchars($arProduct['NAME']) . '<br>
        <strong>Артикул:</strong> ' . htmlspecialchars($arProduct['PROPERTY_CML2_ARTICLE_VALUE'] ?? 'н/д') . '
    </p>
    
    <div style="text-align:center; margin:30px 0;">
        <img src="file://' . $imageTempPath . '" style="max-width:300px; height:auto; border:2px solid #722e85;" alt="Товар">
    </div>
    
    <p style="text-align:center; color:#666; font-size:12px;">
        Временный файл: ' . basename($imageTempPath) . '<br>
        Формат: JPEG
    </p>
</page>
';

try {
    $html2pdf = new HTML2PDF('P', 'A4', 'ru', 'UTF-8');
    $html2pdf->writeHTML($html);

    $filename = 'minimal_test_' . $productId . '.pdf';
    $html2pdf->output($filename, 'D');

} catch (Exception $e) {
    echo "<h1>Ошибка генерации PDF</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<hr>";
    echo "<h3>Трассировка:</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>