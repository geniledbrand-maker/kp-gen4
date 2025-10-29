<?php
/**
 * Простой тест картинок через браузер
 */

define("STOP_STATISTICS", true);
define("NO_AGENT_STATISTIC", "Y");
define("NO_KEEP_STATISTIC", "Y");
define("DisableEventsCheck", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Подключаем константы
require_once dirname(__DIR__) . '/config/constants.php';

// === Функция для абсолютных URL ===
$serverName = (string)Option::get('main', 'server_name', '');
if ($serverName === '' && defined('SITE_SERVER_NAME')) {
    $serverName = (string)SITE_SERVER_NAME;
}
if ($serverName === '') {
    $serverName = (string)($_SERVER['HTTP_HOST'] ?? '');
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

$abs = static function (string $src) use ($serverName, $scheme): string {
    if ($src === '') return '';
    if (preg_match('~^https?://~i', $src)) return $src;
    if (str_starts_with($src, '//')) return $scheme . ':' . $src;
    if ($serverName === '') return $src;
    if (!str_starts_with($src, '/')) $src = '/' . ltrim($src, '/');
    return $scheme . '://' . $serverName . $src;
};

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Тест картинок</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-item { border: 1px solid #ccc; margin: 10px 0; padding: 15px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px; }
        img { border: 1px solid #ccc; margin: 5px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Тест загрузки картинок</h1>
    
    <div class="debug">
        <strong>Информация о сервере:</strong><br>
        Server Name: <?= $serverName ?><br>
        Scheme: <?= $scheme ?><br>
        HTTP_HOST: <?= $_SERVER['HTTP_HOST'] ?? 'не установлен' ?><br>
        IBLOCK_ID: <?= defined('IBLOCK_ID') ? IBLOCK_ID : 'не определена' ?><br>
    </div>

    <?php
    // ID каталога
    $catalogIBlockId = defined('IBLOCK_ID') ? IBLOCK_ID : (CMaxCache::$arIBlocks[SITE_ID]["aspro_max_catalog"]["aspro_max_catalog"][0] ?? 29);
    
    // Получаем товары с картинками
    $arFilter = [
        "IBLOCK_ID" => $catalogIBlockId,
        "ACTIVE" => "Y",
        "!DETAIL_PICTURE" => false,
    ];

    $res = CIBlockElement::GetList(
        ["ID" => "ASC"],
        $arFilter,
        false,
        ["nTopCount" => 5],
        [
            "ID", "IBLOCK_ID", "NAME", "CODE",
            "DETAIL_PAGE_URL",
            "PREVIEW_PICTURE", "DETAIL_PICTURE",
            "PROPERTY_CML2_ARTICLE",
        ]
    );

    $count = 0;
    while ($ar = $res->GetNext() && $count < 3) {
        $count++;
        echo "<div class='test-item'>";
        echo "<h3>Товар #" . $count . ": " . htmlspecialchars($ar['NAME']) . "</h3>";
        
        $photoId = (int)($ar["DETAIL_PICTURE"] ?: $ar["PREVIEW_PICTURE"]);
        
        echo "<div class='debug'>";
        echo "ID: " . $ar['ID'] . "<br>";
        echo "DETAIL_PICTURE: " . $ar['DETAIL_PICTURE'] . "<br>";
        echo "PREVIEW_PICTURE: " . $ar['PREVIEW_PICTURE'] . "<br>";
        echo "Photo ID для ресайза: " . $photoId . "<br>";
        echo "</div>";
        
        if ($photoId > 0) {
            // Тест 1: Оригинальная картинка
            $originalFile = CFile::GetFileArray($photoId);
            if ($originalFile) {
                echo "<p><strong>Оригинальный файл:</strong></p>";
                echo "<div class='debug'>SRC: " . $originalFile['SRC'] . "<br>";
                echo "Абсолютный URL: " . $abs($originalFile['SRC']) . "</div>";
                echo "<img src='" . $abs($originalFile['SRC']) . "' style='max-width: 200px; max-height: 200px;'><br><br>";
            } else {
                echo "<p class='error'>ОШИБКА: Не удалось получить оригинальный файл!</p>";
            }
            
            // Тест 2: Ресайз
            $img = CFile::ResizeImageGet(
                $photoId,
                ['width' => 60, 'height' => 60],
                BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                true
            );
            
            if (!empty($img['src'])) {
                echo "<p class='success'><strong>Ресайз 60x60:</strong></p>";
                echo "<div class='debug'>SRC: " . $img['src'] . "<br>";
                echo "Абсолютный URL: " . $abs($img['src']) . "</div>";
                echo "<img src='" . $abs($img['src']) . "'><br><br>";
            } else {
                echo "<p class='error'>ОШИБКА: Ресайз не сработал!</p>";
            }
            
            // Тест 3: Разные размеры
            $sizes = [
                ['width' => 52, 'height' => 52],
                ['width' => 100, 'height' => 100]
            ];
            
            foreach ($sizes as $size) {
                $testImg = CFile::ResizeImageGet(
                    $photoId,
                    $size,
                    BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                    true
                );
                
                if (!empty($testImg['src'])) {
                    echo "<p><strong>Ресайз " . $size['width'] . "x" . $size['height'] . ":</strong></p>";
                    echo "<img src='" . $abs($testImg['src']) . "'>";
                }
            }
        } else {
            echo "<p class='error'>ОШИБКА: Нет ID картинки!</p>";
        }
        
        echo "</div>";
    }

    if ($count == 0) {
        echo "<p class='error'>ОШИБКА: Не найдено товаров с картинками!</p>";
        
        // Попробуем найти любые товары
        echo "<h3>Поиск любых товаров:</h3>";
        $anyRes = CIBlockElement::GetList(
            ["ID" => "ASC"],
            ["IBLOCK_ID" => $catalogIBlockId, "ACTIVE" => "Y"],
            false,
            ["nTopCount" => 5],
            ["ID", "NAME", "DETAIL_PICTURE", "PREVIEW_PICTURE"]
        );
        
        while ($anyAr = $anyRes->GetNext()) {
            echo "<p><strong>" . htmlspecialchars($anyAr['NAME']) . "</strong> - DETAIL: " . $anyAr['DETAIL_PICTURE'] . ", PREVIEW: " . $anyAr['PREVIEW_PICTURE'] . "</p>";
        }
    }
    ?>
</body>
</html>
