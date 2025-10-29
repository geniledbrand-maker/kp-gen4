<?php
/**
 * Руководство пользователя - Генератор КП Geniled
 */

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Проверка авторизации (опционально)
global $USER;
if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/');
    die();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Руководство пользователя — Генератор КП Geniled</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?=filemtime(__DIR__.'/assets/css/styles.css')?>">
    <link rel="stylesheet" href="manual.css?v=<?=filemtime(__DIR__.'/manual.css')?>">
</head>
<body class="manual-page">

<!-- Шапка с навигацией -->
<header class="manual-header">
    <div class="manual-container">
        <div class="manual-header__content">
            <h1>📘 Руководство пользователя Генератором КП v.4</h1>
            <div class="manual-header__actions">
                <a href="index.php" class="btn btn--primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12h18M3 6h18M3 18h18"/>
                    </svg>
                    Открыть генератор КП
                </a>
                <button onclick="window.print()" class="btn btn--secondary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    </svg>
                    Печать
                </button>
            </div>
        </div>
    </div>
</header>

<!-- Боковое меню навигации -->
<aside class="manual-sidebar" id="sidebar">
    <div class="manual-sidebar__content">
        <h3>Содержание</h3>
        <nav class="manual-nav">
            <a href="#intro" class="manual-nav__link active">Введение</a>
            <a href="#start" class="manual-nav__link">Начало работы</a>
            <a href="#info" class="manual-nav__link">Информация о КП</a>
            <a href="#products" class="manual-nav__link">Добавление товаров</a>
            <a href="#prices" class="manual-nav__link">Работа с ценами</a>
            <a href="#quantity" class="manual-nav__link">Изменение количества</a>
            <a href="#save" class="manual-nav__link">Сохранение КП</a>
            <a href="#export" class="manual-nav__link">Экспорт в Excel</a>
            <a href="#faq" class="manual-nav__link">Частые вопросы</a>
            <a href="#tips" class="manual-nav__link">Полезные советы</a>
        </nav>
    </div>
</aside>

<!-- Основной контент -->
<main class="manual-main">
    <div class="manual-container">

        <!-- Введение -->
        <section id="intro" class="manual-section">
            <h2>Введение</h2>
            <div class="manual-intro">
                <p><strong>Генератор КП</strong> — это веб-приложение для быстрого создания коммерческих предложений с автоматическим расчётом скидок, подтягиванием цен из каталога и экспортом в профессиональный Excel-документ.</p>
            </div>

            <div class="manual-features">
                <h3>Основные возможности</h3>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🔍</div>
                        <h4>Умный поиск</h4>
                        <p>Быстрый поиск товаров по названию и артикулу с автоподсказками</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h4>Автоматический расчёт</h4>
                        <p>Скидки рассчитываются автоматически относительно МИЦ</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📦</div>
                        <h4>Массовое добавление</h4>
                        <p>Добавляйте товары списком артикулов за секунды</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔄</div>
                        <h4>Глобальная смена цен</h4>
                        <p>Меняйте тип цены для всех товаров одним кликом</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">👤</div>
                        <h4>Интеграция с Битрикс</h4>
                        <p>Автозагрузка ваших контактных данных из профиля</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📄</div>
                        <h4>Профессиональный экспорт</h4>
                        <p>Excel с логотипами, реквизитами и красивым оформлением</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Начало работы -->
        <section id="start" class="manual-section">
            <h2>Начало работы</h2>

            <div class="manual-step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Откройте генератор КП</h3>
                    <p>Перейдите по ссылке: <code>https://geniled.ru/local/tools/kp_gen4/</code></p>
                    <div class="note note--info">
                        <strong>💡 Важно:</strong> Вы должны быть авторизованы в системе Битрикс
                    </div>
                </div>
            </div>

            <div class="manual-step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Интерфейс приложения</h3>
                    <div class="interface-diagram">
                        <pre>
