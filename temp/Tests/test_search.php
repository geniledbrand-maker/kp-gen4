<?php
/**
 * Тест поиска товаров
 */

define("STOP_STATISTICS", true);
define("NO_AGENT_STATISTIC", "Y");
define("NO_KEEP_STATISTIC", "Y");
define("DisableEventsCheck", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Подключаем константы
require_once dirname(__DIR__) . '/config/constants.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Тест поиска</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-item { border: 1px solid #ccc; margin: 10px 0; padding: 15px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px; }
        .error { color: red; }
        .success { color: green; }
        input { padding: 10px; font-size: 16px; width: 300px; }
        button { padding: 10px 20px; font-size: 16px; }
    </style>
</head>
<body>
    <h1>Тест поиска товаров</h1>
    
    <form method="GET">
        <input type="text" name="q" placeholder="Введите поисковый запрос" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <button type="submit">Поиск</button>
    </form>
    
    <?php
    if (!empty($_GET['q'])) {
        $q = trim($_GET['q']);
        echo "<h2>Результаты поиска для: " . htmlspecialchars($q) . "</h2>";
        
        // ID каталога
        $catalogIBlockId = defined('IBLOCK_ID') ? IBLOCK_ID : (CMaxCache::$arIBlocks[SITE_ID]["aspro_max_catalog"]["aspro_max_catalog"][0] ?? 29);
        
        echo "<div class='debug'>IBLOCK_ID: " . $catalogIBlockId . "</div>";
        
        // Фильтр поиска (как в ajax_search.php)
        $arFilter = [
            "IBLOCK_ID" => $catalogIBlockId,
            "ACTIVE"    => "Y",
            [
                "LOGIC" => "OR",
                ["?NAME" => $q],
                ["?PROPERTY_CML2_ARTICLE" => $q],
            ],
        ];

        if (defined('ARTICLE_PROP_ID') && ARTICLE_PROP_ID > 0) {
            $arFilter[0][] = ["?PROPERTY_" . ARTICLE_PROP_ID => $q];
        }
        
        echo "<div class='debug'>Фильтр: " . print_r($arFilter, true) . "</div>";
        
        $res = CIBlockElement::GetList(
            ["NAME" => "ASC"],
            $arFilter,
            false,
            ["nTopCount" => 10],
            [
                "ID", "IBLOCK_ID", "NAME", "CODE",
                "IBLOCK_SECTION_ID",
                "DETAIL_PAGE_URL",
                "PREVIEW_PICTURE", "DETAIL_PICTURE",
                "PROPERTY_CML2_ARTICLE",
            ]
        );

        $count = 0;
        while ($ar = $res->GetNext()) {
            $count++;
            echo "<div class='test-item'>";
            echo "<h3>Товар #" . $count . ": " . htmlspecialchars($ar['NAME']) . "</h3>";
            
            echo "<div class='debug'>";
            echo "ID: " . $ar['ID'] . "<br>";
            echo "DETAIL_PICTURE: " . $ar['DETAIL_PICTURE'] . "<br>";
            echo "PREVIEW_PICTURE: " . $ar['PREVIEW_PICTURE'] . "<br>";
            echo "PROPERTY_CML2_ARTICLE: " . $ar['PROPERTY_CML2_ARTICLE_VALUE'] . "<br>";
            echo "</div>";
            
            // Тест картинки
            $photoId = (int)($ar["DETAIL_PICTURE"] ?: $ar["PREVIEW_PICTURE"]);
            if ($photoId > 0) {
                $fileInfo = CFile::GetFileArray($photoId);
                if ($fileInfo) {
                    echo "<p class='success'>Картинка найдена: " . $fileInfo['SRC'] . "</p>";
                } else {
                    echo "<p class='error'>Картинка не найдена для ID: " . $photoId . "</p>";
                }
            } else {
                echo "<p class='error'>Нет ID картинки</p>";
            }
            
            echo "</div>";
        }
        
        if ($count == 0) {
            echo "<p class='error'>Товары не найдены!</p>";
        }
    }
    ?>
</body>
</html>
