<?php
/**
 * Тестовый скрипт для ProfileManager
 *
 * Положить в /local/tools/kp_gen4/test_profile_manager.php
 * Открыть в браузере: https://ваш-сайт.ru/local/tools/kp_gen4/test_profile_manager.php
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Loader;

Loader::includeModule('main');

global $USER;

// Проверка авторизации
if (!$USER->IsAuthorized()) {
    die('Необходима авторизация');
}

require_once __DIR__ . '/classes/ProfileManager.php';

$userId = $USER->GetID();

echo '<h1>Тест ProfileManager</h1>';
echo '<p>Пользователь ID: ' . $userId . '</p>';
echo '<hr>';

// ============================================
// ТЕСТ 1: Создание таблицы
// ============================================
echo '<h2>ТЕСТ 1: Создание таблицы</h2>';
if (ProfileManager::createTable()) {
    echo '✅ Таблица geniled_kp_profiles создана (или уже существует)<br>';
} else {
    echo '❌ Ошибка создания таблицы<br>';
}
echo '<hr>';

// ============================================
// ТЕСТ 2: Сохранение нового профиля
// ============================================
echo '<h2>ТЕСТ 2: Сохранение нового профиля</h2>';
$newProfileId = ProfileManager::saveProfile([
    'profile_name'     => 'Тестовый профиль ' . time(),
    'manager_name'     => 'Тестов Тест Тестович',
    'manager_position' => 'Тестовый менеджер',
    'manager_phone'    => '+7 (999) 123-45-67',
    'manager_email'    => 'test@test.ru',
    'manager_company'  => 'ООО Тест',
    'set_as_default'   => true
], $userId);

if ($newProfileId > 0) {
    echo '✅ Профиль создан с ID: ' . $newProfileId . '<br>';
} else {
    echo '❌ Ошибка создания профиля<br>';
}
echo '<hr>';

// ============================================
// ТЕСТ 3: Получение всех профилей
// ============================================
echo '<h2>ТЕСТ 3: Получение всех профилей</h2>';
$profiles = ProfileManager::getUserProfiles($userId);
echo '<p>Найдено профилей: ' . count($profiles) . '</p>';
if (!empty($profiles)) {
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>ID</th><th>Название</th><th>ФИО</th><th>Должность</th><th>Телефон</th><th>Email</th><th>По умолчанию</th></tr>';
    foreach ($profiles as $p) {
        echo '<tr>';
        echo '<td>' . $p['id'] . '</td>';
        echo '<td>' . htmlspecialchars($p['profile_name']) . '</td>';
        echo '<td>' . htmlspecialchars($p['manager_name']) . '</td>';
        echo '<td>' . htmlspecialchars($p['manager_position']) . '</td>';
        echo '<td>' . htmlspecialchars($p['manager_phone']) . '</td>';
        echo '<td>' . htmlspecialchars($p['manager_email']) . '</td>';
        echo '<td>' . ($p['is_default'] ? '⭐ Да' : 'Нет') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p>Профилей нет</p>';
}
echo '<hr>';

// ============================================
// ТЕСТ 4: Получение профиля по умолчанию
// ============================================
echo '<h2>ТЕСТ 4: Получение профиля по умолчанию</h2>';
$defaultProfile = ProfileManager::getDefaultProfile($userId);
if ($defaultProfile) {
    echo '✅ Профиль по умолчанию найден:<br>';
    echo '<pre>' . print_r($defaultProfile, true) . '</pre>';
} else {
    echo '❌ Профиль по умолчанию не найден<br>';
}
echo '<hr>';

// ============================================
// ТЕСТ 5: Получение конкретного профиля
// ============================================
if ($newProfileId > 0) {
    echo '<h2>ТЕСТ 5: Получение конкретного профиля (ID=' . $newProfileId . ')</h2>';
    $profile = ProfileManager::getProfile($newProfileId, $userId);
    if ($profile) {
        echo '✅ Профиль найден:<br>';
        echo '<pre>' . print_r($profile, true) . '</pre>';
    } else {
        echo '❌ Профиль не найден<br>';
    }
    echo '<hr>';
}

// ============================================
// ТЕСТ 6: Обновление профиля
// ============================================
if ($newProfileId > 0) {
    echo '<h2>ТЕСТ 6: Обновление профиля (ID=' . $newProfileId . ')</h2>';
    $updated = ProfileManager::updateProfile($newProfileId, [
        'manager_phone' => '+7 (999) 999-99-99',
        'manager_email' => 'updated@test.ru'
    ], $userId);

    if ($updated) {
        echo '✅ Профиль обновлён<br>';
        $profile = ProfileManager::getProfile($newProfileId, $userId);
        echo 'Новый телефон: ' . $profile['manager_phone'] . '<br>';
        echo 'Новый email: ' . $profile['manager_email'] . '<br>';
    } else {
        echo '❌ Ошибка обновления профиля<br>';
    }
    echo '<hr>';
}

// ============================================
// ТЕСТ 7: Установка другого профиля по умолчанию
// ============================================
if (count($profiles) > 1) {
    // Берём профиль, который НЕ по умолчанию
    $anotherProfile = null;
    foreach ($profiles as $p) {
        if (!$p['is_default']) {
            $anotherProfile = $p;
            break;
        }
    }

    if ($anotherProfile) {
        echo '<h2>ТЕСТ 7: Установка профиля ID=' . $anotherProfile['id'] . ' по умолчанию</h2>';
        $set = ProfileManager::setDefaultProfile($anotherProfile['id'], $userId);

        if ($set) {
            echo '✅ Профиль установлен по умолчанию<br>';
            $newDefault = ProfileManager::getDefaultProfile($userId);
            echo 'Новый профиль по умолчанию: ' . $newDefault['profile_name'] . '<br>';
        } else {
            echo '❌ Ошибка установки профиля по умолчанию<br>';
        }
        echo '<hr>';
    }
}

// ============================================
// ТЕСТ 8: Удаление профиля
// ============================================
if ($newProfileId > 0) {
    echo '<h2>ТЕСТ 8: Удаление тестового профиля (ID=' . $newProfileId . ')</h2>';
    echo '<p><strong>⚠️ Раскомментируйте код ниже, чтобы удалить тестовый профиль</strong></p>';

    /*
    $deleted = ProfileManager::deleteProfile($newProfileId, $userId);
    if ($deleted) {
        echo '✅ Профиль удалён<br>';
    } else {
        echo '❌ Ошибка удаления профиля<br>';
    }
    */

    echo '<hr>';
}

// ============================================
// ИТОГИ
// ============================================
echo '<h2>✅ Все тесты завершены</h2>';
echo '<p><a href="/local/tools/kp_gen4/">← Вернуться в генератор КП</a></p>';

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");