┌─────────────────────────────────────────┐
│  🏠 Генератор КП           🎨 Geniled   │
├─────────────────────────────────────────┤
│  📋 Информация о КП (свернуть/развернуть)│
│  👤 Загрузить из профиля Битрикс        │
├─────────────────────────────────────────┤
│  🔍 Поиск товаров                       │
│  📦 Добавить списком   [Тест]           │
├─────────────────────────────────────────┤
│  📊 Таблица товаров                     │
├─────────────────────────────────────────┤
│  💾 Сохранить  📁 Мои КП  📄 Excel     │
└─────────────────────────────────────────┘
                        </pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Информация о КП -->
        <section id="info" class="manual-section">
            <h2>Заполнение информации о КП</h2>

            <h3>Автозаполнение из профиля Битрикс</h3>
            <div class="manual-step">
                <div class="step-content">
                    <ol>
                        <li>Нажмите кнопку <strong>"Загрузить из профиля Битрикс"</strong></li>
                        <li>Автоматически заполнятся:
                            <ul>
                                <li>ФИО менеджера</li>
                                <li>Email менеджера</li>
                                <li>Телефон менеджера</li>
                                <li>Компания</li>
                            </ul>
                        </li>
                    </ol>
                    <div class="note note--success">
                        <strong>✅ Рекомендация:</strong> Используйте автозаполнение — это экономит время и исключает ошибки
                    </div>
                </div>
            </div>

            <h3>Поля формы</h3>
            <table class="manual-table">
                <thead>
                <tr>
                    <th>Поле</th>
                    <th>Описание</th>
                    <th>Обязательное</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>Дата КП</strong></td>
                    <td>Дата создания предложения</td>
                    <td><span class="badge badge--required">Да</span></td>
                </tr>
                <tr>
                    <td><strong>Заказчик</strong></td>
                    <td>Название компании клиента</td>
                    <td><span class="badge badge--required">Да</span></td>
                </tr>
                <tr>
                    <td><strong>Объект</strong></td>
                    <td>Адрес или описание объекта</td>
                    <td><span class="badge badge--optional">Рекомендуется</span></td>
                </tr>
                <tr>
                    <td><strong>Менеджер</strong></td>
                    <td>Ваше ФИО</td>
                    <td><span class="badge badge--required">Да</span></td>
                </tr>
                <tr>
                    <td><strong>Телефон</strong></td>
                    <td>Ваш контактный телефон</td>
                    <td><span class="badge badge--required">Да</span></td>
                </tr>
                <tr>
                    <td><strong>Email</strong></td>
                    <td>Ваш рабочий email</td>
                    <td><span class="badge badge--required">Да</span></td>
                </tr>
                <tr>
                    <td><strong>Комментарий</strong></td>
                    <td>Сроки, условия доставки, гарантия</td>
                    <td><span class="badge badge--optional">Опционально</span></td>
                </tr>
                </tbody>
            </table>
        </section>

        <!-- Добавление товаров -->
        <section id="products" class="manual-section">
            <h2>Добавление товаров</h2>

            <div class="methods-grid">
                <div class="method-card">
                    <h3>🔍 Способ 1: Поиск (рекомендуется)</h3>
                    <ol>
                        <li>Введите название товара или артикул в строку поиска</li>
                        <li>Подождите 0.3 секунды — появятся результаты</li>
                        <li>Нажмите <strong>"Добавить"</strong> на нужном товаре</li>
                    </ol>
                    <div class="note note--info">
                        Минимум 2 символа для начала поиска
                    </div>
                </div>

                <div class="method-card">
                    <h3>📦 Способ 2: Массовое добавление</h3>
                    <ol>
                        <li>Нажмите <strong>"Добавить артикулы списком"</strong></li>
                        <li>Введите артикулы (каждый с новой строки)</li>
                        <li>Отметьте чекбокс <strong>"Добавлять дубли как отдельные позиции"</strong></li>
                        <li>Нажмите <strong>"Добавить из списка"</strong></li>
                    </ol>
                    <div class="code-example">
                        <strong>Поддерживаемые форматы:</strong>
                        <pre>08575
