<?php
/**
 * AJAX-обработчик КП Geniled
 * Безопасный вход + только POST
 */

// Если файл открыли напрямую, подключим пролог и модули Bitrix
if (!defined('B_PROLOG_INCLUDED')) {
    require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';
    \Bitrix\Main\Loader::includeModule('iblock');
    \Bitrix\Main\Loader::includeModule('catalog');
    // Дополнительные модули, если нужны для новых сервисов
    \Bitrix\Main\Loader::includeModule('highloadblock'); // ✅ Добавлено для SavedKpService
}

// Разрешаем только POST и только при наличии action
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подключения
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/ProductService.php';
require_once __DIR__ . '/../classes/ProfileManager.php';
require_once __DIR__ . '/../classes/SavedKpService.php'; // ✅ Добавлен сервис сохраненных КП

// Авторизация
checkAuth();

// ✅ ИСПРАВЛЕНО: Получаем action ДО проверки sessid
$action = (string)($_POST['action'] ?? '');

// ✅ ИСПРАВЛЕНО: Проверка sessid только для операций изменения данных
$readOnlyActions = ['get_bitrix_profile', 'get_user_kps', 'load_kp', 'get_product', 'get_available_price_types', 'get_user_profiles', 'get_profile_data', 'debug_user_fields'];
$requiresSessid = !in_array($action, $readOnlyActions);

if ($requiresSessid && !checkSessid()) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'BAD_SESSID'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

