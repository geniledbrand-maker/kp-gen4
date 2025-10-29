<?php
/**
 * Проверка авторизации и прав доступа
 */

use Bitrix\Main\Loader;

/**
 * Проверяет авторизацию пользователя
 */
function checkAuth(): void {
    global $USER;

    if (!$USER->IsAuthorized()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        LocalRedirect('/auth/?backurl=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Проверяет права доступа пользователя к инструменту
 */
function checkPermissions(): void {
    global $USER;

    $config = require __DIR__ . '/../config/permissions.php';
    $userGroups = $USER->GetUserGroupArray();

    if (!array_intersect($config['allowed_groups'], $userGroups)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'error' => 'ACCESS_DENIED'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(403);
        die('Доступ запрещён: недостаточно прав');
    }
}

/**
 * Получает список доступных типов цен для текущего пользователя
 */
function getAllowedPriceTypes(): array {
    global $USER;

    $config = require __DIR__ . '/../config/permissions.php';
    $priceConfig = require __DIR__ . '/../config/price_types.php';

    $userGroups = $USER->GetUserGroupArray();
    $allowed = [];

    foreach ($userGroups as $gid) {
        if (isset($config['group_price_access'][$gid])) {
            $allowed = array_merge($allowed, $config['group_price_access'][$gid]);
        }
    }

    $allowed = array_values(array_unique($allowed));

    // Формируем массив с ID и названиями
    $result = [];
    foreach ($allowed as $name) {
        if (isset($priceConfig['names_to_ids'][$name])) {
            $result[] = [
                'id' => $priceConfig['names_to_ids'][$name],
                'name' => $name
            ];
        }
    }

    return $result;
}

/**
 * Проверяет, может ли пользователь видеть указанный тип цены
 */
function userCanSeePriceType(int $priceTypeId): bool {
    $priceConfig = require __DIR__ . '/../config/price_types.php';

    // Находим название типа цены по ID
    $priceName = null;
    foreach ($priceConfig['names_to_ids'] as $name => $id) {
        if ($id === $priceTypeId) {
            $priceName = $name;
            break;
        }
    }

    if (!$priceName) {
        return false;
    }

    $allowedTypes = getAllowedPriceTypes();
    foreach ($allowedTypes as $type) {
        if ($type['id'] === $priceTypeId) {
            return true;
        }
    }

    return false;
}