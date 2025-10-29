<?php
/**
 * ✅ ТЕСТ АВТОМАТИЧЕСКОГО МАСШТАБИРОВАНИЯ ИЗОБРАЖЕНИЙ
 * Проверяем новую функциональность обработки больших изображений
 */

require_once 'classes/ExcelGenerator.php';

echo "<h2>🧪 Тест автоматического масштабирования изображений</h2>";

// Тестовые данные с разными размерами изображений
$testProducts = [
    [
        'name' => 'Товар с маленьким изображением',
        'article' => 'TEST001',
        'price' => 1000,
        'base_price' => 1200,
        'discount_percent' => 16.67,
        'quantity' => 1,
        'image' => 'https://via.placeholder.com/50x50/FF0000/FFFFFF?text=SMALL'
    ],
    [
        'name' => 'Товар со средним изображением',
        'article' => 'TEST002', 
        'price' => 2000,
        'base_price' => 2500,
        'discount_percent' => 20,
        'quantity' => 2,
        'image' => 'https://via.placeholder.com/200x200/00FF00/FFFFFF?text=MEDIUM'
    ],
    [
        'name' => 'Товар с большим изображением',
        'article' => 'TEST003',
        'price' => 3000,
        'base_price' => 4000,
        'discount_percent' => 25,
        'quantity' => 1,
        'image' => 'https://via.placeholder.com/800x600/0000FF/FFFFFF?text=LARGE'
    ],
    [
        'name' => 'Товар с очень большим изображением',
        'article' => 'TEST004',
        'price' => 4000,
        'base_price' => 5000,
        'discount_percent' => 20,
        'quantity' => 1,
        'image' => 'https://via.placeholder.com/1200x800/FF00FF/FFFFFF?text=HUGE'
    ],
    [
        'name' => 'Товар без изображения',
        'article' => 'TEST005',
        'price' => 5000,
        'base_price' => 6000,
        'discount_percent' => 16.67,
        'quantity' => 1,
        'image' => ''
    ]
];

echo "<h3>📊 Тестовые товары:</h3>";
echo "<ul>";
foreach ($testProducts as $product) {
    $imageInfo = $product['image'] ? "✅ Изображение: {$product['image']}" : "❌ Без изображения";
    echo "<li><strong>{$product['name']}</strong> - {$imageInfo}</li>";
}
echo "</ul>";

try {
    echo "<h3>🔧 Создание Excel с автоматическим масштабированием...</h3>";
    
    $generator = new ExcelGenerator();
    
    // Добавляем товары
    foreach ($testProducts as $product) {
        $generator->addProduct($product);
    }
    
    // Генерируем Excel
    $filename = 'test_image_scaling_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filepath = $generator->generate($filename);
    
    if ($filepath && file_exists($filepath)) {
        $fileSize = filesize($filepath);
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>✅ Excel файл успешно создан!</h4>";
        echo "<p><strong>Файл:</strong> {$filename}</p>";
        echo "<p><strong>Размер:</strong> " . number_format($fileSize / 1024, 2) . " KB</p>";
        echo "<p><strong>Путь:</strong> {$filepath}</p>";
        echo "</div>";
        
        echo "<h4>🎯 Ожидаемые результаты:</h4>";
        echo "<ul>";
        echo "<li><strong>Маленькое изображение (50x50):</strong> Должно отображаться в оригинальном размере</li>";
        echo "<li><strong>Среднее изображение (200x200):</strong> Должно масштабироваться до 90x90 пикселей</li>";
        echo "<li><strong>Большое изображение (800x600):</strong> Должно масштабироваться до 90x68 пикселей</li>";
        echo "<li><strong>Очень большое изображение (1200x800):</strong> Должно масштабироваться до 90x60 пикселей</li>";
        echo "<li><strong>Товар без изображения:</strong> Строка должна быть обычной высоты (100px)</li>";
        echo "<li><strong>Товары с изображениями:</strong> Строки должны быть увеличенной высоты (120px)</li>";
        echo "</ul>";
        
        echo "<h4>📋 Проверьте в Excel:</h4>";
        echo "<ol>";
        echo "<li>Откройте созданный файл</li>";
        echo "<li>Проверьте, что все изображения помещаются в ячейки</li>";
        echo "<li>Убедитесь, что изображения центрированы в ячейках</li>";
        echo "<li>Проверьте, что высота строк соответствует наличию изображений</li>";
        echo "<li>Убедитесь, что скидки рассчитываются корректно</li>";
        echo "</ol>";
        
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>❌ Ошибка создания Excel файла</h4>";
        echo "<p>Не удалось создать файл или файл не найден</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ Ошибка:</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<h3>📝 Логи обработки изображений:</h3>";
echo "<p>Проверьте логи в файле error_log для детальной информации о:</p>";
echo "<ul>";
echo "<li>Загрузке изображений</li>";
echo "<li>Создании миниатюр для больших файлов</li>";
echo "<li>Масштабировании изображений</li>";
echo "<li>Центрировании в ячейках</li>";
echo "</ul>";

echo "<hr>";
echo "<p><em>Тест завершен. Проверьте созданный Excel файл на соответствие ожидаемым результатам.</em></p>";
?>
