<?php
/**
 * Генератор коммерческих предложений
 * Главная страница приложения
 */

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('DisableEventsCheck', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

// Подключаем модули
Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Подключаем конфигурацию
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Проверка авторизации и прав
checkAuth();
checkPermissions();

// Обработка AJAX-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/includes/ajax_handler.php';
    exit;
}

// Получаем доступные типы цен для пользователя
$allowedPriceOptions = getAllowedPriceTypes();
$allowedPriceOptionsJson = json_encode($allowedPriceOptions, JSON_UNESCAPED_UNICODE);

// Получаем данные текущего пользователя для автозаполнения
global $USER;
$currentUser   = CUser::GetByID($USER->GetID())->Fetch() ?: [];
$defaultManager  = trim(($currentUser['NAME'] ?? '') . ' ' . ($currentUser['LAST_NAME'] ?? ''));
$defaultEmail    = $currentUser['EMAIL'] ?? '';
$defaultPhone    = ($currentUser['WORK_PHONE'] ?? '') ?: ($currentUser['PERSONAL_MOBILE'] ?? '');
$defaultPosition = $currentUser['UF_POSITION'] ?? '';
$defaultCompany  = $currentUser['WORK_COMPANY'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Генератор КП - Geniled</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?=filemtime(__DIR__.'/assets/css/styles.css')?>">
    <link rel="stylesheet" href="assets/css/fonts.css?v=<?=filemtime(__DIR__.'/assets/css/fonts.css')?>">
    <style>
        .btn-manual {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(114, 46, 133, 0.15);
            color: #722e85;
            border: 2px solid rgba(114, 46, 133, 0.3);
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            flex-shrink: 0;
            cursor: pointer;
        }

        .btn-manual:hover {
            background: #722e85;
            color: white;
            border-color: #722e85;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(114, 46, 133, 0.3);
        }

        .btn-manual svg {
            flex-shrink: 0;
        }

        .btn-manual--desktop {
            display: inline-flex !important;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
        }

        .btn-manual--desktop svg {
            width: 20px;
            height: 20px;
        }

        .btn-manual--mobile {
            display: none !important;
            padding: 8px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .btn-manual--mobile svg {
            width: 22px;
            height: 22px;
        }

        @media screen and (max-width: 900px) {
            .btn-manual--desktop {
                display: none !important;
            }

            .btn-manual--mobile {
                display: inline-flex !important;
            }
        }

        @media screen and (max-width: 480px) {
            .btn-manual--mobile {
                width: 36px !important;
                height: 36px !important;
                padding: 6px !important;
            }

            .btn-manual--mobile svg {
                width: 20px !important;
                height: 20px !important;
            }
        }
    </style>

    <script>
        window.ALLOWED_PRICE_OPTIONS = <?=$allowedPriceOptionsJson?>;
        window.BITRIX_SESSID = '<?=$sessid?>';
    </script>
</head>
<body>

<input type="hidden" name="sessid" value="<?=bitrix_sessid()?>">
<div class="page-header">
    <h1 class="page-title">Генератор КП</h1>
    <a href="manual.php" class="btn-manual btn-manual--inline" title="Руководство пользователя" target="_blank" style="padding:10px 14px; border-radius:8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right:8px;">
            <path d="M11 7h2v2h-2zm0 4h2v6h-2zM12 2a10 10 0 100 20 10 10 0 000-20z"/>
        </svg>
        <span>Руководство</span>
    </a>
    <div class="logo-wrap">
        <svg width="114" height="35" viewBox="-2 -2 118 39" fill="none" xmlns="http://www.w3.org/2000/svg" class="logo">
            <defs>
                <linearGradient id="animatedGradient" x1="0%" y1="0%" x2="200%" y2="0%">
                    <stop offset="0%" style="stop-color:#722e85;stop-opacity:1">
                        <animate attributeName="offset" values="-2;1" dur="3s" repeatCount="indefinite"></animate>
                    </stop>
                    <stop offset="50%" style="stop-color:#a855f7;stop-opacity:1">
                        <animate attributeName="offset" values="-1;2" dur="3s" repeatCount="indefinite"></animate>
                    </stop>
                    <stop offset="100%" style="stop-color:#722e85;stop-opacity:1">
                        <animate attributeName="offset" values="0;3" dur="3s" repeatCount="indefinite"></animate>
                    </stop>
                </linearGradient>
            </defs>
            <g>
                <path fill="url(#animatedGradient)" d="M109.722 2.43555V9.11651C108.285 7.92057 106.573 7.39043 104.735 7.39043C99.6314 7.39043 95.4694 11.5251 95.4694 16.5948C95.4694 21.2804 99.0054 25.1541 103.577 25.7219C105.878 26.0057 108.827 25.0247 110.04 23.7745L110.325 25.2168H114V2.43555H109.722ZM104.735 21.8251C101.978 21.8251 99.747 19.6086 99.747 16.5948C99.747 13.5809 101.97 11.3644 104.735 11.3644C107.5 11.3644 109.722 13.5809 109.722 16.5948C109.722 19.6086 107.5 21.8251 104.735 21.8251Z"></path>
                <path fill="url(#animatedGradient)" d="M73.1573 0L68.8819 0.836945V25.2211H73.1573V0Z"></path>
                <path fill="url(#animatedGradient)" d="M45.4518 9.44641L45.2165 8.28387H41.3234V25.219H45.5989V16.5907C45.5989 13.7564 47.4645 11.5273 50.3176 11.3457C53.7022 11.1307 54.6246 13.6875 54.6246 16.5907V25.219H58.9V16.5907C58.9 14.5307 58.6689 12.7691 58.047 11.3374C55.904 6.39709 49.1054 6.36369 45.4518 9.44641Z"></path>
                <path fill="url(#animatedGradient)" d="M66.0224 8.08789L61.747 8.92275V25.2213H66.0224V8.08789Z"></path>
                <path fill="url(#animatedGradient)" d="M66.0227 1.41309L61.7493 2.25003V6.92524L66.0227 6.09038V1.41309Z"></path>
                <path fill="url(#animatedGradient)" d="M84.7842 7.39271C79.681 7.39271 75.5274 11.5274 75.5274 16.597C75.5274 20.4937 77.5107 23.4763 80.8848 24.8809C83.9501 26.1541 88.5281 25.8473 91.5871 24.7348L90.9232 21.3975C86.3599 22.871 81.0319 22.1488 80.076 18.4442H93.3246C94.6062 13.4058 91.4631 7.39062 84.7842 7.39062V7.39271ZM80.1831 14.6727C81.0403 10.3544 88.9231 10.1018 89.4567 14.6727H80.1831Z"></path>
                <path fill="url(#animatedGradient)" d="M30.1571 7.39271C25.0539 7.39271 20.9003 11.5274 20.9003 16.597C20.9003 20.4937 22.8836 23.4763 26.2577 24.8809C29.323 26.1541 33.901 25.8473 36.96 24.7348L36.2961 21.3975C31.7328 22.871 26.4048 22.1488 25.4488 18.4442H38.6975C39.9875 13.4058 36.8444 7.39062 30.1571 7.39062V7.39271ZM25.5623 14.6727C26.4111 10.3544 34.3023 10.1018 34.8275 14.6727H25.5623Z"></path>
                <path fill="url(#animatedGradient)" d="M14.9168 8.2819L14.6164 9.34843C13.1415 7.79977 10.4081 7.1778 8.09919 7.46165C3.53591 8.02936 0 11.9031 0 16.5971C0 21.6668 4.15359 25.7931 9.26522 25.7931C11.0952 25.7931 12.8095 25.2629 14.2529 24.067C14.2529 25.8849 14.152 27.4962 12.7087 28.8612C9.87449 31.5536 4.66412 29.9361 3.52751 26.4067L0.17648 27.0662C1.01056 31.2844 4.76287 34.2001 9.26312 34.2001C15.3853 34.2001 18.5199 29.7753 18.5199 24.067V8.2819H14.9147H14.9168ZM9.26522 21.8275C6.50036 21.8275 4.27755 19.6193 4.27755 16.5971C4.27755 13.5749 6.50876 11.3584 9.26522 11.3584C12.0217 11.3584 14.2529 13.5666 14.2529 16.5971C14.2529 19.6276 12.0133 21.8275 9.26522 21.8275Z"></path>
            </g>
        </svg>
    </div>
</div>

<div class="kp-info-wrapper">
    <div class="kp-info-header-row">
        <button type="button" class="btn-kp-info-toggle" onclick="toggleKpInfo()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M11 7h2v2h-2zm0 4h2v6h-2zM12 2a10 10 0 100 20 10 10 0 000-20z"></path>
            </svg>
            <span>Информация о КП</span>
            <svg class="toggle-arrow" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="transform: rotate(-90deg);">
                <path d="M7 10l5 5 5-5z"></path>
            </svg>
        </button>
        <button type="button" class="btn-bitrix-profile state-stale" onclick="loadFromBitrixProfile()" title="Загрузить ФИО, Email, Телефон и Компанию из вашего профиля Битрикс">
        <span class="kp-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z"></path>
                <path d="M4 18c0-2.66 5.33-4 8-4s8 1.34 8 4v2H4v-2z"></path>
            </svg>
        </span>
            <span>Загрузить из профиля Битрикс</span>
        </button>
    </div>

    <div class="kp-info-content kp-info-content--hidden" id="kpInfoContent">
        <div class="grid grid--two-cols">
            <div class="form-field">
                <label>Дата КП</label>
                <input type="date" id="kpDate" value="<?=date('Y-m-d')?>">
            </div>
            <div class="form-field">
                <label>Email менеджера <span class="label-hint">(из Битрикс)</span></label>
                <input type="email" id="managerEmail" placeholder="manager@example.com" value="<?=htmlspecialchars($defaultEmail)?>">
            </div>

            <div class="form-field">
                <label>Заказчик</label>
                <input type="text" id="customerName" placeholder="Название компании">
            </div>
            <div class="form-field">
                <label>Компания <span class="label-hint">(из Битрикс)</span></label>
                <input type="text" id="managerCompany" placeholder="ООО ИнПродакшн" value="<?=htmlspecialchars($defaultCompany)?>">
            </div>

            <div class="form-field">
                <label>Объект</label>
                <input type="text" id="objectName" placeholder="Адрес или описание объекта">
            </div>
            <div class="form-field">
                <label>Менеджер <span class="label-hint">(из Битрикс)</span></label>
                <input type="text" id="managerName" placeholder="Иванов Иван Иванович" value="<?=htmlspecialchars($defaultManager)?>">
            </div>

            <div class="form-field">
                <label>Должность менеджера <span class="label-hint">(из Битрикс)</span></label>
                <input type="text" id="managerPosition" placeholder="Координатор отдела продаж" value="<?=htmlspecialchars($defaultPosition)?>">
            </div>
            <div class="form-field">
                <label>Телефон менеджера <span class="label-hint">(из Битрикс)</span></label>
                <input type="tel" id="managerPhone" placeholder="+7 (xxx) xxx-xx-xx" value="<?=htmlspecialchars($defaultPhone)?>">
            </div>

            <div class="form-field form-field--full">
                <label>Комментарий</label>
                <textarea id="comment" placeholder="Укажите описание, сроки поставки и т.д"></textarea>
            </div>
        </div>
    </div>
</div>

<div class="input-section">
    <div class="input-row">
        <div class="search-block">
            <label for="siteSearchInput" class="search-label">Поиск по названию и артикулу</label>
            <div class="search-inline" style="position: relative;">
                <input type="text" id="siteSearchInput" placeholder="Например: светильник офисный" autocomplete="off">
                <div id="liveSearchResults"
                     style="position:absolute; top:105%; left:0; width:100%; background:#fff;
                            border:1px solid #ddd; border-radius:6px;
                            box-shadow:0 2px 8px rgba(0,0,0,.08); z-index:1000;
                            display:none; max-height:420px; overflow:auto;"></div>
            </div>
        </div>

        <button class="secondary" onclick="toggleBulkAdd(event)">Добавить артикулы списком</button>
    </div>
</div>

<script>
    let searchTimer;

    function runLiveSearch(query){
        if (!query || query.trim().length < 2) {
            document.getElementById('liveSearchResults').style.display = 'none';
            return;
        }
        fetch('/local/tools/kp_gen4/ajax/ajax_search.php?q=' + encodeURIComponent(query.trim()))
            .then(r => r.text())
            .then(html => {
                const box = document.getElementById('liveSearchResults');
                box.innerHTML = html;
                box.style.display = html.trim() ? 'block' : 'none';

                if (window.markAddedInLiveSearch) {
                    window.markAddedInLiveSearch();
                }

                try {
                    const imgs = box.querySelectorAll('img[data-src], img[data-lazyload]');
                    imgs.forEach(img => {
                        const real = img.getAttribute('data-src') || img.getAttribute('data-original') || '';
                        if (real) img.setAttribute('src', real);
                        img.removeAttribute('data-lazyload');
                        img.removeAttribute('data-original');
                    });
                } catch (e) { console.warn('force image load failed', e); }
            })
            .catch(() => {
                document.getElementById('liveSearchResults').style.display = 'none';
            });
    }

    const siteSearchInput = document.getElementById('siteSearchInput');
    siteSearchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        clearTimeout(searchTimer);
        if (query.length < 2) {
            document.getElementById('liveSearchResults').style.display = 'none';
            return;
        }
        searchTimer = setTimeout(() => runLiveSearch(query), 350);
    });

    ;['focus','click'].forEach(evt => {
        siteSearchInput.addEventListener(evt, () => {
            const query = siteSearchInput.value.trim();
            const box = document.getElementById('liveSearchResults');
            if (query.length >= 2) {
                if (box && box.innerHTML.trim()) {
                    box.style.display = 'block';
                } else {
                    runLiveSearch(query);
                }
            }
        });
    });

    document.addEventListener('click', (e) => {
        const box = document.getElementById('liveSearchResults');
        const input = document.getElementById('siteSearchInput');
        if (box && !box.contains(e.target) && e.target !== input) box.style.display = 'none';
    });
