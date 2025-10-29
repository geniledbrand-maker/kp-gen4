<?php
namespace Geniled\KpGen;

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Catalog\ProductTable;

Loader::includeModule('sale');
Loader::includeModule('catalog');

class OrderKpService
{
    /**
     * Создать заказ-КП в модуле Sale
     */
    public static function createOrderFromKp($userId, $kpTitle, $kpData, $products)
    {
        try {
            $siteId = SITE_ID;

            // Создаём заказ
            $order = Order::create($siteId, $userId);
            $order->setPersonTypeId(1); // 1 = физлицо, измените если нужно

            // Устанавливаем свойства заказа
            $propertyCollection = $order->getPropertyCollection();

            // Сохраняем название КП в комментарий
            if ($comment = $propertyCollection->getItemByOrderPropertyCode('COMMENTS')) {
                $comment->setValue("КП: {$kpTitle}\n\n" . ($kpData['comment'] ?? ''));
            }

            // Заказчик
            if ($kpData['customer'] ?? '') {
                if ($prop = $propertyCollection->getItemByOrderPropertyCode('COMPANY')) {
                    $prop->setValue($kpData['customer']);
                }
            }

            // ФИО контакта
            if ($kpData['manager'] ?? '') {
                if ($prop = $propertyCollection->getItemByOrderPropertyCode('FIO')) {
                    $prop->setValue($kpData['manager']);
                }
            }

            // Email
            if ($kpData['email'] ?? '') {
                if ($prop = $propertyCollection->getItemByOrderPropertyCode('EMAIL')) {
                    $prop->setValue($kpData['email']);
                }
            }

            // Телефон
            if ($kpData['phone'] ?? '') {
                if ($prop = $propertyCollection->getItemByOrderPropertyCode('PHONE')) {
                    $prop->setValue($kpData['phone']);
                }
            }

            // Создаём корзину
            $basket = Basket::create($siteId);

            foreach ($products as $product) {
                $productId = (int)$product['id'];
                $quantity = (float)($product['quantity'] ?? 1);
                $price = (float)($product['price'] ?? 0);

                if (!$productId || $price <= 0) continue;

                // Получаем данные товара
                $arProduct = \CIBlockElement::GetByID($productId)->GetNext();
                if (!$arProduct) continue;

                $item = $basket->createItem('catalog', $productId);
                $item->setFields([
                    'QUANTITY' => $quantity,
                    'CURRENCY' => $product['currency'] ?? 'RUB',
                    'LID' => $siteId,
                    'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
                    'NAME' => $arProduct['NAME'],
                    'CUSTOM_PRICE' => 'Y',
                    'PRICE' => $price,
                    'BASE_PRICE' => $price,
                ]);
            }

            $order->setBasket($basket);

            // Устанавливаем статус "КП"
            $order->setField('STATUS_ID', 'KP');

            // Сохраняем заказ
            $result = $order->save();

            if ($result->isSuccess()) {
                return [
                    'success' => true,
                    'orderId' => $order->getId(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => implode(', ', $result->getErrorMessages()),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить список КП-заказов пользователя
     */
    public static function getUserKpOrders($userId, $limit = 50)
    {
        $result = OrderTable::getList([
            'filter' => [
                '=USER_ID' => $userId,
                '=STATUS_ID' => 'KP',
            ],
            'order' => ['DATE_UPDATE' => 'DESC'],
            'limit' => $limit,
            'select' => ['ID', 'DATE_INSERT', 'DATE_UPDATE', 'PRICE', 'CURRENCY'],
        ]);

        $orders = [];
        while ($row = $result->fetch()) {
            $order = Order::load($row['ID']);
            if (!$order) continue;

            $propertyCollection = $order->getPropertyCollection();
            $comment = '';
            if ($commentProp = $propertyCollection->getItemByOrderPropertyCode('COMMENTS')) {
                $comment = $commentProp->getValue();
            }

            // Извлекаем название КП из комментария
            $title = "Заказ #{$row['ID']}";
            if (preg_match('/^КП:\s*(.+?)(?:\n|$)/u', $comment, $matches)) {
                $title = trim($matches[1]);
            }

            $orders[] = [
                'id' => $row['ID'],
                'title' => $title,
                'date' => $row['DATE_UPDATE'] ?: $row['DATE_INSERT'],
                'price' => $row['PRICE'],
                'currency' => $row['CURRENCY'],
            ];
        }

        return $orders;
    }
}