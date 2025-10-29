<?php
/**
 * AJAX поиск товаров по названию и артикулу
 * Полная версия с ценами, картинками и оптимизацией
 * ✅ ДОБАВЛЕНО:
 *   – Транслитерация (рус <-> лат)
 *   – Кнопка «Техническое описание» с открытием PDF
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

$request = Context::getCurrent()->getRequest();
$q = trim((string)$request->get('q'));

if (mb_strlen($q) < 2) {
    echo '<div style="padding:12px;color:#999;text-align:center;">Введите минимум 2 символа</div>';
    exit;
}

/* ==========================================================
 *  ФУНКЦИЯ ТРАНСЛИТЕРАЦИИ
 * ========================================================== */
function generateSearchVariants(string $query): array {
    static $cache = [];
    $key = md5($query);
    if (isset($cache[$key])) return $cache[$key];

    $variants = [$query];
    $lower = mb_strtolower($query, 'UTF-8');

    // Рус -> лат
    if (preg_match('/[а-яё]/iu', $lower)) {
        $translit = \CUtil::translit($lower, 'ru', ['replace_space'=>' ','replace_other'=>'']);
        if ($translit !== $lower) $variants[] = $translit;
    }
    // Лат -> рус
    if (preg_match('/[a-z]/i', $lower)) {
        $toRu = strtr($lower, [
            'a'=>'а','b'=>'б','c'=>'ц','e'=>'е','h'=>'х','k'=>'к',
            'm'=>'м','o'=>'о','p'=>'р','t'=>'т','x'=>'х','y'=>'у'
        ]);
        if ($toRu !== $lower) $variants[] = $toRu;
    }

    return $cache[$key] = array_unique($variants);
}

$searchVariants = generateSearchVariants($q);

/* ==========================================================
 *  ФУНКЦИЯ АБСОЛЮТНЫХ URL
 * ========================================================== */
$serverName = (string)Option::get('main', 'server_name', '');
if ($serverName === '' && defined('SITE_SERVER_NAME')) $serverName = SITE_SERVER_NAME;
if ($serverName === '') $serverName = (string)($_SERVER['HTTP_HOST'] ?? '');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

$abs = static function (string $src) use ($serverName, $scheme): string {
    if ($src === '') return '';
    if (preg_match('~^https?://~i', $src)) return $src;
    if (str_starts_with($src, '//')) return $scheme . ':' . $src;
    if (str_starts_with($src, '/')) return $scheme . '://' . $serverName . $src;
    return $scheme . '://' . $serverName . '/' . ltrim($src, '/');
};

/* ==========================================================
 *  ПОИСК
 * ========================================================== */
$catalogIBlockId = defined('IBLOCK_ID')
    ? IBLOCK_ID
    : (CMaxCache::$arIBlocks[SITE_ID]["aspro_max_catalog"]["aspro_max_catalog"][0] ?? 29);

$orLogic = ['LOGIC' => 'OR'];
foreach ($searchVariants as $variant) {
    $orLogic[] = ["?NAME" => $variant];
    $orLogic[] = ["?PROPERTY_CML2_ARTICLE" => $variant];
}
$arFilter = [
    "IBLOCK_ID" => $catalogIBlockId,
    "ACTIVE"    => "Y",
    $orLogic,
];

$res = CIBlockElement::GetList(
    ["NAME" => "ASC"],
    $arFilter,
    false,
    ["nTopCount" => 10],
    [
        "ID", "IBLOCK_ID", "NAME", "CODE",
        "IBLOCK_SECTION_ID", "DETAIL_PAGE_URL",
        "PREVIEW_PICTURE", "DETAIL_PICTURE", "PROPERTY_CML2_ARTICLE"
    ]
);

if (!$res) {
    echo '<div style="padding:16px;color:#999;text-align:center;">Ошибка поиска</div>';
    exit;
}

$buildSectionCodePath = static function (int $iblockId, int $sectionId): string {
    if ($sectionId <= 0) return '';
    $path = '';
    $nav = CIBlockSection::GetNavChain($iblockId, $sectionId, ["ID", "CODE"]);
    while ($s = $nav->GetNext()) {
        if ($s['CODE']) $path .= ($path ? '/' : '') . $s['CODE'];
    }
    return $path;
};

/* ==========================================================
 *  РЕНДЕР РЕЗУЛЬТАТОВ
 * ========================================================== */
$hasResults = false;

