/**
 * Показать модальное окно выбора типа цен на мобильной версии
 * ----------------------------------
 * Открывает список глобальных типов цен в модальном окне
 * и применяет выбранный тип ко всем товарам.
 */
function showMobilePriceTypeModal() {
    const modal = document.getElementById('mobilePriceTypeModal');
    const list = document.getElementById('mobilePriceTypeList');
    if (!modal || !list) return;

    list.innerHTML = '';

    const select = document.getElementById('globalPriceTypeSelect');
    if (!select) return;

    Array.from(select.options).forEach(option => {
        if (!option.value) return;
        const item = document.createElement('div');
        item.className = 'mobile-price-type-item';
        item.textContent = option.textContent;
        item.dataset.value = option.value;

        if (option.selected) {
            item.classList.add('active');
        }

        item.addEventListener('click', function() {
            const priceTypeId = this.dataset.value;
            changeAllPriceTypes(priceTypeId);
            modal.classList.remove('show');
        });

        list.appendChild(item);
    });

    modal.classList.add('show');
}

/**
 * Основной модуль приложения
 */

document.addEventListener('DOMContentLoaded', () => {
    const bulkArea = document.getElementById('bulkArea');
    if (bulkArea && !bulkArea.style.display) {
        bulkArea.style.display = 'none';
    }

    updateMobileSticky();
});

const SESSID = document.querySelector('[name="sessid"]')?.value || '';
const ALLOWED_PRICE_OPTIONS = window.ALLOWED_PRICE_OPTIONS || [];

let products = [];
let counter = 0;
let currentKpId = null;
let currentKpTitle = null;

/**
 * Инициализация приложения
 */
document.addEventListener('DOMContentLoaded', () => {
    initEventListeners();
    updateMobileSticky();
});

/**
 * Инициализация обработчиков событий
 */
function initEventListeners() {
    const articleInput = document.getElementById('articleInput');
    if (articleInput) {
        articleInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                addProduct();
            }
        });
    }

    document.addEventListener('click', (e) => {
        const bulkArea = document.getElementById('bulkArea');
        const bulkBtn = document.querySelector('[onclick^="toggleBulkAdd"]');

        if (bulkArea && bulkArea.style.display === 'block') {
            if (!bulkArea.contains(e.target) && !bulkBtn.contains(e.target)) {
                bulkArea.style.display = 'none';
            }
        }
    });
}

/**
 * Переключает блок массового добавления
 */
function toggleBulkAdd(e) {
    if (e) e.stopPropagation();

    const area = document.getElementById('bulkArea');
    if (!area) return;

    area.style.display = (area.style.display === 'block') ? 'none' : 'block';
}

/**
 * Вспомогательные функции
 */
function toast(msg, isError = false) {
    const toastEl = document.getElementById('toast');
    if (!toastEl) return;

    toastEl.textContent = msg;
    toastEl.className = 'toast' + (isError ? ' error' : '');
    toastEl.style.display = 'block';

    setTimeout(() => {
        toastEl.style.display = 'none';
    }, 2500);
}

function money(value, currency = 'RUB') {
    const num = Number(value) || 0;
    const formatted = num.toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    const symbol = currency === 'RUB' ? '₽' : currency;
    return formatted + '\u00A0' + symbol;
}

async function post(action, payload) {
    const body = new URLSearchParams({
        action,
        sessid: SESSID,
        ...payload
    });

    const response = await fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body
    });

    return await response.json();
}

/**
 * Построение данных КП для отправки
 */
function buildKpInfo() {
    const dateField = document.getElementById('kpDate');
    const date = dateField && dateField.value ? new Date(dateField.value) : new Date();

    return {
        date: date.toLocaleDateString('ru-RU'),
        customer: fieldValue('customerName'),
        object: fieldValue('objectName'),
        manager: fieldValue('managerName'),
        position: fieldValue('managerPosition'),
        phone: fieldValue('managerPhone'),
        email: fieldValue('managerEmail'),
        company: fieldValue('managerCompany'),
        comment: fieldValue('comment')
    };
}

function fieldValue(id) {
    const field = document.getElementById(id);
    return field ? field.value.trim() : '';
}

function setFieldValue(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val || "";
}

/**
 * Заполнение тестовых данных
 */
