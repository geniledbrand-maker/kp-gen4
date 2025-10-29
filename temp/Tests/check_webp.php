<?php
/**
 * Проверка поддержки WebP в PHP GD
 * Путь: /local/tools/kp_gen4/temp/Tests/check_webp.php
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Проверка поддержки WebP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #722e85; }
        .success { background: #e7f4e7; padding: 15px; border: 1px solid #4caf50; border-radius: 4px; margin: 20px 0; }
        .error { background: #ffe7e7; padding: 15px; border: 1px solid #f44336; border-radius: 4px; margin: 20px 0; }
        .info { background: #e3f2fd; padding: 15px; border: 1px solid #2196f3; border-radius: 4px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table td { padding: 8px; border: 1px solid #ddd; }
        table td:first-child { font-weight: bold; width: 300px; background: #f9f9f9; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Проверка поддержки WebP</h1>

    <h3>PHP GD Library</h3>
    <table>
        <tr>
            <td>GD установлен</td>
            <td><?php echo extension_loaded('gd') ? '✅ ДА' : '❌ НЕТ'; ?></td>
        </tr>
        <?php if (extension_loaded('gd')): ?>
            <tr>
                <td>Версия GD</td>
                <td><code><?php $info = gd_info(); echo $info['GD Version']; ?></code></td>
            </tr>
            <tr>
                <td>WebP Support</td>
                <td><?php echo !empty($info['WebP Support']) ? '✅ ДА' : '❌ НЕТ'; ?></td>
            </tr>
            <tr>
                <td>JPEG Support</td>
                <td><?php echo !empty($info['JPEG Support']) || !empty($info['JPG Support']) ? '✅ ДА' : '❌ НЕТ'; ?></td>
            </tr>
            <tr>
                <td>PNG Support</td>
                <td><?php echo !empty($info['PNG Support']) ? '✅ ДА' : '❌ НЕТ'; ?></td>
            </tr>
            <tr>
                <td>GIF Support</td>
                <td><?php echo !empty($info['GIF Read Support']) && !empty($info['GIF Create Support']) ? '✅ ДА' : '❌ НЕТ'; ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <h3>Функции для работы с WebP</h3>
    <table>
        <tr>
            <td>imagecreatefromwebp()</td>
            <td><?php echo function_exists('imagecreatefromwebp') ? '✅ Доступна' : '❌ Недоступна'; ?></td>
        </tr>
        <tr>
            <td>imagewebp()</td>
            <td><?php echo function_exists('imagewebp') ? '✅ Доступна' : '❌ Недоступна'; ?></td>
        </tr>
        <tr>
            <td>imagejpeg()</td>
            <td><?php echo function_exists('imagejpeg') ? '✅ Доступна' : '❌ Недоступна'; ?></td>
        </tr>
        <tr>
            <td>imagepng()</td>
            <td><?php echo function_exists('imagepng') ? '✅ Доступна' : '❌ Недоступна'; ?></td>
        </tr>
    </table>

    <?php if (extension_loaded('gd') && !empty($info['WebP Support']) && function_exists('imagecreatefromwebp')): ?>
        <div class="success">
            <strong>✅ ОТЛИЧНО!</strong> Ваш сервер полностью поддерживает WebP.<br>
            Конвертация WebP → JPEG для PDF будет работать корректно.
        </div>
    <?php elseif (extension_loaded('gd') && function_exists('imagejpeg')): ?>
        <div class="info">
            <strong>⚠️ ЧАСТИЧНАЯ ПОДДЕРЖКА</strong><br>
            GD установлен, но WebP не поддерживается. Это нормально для старых версий PHP.<br>
            Рекомендация: обновить PHP или пересохранить изображения в JPEG/PNG формате.
        </div>
    <?php else: ?>
        <div class="error">
            <strong>❌ ОШИБКА!</strong> GD library не установлен или настроен некорректно.<br>
            Обратитесь к администратору сервера для установки модуля php-gd.
        </div>
    <?php endif; ?>

    <hr>
    <h3>Информация о PHP</h3>
    <table>
        <tr>
            <td>Версия PHP</td>
            <td><code><?php echo PHP_VERSION; ?></code></td>
        </tr>
        <tr>
            <td>memory_limit</td>
            <td><code><?php echo ini_get('memory_limit'); ?></code></td>
        </tr>
        <tr>
            <td>upload_max_filesize</td>
            <td><code><?php echo ini_get('upload_max_filesize'); ?></code></td>
        </tr>
        <tr>
            <td>post_max_size</td>
            <td><code><?php echo ini_get('post_max_size'); ?></code></td>
        </tr>
    </table>

    <?php if (extension_loaded('gd')): ?>
        <hr>
        <h3>Полная информация о GD</h3>
        <div class="info">
            <pre><?php print_r(gd_info()); ?></pre>
        </div>
    <?php endif; ?>

    <hr>
    <p><a href="debug_images.php?ID=247327" style="color:#722e85;">← Вернуться к отладке изображений</a></p>
    <p><small>Время выполнения: <?=date('Y-m-d H:i:s')?></small></p>
</div>
</body>
</html>