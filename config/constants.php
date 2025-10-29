<?php
/**
 * Константы проекта
 */

// Инфоблок и свойства товаров
define('IBLOCK_ID', 29);
define('ARTICLE_PROP_ID', 404);
define('DEFAULT_PRICE_TYPE_ID', 2);

// Пути
define('TEMP_DIR', __DIR__ . '/../temp/');
define('TEMPLATE_XLSX', __DIR__ . '/../templates/excel/template-new2.xlsx');

// Свойства товаров для параметров
define('PROP_CT_LIST', [9364, 9646, 9476]);  // Цветовая температура
define('PROP_PWR_LIST', [9491, 9647]); // Электропотребление
define('PROP_IP_LIST', [9575]);        // Степень пылевлагозащиты
define('PROP_CRI_LIST', [9368, 9670]); // Индекс цветопередачи

// ✅ Дополнительные свойства
define('PROP_LUMEN_LIST', [9574, 9642]);     // Световой поток, Лм
define('PROP_ANGLE_LIST', [9366]);           // Угол рассеивания, Градус
define('PROP_SIZE_LIST', [9496]);            // Габаритные размеры, мм
define('PROP_MATERIAL_LIST', [9472]);        // Материал корпуса
define('PROP_WARRANTY_LIST', [9367]);        // Гарантия, Год
define('PROP_EFFICIENCY_LIST', [9407, 9643]); // Энергоэффективность, лм/Вт
define('PROP_LIFETIME_LIST', [9371]);        // Срок службы, ч
define('PROP_TEMP_LIST', [9408]);            // Температура эксплуатации, °C
define('PROP_PROTECTION_CLASS_LIST', [9400]); // Класс защиты от поражения электрическим током