function fillTestData() {
    const today = new Date();
    const fields = {
        'kpDate': today.toISOString().split('T')[0],
        'customerName': 'ООО "Рога и Копыта"',
        'objectName': 'Москва, ул. Ленина, д. 15, офис 301',
        'managerName': 'Иванов Иван Иванович',
        'managerPosition': 'Координатор отдела продаж',
        'managerEmail': 'ivanov@rogaikopyta.ru',
        'managerPhone': '+7 (495) 123-45-67',
        'managerCompany': 'ООО Инпродакшн',
        'comment': 'Срок поставки: 14 рабочих дней. Гарантия: 3 года. Монтаж обсуждается отдельно.'
    };

    for (const [id, value] of Object.entries(fields)) {
        const el = document.getElementById(id);
        if (el) {
            el.value = value;
        }
    }

    toast('Тестовые данные заполнены');
}

/**
 * ✅ Загрузка профиля менеджера из Битрикс
 */
async function loadBitrixProfile() {
    try {
        const res = await post('get_bitrix_profile', {});

        if (res?.success && res.data) {
            const data = res.data;

            if (data.manager_name) setFieldValue('managerName', data.manager_name);
            if (data.manager_email) setFieldValue('managerEmail', data.manager_email);
            if (data.manager_phone) setFieldValue('managerPhone', data.manager_phone);
            if (data.manager_position) setFieldValue('managerPosition', data.manager_position);
            if (data.manager_company) setFieldValue('managerCompany', data.manager_company);

            toast('Профиль загружен из Битрикс');
        } else {
            toast(res?.error || 'Не удалось загрузить профиль', true);
        }
    } catch (e) {
        console.error('loadBitrixProfile error:', e);
        toast('Ошибка при загрузке профиля', true);
    }
}

/**
 * Очистка всех товаров
 */
function clearAll() {
    if (confirm('Очистить таблицу товаров?')) {
        products = [];
        currentKpId = null;
        currentKpTitle = null;
        render();
        toast('Таблица очищена');

        if (typeof window.markAddedInLiveSearch === 'function') {
            window.markAddedInLiveSearch();
        }

        updateMobileSticky();

        if (typeof window.updateTableVisibility === 'function') {
            window.updateTableVisibility();
        }
    }
}

/**
 * Экспорт в Excel
 */
function exportExcel() {
    if (products.length === 0) {
        toast('Добавьте товары для экспорта', true);
        return;
    }

    postForm('export_excel', {
        products: JSON.stringify(products),
        kp_info: JSON.stringify(buildKpInfo())
    });

    toast('Генерация Excel…');
}

/**
 * Экспорт в PDF
 */
function exportPdf() {
    if (products.length === 0) {
        toast('Добавьте товары для экспорта', true);
        return;
    }

    postForm('export_pdf', {
        products: JSON.stringify(products),
        kp_info: JSON.stringify(buildKpInfo())
    });

    toast('Генерация PDF…');
}

/**
 * Отправка формы в новом окне
 */
