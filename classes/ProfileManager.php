<?php
/**
 * Менеджер профилей пользователей для генератора КП
 *
 * Управляет профилями менеджеров (ФИО, должность, контакты)
 * для быстрого переключения между разными отделами/ролями
 */

use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;

class ProfileManager
{
    /**
     * Название таблицы профилей
     */
    private const TABLE_NAME = 'geniled_kp_profiles';

    /**
     * Получить все профили пользователя
     *
     * @param int $userId ID пользователя из b_user
     * @return array Массив профилей [{id, profile_name, manager_name, ...}, ...]
     */
    public static function getUserProfiles(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        $sql = "
            SELECT 
                id,
                user_id,
                profile_name,
                manager_name,
                manager_position,
                manager_phone,
                manager_email,
                manager_company,
                is_default,
                date_create,
                date_update
            FROM {$tableName}
            WHERE user_id = " . (int)$userId . "
            ORDER BY is_default DESC, date_update DESC
        ";

        try {
            $result = $connection->query($sql);
            $profiles = [];

            while ($row = $result->fetch()) {
                $profiles[] = [
                    'id'               => (int)$row['id'],
                    'user_id'          => (int)$row['user_id'],
                    'profile_name'     => (string)$row['profile_name'],
                    'manager_name'     => (string)$row['manager_name'],
                    'manager_position' => (string)$row['manager_position'],
                    'manager_phone'    => (string)$row['manager_phone'],
                    'manager_email'    => (string)$row['manager_email'],
                    'manager_company'  => (string)$row['manager_company'],
                    'is_default'       => $row['is_default'] === 'Y',
                    'date_create'      => $row['date_create'],
                    'date_update'      => $row['date_update'],
                ];
            }

            return $profiles;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::getUserProfiles error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить профиль по умолчанию для пользователя
     *
     * @param int $userId ID пользователя
     * @return array|null Данные профиля или null
     */
    public static function getDefaultProfile(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        $sql = "
            SELECT 
                id,
                user_id,
                profile_name,
                manager_name,
                manager_position,
                manager_phone,
                manager_email,
                manager_company,
                is_default,
                date_create,
                date_update
            FROM {$tableName}
            WHERE user_id = " . (int)$userId . "
              AND is_default = 'Y'
            LIMIT 1
        ";

        try {
            $result = $connection->query($sql);

            if ($row = $result->fetch()) {
                return [
                    'id'               => (int)$row['id'],
                    'user_id'          => (int)$row['user_id'],
                    'profile_name'     => (string)$row['profile_name'],
                    'manager_name'     => (string)$row['manager_name'],
                    'manager_position' => (string)$row['manager_position'],
                    'manager_phone'    => (string)$row['manager_phone'],
                    'manager_email'    => (string)$row['manager_email'],
                    'manager_company'  => (string)$row['manager_company'],
                    'is_default'       => true,
                    'date_create'      => $row['date_create'],
                    'date_update'      => $row['date_update'],
                ];
            }

            return null;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::getDefaultProfile error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Получить конкретный профиль по ID
     *
     * @param int $profileId ID профиля
     * @param int $userId ID пользователя (для проверки прав доступа)
     * @return array|null Данные профиля или null
     */
    public static function getProfile(int $profileId, int $userId): ?array
    {
        if ($profileId <= 0 || $userId <= 0) {
            return null;
        }

        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        $sql = "
            SELECT 
                id,
                user_id,
                profile_name,
                manager_name,
                manager_position,
                manager_phone,
                manager_email,
                manager_company,
                is_default,
                date_create,
                date_update
            FROM {$tableName}
            WHERE id = " . (int)$profileId . "
              AND user_id = " . (int)$userId . "
            LIMIT 1
        ";

        try {
            $result = $connection->query($sql);

            if ($row = $result->fetch()) {
                return [
                    'id'               => (int)$row['id'],
                    'user_id'          => (int)$row['user_id'],
                    'profile_name'     => (string)$row['profile_name'],
                    'manager_name'     => (string)$row['manager_name'],
                    'manager_position' => (string)$row['manager_position'],
                    'manager_phone'    => (string)$row['manager_phone'],
                    'manager_email'    => (string)$row['manager_email'],
                    'manager_company'  => (string)$row['manager_company'],
                    'is_default'       => $row['is_default'] === 'Y',
                    'date_create'      => $row['date_create'],
                    'date_update'      => $row['date_update'],
                ];
            }

            return null;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::getProfile error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Сохранить новый профиль
     *
     * @param array $data Данные профиля (profile_name, manager_name, ...)
     * @param int $userId ID пользователя-владельца
     * @return int ID созданного профиля или 0 при ошибке
     */
    public static function saveProfile(array $data, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        // Валидация обязательных полей
        $profileName = trim((string)($data['profile_name'] ?? ''));
        if (empty($profileName)) {
            return 0;
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->forSql(self::TABLE_NAME);

        // Подготовка данных
        $managerName     = $sqlHelper->forSql(trim((string)($data['manager_name'] ?? '')));
        $managerPosition = $sqlHelper->forSql(trim((string)($data['manager_position'] ?? '')));
        $managerPhone    = $sqlHelper->forSql(trim((string)($data['manager_phone'] ?? '')));
        $managerEmail    = $sqlHelper->forSql(trim((string)($data['manager_email'] ?? '')));
        $managerCompany  = $sqlHelper->forSql(trim((string)($data['manager_company'] ?? '')));
        $setAsDefault    = !empty($data['set_as_default']);

        // Если профиль должен стать по умолчанию - сбрасываем флаг у других
        if ($setAsDefault) {
            self::clearDefaultFlag($userId);
        }

        $isDefaultValue = $setAsDefault ? 'Y' : 'N';

        $sql = "
            INSERT INTO {$tableName} (
                user_id,
                profile_name,
                manager_name,
                manager_position,
                manager_phone,
                manager_email,
                manager_company,
                is_default,
                date_create,
                date_update
            ) VALUES (
                " . (int)$userId . ",
                '" . $sqlHelper->forSql($profileName) . "',
                '" . $managerName . "',
                '" . $managerPosition . "',
                '" . $managerPhone . "',
                '" . $managerEmail . "',
                '" . $managerCompany . "',
                '" . $isDefaultValue . "',
                NOW(),
                NOW()
            )
        ";

        try {
            $connection->query($sql);
            return (int)$connection->getInsertedId();
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::saveProfile error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Обновить существующий профиль
     *
     * @param int $profileId ID профиля
     * @param array $data Новые данные
     * @param int $userId ID пользователя (для проверки прав)
     * @return bool Успех операции
     */
    public static function updateProfile(int $profileId, array $data, int $userId): bool
    {
        if ($profileId <= 0 || $userId <= 0) {
            return false;
        }

        // Проверяем, что профиль принадлежит пользователю
        $existing = self::getProfile($profileId, $userId);
        if (!$existing) {
            return false;
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->forSql(self::TABLE_NAME);

        $updates = [];

        // Обновляем только переданные поля
        if (isset($data['profile_name'])) {
            $profileName = trim((string)$data['profile_name']);
            if (!empty($profileName)) {
                $updates[] = "profile_name = '" . $sqlHelper->forSql($profileName) . "'";
            }
        }

        if (isset($data['manager_name'])) {
            $updates[] = "manager_name = '" . $sqlHelper->forSql(trim((string)$data['manager_name'])) . "'";
        }

        if (isset($data['manager_position'])) {
            $updates[] = "manager_position = '" . $sqlHelper->forSql(trim((string)$data['manager_position'])) . "'";
        }

        if (isset($data['manager_phone'])) {
            $updates[] = "manager_phone = '" . $sqlHelper->forSql(trim((string)$data['manager_phone'])) . "'";
        }

        if (isset($data['manager_email'])) {
            $updates[] = "manager_email = '" . $sqlHelper->forSql(trim((string)$data['manager_email'])) . "'";
        }

        if (isset($data['manager_company'])) {
            $updates[] = "manager_company = '" . $sqlHelper->forSql(trim((string)$data['manager_company'])) . "'";
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = "date_update = NOW()";

        $sql = "
            UPDATE {$tableName}
            SET " . implode(", ", $updates) . "
            WHERE id = " . (int)$profileId . "
              AND user_id = " . (int)$userId . "
        ";

        try {
            $connection->query($sql);
            return true;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::updateProfile error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить профиль
     *
     * @param int $profileId ID профиля
     * @param int $userId ID пользователя (для проверки прав)
     * @return bool Успех операции
     */
    public static function deleteProfile(int $profileId, int $userId): bool
    {
        if ($profileId <= 0 || $userId <= 0) {
            return false;
        }

        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        $sql = "
            DELETE FROM {$tableName}
            WHERE id = " . (int)$profileId . "
              AND user_id = " . (int)$userId . "
        ";

        try {
            $connection->query($sql);
            return true;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::deleteProfile error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Установить профиль по умолчанию
     *
     * @param int $profileId ID профиля
     * @param int $userId ID пользователя (для проверки прав)
     * @return bool Успех операции
     */
    public static function setDefaultProfile(int $profileId, int $userId): bool
    {
        if ($profileId <= 0 || $userId <= 0) {
            return false;
        }

        // Проверяем, что профиль существует и принадлежит пользователю
        $profile = self::getProfile($profileId, $userId);
        if (!$profile) {
            return false;
        }

        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        try {
            // Сбрасываем флаг у всех профилей пользователя
            self::clearDefaultFlag($userId);

            // Устанавливаем флаг для выбранного профиля
            $sql = "
                UPDATE {$tableName}
                SET is_default = 'Y',
                    date_update = NOW()
                WHERE id = " . (int)$profileId . "
                  AND user_id = " . (int)$userId . "
            ";

            $connection->query($sql);
            return true;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::setDefaultProfile error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Сбросить флаг "по умолчанию" у всех профилей пользователя
     *
     * @param int $userId ID пользователя
     * @return bool Успех операции
     */
    private static function clearDefaultFlag(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        $sql = "
            UPDATE {$tableName}
            SET is_default = 'N'
            WHERE user_id = " . (int)$userId . "
        ";

        try {
            $connection->query($sql);
            return true;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::clearDefaultFlag error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Создать таблицу профилей (для первичной установки)
     *
     * @return bool Успех операции
     */
    public static function createTable(): bool
    {
        $connection = Application::getConnection();
        $tableName = $connection->getSqlHelper()->forSql(self::TABLE_NAME);

        $sql = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                profile_name VARCHAR(255) NOT NULL,
                manager_name VARCHAR(255) DEFAULT '',
                manager_position VARCHAR(255) DEFAULT '',
                manager_phone VARCHAR(50) DEFAULT '',
                manager_email VARCHAR(255) DEFAULT '',
                manager_company VARCHAR(255) DEFAULT '',
                is_default CHAR(1) DEFAULT 'N',
                date_create DATETIME DEFAULT CURRENT_TIMESTAMP,
                date_update DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_user_id (user_id),
                INDEX idx_user_default (user_id, is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $connection->query($sql);
            return true;
        } catch (SqlQueryException $e) {
            error_log('ProfileManager::createTable error: ' . $e->getMessage());
            return false;
        }
    }
}