while ($ar = $res->GetNext()) {
    $hasResults = true;
    $tpl = CIBlock::GetArrayByID($ar["IBLOCK_ID"], "DETAIL_PAGE_URL");
    $f = $ar;
    $f['ELEMENT_ID'] = $ar['ID'];
    $f['SECTION_ID'] = (int)$ar['IBLOCK_SECTION_ID'];
    if (strpos($tpl, '#SECTION_CODE_PATH#') !== false)
        $f['SECTION_CODE_PATH'] = $buildSectionCodePath((int)$ar['IBLOCK_ID'], (int)$ar['IBLOCK_SECTION_ID']);
    $link = $abs(CIBlock::ReplaceDetailUrl($tpl, $f, true, 'E') ?: $ar['DETAIL_PAGE_URL']);

    $photo = '';
    $photoId = (int)($ar["DETAIL_PICTURE"] ?: $ar["PREVIEW_PICTURE"]);
    if ($photoId > 0) {
        $img = CFile::ResizeImageGet($photoId, ['width'=>60,'height'=>60], BX_RESIZE_IMAGE_PROPORTIONAL_ALT, true);
        if (!empty($img['src'])) $photo = $abs($img['src']);
    }

    $article = trim((string)$ar['PROPERTY_CML2_ARTICLE_VALUE']);
    $priceTypeId = defined('DEFAULT_PRICE_TYPE_ID') ? DEFAULT_PRICE_TYPE_ID : 2;
    $p = CPrice::GetList([], [
        'PRODUCT_ID'=>$ar['ID'],
        'CATALOG_GROUP_ID'=>$priceTypeId
    ])->Fetch();
    $price = $p?(float)$p['PRICE']:0;
    $currency = $p['CURRENCY'] ?? 'RUB';
    $priceFormatted = $price>0 ? number_format($price,2,',',' ').' '.($currency==='RUB'?'₽':$currency) : '';

    $techLink = "/ajax/commercial/technical_desc.php?ID={$ar['ID']}";
    ?>
    <div class="live-item" style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid #f0f0f0;">
        <!-- Фото -->
        <div style="width:60px;height:60px;flex-shrink:0;cursor:pointer;border-radius:6px;overflow:hidden;background:#f5f5f5;border:1px solid #e5e5e5;display:flex;align-items:center;justify-content:center;"
             onclick="window.open('<?=htmlspecialcharsbx($link)?>','_blank')">
            <?php if ($photo): ?>
                <img src="<?=htmlspecialcharsbx($photo)?>" alt="<?=htmlspecialcharsbx($ar['NAME'])?>"
                     style="width:100%;height:100%;object-fit:contain;" loading="lazy">
            <?php else: ?><span style="color:#999;font-size:9px;">📦</span><?php endif; ?>
        </div>

        <!-- Название -->
        <div style="flex:1;cursor:pointer;min-width:0;" onclick="window.open('<?=htmlspecialcharsbx($link)?>','_blank')">
            <div style="font-weight:600;color:#333;font-size:14px;line-height:1.4;"><?=htmlspecialcharsbx($ar['NAME'])?></div>
        </div>

        <!-- Артикул -->
        <div style="flex-shrink:0;padding:0 12px;min-width:110px;">
            <?php if ($article): ?>
                <div style="font-size:13px;color:#666;white-space:nowrap;">Арт. <?=$article?></div>
            <?php else: ?><div style="font-size:13px;color:#999;">—</div><?php endif; ?>
        </div>

        <!-- Цена + Тех. описание -->
        <div style="flex-shrink:0;padding:0 12px;text-align:right;min-width:170px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
            <?php if ($priceFormatted): ?>
                <div style="font-size:16px;color:#722e85;font-weight:700;white-space:nowrap;"><?=$priceFormatted?></div>
            <?php else: ?><div style="font-size:13px;color:#999;">—</div><?php endif; ?>

            <a href="<?=htmlspecialcharsbx($techLink)?>" target="_blank"
               style="font-size:12px;color:#555;text-decoration:none;padding:4px 8px;border:1px solid #ccc;border-radius:4px;transition:.2s;"
               onmouseover="this.style.background='#f3f3f3';"
               onmouseout="this.style.background='transparent';">
                Тех. описание
            </a>
        </div>

        <!-- Кнопка Добавить -->
        <button class="btn-add-product"
                data-article="<?=$article?>"
                onclick="event.stopPropagation();addProductFromSearch('<?=htmlspecialcharsbx($article,ENT_QUOTES)?>');"
                style="padding:8px 16px;background:#722E85;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;white-space:nowrap;font-weight:500;transition:.2s;flex-shrink:0;">
            Добавить
        </button>
    </div>
    <?php
}

if (!$hasResults) {
    echo '<div style="padding:16px;color:#999;text-align:center;">Ничего не найдено по запросу "' . htmlspecialchars($q) . '"</div>';
}
?>

<style>
    @media screen and (max-width:900px){
        .live-item{flex-wrap:wrap!important;gap:8px!important;padding:12px!important;}
        .live-item>div:first-child{order:1!important;width:48px!important;height:48px!important;}
        .live-item>div:nth-child(2){flex:1 1 100%!important;order:2!important;margin-bottom:4px!important;}
        .live-item>div:nth-child(3),.live-item>div:nth-child(4){order:3!important;min-width:auto!important;padding:0 8px!important;}
        .live-item button{order:4!important;width:100%!important;min-height:44px!important;font-size:14px!important;padding:12px 16px!important;}
    }
</style>