</script>

<div id="bulkArea" class="bulk-area">
    <div class="hint">
        Каждую позицию с новой строки. Допускается формат
        <code>АРТ123;5</code>, <code>АРТ123 x 2</code> или просто артикул.
        <br>
        <label style="margin-top:8px; display:inline-block;">
            <input type="checkbox" id="addAsSeparate"> Добавлять дубли как отдельные позиции
        </label>
    </div>
    <textarea id="bulkTextarea" placeholder="АРТ123;5&#10;АРТ456&#10;АРТ789 x 2"></textarea>
    <div class="inline-actions">
        <button type="button" class="primary" onclick="addMultiple()">Добавить из списка</button>
        <button type="button" class="secondary" onclick="document.getElementById('bulkTextarea').value=''">Очистить</button>
    </div>
</div>

<div id="kpWrap" style="display:none">
    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>Фото</th>
                <th>Артикул</th>
                <th>Наименование / Параметры</th>
                <th>Ед.</th>
                <th>Кол-во</th>

                <th style="text-align:center; vertical-align:middle;">
                    <div class="price-header">
                        <div class="price-header__label">Цена</div>
                        <div class="custom-select global-price-select" id="globalPriceTypeSelectWrap">
                            <button type="button" class="custom-select__button" onclick="toggleGlobalPriceSelect(this)">Тип цены</button>
                            <ul class="custom-select__list">
                                <?php foreach ($allowedPriceOptions as $opt): if(!empty($opt['id']) && !empty($opt['name'])): ?>
                                    <li class="custom-select__item" data-value="<?=htmlspecialcharsbx($opt['id'])?>" onclick="selectGlobalPriceType(this)"><?=htmlspecialcharsbx($opt['name'])?></li>
                                <?php endif; endforeach; ?>
                            </ul>
                            <select id="globalPriceTypeSelect" class="custom-select__native" onchange="changeAllPriceTypes(this.value)">
                                <option value="">Тип цены ▼</option>
                                <?php foreach ($allowedPriceOptions as $opt): if(!empty($opt['id']) && !empty($opt['name'])): ?>
                                    <option value="<?=htmlspecialcharsbx($opt['id'])?>"><?=htmlspecialcharsbx($opt['name'])?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                    </div>
                </th>

                <th style="padding: 8px 4px;">Скидка<br><small>%</small></th>
                <th>Сумма</th>
                <th>Уд-е</th>
            </tr>
            </thead>

            <tbody id="tbody"></tbody>

            <tfoot class="desktop-total-footer">
            <tr class="total-row">
                <td colspan="8" style="text-align: right; font-weight: 700; font-size: 16px; color: #722e85; padding: 16px;">
                    ИТОГО:
                </td>
                <td id="totalCell" style="font-weight: 700; font-size: 18px; color: #722e85; padding: 16px;">
                    0,00 ₽
                </td>
                <td></td>
            </tr>
            </tfoot>
        </table>
        <!-- ✅ Премиум блок ИТОГО для десктопа -->
        <div id="desktopTotalBlock" class="desktop-total-block">
            <div class="total-bar">
                <span class="total-label">ИТОГО</span>
                <span class="total-amount" id="desktopTotalAmount">0,00 ₽</span>
            </div>
        </div>
    </div>