switch ($action) {

    // ===================== ТОВАРЫ =====================
    case 'get_product':
        $article = htmlspecialchars(trim((string)($_POST['article'] ?? '')), ENT_QUOTES, 'UTF-8');
        $priceTypeId = isset($_POST['price_type']) ? (int)$_POST['price_type'] : DEFAULT_PRICE_TYPE_ID;
        echo json_encode(ProductService::getByArticle($article, $priceTypeId), JSON_UNESCAPED_UNICODE);
        break;

    case 'change_price_type':
        $productId  = (int)($_POST['product_id'] ?? 0);
        $priceTypeId= (int)($_POST['price_type'] ?? 0);
        if (!$productId || !$priceTypeId) {
            echo json_encode(['success' => false, 'error' => 'Неверные параметры'], JSON_UNESCAPED_UNICODE);
            break;
        }
        $res = CPrice::GetList([], ['PRODUCT_ID'=>$productId,'CATALOG_GROUP_ID'=>$priceTypeId]);
        if ($price = $res->Fetch()) {
            echo json_encode(['success'=>true,'price'=>(float)$price['PRICE']], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success'=>false,'error'=>'Цена не найдена','not_found'=>true,'price_type_id'=>$priceTypeId
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'get_available_price_types':
        $allowedPriceOptions = getAllowedPriceTypes(); // [['id'=>..., 'name'=>...], ...]
        $allowedIds = array_map('intval', array_column($allowedPriceOptions, 'id'));
        $allowedById = [];
        foreach ($allowedPriceOptions as $opt) {
            if (!empty($opt['id']) && !empty($opt['name'])) {
                $allowedById[(int)$opt['id']] = ['id'=>(int)$opt['id'],'name'=>(string)$opt['name']];
            }
        }

        $productIds = json_decode((string)($_POST['product_ids'] ?? '[]'), true);
        $mode = (string)($_POST['mode'] ?? 'all'); $mode = ($mode === 'any') ? 'any' : 'all';

        if (!is_array($productIds) || empty($allowedIds)) {
            echo json_encode(['success'=>true,'types'=>array_values($allowedById)], JSON_UNESCAPED_UNICODE);
            break;
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (empty($productIds)) {
            echo json_encode(['success'=>true,'types'=>array_values($allowedById)], JSON_UNESCAPED_UNICODE);
            break;
        }

        $availableByProduct = [];
        foreach ($productIds as $pid) {
            $types = [];
            $dbRes = CPrice::GetList([], ['PRODUCT_ID'=>$pid,'CATALOG_GROUP_ID'=>$allowedIds], false, false, ['CATALOG_GROUP_ID']);
            while ($row = $dbRes->Fetch()) {
                $gid = (int)$row['CATALOG_GROUP_ID'];
                if (isset($allowedById[$gid])) $types[$gid] = true;
            }
            $availableByProduct[$pid] = array_keys($types);
        }

        $resultIds = [];
        if ($mode === 'all') {
            $first = true;
            foreach ($availableByProduct as $ids) {
                $ids = array_map('intval', $ids);
                if ($first) { $resultIds = $ids; $first = false; }
                else { $resultIds = array_values(array_intersect($resultIds, $ids)); }
                if (empty($resultIds)) break;
            }
        } else {
            $set = [];
            foreach ($availableByProduct as $ids) foreach ($ids as $gid) $set[(int)$gid] = true;
            $resultIds = array_keys($set);
        }

        $result = [];
        foreach ($resultIds as $gid) if (isset($allowedById[$gid])) $result[] = $allowedById[$gid];
        echo json_encode(['success'=>true,'types'=>$result], JSON_UNESCAPED_UNICODE);
        break;

    // ===================== ПРОФИЛИ =====================
    // Список профилей (нормализованный для фронта)
    case 'get_user_profiles': {
        global $USER; $userId = (int)$USER->GetID();
        if ($userId <= 0) {
            echo json_encode(['success'=>false,'error'=>'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE);
            break;
        }

        ProfileManager::createTable();
        $rows = ProfileManager::getUserProfiles($userId) ?: [];

        // НОРМАЛИЗОВАННЫЕ ключи для фронта
        $profiles = [];
        foreach ($rows as $r) {
            $profiles[] = [
                'id'               => (int)($r['ID'] ?? 0),
                'profile_name'     => (string)($r['PROFILE_NAME'] ?? ''),
                'is_default'       => (($r['IS_DEFAULT'] ?? 'N') === 'Y') ? 1 : 0,
                // добавим удобные поля, чтобы ЛК сразу показывал таблицу
                'manager_name'     => (string)($r['MANAGER_NAME'] ?? ''),
                'manager_email'    => (string)($r['MANAGER_EMAIL'] ?? ''),
                'manager_position' => (string)($r['MANAGER_POSITION'] ?? ''),
                'manager_phone'    => (string)($r['MANAGER_PHONE'] ?? ''),
                'manager_company'  => (string)($r['MANAGER_COMPANY'] ?? ''),
            ];
        }

        echo json_encode(['success'=>true,'profiles'=>$profiles], JSON_UNESCAPED_UNICODE);
        break;
    }


    // Получить данные конкретного профиля (для автозаполнения полей генератора)
    case 'get_profile_data': {
        global $USER;
        $userId    = (int)$USER->GetID();
        $profileId = (int)($_POST['profile_id'] ?? 0);

        if ($userId <= 0 || $profileId <= 0) {
            echo json_encode(['success'=>false,'error'=>'BAD_PARAMS'], JSON_UNESCAPED_UNICODE); break;
        }

        ProfileManager::createTable();

        $row = null;
        if (method_exists('ProfileManager','getProfileById')) {
            $row = ProfileManager::getProfileById($profileId, $userId);
        } else {
            $all = ProfileManager::getUserProfiles($userId) ?: [];
            foreach ($all as $r) {
                $rid = (int)($r['ID'] ?? $r['id'] ?? 0);
                if ($rid === $profileId) { $row = $r; break; }
            }
        }

        if (!$row) { echo json_encode(['success'=>false,'error'=>'PROFILE_NOT_FOUND'], JSON_UNESCAPED_UNICODE); break; }

        $data = [
            'manager_name'     => (string)($row['MANAGER_NAME']     ?? $row['manager_name']     ?? ''),
            'manager_position' => (string)($row['MANAGER_POSITION'] ?? $row['manager_position'] ?? ''),
            'manager_phone'    => (string)($row['MANAGER_PHONE']    ?? $row['manager_phone']    ?? ''),
            'manager_email'    => (string)($row['MANAGER_EMAIL']    ?? $row['manager_email']    ?? ''),
            'manager_company'  => (string)($row['MANAGER_COMPANY']  ?? $row['manager_company']  ?? ''),
        ];

        echo json_encode(['success'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
        break;
    }

    // Создать новый профиль
    case 'save_profile': {
        global $USER; $userId = (int)$USER->GetID();
        if ($userId <= 0) { echo json_encode(['success'=>false,'error'=>'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE); break; }
        $data = [
            'profile_name'=>(string)($_POST['profile_name']??''),
            'manager_name'=>(string)($_POST['manager_name']??''),
            'manager_position'=>(string)($_POST['manager_position']??''),
            'manager_phone'=>(string)($_POST['manager_phone']??''),
            'manager_email'=>(string)($_POST['manager_email']??''),
            'manager_company'=>(string)($_POST['manager_company']??''),
            'set_as_default'=>(int)($_POST['set_as_default']??0),
        ];
        $hasAny=false; foreach(['profile_name','manager_name','manager_position','manager_phone','manager_email','manager_company'] as $k){ if($data[$k] !== ''){$hasAny=true;break;}}
        if(!$hasAny){ echo json_encode(['success'=>false,'error'=>'EMPTY_DATA'], JSON_UNESCAPED_UNICODE); break; }
        ProfileManager::createTable();
        try {
            $profileId = ProfileManager::saveProfile($data, $userId);
            echo json_encode(['success'=>true,'profile_id'=>(int)$profileId], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    // Обновить существующий профиль
    case 'update_profile': {
        global $USER; $userId = (int)$USER->GetID();
        $profileId = (int)($_POST['profile_id'] ?? 0);
        if ($userId <= 0 || $profileId <= 0) { echo json_encode(['success'=>false,'error'=>'BAD_PARAMS'], JSON_UNESCAPED_UNICODE); break; }
        $data = [
            'profile_name'=>(string)($_POST['profile_name']??''),
            'manager_name'=>(string)($_POST['manager_name']??''),
            'manager_position'=>(string)($_POST['manager_position']??''),
            'manager_phone'=>(string)($_POST['manager_phone']??''),
            'manager_email'=>(string)($_POST['manager_email']??''),
            'manager_company'=>(string)($_POST['manager_company']??''),
            'set_as_default'=>(int)($_POST['set_as_default']??0),
        ];
        ProfileManager::createTable();
        try {
            if (method_exists('ProfileManager','updateProfile')) {
                $ok = ProfileManager::updateProfile($profileId, $data, $userId);
            } else {
                $ok = (bool)ProfileManager::saveProfile(array_merge($data,['profile_id'=>$profileId]), $userId);
            }
            echo json_encode(['success'=>(bool)$ok,'profile_id'=>$profileId], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    case 'set_default_profile': {
        global $USER; $userId = (int)$USER->GetID();
        $profileId = (int)($_POST['profile_id'] ?? 0);
        if ($userId <= 0 || $profileId <= 0) { echo json_encode(['success'=>false,'error'=>'BAD_PARAMS'], JSON_UNESCAPED_UNICODE); break; }
        try {
            $ok = ProfileManager::setDefaultProfile($profileId, $userId);
            echo json_encode(['success'=>(bool)$ok], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    case 'delete_profile': {
        global $USER; $userId = (int)$USER->GetID();
        $profileId = (int)($_POST['profile_id'] ?? 0);
        if ($userId <= 0 || $profileId <= 0) { echo json_encode(['success'=>false,'error'=>'BAD_PARAMS'], JSON_UNESCAPED_UNICODE); break; }
        try {
            $ok = ProfileManager::deleteProfile($profileId, $userId);
            echo json_encode(['success'=>(bool)$ok], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    // ===================== СОХРАНЕННЫЕ КП =====================
    case 'save_kp': {
        global $USER; $userId = (int)$USER->GetID();
        if ($userId <= 0) { echo json_encode(['success'=>false,'error'=>'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE); break; }

        $title = htmlspecialchars(trim((string)($_POST['title'] ?? 'КП')), ENT_QUOTES, 'UTF-8');
        $kpData = json_decode((string)($_POST['kp_data'] ?? '{}'), true);
        $products = json_decode((string)($_POST['products'] ?? '[]'), true);

        if (!is_array($products) || empty($products)) {
            echo json_encode(['success'=>false,'error'=>'Нет товаров в КП'], JSON_UNESCAPED_UNICODE); break;
        }

        try {
            // Используем SavedKpService для сохранения
            $result = \Geniled\KpGen\SavedKpService::save($userId, $title, $kpData, $products);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log("save_kp error: " . $e->getMessage());
            echo json_encode(['success'=>false,'error'=>'Ошибка сохранения: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    case 'get_user_kps': {
        global $USER; $userId = (int)$USER->GetID();
        if ($userId <= 0) { echo json_encode(['success'=>false,'error'=>'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE); break; }

        try {
            // Используем SavedKpService для получения списка
            $kps = \Geniled\KpGen\SavedKpService::getUserKps($userId);
            // Нормализуем структуру для фронта, чтобы быть уверенными в ключах
            $normalizedKps = [];
            foreach ($kps as $kp) {
                $normalizedKps[] = [
                    'id'            => (int)($kp['id'] ?? 0),
                    'title'         => (string)($kp['title'] ?? 'Без названия'),
                    'date_create'   => (string)($kp['dateCreate'] ?? ''), // UF_DATE_CREATE
                    'date_modify'   => (string)($kp['dateModify'] ?? ''), // UF_DATE_MODIFY
                ];
            }
            echo json_encode(['success'=>true,'kps'=>$normalizedKps], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log("get_user_kps error: " . $e->getMessage());
            echo json_encode(['success'=>false,'error'=>'Ошибка получения списка КП'], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    case 'load_kp': {
        global $USER; $userId = (int)$USER->GetID();
        $kpId = (int)($_POST['kp_id'] ?? 0);

        if ($userId <= 0 || $kpId <= 0) { echo json_encode(['success'=>false,'error'=>'BAD_PARAMS'], JSON_UNESCAPED_UNICODE); break; }

        try {
            // Используем SavedKpService для загрузки
            $kpData = \Geniled\KpGen\SavedKpService::get($kpId, $userId);

            if (!$kpData) {
                echo json_encode(['success'=>false,'error'=>'КП не найдено или нет прав доступа'], JSON_UNESCAPED_UNICODE); break;
            }

            // ✅ ИСПРАВЛЕНО: Правильная структура ответа
            $kpDataFromDB = $kpData['kpData'] ?? [];
            $kpInfo = $kpDataFromDB['kp_info'] ?? $kpDataFromDB; // Пробуем оба варианта

            $response = [
                'success'   => true,
                'products'  => $kpData['products'] ?? [],
                'kp_info'   => $kpInfo,
                'title'     => $kpData['title'] ?? '',
            ];

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log("load_kp error: " . $e->getMessage());
            echo json_encode(['success'=>false,'error'=>'Ошибка загрузки КП'], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    case 'delete_kp': {
        global $USER; $userId = (int)$USER->GetID();
        $kpId = (int)($_POST['kp_id'] ?? 0);

        if ($userId <= 0 || $kpId <= 0) { echo json_encode(['success'=>false,'error'=>'BAD_PARAMS'], JSON_UNESCAPED_UNICODE); break; }

        try {
            // Используем SavedKpService для удаления
            $result = \Geniled\KpGen\SavedKpService::delete($kpId, $userId);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log("delete_kp error: " . $e->getMessage());
            echo json_encode(['success'=>false,'error'=>'Ошибка удаления КП: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

// ===================== БИТРИКС ПРОФИЛЬ =====================
    case 'get_bitrix_profile': {
        global $USER;
        $userId = (int)$USER->GetID();

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        if (!$arUser) {
            echo json_encode(['success' => false, 'error' => 'Пользователь не найден'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $fullName = trim(implode(' ', array_filter([
            $arUser['LAST_NAME'] ?? '',
            $arUser['NAME'] ?? '',
            $arUser['SECOND_NAME'] ?? ''
        ])));

        $phone = $arUser['PERSONAL_PHONE'] ?? $arUser['WORK_PHONE'] ?? $arUser['PERSONAL_MOBILE'] ?? '';

        $data = [
            'manager_name'     => $fullName ?: ($arUser['LOGIN'] ?? ''),
            'manager_position' => $arUser['WORK_POSITION'] ?? '',
            'manager_phone'    => $phone,
            'manager_email'    => $arUser['EMAIL'] ?? '',
            'manager_company'  => $arUser['WORK_COMPANY'] ?? ''
        ];

        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'debug_user_fields': {
        global $USER;
        $userId = (int)$USER->GetID();

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $rsUser = CUser::GetByID($userId);
        $arUser = $rsUser->Fetch();

        echo json_encode(['success' => true, 'fields' => $arUser ?: []], JSON_UNESCAPED_UNICODE);
        break;
    }

    case 'update_bitrix_profile': {
        global $USER;
        $userId = (int)$USER->GetID();

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'error' => 'AUTH_REQUIRED'], JSON_UNESCAPED_UNICODE);
            break;
        }

        $user = new CUser;
        $fields = [];

        if (!empty($_POST['manager_position'])) {
            $fields['WORK_POSITION'] = trim($_POST['manager_position']);
        }
        if (!empty($_POST['manager_phone'])) {
            $fields['PERSONAL_PHONE'] = trim($_POST['manager_phone']);
        }
        if (!empty($_POST['manager_company'])) {
            $fields['WORK_COMPANY'] = trim($_POST['manager_company']);
        }

        if (empty($fields)) {
            echo json_encode(['success' => false, 'error' => 'Нет данных для обновления'], JSON_UNESCAPED_UNICODE);
            break;
        }

        if ($user->Update($userId, $fields)) {
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } else {
            $error = $user->LAST_ERROR ?: 'Ошибка обновления профиля';
            echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
        }
        break;
    }

    // ===================== ЭКСПОРТ =====================
    case 'export_excel':
        // Подключаем ExcelGenerator ТОЛЬКО здесь
        require_once $_SERVER['DOCUMENT_ROOT'].'/local/tools/kp_gen4/classes/ExcelGenerator.php';
        $productsData = json_decode((string)($_POST['products'] ?? '[]'), true);
        $kpInfo       = json_decode((string)($_POST['kp_info'] ?? '{}'), true);
        if (!is_array($productsData) || !is_array($kpInfo)) {
            echo json_encode(['success'=>false,'error'=>'Некорректные данные'], JSON_UNESCAPED_UNICODE); break;
        }
        try {
            $generator = new ExcelGenerator();
            $generator->generate($productsData, $kpInfo);
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'export_pdf':
        require_once __DIR__ . '/../classes/PdfGenerator.php';
        $productsData = json_decode((string)($_POST['products'] ?? '[]'), true);
        $kpInfo       = json_decode((string)($_POST['kp_info'] ?? '{}'), true);
        if (!is_array($productsData) || !is_array($kpInfo)) {
            echo json_encode(['success'=>false,'error'=>'Некорректные данные'], JSON_UNESCAPED_UNICODE); break;
        }
        try {
            $generator = new PdfGenerator();
            $generator->generate($productsData, $kpInfo);
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        break;

    // ===================== DEFAULT =====================
    default:
        echo json_encode(['success'=>false,'error'=>'Неизвестное действие'], JSON_UNESCAPED_UNICODE);
}

exit;