08574;3
90988_4000 x 2
APT123 * 5</pre>
                    </div>
                </div>
            </div>

            <!-- НОВЫЙ РАЗДЕЛ: Загрузка из файлов -->
            <h3>📁 Способ 3: Загрузка из файлов</h3>
            <p>Система поддерживает загрузку списков артикулов из файлов. Это удобно для больших заказов (50+ позиций).</p>

            <h4>Поддерживаемые форматы файлов</h4>
            <table class="manual-table">
                <thead>
                <tr>
                    <th>Формат</th>
                    <th>Описание</th>
                    <th>Как создать</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>.txt</strong></td>
                    <td>Простой текстовый файл</td>
                    <td>Блокнот → каждый артикул с новой строки</td>
                </tr>
                <tr>
                    <td><strong>.csv</strong></td>
                    <td>CSV таблица</td>
                    <td>Excel → Сохранить как CSV</td>
                </tr>
                <tr>
                    <td><strong>.xlsx / .xls</strong></td>
                    <td>Файлы Excel</td>
                    <td>Microsoft Excel</td>
                </tr>
                </tbody>
            </table>

            <h4>Способы загрузки файла</h4>
            <div class="methods-grid">
                <div class="method-card">
                    <h4>🔘 Вариант A: Кнопка "Загрузить файл"</h4>
                    <ol>
                        <li>Откройте модальное окно массового добавления</li>
                        <li>Нажмите кнопку <strong>"Загрузить файл"</strong></li>
                        <li>Выберите файл (.txt, .csv, .xlsx)</li>
                        <li>Артикулы загрузятся автоматически</li>
                        <li>Проверьте список и нажмите <strong>"Добавить из списка"</strong></li>
                    </ol>
                </div>

                <div class="method-card">
                    <h4>🎯 Вариант B: Drag & Drop</h4>
                    <ol>
                        <li>Откройте модальное окно массового добавления</li>
                        <li>Перетащите файл мышкой в область текстового поля</li>
                        <li>Отпустите кнопку мыши</li>
                        <li>Артикулы загрузятся автоматически</li>
                        <li>Нажмите <strong>"Добавить из списка"</strong></li>
                    </ol>
                    <div class="note note--success">
                        <strong>✨ Самый быстрый способ!</strong> Просто перетащите файл в окно
                    </div>
                </div>
            </div>

            <h4>Пример структуры файла .txt</h4>
            <div class="code-example">
                <strong>artikuly.txt:</strong>
                <pre>90550_3000
