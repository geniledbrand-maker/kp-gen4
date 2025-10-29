<?php
namespace Geniled\KpGen;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity\DataManager;

class SavedKpService
{
    private static $entityClass = null;

    /**
     * Получить entity-класс HL-блока
     */
    private static function getEntityClass(): ?string
    {
        if (self::$entityClass !== null) {
            return self::$entityClass;
        }

        if (!Loader::includeModule('highloadblock')) {
            return null;
        }

        // Ищем HL-блок по имени
        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => 'SavedKP']
        ])->fetch();

        if (!$hlblock) {
            return null;
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);
        self::$entityClass = $entity->getDataClass();

        return self::$entityClass;
    }

    /**
     * Сохранить КП (создание или обновление)
     *
     * @param int $userId ID пользователя
     * @param string $title Заголовок КП
     * @param array $kpData Основная информация о КП (включая ID для обновления)
     * @param array $products Данные о товарах
     * @return array Результат операции {success: bool, id?: int, error?: string, message?: string, action?: string}
     */
    public static function save(int $userId, string $title, array $kpData, array $products): array
    {
        /** @var DataManager|null $entityClass */
        $entityClass = self::getEntityClass();
        if (!$entityClass) {
            return ['success' => false, 'error' => 'HL-блок не найден'];
        }

        // ✅ Извлекаем ID из kpData и удаляем его перед сохранением
        $id = (int)($kpData['id'] ?? 0);
        unset($kpData['id']); // Не сохраняем ID в JSON

        $fields = [
            'UF_USER_ID'     => $userId,
            'UF_TITLE'       => $title,
            'UF_KP_DATA'     => json_encode($kpData, JSON_UNESCAPED_UNICODE),
            'UF_PRODUCTS'    => json_encode($products, JSON_UNESCAPED_UNICODE),
            'UF_DATE_MODIFY' => new DateTime(),
        ];

        $result = null;
        $action = 'create';
        $returnId = 0;

        if ($id > 0) {
            // Проверка прав на обновление: КП должно принадлежать текущему пользователю
            $existing = $entityClass::getById($id)->fetch();
            if (!$existing || (int)$existing['UF_USER_ID'] !== $userId) {
                return ['success' => false, 'error' => 'КП не найдено или нет прав для обновления'];
            }

            // Обновление существующего
            $result = $entityClass::update($id, $fields);
            $action = 'update';
            $returnId = $id;
        } else {
            // Создание новой записи
            $fields['UF_DATE_CREATE'] = new DateTime();
            $result = $entityClass::add($fields);
            $returnId = $result->getId();
            $action = 'create';
        }

        if ($result->isSuccess()) {
            return [
                'success' => true,
                'id' => $returnId,
                'action' => $action,
                'message' => $action === 'update' ? 'КП успешно обновлено' : 'КП успешно сохранено',
            ];
        } else {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }
    }

    /**
     * Получить КП по ID
     */
    public static function get($id, $userId)
    {
        $entityClass = self::getEntityClass();

        if (!$entityClass) {
            return null;
        }

        $row = $entityClass::getList([
            'filter' => [
                '=ID'         => (int)$id,
                '=UF_USER_ID' => (int)$userId,
            ],
        ])->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id'          => $row['ID'],
            'title'       => $row['UF_TITLE'],
            'dateCreate'  => $row['UF_DATE_CREATE'],
            'dateModify'  => $row['UF_DATE_MODIFY'],
            'kpData'      => json_decode($row['UF_KP_DATA'], true),
            'products'    => json_decode($row['UF_PRODUCTS'], true),
        ];
    }

    /**
     * Совместимое имя метода для ajax_handler: getById
     */
    public static function getById($id, $userId)
    {
        return self::get($id, $userId);
    }

    /**
     * Список КП пользователя (минимальная информация для списка)
     *
     * @param int $userId
     * @param int $limit
     * @return array<int, array{id:int,title:string,dateCreate:string,dateModify:string}>
     */
    public static function getUserKps(int $userId, int $limit = 100): array
    {
        /** @var DataManager|null $entityClass */
        $entityClass = self::getEntityClass();
        if (!$entityClass) return [];

        $rows = [];
        $res = $entityClass::getList([
            'filter' => ['=UF_USER_ID' => (int)$userId],
            'select' => ['ID','UF_TITLE','UF_DATE_CREATE','UF_DATE_MODIFY'],
            'order'  => ['ID' => 'DESC'],
            'limit'  => $limit,
        ]);
        while ($r = $res->fetch()) {
            $rows[] = [
                'id'         => (int)($r['ID'] ?? 0),
                'title'      => (string)($r['UF_TITLE'] ?? ''),
                'dateCreate' => (string)($r['UF_DATE_CREATE'] ?? ''),
                'dateModify' => (string)($r['UF_DATE_MODIFY'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * Удалить КП
     */
    public static function delete($id, $userId)
    {
        $entityClass = self::getEntityClass();
        if (!$entityClass) {
            return ['success' => false, 'error' => 'HL-блок не найден'];
        }

        // Проверка прав
        $existing = $entityClass::getById($id)->fetch();
        if (!$existing || (int)$existing['UF_USER_ID'] !== (int)$userId) {
            return ['success' => false, 'error' => 'КП не найдено или нет прав'];
        }

        $result = $entityClass::delete($id);

        if ($result->isSuccess()) {
            return ['success' => true, 'message' => 'КП успешно удалено.'];
        } else {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }
    }

    /**
     * Получить список КП-заказов пользователя (заглушка для совместимости)
     */
    public static function getUserKpOrders($userId, $limit = 50)
    {
        return [];
    }
}