</div>

<!-- ✅ КНОПКА СМЕНЫ ТИПА ЦЕНЫ ДЛЯ МОБИЛЬНЫХ -->
<div class="mobile-price-controls">
    <button class="action-btn action-btn--secondary" onclick="showMobilePriceTypeModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
        <span>Изменить тип цены для всех</span>
    </button>
</div>

<!-- ✅ БЛОК ИТОГО НА МОБИЛЬНЫХ (показывается только когда есть товары) -->
<div id="mobileTotalBlock">
    <div class="total-bar">
        <span class="total-label">ИТОГО:</span>
        <span class="total-amount" id="mobileTotalAmount">0 ₽</span>
    </div>
</div>

<div class="actions">
    <button class="action-btn action-btn--primary" onclick="saveKp()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
        </svg>
        <span>Сохранить КП</span>
    </button>

    <button class="action-btn action-btn--secondary" onclick="showSavedKpList()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        <span>Мои КП</span>
    </button>

    <button class="action-btn action-btn--secondary" onclick="exportExcel()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="12" y1="18" x2="12" y2="12"/>
            <line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        <span>Экспорт в Excel</span>
    </button>

    <button class="action-btn action-btn--danger" onclick="clearAll()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        </svg>
        <span>Очистить</span>
    </button>
