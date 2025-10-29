<?php
/**
 * ProductService
 * ----------------------------
 * Получение товара и сборка параметров для генератора КП.
 *
 * Требует:
 *  - config/constants.php  (IBLOCK_ID, ARTICLE_PROP_ID, DEFAULT_PRICE_TYPE_ID, PROP_*_LIST ...)
 *  - includes/helpers.php  (getMergedPropsByIdList, getAbsoluteImageUrl, getAbsoluteProductUrl)
 */

use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

Loader::includeModule('iblock');
Loader::includeModule('catalog');

class ProductService
{
    /**
     * Получить товар по артикулу.
     * Возвращает массив данных для фронта генератора КП.
     *
     * @param string $article
     * @return array {success:bool, error?:string, data?:array}
     */
    public static function getByArticle(string $article): array
    {
        $article = trim($article);
        if ($article === '') {
            return ['success' => false, 'error' => 'Не указан артикул'];
        }

        // 1) Находим элемент по артикулу
        $filter = [
            'IBLOCK_ID' => IBLOCK_ID,
            'ACTIVE'    => 'Y',
            '=PROPERTY_' . ARTICLE_PROP_ID => $article,
        ];

        $select = [
            'ID', 'NAME', 'DETAIL_PAGE_URL',
            'PREVIEW_PICTURE', 'DETAIL_PICTURE',
        ];

        $res = CIBlockElement::GetList(['ID' => 'ASC'], $filter, false, ['nTopCount' => 1], $select);
        $el  = $res ? $res->GetNextElement() : null;

        if (!$el) {
            return ['success' => false, 'error' => 'Товар не найден'];
        }

        $fields = $el->GetFields();     // ['ID','NAME','DETAIL_PAGE_URL',...]
        $productId = (int)$fields['ID'];

        // 2) Цена по типу (по умолчанию — МИЦ = DEFAULT_PRICE_TYPE_ID)
        $priceTypeId = (int)(defined('DEFAULT_PRICE_TYPE_ID') ? DEFAULT_PRICE_TYPE_ID : 2);
        $priceRow = CPrice::GetList([], [
            'PRODUCT_ID'      => $productId,
            'CATALOG_GROUP_ID'=> $priceTypeId
        ])->Fetch();

        $basePrice = $priceRow ? (float)$priceRow['PRICE'] : 0.0;
        $currency  = $priceRow['CURRENCY'] ?? 'RUB';

        // === 3) Изображение и ссылки ===
        $imageId = (int)($fields['DETAIL_PICTURE'] ?: $fields['PREVIEW_PICTURE']);
        if ($imageId > 0) {
            $image = getAbsoluteImageUrl($imageId);
        } else {
            // ✅ Добавлено: резервная картинка, если фото отсутствует
            $image = 'https://geniled.ru/local/templates/aspro_max/images/no_photo.png';
        }

        // Абсолютная ссылка на карточку товара
        $url = getAbsoluteProductUrl((string)($fields['DETAIL_PAGE_URL'] ?? ''));

        // 4) Параметры (оба формата: новый + совместимый)
        $paramPack   = self::collectParams($productId);
        $propsDetails= $paramPack['propsDetails'];
        $propsArray  = $paramPack['propsArray']; // совместимость: [{name,value}]

        // 5) Ед. измерения (если у тебя где-то есть сложная логика — замени на свою)
        $measure = self::getMeasure($productId) ?: 'шт';

        // 6) Собираем данные для фронта
        return [
            'success' => true,
            'data' => [
                'id'            => $productId,
                'name'          => (string)$fields['NAME'],
                'article'       => $article,
                'price'         => $basePrice,
                'mic_price'     => $basePrice,   // чтобы UI мог считать скидку
                'base_price'    => $basePrice,
                'price_type_id' => $priceTypeId,
                'currency'      => $currency,
                'measure'       => $measure,
                'image'         => $image,
                'url'           => $url,

                // ✅ Параметры
                'props'         => $propsArray,   // старый формат (массив [{name,value}])
                'propsDetails'  => $propsDetails, // новый формат (map key => {value,label,order,required,queryKey})
            ],
        ];
    }

    /**
     * Единица измерения товара.
     * Здесь простая заглушка — при необходимости замени на свою бизнес-логику.
     *
     * @param int $productId
     * @return string|null
     */
    private static function getMeasure(int $productId): ?string
    {
        // Если используется модуль catalog.measure — можно вытащить реальную ед. изм.
        // Возвращаем "шт" как дефолт
        return 'шт';
    }