function postForm(action, payload) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.target = '_blank';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);

    const sessidInput = document.createElement('input');
    sessidInput.type = 'hidden';
    sessidInput.name = 'sessid';
    sessidInput.value = SESSID;
    form.appendChild(sessidInput);

    for (const [key, value] of Object.entries(payload)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = typeof value === 'string' ? value : JSON.stringify(value);
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

/**
 * Добавление или удаление товара из живого поиска (toggle)
 * ✅ ИСПРАВЛЕНО: Четкое разделение логики добавления/удаления
 */
function addProductFromSearch(article) {
    if (!article) {
        toast('Пустой артикул', true);
        return;
    }

    // Проверяем, есть ли товар в таблице
    const existingProduct = products.find(p => {
        const pArticle = String(p.article || p.xml_id || p.code || '').trim();
        const searchArticle = String(article).trim();
        return pArticle === searchArticle;
    });

    if (existingProduct) {
        // ✅ Товар уже добавлен — УДАЛЯЕМ ЕГО
        console.log('🗑 Удаляем товар из таблицы:', article);
        if (typeof removeProduct === 'function') {
            removeProduct(existingProduct.rowId);
        } else {
            console.error('Функция removeProduct() не найдена');
            toast('Ошибка: removeProduct() не найдена', true);
        }
    } else {
        // ✅ Товар не добавлен — ДОБАВЛЯЕМ ЕГО
        console.log('➕ Добавляем товар в таблицу:', article);

        if (typeof addProductByArticle === 'function') {
            addProductByArticle(article, 1, false);
        } else {
            console.error('Функция addProductByArticle() не найдена');
            toast('Ошибка: addProductByArticle() не найдена', true);
        }
    }
}

/**
 * ✅ ПРАВИЛЬНАЯ ВЕРСИЯ: Обновляет отметки "Уже в КП" в живом поиске (toggle версия)
 * Делает кнопку КЛИКАБЕЛЬНОЙ для удаления товара
 */
window.markAddedInLiveSearch = function() {
    console.log('🔄 markAddedInLiveSearch: обновляем кнопки в живом поиске...');

    try {
        const box = document.getElementById('liveSearchResults');
        if (!box || box.style.display === 'none') {
            console.log('⚠️ Живой поиск не отображается');
            return;
        }

        const buttons = box.querySelectorAll('.btn-add-product');
        console.log(`📦 Найдено кнопок в живом поиске: ${buttons.length}`);
        console.log(`📦 Товаров в КП: ${products.length}`);

        let markedCount = 0;

        buttons.forEach(btn => {
            const article = btn.getAttribute('data-article');
            if (!article) {
                console.warn('⚠️ Кнопка без data-article', btn);
                return;
            }

            // Проверяем наличие товара в массиве
            const exists = products.some(p => {
                const pArticle = String(p.article || p.xml_id || p.code || '').trim();
                const searchArticle = String(article).trim();
                return pArticle === searchArticle;
            });

            if (exists) {
                // ✅ Товар уже в КП — зеленая кнопка с галочкой, КЛИКАБЕЛЬНАЯ для удаления
                btn.disabled = false; // ✅ Делаем кликабельной
                btn.textContent = 'Уже в КП';
                btn.classList.add('in-kp');
                markedCount++;
                console.log(`✓ Кнопка помечена как "в КП": ${article}`);
            } else {
                // Товар не в КП — фиолетовая кнопка "Добавить"
                btn.disabled = false;
                btn.textContent = 'Добавить';
                btn.classList.remove('in-kp');
            }
        });

        console.log(`✅ Помечено зелёных кнопок: ${markedCount} из ${buttons.length}`);
    } catch (e) {
        console.error('❌ Ошибка в markAddedInLiveSearch:', e);
    }
};

/**
 * Показать модальное окно подтверждения
 */
function showConfirmModal(message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const messageEl = document.getElementById('confirmMessage');
    const titleEl = document.getElementById('confirmTitle');
    const confirmBtn = document.getElementById('confirmActionBtn');

    if (!modal) return;

    if (titleEl) {
        if (typeof message === 'string' && message.indexOf('КП сохранено') === 0) {
            titleEl.textContent = 'КП сохранено';
            if (confirmBtn) confirmBtn.textContent = 'Список моих КП';
        } else {
            titleEl.textContent = 'Изменить тип цены?';
            if (confirmBtn) confirmBtn.textContent = 'Изменить';
        }
    }
    if (messageEl) {
        messageEl.textContent = message;
    }

    confirmBtn.onclick = () => {
        closeConfirmModal();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };

    modal.classList.add('show');
}

/**
 * Закрыть модальное окно подтверждения
 */
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Показать модальное окно результата смены цен
 */
function showPriceChangeModal(priceTypeName) {
    const modal = document.getElementById('priceChangeModal');
    if (!modal) return;

    const message = modal.querySelector('.modal-message');

    if (message) {
        if (priceTypeName) {
            message.textContent = `Применён тип цен: ${priceTypeName}`;
        } else {
            message.textContent = 'Цены для всех товаров обновлены';
        }
    }

    modal.classList.add('show');

    setTimeout(() => {
        modal.classList.remove('show');
    }, 3000);
}

/**
 * Глобальная функция для переключения селекта (вызывается из HTML)
 */
function toggleGlobalPriceSelect(button) {
    const wrapper = button.closest('.custom-select');
    if (!wrapper) return;

    const isOpen = wrapper.classList.contains('open');

    document.querySelectorAll('.custom-select.open').forEach(s => {
        if (s !== wrapper) {
            s.classList.remove('open');
        }
    });

    wrapper.classList.toggle('open');
}

/**
 * Выбор типа цены из глобального селекта (вызывается из HTML)
 */
function selectGlobalPriceType(item) {
    const priceTypeId = item.getAttribute('data-value');
    const priceTypeName = item.textContent;

    if (!priceTypeId) return;

    const nativeSelect = document.getElementById('globalPriceTypeSelect');
    if (nativeSelect) {
        nativeSelect.value = priceTypeId;
    }

    const button = item.closest('.custom-select').querySelector('.custom-select__button');
    if (button) {
        button.textContent = priceTypeName;
    }

    const wrapper = item.closest('.custom-select');
    if (wrapper) {
        wrapper.classList.remove('open');
    }

    changeAllPriceTypes(priceTypeId);
}

/**
 * Фиксированный мобильный "ИТОГО" sticky footer
 */
function updateMobileSticky() {
    const block = document.getElementById('mobileTotalBlock');
    const totalEl = document.getElementById('mobileTotalAmount');

    if (!block || !totalEl) return;

    const hasRows = Array.isArray(products) && products.length > 0;

    if (hasRows) {
        const total = products.reduce((sum, p) => sum + ((+p.price || 0) * (+p.quantity || 1)), 0);
        totalEl.textContent = money(total, products[0]?.currency || 'RUB');
        block.style.display = 'block';
    } else {
        block.style.display = 'none';
    }
}

// === Сохранение КП (отдельно, без оформления заказа) ===
async function saveKp() {
    if (!Array.isArray(products) || products.length === 0) {
        toast('Добавьте товары в таблицу', true);
        return;
    }

    const kpInfo = buildKpInfo();
    const total = products.reduce((s, p) => s + ((+p.price || 0) * (+p.quantity || 1)), 0);

    const items = products.map(p => ({
        id: p.id,
        article: p.article || p.xml_id || p.code || '',
        name: p.name,
        measure: p.measure || 'шт',
        quantity: +p.quantity || 1,
        price: +p.price || 0,
        mic_price: +(p.mic_price ?? p.base_price ?? p.price ?? 0),
        base_price: +(p.base_price ?? p.mic_price ?? p.price ?? 0),
        original_mic: +(p.original_mic ?? p.mic_price ?? p.base_price ?? p.price ?? 0),
        price_type_id: +p.price_type_id || null,
        discount_percent: p.discount_percent ?? null,
        currency: p.currency || 'RUB',
        image: p.image || null,
        url: p.url || null,
        props: Array.isArray(p.props) ? p.props : [],
    }));

    let title;
    if (currentKpId && currentKpTitle) {
        title = currentKpTitle;
    } else {
        const titleParts = [];
        if (kpInfo.customer) titleParts.push(kpInfo.customer);
        if (kpInfo.object) titleParts.push(kpInfo.object);
        title = (titleParts.join(' · ') || 'КП') + ' — ' + (kpInfo.date || new Date().toLocaleDateString('ru-RU'));
    }

    try {
        const payload = {
            title,
            kp_data: JSON.stringify({
                ...kpInfo,
                total,
                currency: products?.[0]?.currency || 'RUB',
                created_at: new Date().toISOString(),
                id: currentKpId || undefined,
            }),
            products: JSON.stringify(items),
        };

        const res = await post('save_kp', payload);

        if (res?.success) {
            const kpId = res.id || 0;
            const action = res.action || 'create';

            currentKpId = kpId;
            currentKpTitle = title;

            window.lastSavedKpId = kpId;
            window.lastSavedKpTitle = title;

            if (action === 'update') {
                toast(`КП обновлено (#${kpId})`);
            } else {
                toast(`КП сохранено (#${kpId})`);
            }

        } else {
            toast(res?.error || 'Не удалось сохранить КП', true);
        }
    } catch (e) {
        console.error(e);
        toast('Ошибка сети при сохранении', true);
    }
}

// Открыть список моих КП
function showSavedKpList() {
    openSavedKpModal();
}

// === Внутренний список "Мои КП" ===
function openSavedKpModal(){
    const modal = document.getElementById('savedKpModal');
    const list = document.getElementById('savedKpList');
    if (!modal || !list) return;
    modal.classList.add('show');
    loadSavedKpInto(list);
}

function closeSavedKpModal(){
    const modal = document.getElementById('savedKpModal');
    if (modal) modal.classList.remove('show');
}

async function loadSavedKpInto(container){
    container.innerHTML = 'Загрузка…';
    try{
        const res = await post('get_user_kps', {});
        if (!res?.success){ container.innerHTML = 'Ошибка загрузки списка'; return; }
        const rows = Array.isArray(res.kps) ? res.kps : [];
        if (!rows.length){ container.innerHTML = 'Нет сохранённых КП'; return; }
        container.innerHTML = rows.map(r => `
            <div class="kp-item">
                <div>
                    <div class="kp-title">${(window.escapeHtml?window.escapeHtml:(s=>String(s)))(r.title || 'Без названия')} ${r.id ? `(№${r.id})` : ''}</div>
                    <div class="kp-meta">Создано: ${((r.date_create||r.dateCreate)||'')} · Изм.: ${((r.date_modify||r.dateModify)||'')}</div>
                </div>
                <div class="kp-actions">
                    <button class="action-btn action-btn--secondary" onclick="loadSavedKp(${Number(r.id)})">Открыть</button>
                    <button class="action-btn action-btn--danger" onclick="deleteSavedKp(${Number(r.id)})">Удалить</button>
                </div>
            </div>
        `).join('');
    }catch(e){
        console.error('loadSavedKpInto error', e);
        container.innerHTML = 'Сбой загрузки';
    }
}

async function loadSavedKp(kpId){
    if (!kpId) return;
    try{
        const res = await post('load_kp', { kp_id: kpId });
        if (!res?.success){ toast(res?.error || 'Не удалось открыть КП', true); return; }
        const list = Array.isArray(res.products) ? res.products : [];
        products = list.map((p, idx) => {
            const mic = +(p.mic_price ?? p.base_price ?? p.price ?? 0);
            const cur = +p.price || 0;
            const discount = mic > 0 ? ((mic - cur) / mic * 100) : 0;
            return {
                ...p,
                mic_price: mic,
                base_price: +(p.base_price ?? mic),
                original_mic: +(p.original_mic ?? mic),
                discount_percent: p.discount_percent ?? Number(discount.toFixed(2)),
                rowId: idx + 1,
            };
        });

        if (res.kp_info) {
            if (res.kp_info.date) {
                const dateParts = res.kp_info.date.split('.');
                if (dateParts.length === 3) {
                    const dateField = document.getElementById('kpDate');
                    if (dateField) {
                        dateField.value = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
                    }
                }
            }
            if (res.kp_info.customer) setFieldValue('customerName', res.kp_info.customer);
            if (res.kp_info.object) setFieldValue('objectName', res.kp_info.object);
            if (res.kp_info.manager) setFieldValue('managerName', res.kp_info.manager);
            if (res.kp_info.position) setFieldValue('managerPosition', res.kp_info.position);
            if (res.kp_info.phone) setFieldValue('managerPhone', res.kp_info.phone);
            if (res.kp_info.email) setFieldValue('managerEmail', res.kp_info.email);
            if (res.kp_info.company) setFieldValue('managerCompany', res.kp_info.company);
            if (res.kp_info.comment) setFieldValue('comment', res.kp_info.comment);
        }

        currentKpId = kpId;
        currentKpTitle = res.title || `КП №${kpId}`;

        try { localStorage.removeItem('kp_products'); } catch (_e) {}
        try { localStorage.removeItem('kp_info'); } catch (_e) {}
        counter = products.length;
        await render();
        toast('КП загружено для редактирования');
        closeSavedKpModal();

        updateMobileSticky();
    }catch(e){ console.error(e); toast('Ошибка сети при загрузке КП', true); }
}

/**
 * ✅ Удаление сохранённого КП
 */
async function deleteSavedKp(kpId) {
    if (!kpId) return;

    if (!confirm('Удалить это КП? Действие необратимо.')) {
        return;
    }

    try {
        const res = await post('delete_kp', { kp_id: kpId });

        if (res?.success) {
            toast('КП удалено');

            const list = document.getElementById('savedKpList');
            if (list) {
                loadSavedKpInto(list);
            }
        } else {
            toast(res?.error || 'Не удалось удалить КП', true);
        }
    } catch (e) {
        console.error('deleteSavedKp error:', e);
        toast('Ошибка сети при удалении КП', true);
    }
}