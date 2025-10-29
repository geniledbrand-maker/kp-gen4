<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$props = CIBlockProperty::GetList(
    ['SORT' => 'ASC', 'NAME' => 'ASC'],
    ['IBLOCK_ID' => 29, 'ACTIVE' => 'Y']
);

echo '<style>table{border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;text-align:left;}</style>';
echo '<table>';
echo '<tr><th>ID</th><th>CODE</th><th>NAME</th></tr>';

while ($prop = $props->Fetch()) {
    // Фильтруем интересующие нас свойства
    $keywords = ['световой', 'поток', 'угол', 'размер', 'материал', 'гарантия', 'энерг', 'срок', 'температура', 'класс'];
    $match = false;

    foreach ($keywords as $kw) {
        if (mb_stripos($prop['NAME'], $kw) !== false || mb_stripos($prop['CODE'], $kw) !== false) {
            $match = true;
            break;
        }
    }

    if ($match) {
        echo '<tr>';
        echo '<td>' . $prop['ID'] . '</td>';
        echo '<td>' . htmlspecialchars($prop['CODE']) . '</td>';
        echo '<td>' . htmlspecialchars($prop['NAME']) . '</td>';
        echo '</tr>';
    }
}

echo '</table>';