</div>

<div id="toast" class="toast"></div>

<script>
    function toggleKpInfo() {
        const content = document.getElementById('kpInfoContent');
        if (!content) {
            console.error('Element kpInfoContent not found');
            return;
        }

        const arrow = document.querySelector('.toggle-arrow');
        const isVisible = content.classList.contains('kp-info-content--visible');

        if (isVisible) {
            content.classList.remove('kp-info-content--visible');
            content.classList.add('kp-info-content--hidden');
            if (arrow) arrow.style.transform = 'rotate(-90deg)';
        } else {
            content.classList.remove('kp-info-content--hidden');
            content.classList.add('kp-info-content--visible');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }

        console.log('Toggle completed. Is visible:', !isVisible);
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function(){
        try{
            const params = new URLSearchParams(window.location.search);
            const kpId = parseInt(params.get('kp_id')||'0',10);
            if (kpId > 0) {
                (function waitAndLoad(){
                    if (typeof window.loadSavedKp === 'function') {
                        window.loadSavedKp(kpId);
                    } else {
                        setTimeout(waitAndLoad, 50);
                    }
                })();
            }
        }catch(e){ console.warn('kp_id autoload failed', e); }
    });
</script>

<div class="page-footer">
    <p>v2.0_gen4 Powered ©Marketing Geniled 2025 by <a href="https://t.me/gypsea67" target="_blank" rel="noopener">Gypsea67</a></p>
