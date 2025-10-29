<?php
/**
 * Скрипт для отладки работы с изображениями в PDF
 * Путь: /local/tools/kp_gen4/temp/Tests/debug_images.php
 * Использование: https://geniled.ru/local/tools/kp_gen4/temp/Tests/debug_images.php?ID=123
 */

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Подключаем функции из commercial
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/commercial/functions.php';

if(!CModule::IncludeModule("iblock")){
    die("Module iblock not loaded");
}

$productId = (int)($_GET['ID'] ?? 0);

if (!$productId) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Отладка изображений в PDF</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
            h1 { color: #722e85; }
            .form-group { margin: 20px 0; }
            input[type="number"] { padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
            button { padding: 10px 20px; background: #722e85; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #5a2369; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>🔍 Отладка изображений в PDF</h1>
        <p>Этот скрипт проверяет, корректно ли работает конвертация изображений товара в Base64 для PDF.</p>

        <div class="form-group">
            <form method="GET">
                <label for="productId">Введите ID товара:</label><br><br>
                <input type="number" id="productId" name="ID" placeholder="Например: 123456" required>
                <button type="submit">Проверить</button>
            </form>
        </div>

        <hr>
        <p><small>Путь: /local/tools/kp_gen4/temp/Tests/debug_images.php</small></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отладка изображений - Товар #<?=$productId?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1, h2, h3 { color: #722e85; }
        .success { background: #e7f4e7; padding: 15px; border: 1px solid #4caf50; border-radius: 4px; margin: 20px 0; }
        .error { background: #ffe7e7; padding: 15px; border: 1px solid #f44336; border-radius: 4px; margin: 20px 0; }
        .warning { background: #fff3cd; padding: 15px; border: 1px solid #ffc107; border-radius: 4px; margin: 20px 0; }
        .info { background: #e3f2fd; padding: 15px; border: 1px solid #2196f3; border-radius: 4px; margin: 20px 0; }
        .img-container { border: 1px solid #ccc; padding: 15px; margin: 15px 0; border-radius: 4px; background: #fafafa; }
        .img-container img { max-width: 100%; height: auto; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table td { padding: 8px; border: 1px solid #ddd; }
        table td:first-child { font-weight: bold; width: 200px; background: #f9f9f9; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .back-link { display: inline-block; margin: 20px 0; padding: 10px 20px; background: #722e85; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background: #5a2369; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Отладка изображений для товара ID=<?=$productId?></h1>

    <?php
    // Получаем товар через стандартный API
    $arSelect = ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PROPERTY_CML2_ARTICLE'];
    $arFilter = ['IBLOCK_ID' => 29, 'ID' => $productId];
    $res = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

    if ($arProduct = $res->Fetch()) {
        ?>
        <div class="info">
            <h3>✅ Товар найден в базе</h3>
            <table>
                <tr>
                    <td>Название</td>
                    <td><?=htmlspecialchars($arProduct['NAME'])?></td>
                </tr>
                <tr>
                    <td>Артикул</td>
                    <td><?=htmlspecialchars($arProduct['PROPERTY_CML2_ARTICLE_VALUE'] ?? 'не указан')?></td>
                </tr>
                <tr>
                    <td>PREVIEW_PICTURE</td>
                    <td><?=(int)$arProduct['PREVIEW_PICTURE']?> <?=(int)$arProduct['PREVIEW_PICTURE'] > 0 ? '✅' : '❌'?></td>
                </tr>
                <tr>
                    <td>DETAIL_PICTURE</td>
                    <td><?=(int)$arProduct['DETAIL_PICTURE']?> <?=(int)$arProduct['DETAIL_PICTURE'] > 0 ? '✅' : '❌'?></td>
                </tr>
            </table>
        </div>

        <?php
        $imageId = (int)($arProduct['DETAIL_PICTURE'] ?: $arProduct['PREVIEW_PICTURE']);

        if ($imageId > 0) {
            ?>
            <hr>
            <h2>📸 Проверка изображения (ID: <?=$imageId?>)</h2>

            <?php
            // Шаг 1: Оригинальное изображение
            $originalFile = CFile::GetFileArray($imageId);
            if ($originalFile) {
                $originalPath = $_SERVER['DOCUMENT_ROOT'] . $originalFile['SRC'];
                $originalExists = file_exists($originalPath);
                ?>

                <h3>Шаг 1: Оригинальное изображение</h3>
                <table>
                    <tr>
                        <td>Относительный путь</td>
                        <td><code><?=htmlspecialchars($originalFile['SRC'])?></code></td>
                    </tr>
                    <tr>
                        <td>Полный путь</td>
                        <td><code><?=htmlspecialchars($originalPath)?></code></td>
                    </tr>
                    <tr>
                        <td>Файл существует</td>
                        <td><?=$originalExists ? '✅ ДА' : '❌ НЕТ'?></td>
                    </tr>
                    <?php if ($originalExists): ?>
                        <tr>
                            <td>Размер файла</td>
                            <td><?=number_format(filesize($originalPath))?> байт</td>
                        </tr>
                        <tr>
                            <td>MIME-тип</td>
                            <td><?php
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                echo htmlspecialchars(finfo_file($finfo, $originalPath));
                                finfo_close($finfo);
                                ?></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php if ($originalExists): ?>
                    <div class="img-container">
                        <h4>Превью оригинала:</h4>
                        <img src="<?=htmlspecialchars($originalFile['SRC'])?>" alt="Оригинал">
                    </div>
                <?php endif; ?>

                <?php
            }

            // Шаг 2: Ресайз изображения
            ?>
            <hr>
            <h3>Шаг 2: Ресайз изображения (400x400)</h3>
            <?php

            $resized = CFile::ResizeImageGet(
                $imageId,
                ['width' => 400, 'height' => 400],
                BX_RESIZE_IMAGE_PROPORTIONAL,
                false
            );

            if ($resized && !empty($resized['src'])) {
                $resizedPath = $_SERVER['DOCUMENT_ROOT'] . $resized['src'];
                $resizedExists = file_exists($resizedPath);
                ?>

                <table>
                    <tr>
                        <td>Относительный путь</td>
                        <td><code><?=htmlspecialchars($resized['src'])?></code></td>
                    </tr>
                    <tr>
                        <td>Полный путь</td>
                        <td><code><?=htmlspecialchars($resizedPath)?></code></td>
                    </tr>
                    <tr>
                        <td>Файл существует</td>
                        <td><?=$resizedExists ? '✅ ДА' : '❌ НЕТ'?></td>
                    </tr>
                    <?php if ($resizedExists): ?>
                        <tr>
                            <td>Размер файла</td>
                            <td><?=number_format(filesize($resizedPath))?> байт</td>
                        </tr>
                        <tr>
                            <td>MIME-тип</td>
                            <td><?php
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                echo htmlspecialchars(finfo_file($finfo, $resizedPath));
                                finfo_close($finfo);
                                ?></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php if ($resizedExists): ?>
                    <div class="img-container">
                        <h4>Превью ресайза:</h4>
                        <img src="<?=htmlspecialchars($resized['src'])?>" alt="Ресайз" style="max-width:400px;">
                    </div>

                    <?php
                    // Шаг 3: Конвертация в Base64
                    ?>
                    <hr>
                    <h3>Шаг 3: Конвертация в Base64 (для PDF)</h3>
                    <?php

                    $imageData = @file_get_contents($resizedPath);
                    if ($imageData !== false) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $resizedPath);
                        finfo_close($finfo);

                        $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                        $base64Length = strlen($base64);
                        ?>

                        <table>
                            <tr>
                                <td>MIME-тип</td>
                                <td><code><?=htmlspecialchars($mimeType)?></code></td>
                            </tr>
                            <tr>
                                <td>Размер Base64</td>
                                <td><?=number_format($base64Length)?> символов</td>
                            </tr>
                            <tr>
                                <td>Начало строки</td>
                                <td><code><?=htmlspecialchars(substr($base64, 0, 80))?>...</code></td>
                            </tr>
                        </table>

                        <div class="img-container">
                            <h4>Изображение через Base64 (как в PDF):</h4>
                            <img src="<?=$base64?>" alt="Base64" style="max-width:400px;">
                        </div>

                        <div class="success">
                            <strong>✅ УСПЕХ!</strong> Изображение корректно конвертировано в Base64 и отображается.<br>
                            Размер: <?=number_format($base64Length)?> символов (<?=number_format($base64Length/1024, 2)?> КБ)
                        </div>

                        <?php
                        // Шаг 4: Проверка функции getImageAsBase64
                        ?>
                        <hr>
                        <h3>Шаг 4: Проверка функции getImageAsBase64() с конвертацией WebP</h3>
                        <?php

                        if (function_exists('getImageAsBase64')) {
                            $functionResult = getImageAsBase64($resizedPath);

                            if (!empty($functionResult)) {
                                // Проверяем, была ли конвертация WebP
                                $wasWebP = ($mimeType === 'image/webp');
                                $isJpeg = (strpos($functionResult, 'data:image/jpeg') === 0);

                                ?>
                                <div class="success">
                                    <strong>✅ Функция работает!</strong><br>
                                    Длина результата: <?=number_format(strlen($functionResult))?> символов<br>
                                    <?php if ($wasWebP && $isJpeg): ?>
                                        <strong style="color:#ff9800;">🔄 WebP автоматически сконвертирован в JPEG для совместимости с PDF!</strong><br>
                                    <?php endif; ?>
                                    Совпадает с прямой конвертацией: <?=($functionResult === $base64 ? '✅ ДА' : '⚠️ НЕТ (это нормально при конвертации WebP)')?>
                                </div>

                                <?php if ($wasWebP && !$isJpeg): ?>
                                    <div class="warning">
                                        <strong>⚠️ ВНИМАНИЕ!</strong> WebP не был сконвертирован в JPEG!<br>
                                        Html2Pdf может не поддерживать WebP формат. Рекомендуется проверить работу библиотеки GD.
                                    </div>
                                <?php endif; ?>

                                <div class="img-container">
                                    <h4>Результат getImageAsBase64() <?=$wasWebP && $isJpeg ? '(WebP → JPEG)' : ''?>:</h4>
                                    <img src="<?=$functionResult?>" alt="Function Result" style="max-width:400px;">
                                </div>

                                <?php if ($wasWebP): ?>
                                    <div class="info">
                                        <strong>ℹ️ Информация о конвертации:</strong><br>
                                        • Исходный формат: WebP<br>
                                        • Конечный формат: <?=$isJpeg ? 'JPEG' : 'WebP (не сконвертирован)'?><br>
                                        • Размер до: <?=number_format(strlen($base64))?> символов<br>
                                        • Размер после: <?=number_format(strlen($functionResult))?> символов<br>
                                        • Разница: <?=number_format(strlen($functionResult) - strlen($base64))?> символов
                                    </div>
                                <?php endif; ?>
                                <?php
                            } else {
                                ?>
                                <div class="error">
                                    <strong>❌ ОШИБКА!</strong> Функция getImageAsBase64() вернула пустой результат!
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="error">
                                <strong>❌ ОШИБКА!</strong> Функция getImageAsBase64() не найдена!<br>
                                Убедитесь, что файл /ajax/commercial/functions.php подключен и содержит эту функцию.
                            </div>
                            <?php
                        }

                    } else {
                        ?>
                        <div class="error">
                            <strong>❌ ОШИБКА!</strong> Не удалось прочитать файл ресайза для конвертации в Base64.
                        </div>
                        <?php
                    }
                    ?>

                <?php
                else: // !$resizedExists
                    ?>
                    <div class="error">
                        <strong>❌ ОШИБКА!</strong> Файл ресайза не найден на диске!<br>
                        Путь: <code><?=htmlspecialchars($resizedPath)?></code>
                    </div>
                <?php
                endif;
                ?>

                <?php
            } else {
                ?>
                <div class="error">
                    <strong>❌ ОШИБКА!</strong> CFile::ResizeImageGet() не вернул корректный результат.<br>
                    Возможные причины:
                    <ul>
                        <li>Оригинальное изображение повреждено</li>
                        <li>Недостаточно прав для записи в директорию /upload/resize_cache/</li>
                        <li>Недостаточно памяти PHP для обработки изображения</li>
                    </ul>
                </div>
                <?php
            }

        } else {
            // Нет изображений у товара
            ?>
            <div class="warning">
                <strong>⚠️ ВНИМАНИЕ!</strong> У товара нет изображений (PREVIEW_PICTURE и DETAIL_PICTURE пусты).
            </div>

            <hr>
            <h3>Проверка заглушки no_photo.png</h3>
            <?php

            $noPhotoPath = $_SERVER['DOCUMENT_ROOT'] . '/local/templates/aspro_max/images/no_photo.png';
            $noPhotoExists = file_exists($noPhotoPath);
            ?>

            <table>
                <tr>
                    <td>Путь к заглушке</td>
                    <td><code><?=htmlspecialchars($noPhotoPath)?></code></td>
                </tr>
                <tr>
                    <td>Файл существует</td>
                    <td><?=$noPhotoExists ? '✅ ДА' : '❌ НЕТ'?></td>
                </tr>
            </table>

            <?php if ($noPhotoExists): ?>
                <div class="img-container">
                    <h4>Заглушка no_photo.png:</h4>
                    <img src="/local/templates/aspro_max/images/no_photo.png" alt="No Photo" style="max-width:400px;">
                </div>

                <div class="info">
                    В PDF будет использована эта заглушка.
                </div>
            <?php else: ?>
                <div class="error">
                    <strong>❌ ОШИБКА!</strong> Файл заглушки no_photo.png не найден!<br>
                    Создайте файл по пути: <code><?=htmlspecialchars($noPhotoPath)?></code>
                </div>
            <?php endif; ?>

            <?php
        }
        ?>

        <?php
    } else {
        ?>
        <div class="error">
            <strong>❌ ОШИБКА!</strong> Товар с ID=<?=$productId?> не найден в инфоблоке 29.
        </div>
        <?php
    }
    ?>

    <hr>
    <h3>🔗 Ссылки</h3>
    <p>
        <a href="/ajax/commercial/technical_desc.php?ID=<?=$productId?>" target="_blank" class="back-link">
            📄 Скачать PDF для этого товара
        </a>
    </p>
    <p>
        <a href="?ID=" class="back-link">🔍 Проверить другой товар</a>
    </p>

    <hr>
    <p><small>Время выполнения: <?=date('Y-m-d H:i:s')?></small></p>
    <p><small>Путь скрипта: /local/tools/kp_gen4/temp/Tests/debug_images.php</small></p>
</div>
</body>
</html>