    /**
     * Собирает параметры товара по константам PROP_*_LIST.
     * Возвращает:
     *  - propsDetails: ассоц. массив key => ['value','label','order','required','queryKey']
     *  - propsArray:   массив совместимости [{name, value}]
     *
     * @param int $productId
     * @return array{propsDetails: array, propsArray: array}
     */
    private static function collectParams(int $productId): array
    {
        // Карта: ключ → [имя константы-списка, label, order, required, queryKey]
        $map = [
            // базовые (required)
            'ct'  => ['const' => 'PROP_CT_LIST',  'label' => 'Цветовая температура, К',   'order' => 1,  'required' => true,  'queryKey' => 'ct'],
            'pwr' => ['const' => 'PROP_PWR_LIST', 'label' => 'Электропотребление, Вт',    'order' => 2,  'required' => true,  'queryKey' => 'pwr'],
            'ip'  => ['const' => 'PROP_IP_LIST',  'label' => 'Степень пылевлагозащиты',   'order' => 3,  'required' => true,  'queryKey' => 'ip'],
            'cri' => ['const' => 'PROP_CRI_LIST', 'label' => 'Индекс цветопередачи, Ra',  'order' => 4,  'required' => true,  'queryKey' => 'cri'],

            // дополнительные
            'lumen'      => ['const' => 'PROP_LUMEN_LIST',      'label' => 'Световой поток, Лм',           'order' => 5,  'required' => false, 'queryKey' => 'lumen'],
            'efficiency' => ['const' => 'PROP_EFFICIENCY_LIST', 'label' => 'Энергоэффективность, лм/Вт',   'order' => 6,  'required' => false, 'queryKey' => 'efficiency'],
            'angle'      => ['const' => 'PROP_ANGLE_LIST',      'label' => 'Угол рассеивания, °',          'order' => 7,  'required' => false, 'queryKey' => 'angle'],
            'size'       => ['const' => 'PROP_SIZE_LIST',       'label' => 'Габаритные размеры, мм',       'order' => 8,  'required' => false, 'queryKey' => 'size'],
            'material'   => ['const' => 'PROP_MATERIAL_LIST',   'label' => 'Материал корпуса',             'order' => 9,  'required' => false, 'queryKey' => 'material'],
            'warranty'   => ['const' => 'PROP_WARRANTY_LIST',   'label' => 'Гарантия, лет',                'order' => 10, 'required' => false, 'queryKey' => 'warranty'],
            'lifetime'   => ['const' => 'PROP_LIFETIME_LIST',   'label' => 'Срок службы, ч',               'order' => 11, 'required' => false, 'queryKey' => 'lifetime'],
            'temp'       => ['const' => 'PROP_TEMP_LIST',       'label' => 'Температура эксплуатации, °C', 'order' => 12, 'required' => false, 'queryKey' => 'temp'],
            'protection_class' => ['const' => 'PROP_PROTECTION_CLASS_LIST', 'label' => 'Класс защиты',     'order' => 13, 'required' => false, 'queryKey' => 'protection_class'],
        ];

        $details = [];
        $array   = [];

        foreach ($map as $key => $cfg) {
            $constName = $cfg['const'];
            if (!defined($constName)) {
                continue; // константа не задана — пропускаем безопасно
            }
            $ids = constant($constName);
            if (!is_array($ids) || !$ids) {
                continue; // пустой список — пропускаем
            }

            // Соберём значения по всем id (у тебя уже есть helper)
            $value = getMergedPropsByIdList($productId, $ids);
            $value = trim((string)$value);
            if ($value === '') {
                continue; // пустые значения не отдаём
            }

            $row = [
                'value'    => $value,
                'label'    => $cfg['label'],
                'order'    => (int)$cfg['order'],
                'required' => (bool)$cfg['required'],
                'queryKey' => $cfg['queryKey'] ?? $key,
            ];
            $details[$key] = $row;
            $array[] = ['name' => $row['label'], 'value' => $row['value']]; // совместимость
        }

        // Отсортируем массив совместимости так же, как в UI (по order)
        if ($array) {
            usort($array, function ($a, $b) use ($details) {
                $oa = 999; $ob = 999;

                // Ищем order по label
                foreach ($details as $k => $d) {
                    if ($d['label'] === $a['name']) $oa = (int)$d['order'];
                    if ($d['label'] === $b['name']) $ob = (int)$d['order'];
                }
                return $oa <=> $ob;
            });
        }

        return ['propsDetails' => $details, 'propsArray' => $array];
    }
}
