<?php
/**
 * Вспомогательные функции
 */

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

/**
 * Заполняет информацию о КП данными из профиля пользователя
 */
function fillKpInfoDefaults(array $kp): array {
    global $USER;

    $u = CUser::GetByID($USER->GetID())->Fetch() ?: [];
    $fio = trim(($u['NAME'] ?? '') . ' ' . ($u['LAST_NAME'] ?? ''));

    // Берём из формы, если пусто - из профиля
    if (empty($kp['manager'])) {
        $kp['manager'] = $fio ?: ($u['LOGIN'] ?? '');
    }
    if (empty($kp['email'])) {
        $kp['email'] = (string)($u['EMAIL'] ?? '');
    }
    if (empty($kp['position'])) {
        $kp['position'] = (string)($u['UF_POSITION'] ?? '');
    }
    if (empty($kp['phone'])) {
        $kp['phone'] = (string)(($u['WORK_PHONE'] ?? '') ?: ($u['PERSONAL_MOBILE'] ?? ''));
    }
    if (empty($kp['company'])) {
        $kp['company'] = (string)($u['WORK_COMPANY'] ?? '');
    }

    return $kp;
}

/**
 * Получает все значения свойства элемента
 */
function getAllValuesByPropId(int $elementId, int $propId): array {
    $vals = [];
    $res = CIBlockElement::GetProperty(IBLOCK_ID, $elementId, ['sort' => 'asc'], ['ID' => $propId, 'CHECK_PERMISSIONS' => 'Y', 'MIN_PERMISSION' => 'R']);

    while ($p = $res->Fetch()) {
        $v = '';
        if (!empty($p['VALUE_ENUM'])) {
            $v = (string)$p['VALUE_ENUM'];
        } else {
            $v = is_array($p['VALUE']) ? trim((string)($p['VALUE']['TEXT'] ?? '')) : (string)$p['VALUE'];
        }
        $v = trim($v);
        if ($v !== '') {
            $vals[] = $v;
        }
    }

    return $vals;
}

/**
 * Объединяет значения нескольких свойств в строку
 */
function getMergedPropsByIdList(int $elementId, array $propIdList): string {
    $seen = [];
    $out = [];

    foreach ($propIdList as $pid) {
        $pid = (int)$pid;
        if ($pid <= 0) continue;

        foreach (getAllValuesByPropId($elementId, $pid) as $v) {
            $key = mb_strtolower(trim($v));
            if ($key === '') continue;
            if (!isset($seen[$key])) {
                $seen[$key] = 1;
                $out[] = $v;
            }
        }
    }

    return implode(', ', $out);
}

/**
 * Генерирует абсолютный URL для изображения
 */
function getAbsoluteImageUrl(?int $fileId): string {
    if (!$fileId) {
        return '';
    }

    $path = CFile::GetPath($fileId);
    if (!$path) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . $path;
}

/**
 * Генерирует абсолютный URL для детальной страницы товара
 */
function getAbsoluteProductUrl(string $detailPageUrl): string {
    if (empty($detailPageUrl)) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . $detailPageUrl;
}

/**
 * Проверяет sessid для AJAX-запросов
 */
function checkSessid(): bool {
    return check_bitrix_sessid();
}