</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close-btn" onclick="closeConfirmModal()">&times;</button>
        <div class="modal-icon warning">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" fill="currentColor"/>
            </svg>
        </div>
        <h3 class="modal-title" id="confirmTitle">Изменить тип цены?</h3>
        <p class="modal-message" id="confirmMessage">Изменить тип цены для всех товаров?</p>
        <div class="modal-buttons">
            <button class="modal-btn cancel" onclick="closeConfirmModal()">Отмена</button>
            <button class="modal-btn confirm" id="confirmActionBtn">Изменить</button>
        </div>
    </div>
</div>

<div id="priceChangeModal" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close-btn" onclick="document.getElementById('priceChangeModal').classList.remove('show')">&times;</button>
        <div class="modal-icon success">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
            </svg>
        </div>
        <h3 class="modal-title">Тип цен изменён</h3>
        <p class="modal-message">Цены для всех товаров обновлены</p>
        <div class="modal-progress"></div>
    </div>
</div>

<div id="savedKpModal" class="modal-overlay">
    <div class="modal-content" style="max-width:720px; width:95%; text-align:left;">
        <button class="modal-close-btn" onclick="closeSavedKpModal()">&times;</button>
        <div class="modal-icon success">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
            </svg>
        </div>
        <h3 class="modal-title">Мои КП</h3>
        <div id="savedKpList" style="margin:16px 0; max-height:340px; overflow:auto;"></div>
        <div class="modal-buttons">
            <button class="modal-btn cancel" onclick="closeSavedKpModal()">Закрыть</button>
        </div>
    </div>
    <style>
        #savedKpList .kp-item{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #eee;}
        #savedKpList .kp-item:last-child{border-bottom:none;}
        #savedKpList .kp-title{font-weight:600; color:#333;}
        #savedKpList .kp-meta{font-size:12px; color:#666;}
        #savedKpList .kp-actions{display:flex; gap:8px;}
        #savedKpList .kp-actions button{min-width:auto; padding:8px 12px;}
        #savedKpModal .modal-content{padding:24px;}
        #savedKpModal .modal-icon{width:40px; height:40px; margin:0 auto 8px;}
        #savedKpModal .modal-icon svg{width:32px; height:32px;}
        #savedKpModal .modal-title{font-size:18px; margin:0 0 8px;}
        @media (max-width: 680px){
            #savedKpModal .modal-content{padding:16px;}
            #savedKpModal .modal-title{font-size:16px;}
            #savedKpModal .modal-buttons .modal-btn{min-width:100px; padding:10px 14px;}
        }
    </style>
