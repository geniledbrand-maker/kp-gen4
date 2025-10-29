<?php
/**
 * Тест загрузки картинок для лайф поиска
 */

define("STOP_STATISTICS", true);
define("NO_AGENT_STATISTIC", "Y");
define("NO_KEEP_STATISTIC", "Y");
define("DisableEventsCheck", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Подключаем константы
require_once dirname(__DIR__) . '/config/constants.php';

echo "<h1>Тест загрузки картинок</h1>";

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

echo "<h2>Информация о сервере:</h2>";
echo "<p><strong>Server Name:</strong> " . $serverName . "</p>";
echo "<p><strong>Scheme:</strong> " . $scheme . "</p>";
echo "<p><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'не установлен') . "</p>";

// ID каталога
$catalogIBlockId = defined('IBLOCK_ID') ? IBLOCK_ID : (CMaxCache::$arIBlocks[SITE_ID]["aspro_max_catalog"]["aspro_max_catalog"][0] ?? 29);
echo "<p><strong>Catalog IBlock ID:</strong> " . $catalogIBlockId . "</p>";

// Получаем несколько товаров с картинками
$arFilter = [
    "IBLOCK_ID" => $catalogIBlockId,
    "ACTIVE" => "Y",
    "!DETAIL_PICTURE" => false, // Только с детальными картинками
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

echo "<h2>Тест товаров с картинками:</h2>";

$count = 0;
while ($ar = $res->GetNext() && $count < 3) {
    $count++;
    echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 15px;'>";
    echo "<h3>Товар #" . $count . ": " . htmlspecialchars($ar['NAME']) . "</h3>";
    
    echo "<p><strong>ID:</strong> " . $ar['ID'] . "</p>";
    echo "<p><strong>DETAIL_PICTURE:</strong> " . $ar['DETAIL_PICTURE'] . "</p>";
    echo "<p><strong>PREVIEW_PICTURE:</strong> " . $ar['PREVIEW_PICTURE'] . "</p>";
    
    // Тестируем ресайз
    $photoId = (int)($ar["DETAIL_PICTURE"] ?: $ar["PREVIEW_PICTURE"]);
    echo "<p><strong>Photo ID для ресайза:</strong> " . $photoId . "</p>";
    
    if ($photoId > 0) {
        // Тест 1: Оригинальная картинка
        $originalFile = CFile::GetFileArray($photoId);
        if ($originalFile) {
            echo "<p><strong>Оригинальный файл:</strong></p>";
            echo "<p>SRC: " . $originalFile['SRC'] . "</p>";
            echo "<p>Абсолютный URL: " . $abs($originalFile['SRC']) . "</p>";
            echo "<img src='" . $abs($originalFile['SRC']) . "' style='max-width: 200px; max-height: 200px; border: 1px solid #ccc;'><br><br>";
        }
        
        // Тест 2: Ресайз через CFile::ResizeImageGet
        $img = CFile::ResizeImageGet(
            $photoId,
            ['width' => 60, 'height' => 60],
            BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
            true
        );
        
        if (!empty($img['src'])) {
            echo "<p><strong>Ресайз 60x60:</strong></p>";
            echo "<p>SRC: " . $img['src'] . "</p>";
            echo "<p>Абсолютный URL: " . $abs($img['src']) . "</p>";
            echo "<img src='" . $abs($img['src']) . "' style='border: 1px solid #ccc;'><br><br>";
        } else {
            echo "<p style='color: red;'><strong>ОШИБКА:</strong> Ресайз не сработал!</p>";
        }
        
        // Тест 3: Разные размеры ресайза
        $sizes = [
            ['width' => 52, 'height' => 52],
            ['width' => 100, 'height' => 100],
            ['width' => 200, 'height' => 200]
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
                echo "<img src='" . $abs($testImg['src']) . "' style='border: 1px solid #ccc; margin: 5px;'>";
            }
        }
    } else {
        echo "<p style='color: red;'><strong>ОШИБКА:</strong> Нет ID картинки!</p>";
    }
    
    echo "</div>";
}

if ($count == 0) {
    echo "<p style='color: red;'><strong>ОШИБКА:</strong> Не найдено товаров с картинками!</p>";
    
    // Попробуем найти любые товары
    echo "<h3>Поиск любых товаров:</h3>";
    $anyRes = CIBlockElement::GetList(
        ["ID" => "ASC"],
        ["IBLOCK_ID" => $catalogIBlockId, "ACTIVE" => "Y"],
        false,
        ["nTopCount" => 3],
        ["ID", "NAME", "DETAIL_PICTURE", "PREVIEW_PICTURE"]
    );
    
    while ($anyAr = $anyRes->GetNext()) {
        echo "<p><strong>" . htmlspecialchars($anyAr['NAME']) . "</strong> - DETAIL: " . $anyAr['DETAIL_PICTURE'] . ", PREVIEW: " . $anyAr['PREVIEW_PICTURE'] . "</p>";
    }
}

echo "<h2>Тест констант:</h2>";
echo "<p><strong>IBLOCK_ID:</strong> " . (defined('IBLOCK_ID') ? IBLOCK_ID : 'не определена') . "</p>";
echo "<p><strong>ARTICLE_PROP_ID:</strong> " . (defined('ARTICLE_PROP_ID') ? ARTICLE_PROP_ID : 'не определена') . "</p>";
echo "<p><strong>SITE_ID:</strong> " . SITE_ID . "</p>";

?>
