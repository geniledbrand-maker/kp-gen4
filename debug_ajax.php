<?php
/**
 * Отладка AJAX запросов
 */

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('DisableEventsCheck', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

// Подключаем модули
Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Подключаем конфигурацию
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

$debug = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'post_data' => $_POST,
    'json_data' => null,
    'sessid_check' => false,
    'auth_check' => false,
    'bitrix_sessid' => bitrix_sessid(),
];

// Проверяем JSON
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $debug['json_data'] = json_decode(file_get_contents('php://input'), true) ?: [];
}

// Проверяем авторизацию
try {
    checkAuth();
    $debug['auth_check'] = true;
} catch (Exception $e) {
    $debug['auth_error'] = $e->getMessage();
}

// Проверяем sessid
try {
    $debug['sessid_check'] = checkSessid();
} catch (Exception $e) {
    $debug['sessid_error'] = $e->getMessage();
}

echo json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