</div>

<div id="mobilePriceTypeModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <button class="modal-close-btn" onclick="document.getElementById('mobilePriceTypeModal').classList.remove('show')">&times;</button>
        <div class="modal-icon success">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <h3 class="modal-title">Выберите тип цены</h3>
        <div id="mobilePriceTypeList" class="mobile-price-list">
            <!-- Список будет заполнен через JS -->
        </div>
    </div>
</div>
<!-- Библиотека для drag-and-drop для свитча строчек в таблице с товарами -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<script src="assets/js/app.js?v=<?=filemtime(__DIR__.'/assets/js/app.js')?>"></script>
<script src="assets/js/price-math.js?v=<?=filemtime(__DIR__.'/assets/js/price-math.js')?>"></script>
<script src="assets/js/product-manager.js?v=<?=filemtime(__DIR__.'/assets/js/product-manager.js')?>"></script>
<script src="assets/js/ui.js?v=<?=filemtime(__DIR__.'/assets/js/ui.js')?>"></script>
<script src="assets/js/profiles.js?v=<?=filemtime(__DIR__.'/assets/js/profiles.js')?>"></script>
<script src="assets/js/drag-drop.js?v=<?=filemtime(__DIR__.'/assets/js/drag-drop.js')?>"></script>
<script src="assets/js/sw-register.js?v=<?=filemtime(__DIR__.'/assets/js/sw-register.js')?>"></script>
<!-- Дисклеймер -->
<div class="disclaimer">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
    </svg>
    <p><strong>Внимание:</strong> Подбор компонентов системы носит рекомендательный характер. Ответственность за финальную проверку совместимости и соответствие проектным требованиям несет Пользователь.</p>
</div>
</body>
</html>