90551_4000
APT123
APT456 x 5
APT789;10
APT999 * 3</pre>
            </div>

            <h4>Пример структуры файла Excel (.xlsx)</h4>
            <table class="manual-table" style="max-width: 500px; margin: 1.5rem 0;">
                <thead>
                <tr>
                    <th>Артикул</th>
                    <th>Количество</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>90550_3000</td>
                    <td>1</td>
                </tr>
                <tr>
                    <td>APT123</td>
                    <td>5</td>
                </tr>
                <tr>
                    <td>APT456</td>
                    <td>10</td>
                </tr>
                </tbody>
            </table>

            <div class="note note--warning">
                <strong>⚠️ Важно:</strong> Обязательно отмечайте чекбокс <strong>"Добавлять дубли как отдельные позиции"</strong> если хотите, чтобы каждый артикул добавился отдельной строкой в таблицу
            </div>

            <h4>Полезные советы по загрузке файлов</h4>
            <ul class="checklist">
                <li>✅ Проверяйте правильность артикулов перед загрузкой</li>
                <li>✅ Используйте Excel для больших списков (100+ позиций)</li>
                <li>✅ Drag & Drop работает быстрее кнопки "Загрузить"</li>
                <li>✅ Система покажет предпросмотр перед добавлением</li>
                <li>✅ Можно указать количество: APT123 x 5 или APT123;10</li>
            </ul>
        </section>

        <!-- Работа с ценами -->
        <section id="prices" class="manual-section">
            <h2>Работа с ценами и скидками</h2>

            <h3>Типы цен</h3>
            <table class="manual-table">
                <thead>
                <tr>
                    <th>Тип цены</th>
                    <th>Назначение</th>
                    <th>Типичная скидка</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>МИЦ</strong></td>
                    <td>Минимальная интернет-цена (базовая)</td>
                    <td>0%</td>
                </tr>
                <tr>
                    <td><strong>Мин. опт</strong></td>
                    <td>Минимальная оптовая цена</td>
                    <td>~15%</td>
                </tr>
                <tr>
                    <td><strong>Дилер</strong></td>
                    <td>Дилерская цена</td>
                    <td>~38%</td>
                </tr>
                <tr>
                    <td><strong>Партнер</strong></td>
                    <td>Партнёрская цена</td>
                    <td>~25%</td>
                </tr>
                <tr>
                    <td><strong>Рег. склад</strong></td>
                    <td>Региональный склад</td>
                    <td>~20%</td>
                </tr>
                </tbody>
            </table>

            <h3>⚡ Глобальная смена типа цены (САМОЕ МОЩНОЕ!)</h3>
            <div class="highlight-box">
                <p>Эта функция позволяет изменить тип цены для <strong>ВСЕХ товаров одновременно</strong>!</p>
                <ol>
                    <li>В шапке таблицы найдите кнопку <strong>"Тип цены ▼"</strong></li>
                    <li>Выберите нужный тип (МИЦ, Дилер, Партнер и т.д.)</li>
                    <li>Подтвердите изменение</li>
                    <li>Все цены и скидки обновятся автоматически!</li>
                </ol>
                <div class="note note--success">
                    <strong>✅ Важно:</strong> Даже при повторном выборе того же типа цены система обновит все цены из каталога — это полезно, если цены изменились!
                </div>
            </div>

            <h3>Формула расчёта скидки</h3>
            <div class="formula-box">
                <code>Скидка (%) = (МИЦ - Цена клиента) / МИЦ × 100</code>

                <div class="formula-example">
                    <strong>Пример:</strong>
                    <ul>
                        <li>МИЦ = 7 200 ₽</li>
                        <li>Дилерская цена = 4 465 ₽</li>
                        <li>Скидка = (7200 - 4465) / 7200 × 100 = <strong>38%</strong></li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Изменение количества -->
        <section id="quantity" class="manual-section">
            <h2>Изменение количества товаров</h2>
            <p>Используйте кнопки <strong>[−]</strong> и <strong>[+]</strong> или введите число вручную в поле "Кол-во"</p>
            <div class="note note--info">
                Сумма пересчитывается автоматически при изменении количества
            </div>
        </section>

        <!-- Сохранение -->
        <section id="save" class="manual-section">
            <h2>Сохранение и загрузка КП</h2>

            <h3>Сохранение</h3>
            <ol>
                <li>Нажмите кнопку <strong>"💾 Сохранить КП"</strong></li>
                <li>КП сохранится в вашем личном кабинете</li>
                <li>Появится уведомление с номером КП</li>
            </ol>

            <h3>Просмотр сохранённых КП</h3>
            <ol>
                <li>Нажмите кнопку <strong>"📁 Мои КП"</strong></li>
                <li>Выберите нужное КП из списка</li>
                <li>Нажмите <strong>"Открыть"</strong></li>
                <li>Все данные загрузятся автоматически</li>
            </ol>
        </section>

        <!-- Экспорт -->
        <section id="export" class="manual-section">
            <h2>Экспорт в Excel</h2>

            <h3>Создание документа</h3>
            <ol>
                <li>Заполните информацию о КП</li>
                <li>Добавьте товары</li>
                <li>Настройте цены и скидки</li>
                <li>Нажмите <strong>"📄 Экспорт в Excel"</strong></li>
            </ol>

            <h3>Что будет в файле</h3>
            <ul class="checklist">
                <li>✅ Шапка с датой, заказчиком, объектом</li>
                <li>✅ Ваши контактные данные</li>
                <li>✅ Таблица товаров с фото и параметрами</li>
                <li>✅ Цены, скидки, суммы</li>
                <li>✅ Итоговая сумма с выделением экономии</li>
                <li>✅ НДС 20%</li>
                <li>✅ Логотипы Geniled</li>
                <li>✅ Карта предприятия с реквизитами</li>
                <li>✅ Комментарий (если заполнен)</li>
            </ul>

            <div class="note note--success">
                <strong>Формат файла:</strong> <code>KP_[Заказчик]_[Дата]_[Время].xlsx</code>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="manual-section">
            <h2>Часто задаваемые вопросы</h2>

            <div class="faq-list">
                <details class="faq-item">
                    <summary>❓ Почему не отображаются скидки?</summary>
                    <div class="faq-answer">
                        <p><strong>Причина:</strong> Скидка рассчитывается относительно МИЦ. Если МИЦ не загрузилась, скидка будет 0%.</p>
                        <p><strong>Решение:</strong></p>
                        <ol>
                            <li>Измените тип цены на "МИЦ" для всех товаров</li>
                            <li>Затем переключите на нужный тип (Дилер, Партнер)</li>
                            <li>Скидки пересчитаются автоматически</li>
                        </ol>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Товар не добавляется при поиске</summary>
                    <div class="faq-answer">
                        <p><strong>Возможные причины:</strong></p>
                        <ul>
                            <li>Товара нет в каталоге</li>
                            <li>Артикул указан неправильно</li>
                            <li>У вас нет прав на просмотр этого товара</li>
                        </ul>
                        <p><strong>Решение:</strong> Проверьте артикул или попробуйте искать по названию</p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Как изменить цену только для одного товара?</summary>
                    <div class="faq-answer">
                        <ol>
                            <li>Кликните в поле "Цена" нужного товара</li>
                            <li>Введите новое значение</li>
                            <li>Нажмите Enter</li>
                            <li>Появится кнопка "↻" для сброса</li>
                        </ol>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Можно ли использовать на телефоне?</summary>
                    <div class="faq-answer">
                        <p><strong>Да!</strong> Интерфейс полностью адаптирован для мобильных устройств.</p>
                        <p>Таблица прокручивается горизонтально, кнопки крупнее, итоговая сумма отображается внизу экрана.</p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Куда сохраняются мои КП?</summary>
                    <div class="faq-answer">
                        <p>КП сохраняются в базе данных сайта и привязаны к вашему аккаунту.</p>
                        <p><strong>Где посмотреть:</strong></p>
                        <ul>
                            <li>В генераторе: кнопка "Мои КП"</li>
                            <li>В личном кабинете: раздел "Мои коммерческие предложения"</li>
                        </ul>
                    </div>
                </details>
                <details class="faq-item">
                    <summary>❓ Какие форматы файлов поддерживаются для массовой загрузки?</summary>
                    <div class="faq-answer">
                        <p><strong>Поддерживаются 3 формата:</strong></p>
                        <ul>
                            <li><strong>.txt</strong> — простой текстовый файл (Блокнот)</li>
                            <li><strong>.csv</strong> — таблица в формате CSV</li>
                            <li><strong>.xlsx / .xls</strong> — файлы Microsoft Excel</li>
                        </ul>
                        <p><strong>Рекомендация:</strong> Для больших списков (50+ позиций) используйте Excel</p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Как загрузить артикулы из Excel?</summary>
                    <div class="faq-answer">
                        <ol>
                            <li>Создайте файл .xlsx в Excel</li>
                            <li>В первом столбце укажите артикулы</li>
                            <li>Во втором столбце (опционально) — количество</li>
                            <li>Сохраните файл</li>
                            <li>Перетащите файл в модальное окно массового добавления</li>
                        </ol>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Почему одинаковые артикулы объединяются в одну строку?</summary>
                    <div class="faq-answer">
                        <p><strong>Причина:</strong> Не отмечен чекбокс "Добавлять дубли как отдельные позиции"</p>
                        <p><strong>Решение:</strong></p>
                        <ol>
                            <li>Откройте модальное окно массового добавления</li>
                            <li>Отметьте чекбокс <strong>"Добавлять дубли как отдельные позиции"</strong></li>
                            <li>Теперь каждый артикул будет добавлен отдельной строкой</li>
                        </ol>
                    </div>
                </details>

                <details class="faq-item">
                    <summary>❓ Можно ли указать количество прямо в файле?</summary>
                    <div class="faq-answer">
                        <p><strong>Да!</strong> Поддерживаются следующие форматы:</p>
                        <ul>
                            <li><code>APT123 x 5</code> — через пробел и x</li>
                            <li><code>APT123;10</code> — через точку с запятой</li>
                            <li><code>APT123 * 3</code> — через звездочку</li>
                        </ul>
                        <p>Если количество не указано, добавится 1 шт.</p>
                    </div>
                </details>
            </div>
        </section>

        <!-- Советы -->
        <section id="tips" class="manual-section">
            <h2>🎯 Полезные советы</h2>

            <div class="tips-grid">
                <div class="tip-card">
                    <div class="tip-icon">⚡</div>
                    <h4>Используйте глобальную смену цен</h4>
                    <p>Самый быстрый способ обновить все цены одновременно</p>
                </div>

                <div class="tip-card">
                    <div class="tip-icon">💾</div>
                    <h4>Сохраняйте регулярно</h4>
                    <p>Если работаете над большим КП — сохраняйте промежуточные версии</p>
                </div>

                <div class="tip-card">
                    <div class="tip-icon">✅</div>
                    <h4>Проверяйте скидки</h4>
                    <p>Убедитесь, что все скидки корректны перед экспортом</p>
                </div>

                <div class="tip-card">
                    <div class="tip-icon">📝</div>
                    <h4>Заполняйте комментарий</h4>
                    <p>Укажите сроки, условия оплаты и гарантию</p>
                </div>

                <div class="tip-card">
                    <div class="tip-icon">👤</div>
                    <h4>Используйте автозаполнение</h4>
                    <p>Экономит время и исключает ошибки в контактах</p>
                </div>

                <div class="tip-card">
                    <div class="tip-icon">🔄</div>
                    <h4>Обновляйте страницу при проблемах</h4>
                    <p>Ctrl+F5 решает большинство технических сбоев</p>
                </div>
            </div>
        </section>

        <!-- Футер -->
        <footer class="manual-footer">
            <div class="manual-footer__content">
                <div class="footer-section">
                    <h4>Техническая поддержка</h4>
                    <p>Если возникли проблемы:</p>
                    <ol>
                        <li>Обновите страницу (Ctrl+F5)</li>
                        <li>Очистите кэш браузера</li>
                        <li>Попробуйте другой браузер</li>
                        <li>Обратитесь к администратору</li>
                    </ol>
                </div>

                <div class="footer-section">
                    <h4>Контакты</h4>
                    <p><strong>Разработчик:</strong> @gypsea67 (Telegram)</p>
                    <p><strong>Версия:</strong> 2.0_gen4</p>
                </div>
            </div>

            <div class="manual-footer__bottom">
                <p>© Marketing Geniled 2025 • Powered by <a href="https://t.me/gypsea67" target="_blank">Gypsea67</a></p>
            </div>
        </footer>

    </div>
</main>

<!-- Кнопка "Наверх" -->
<button id="scrollToTop" class="scroll-to-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 15l-6-6-6 6"/>
    </svg>
</button>

<script>
    // Подсветка активного раздела в меню
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                document.querySelectorAll('.manual-nav__link').forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${id}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.manual-section').forEach(section => {
        observer.observe(section);
    });

    // Показ кнопки "Наверх"
    window.addEventListener('scroll', () => {
        const btn = document.getElementById('scrollToTop');
        if (window.scrollY > 500) {
            btn.classList.add('show');
        } else {
            btn.classList.remove('show');
        }
    });

    // Плавная прокрутка к якорям
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>

</body>
</html>