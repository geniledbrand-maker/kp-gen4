<?php
define("STOP_STATISTICS", true);
define("NO_AGENT_STATISTIC", "Y");
define("NO_KEEP_STATISTIC", "Y");
define("DisableEventsCheck", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

$request = Context::getCurrent()->getRequest();
$q = trim((string)$request->get('q'));
if (mb_strlen($q) < 2) {
    die();
}

// === БАЗОВЫЙ ХОСТ/СХЕМА ДЛЯ АБСОЛЮТНЫХ ССЫЛОК ===
$serverName = (string)Option::get('main', 'server_name', '');
if ($serverName === '' && defined('SITE_SERVER_NAME')) {
    $serverName = (string)SITE_SERVER_NAME;
}
if ($serverName === '') {
    $serverName = (string)($_SERVER['HTTP_HOST'] ?? '');
}
$scheme = 'https'; // форсим https, чтобы не ловить mixed content
$abs = static function (string $src) use ($serverName, $scheme): string {
    if ($src === '') return '';
    // уже абсолютный?
    if (preg_match('~^https?://~i', $src)) return $src;
    if (str_starts_with($src, '//')) return $scheme . ':' . $src;
    if ($serverName === '') return $src; // крайний случай — вернём как есть
    if (!str_starts_with($src, '/')) $src = '/' . ltrim($src, '/');
    return $scheme . '://' . $serverName . $src;
};

// ID каталога
$catalogIBlockId = CMaxCache::$arIBlocks[SITE_ID]["aspro_max_catalog"]["aspro_max_catalog"][0];

// Фильтр: поиск по имени и артикулу
$arFilter = [
    "IBLOCK_ID" => $catalogIBlockId,
    "ACTIVE"    => "Y",
    [
        "LOGIC" => "OR",
        ["?NAME" => $q],
        ["?PROPERTY_CML2_ARTICLE" => $q],
    ],
];

$res = CIBlockElement::GetList(
    ["NAME" => "ASC"],
    $arFilter,
    false,
    ["nTopCount" => 10],
    [
        "ID","IBLOCK_ID","NAME","CODE",
        "IBLOCK_SECTION_ID",
        "DETAIL_PAGE_URL",
        "PREVIEW_PICTURE","DETAIL_PICTURE",
        "PROPERTY_CML2_ARTICLE",
    ]
);

// Функция расчёта SECTION_CODE_PATH
$buildSectionCodePath = static function (int $iblockId, int $sectionId): string {
    if ($sectionId <= 0) return '';
    $path = '';
    $nav = CIBlockSection::GetNavChain($iblockId, $sectionId, ["ID","CODE"]);
    while ($s = $nav->GetNext()) {
        if ($s['CODE'] === '' || $s['CODE'] === null) continue;
        $path .= ($path ? '/' : '') . $s['CODE'];
    }
    return $path;
};

while ($ar = $res->GetNext()) {
    // ===== Детальная ссылка (делаем абсолютной) =====
    $tpl = CIBlock::GetArrayByID($ar["IBLOCK_ID"], "DETAIL_PAGE_URL");
    $f = $ar;
    $f['ELEMENT_ID'] = $ar['ID'];
    if (!isset($f['SECTION_ID'])) {
        $f['SECTION_ID'] = (int)$ar['IBLOCK_SECTION_ID'];
    }
    if (strpos($tpl, '#SECTION_CODE_PATH#') !== false) {
        $f['SECTION_CODE_PATH'] = $buildSectionCodePath((int)$ar['IBLOCK_ID'], (int)$ar['IBLOCK_SECTION_ID']);
    }
    $link = CIBlock::ReplaceDetailUrl($tpl, $f, true, 'E');
    if (!$link) {
        $link = (string)$ar['DETAIL_PAGE_URL'];
    }
    $link = $abs($link);

    // ===== Картинка: ресайз + абсолютный URL =====
    $photo = '';
    $photoId = (int)($ar["DETAIL_PICTURE"] ?: $ar["PREVIEW_PICTURE"]);
    if ($photoId > 0) {
        $img = CFile::ResizeImageGet(
            $photoId,
            ['width' => 60, 'height' => 60],
            BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
            true
        );
        if (!empty($img['src'])) {
            $photo = $abs((string)$img['src']);
        }
    }

    // Фолбэк (если хочешь явный плейсхолдер):
    // if ($photo === '') $photo = $abs('/local/tools/kp_gen3/assets/img/no-photo.svg');

    $article = htmlspecialcharsbx($ar['PROPERTY_CML2_ARTICLE_VALUE']);

    // Цена (тип 2 — МИЦ)
    $priceData = CPrice::GetList([], [
        'PRODUCT_ID'       => $ar['ID'],
        'CATALOG_GROUP_ID' => 2
    ])->Fetch();

    $price = 0;
    $currency = 'RUB';
    if ($priceData) {
        $price    = (float)$priceData['PRICE'];
        $currency = $priceData['CURRENCY'] ?? 'RUB';
    }

    $priceFormatted = '';
    if ($price > 0) {
        $priceFormatted = number_format($price, 2, ',', ' ') . ' ' . ($currency === 'RUB' ? '₽' : $currency);
    }
    ?>
    <div class="live-item" style="display:flex; align-items:center; gap:12px; padding:10px 14px; border-bottom:1px solid #f0f0f0;">
        <!-- Фото -->
        <div style="width:60px; height:60px; flex-shrink:0; cursor:pointer; border-radius:6px; overflow:hidden; background:#f5f5f5; border:1px solid #e5e5e5; display:flex; align-items:center; justify-content:center; flex-direction:column;"
             onclick="window.open('<?=htmlspecialcharsbx($link)?>','_blank')"
             title="DETAIL_PICTURE: <?=$ar['DETAIL_PICTURE']?> | PREVIEW_PICTURE: <?=$ar['PREVIEW_PICTURE']?> | PHOTO: <?=$photo?>">
            <?php if ($photo): ?>
                <img src="<?=htmlspecialcharsbx($photo)?>"
                     alt="<?=htmlspecialcharsbx($ar['NAME'])?>"
                     style="width:100%; height:100%; object-fit:contain;"
                     referrerpolicy="no-referrer"
                     onerror="console.error('Image load error:', '<?=$photo?>'); this.style.display='none';">
            <?php else: ?>
                <span style="color:#999; font-size:9px; text-align:center; line-height:1.2;">нет фото</span>
            <?php endif; ?>
        </div>

        <!-- Название -->
        <div style="flex:1; cursor:pointer; min-width:0;" onclick="window.open('<?=htmlspecialcharsbx($link)?>','_blank')">
            <div style="font-weight:600; color:#333; font-size:14px; line-height:1.4;">
                <?=htmlspecialcharsbx($ar['NAME'])?>
            </div>
        </div>

        <!-- Артикул -->
        <div style="flex-shrink:0; padding:0 12px; min-width:110px;">
            <?php if (!empty($ar['PROPERTY_CML2_ARTICLE_VALUE'])): ?>
                <div style="font-size:13px; color:#666; white-space:nowrap;">
                    Арт. <?=$article?>
                </div>
            <?php else: ?>
                <div style="font-size:13px; color:#999;">—</div>
            <?php endif; ?>
        </div>

        <!-- Цена -->
        <div style="flex-shrink:0; padding:0 12px; text-align:right; min-width:130px;">
            <?php if ($priceFormatted): ?>
                <div style="font-size:16px; color:#722e85; font-weight:700; white-space:nowrap;">
                    <?=$priceFormatted?>
                </div>
            <?php else: ?>
                <div style="font-size:13px; color:#999;">—</div>
            <?php endif; ?>
        </div>

        <!-- Кнопка -->
        <button class="btn-add-product"
                data-article="<?=$article?>"
                style="padding:8px 16px; background:#722E85; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:13px; white-space:nowrap; font-weight:500; transition:all 0.2s; flex-shrink:0;">
            Добавить
        </button>
    </div>
    <?php
}
?>
