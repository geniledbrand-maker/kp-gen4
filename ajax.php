<?php
/**
 * AJAX-обработчик для генератора КП
 * Основной endpoint для всех AJAX-запросов
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
Loader::includeModule('highloadblock');

// Подключаем конфигурацию
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Проверка авторизации
checkAuth();

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Получаем данные из JSON или POST
$input = [];
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$action = (string)($input['action'] ?? '');

if (empty($action)) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ACTION_REQUIRED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверка sessid
if (!checkSessid()) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'BAD_SESSID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подключаем обработчик
require_once __DIR__ . '/includes/ajax_handler.php';
