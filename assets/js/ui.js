"use strict";

/**
 * Geniled KP Generator – UI (оптимизированная мобильная версия)
 * ---------------------------------------------------------------
 * ✅ Кэширование типов цен для ускорения рендеринга
 * ✅ Оптимизация для мобильных устройств
 * ✅ Ленивая загрузка изображений
 * ✅ Debounced resize handler
 * ✅ Улучшенная производительность
 * ✅ Загрузка из профиля Битрикс + семафор (красная/зелёная иконка)
 * ✅ ИСПРАВЛЕНО: Правильное обновление мобильного блока ИТОГО
 * ✅ ИСПРАВЛЕНО: Функция render() экспортирована глобально
 * ✅ ДОБАВЛЕНО: Drag-and-drop для перетаскивания строк
 */

let __isRendering = false;
let globalClickHandlerInitialized = false;

// 🔹 Кэш типов цен для оптимизации
let priceTypesCache = new Map();
let lastCacheUpdate = 0;
const CACHE_DURATION = 5000; // 5 секунд

// 🔹 Определение мобильного устройства
function isMobile() {
    return (window.innerWidth || document.documentElement.clientWidth) <= 900;
}

/* === Утилиты === */
function escapeHtml(text) {
    if (text === null || text === undefined) return "";
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
}

if (typeof window.money !== "function") {
    window.money = function money(value, currency = "RUB") {
        const num = Number(value) || 0;
        const parts = num.toFixed(2).split(".");
        const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, " ");
        const frac = parts[1];
        const suffix = currency === "RUB" ? "₽" : currency;
        return `${intPart},${frac} ${suffix}`;
    };
}

/* === ✅ ОБНОВЛЁННАЯ ФУНКЦИЯ для мобильного блока ИТОГО === */
function updateMobileSticky() {
    console.log('🔄 updateMobileSticky вызвана');

    const block = document.getElementById('mobileTotalBlock');
    const totalEl = document.getElementById('mobileTotalAmount');

    if (!block || !totalEl) {
        console.error('❌ Элементы mobileTotalBlock или mobileTotalAmount не найдены!');
        return;
    }

    const productsArray = (typeof products !== 'undefined') ? products : [];
    const hasRows = Array.isArray(productsArray) && productsArray.length > 0;

    if (hasRows) {
        const total = productsArray.reduce((sum, p) => {
            const price = Number(p.price) || 0;
            const qty = Number(p.quantity) || 1;
            return sum + (price * qty);
        }, 0);

        const currency = productsArray[0]?.currency || 'RUB';
        const formattedTotal = typeof money === "function"
            ? money(total, currency)
            : `${total} ${currency}`;

        totalEl.textContent = formattedTotal;
        block.classList.add('has-products');
        console.log('✅ Блок показан. Текст:', formattedTotal);
    } else {
        block.classList.remove('has-products');
        console.log('⚠️ Нет товаров, блок скрыт');
    }
}

// Экспортируем глобально
window.updateMobileSticky = updateMobileSticky;

/* === Получение типов цен с кэшированием === */
async function getProductPriceTypes(productId) {
    const now = Date.now();

    if (priceTypesCache.has(productId) && now - lastCacheUpdate < CACHE_DURATION) {
        return priceTypesCache.get(productId);
    }

    try {
        const res = await post("get_available_price_types", {
            product_ids: JSON.stringify([productId]),
            mode: "all",
        });

        if (res?.success && Array.isArray(res.types)) {
            priceTypesCache.set(productId, res.types);
            lastCacheUpdate = now;
            return res.types;
        }
    } catch (err) {
        console.error("Ошибка загрузки типов цен для товара", productId, err);
    }

    return [];
}

/* === Очистка кэша === */
function clearPriceTypesCache() {
    priceTypesCache.clear();
    lastCacheUpdate = 0;
}

/* === Пакетная загрузка типов цен === */
async function preloadPriceTypes(productIds) {
    const uniqueIds = [...new Set(productIds)];
    const uncachedIds = uniqueIds.filter((id) => !priceTypesCache.has(id));
    if (uncachedIds.length === 0) return;

    try {
        const res = await post("get_available_price_types", {
            product_ids: JSON.stringify(uncachedIds),
            mode: "any",
        });

        if (res?.success && Array.isArray(res.types)) {
            uncachedIds.forEach((id) => {
                priceTypesCache.set(id, res.types);
            });
            lastCacheUpdate = Date.now();
        }
    } catch (err) {
        console.error("Ошибка пакетной загрузки типов цен", err);
    }
}

/* === ✅ ОСНОВНОЙ РЕНДЕР ТАБЛИЦЫ (ИСПРАВЛЕН) === */
async function render() {
    if (__isRendering) return;
    __isRendering = true;

    try {
        const tbody = document.getElementById("tbody");
        const totalCell = document.getElementById("totalCell");
        const kpWrap = document.getElementById("kpWrap");

        if (!tbody || !kpWrap) {
            console.error('❌ Элементы tbody или kpWrap не найдены!');
            return;
        }

        if (products.length === 0) {
            kpWrap.classList.remove('has-products');
            kpWrap.style.display = "none";
            clearPriceTypesCache();

            console.log('📞 Нет товаров, вызываем updateMobileSticky()');
            updateMobileSticky();
            return;
        }

        kpWrap.classList.add('has-products');
        kpWrap.style.display = "block";

        // ⚡ Пакетная предзагрузка типов цен
        const productIds = products.map((p) => p.id).filter(Boolean);
        if (productIds.length > 0) {
            await preloadPriceTypes(productIds);
        }

        let html = "";
        let total = 0;
        const manualRows = [];
        const mobile = isMobile();

        for (let i = 0; i < products.length; i++) {
            const p = products[i];
            const qty = Number(p.quantity) || 1;
            const price = Number(p.price) || 0;
            const sum = qty * price;
            total += sum;

            const basePrice = Number(p.base_price ?? p.mic_price ?? 0);
            const discount = p.discount_percent ?? (basePrice > 0 ? (((basePrice - price) / basePrice) * 100).toFixed(2) : 0);

            if (p.manual_price) manualRows.push(`#${i + 1} (${p.article || p.id})`);

            const imageHtml = p.image
                ? `<img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" ${mobile ? 'loading="lazy"' : ""}>`
                : '<div class="product-image-placeholder">📦</div>';

            const props = Array.isArray(p.props) ? p.props : [];
            const propsHtml =
                props.length > 0
                    ? `<div class="param-block">
              ${props
                        .map(
                            (pr) => `
                <div class="param-row">
                  <span class="param-label">${escapeHtml(pr.name)}:</span>
                  <span class="param-value">${escapeHtml(pr.value)}</span>
                </div>`
                        )
                        .join("")}
           </div>`
                    : "";

            const nameHtml = `<a href="${escapeHtml(p.url || "#")}" target="_blank" class="link-to-product">${escapeHtml(p.name)}</a>`;

            const productPriceTypes = await getProductPriceTypes(p.id);

            const optionsHtml = productPriceTypes
                .map((opt) => {
                    const selected = Number(opt.id) === Number(p.price_type_id) ? "selected" : "";
                    return `<option value="${opt.id}" ${selected}>${escapeHtml(opt.name)}</option>`;
                })
                .join("");

            const selectClass = mobile ? "price-type-select mobile-native" : "price-type-select";

            const priceSelectHtml = `
        <select class="${selectClass}" data-row-id="${p.rowId}"
                onchange="changePriceType(${p.rowId}, this.value)">
          ${optionsHtml || '<option value="">Тип цены</option>'}
        </select>`;

            html += `
      <tr>
        <td data-label="№">${i + 1}</td>
        <td data-label="Фото">${imageHtml}</td>
        <td data-label="Артикул">${escapeHtml(p.article)}</td>
        <td data-label="Наименование / Параметры">
          <div class="name-wrap">${nameHtml}${propsHtml}</div>
        </td>
        <td data-label="Ед.">${escapeHtml(p.measure || "шт")}</td>
        <td data-label="Кол-во">
${mobile ? `
  <!-- МОБИЛЬНАЯ ВЕРСИЯ: [−] [input] [+] в ряд -->
  <div class="qty-wrapper">
    <button class="qty-btn qty-btn-minus" onclick="changeQuantity(${p.rowId}, ${Math.max(1, qty - 1)})" title="Уменьшить">−</button>
    <input type="number" class="qty-input" value="${qty}" min="1"
           onchange="changeQuantity(${p.rowId}, this.value)">
    <button class="qty-btn qty-btn-plus" onclick="changeQuantity(${p.rowId}, ${qty + 1})" title="Увеличить">+</button>
  </div>
` : `
          <div class="qty-wrapper-desktop">
            <button class="qty-btn qty-btn-minus" onclick="changeQuantity(${p.rowId}, ${Math.max(1, qty - 1)})" title="Уменьшить">−</button>
            <input type="number" class="qty-input" value="${qty}" min="1"
                   onchange="changeQuantity(${p.rowId}, this.value)">
            <button class="qty-btn qty-btn-plus" onclick="changeQuantity(${p.rowId}, ${qty + 1})" title="Увеличить">+</button>
          </div>
          `}
        </td>
        <td data-label="Цена">
          <div class="price-wrapper">
            <input type="number" class="price-input" value="${price.toFixed(2)}" min="0" step="0.01"
                   onchange="changePrice(${p.rowId}, this.value)">
            ${p.manual_price ? `<button class="btn-reset" onclick="resetPrice(${p.rowId})" title="Сбросить на МИЦ">↻</button>` : ""}
          </div>
          ${priceSelectHtml}
        </td>
        <td data-label="Скидка, %" class="discount-cell ${Number(discount) > 0 ? "has-discount" : ""}">
          <div class="input-affix">
            <input type="number" class="discount-input" value="${discount}" min="0" max="99" step="0.1"
                   onchange="changeDiscount(${p.rowId}, this.value)">
            <span class="affix">%</span>
          </div>
        </td>
        <td data-label="Сумма" class="sum-cell">${money(sum, p.currency)}</td>
        <td data-label="Удаление">
          <span class="delete-icon" onclick="removeProduct(${p.rowId})" tabindex="0" role="button" aria-label="Удалить товар">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
            </svg>
          </span>
        </td>
      </tr>`;
        }

        tbody.innerHTML = html;

        // ✅ Обновляем десктопный ИТОГО в tfoot
        if (totalCell) {
            totalCell.textContent = money(total, products[0]?.currency || "RUB");
        }

        // ✅ Обновляем ПРЕМИУМ десктопный блок
        const desktopTotalAmount = document.getElementById('desktopTotalAmount');
        if (desktopTotalAmount) {
            desktopTotalAmount.textContent = money(total, products[0]?.currency || "RUB");
            console.log('✅ Desktop total updated:', money(total, products[0]?.currency || "RUB"));
        }

        // ✅ КРИТИЧЕСКИ ВАЖНО: Обновляем мобильный блок ИТОГО после рендера
        console.log('📞 Вызываем updateMobileSticky() после рендера таблицы');
        updateMobileSticky();

        if (!mobile) {
            initCustomSelects();

            // ✅ ДОБАВЛЕНО: Инициализация drag-and-drop
            if (typeof initDragDrop === 'function') {
                setTimeout(initDragDrop, 150);
            }
        }

    } catch (error) {
        console.error('❌ Ошибка в render():', error);
    } finally {
        __isRendering = false;
    }
}

// ✅ ЭКСПОРТИРУЕМ ГЛОБАЛЬНО
window.render = render;

/* === Батч-рендер: если есть PriceMath.schedule — рисуем в один кадр === */
function requestRender() {
    if (window.PriceMath && typeof PriceMath.schedule === 'function') {
        PriceMath.schedule(render);
    } else {
        render();
    }
}
window.requestRender = requestRender;

/* === Кастомные селекты === */
function initCustomSelects() {
    const selects = document.querySelectorAll(".price-type-select:not(.mobile-native)");

    selects.forEach((select) => {
        if (select.parentElement?.classList.contains("custom-select")) return;

        const wrapper = document.createElement("div");
        wrapper.className = "custom-select";

        const button = document.createElement("button");
        button.className = "custom-select__button";
        button.type = "button";
        button.setAttribute("aria-haspopup", "listbox");
        button.setAttribute("aria-expanded", "false");
        button.textContent =
            select.options[select.selectedIndex]?.text || "Выберите";

        const list = document.createElement("div");
        list.className = "custom-select__list";
        list.setAttribute("role", "listbox");

        Array.from(select.options).forEach((option) => {
            const item = document.createElement("div");
            item.className = "custom-select__item";
            item.setAttribute("role", "option");
            item.textContent = option.text;
            item.dataset.value = option.value;
            if (option.selected) item.setAttribute("aria-selected", "true");

            item.addEventListener("click", (e) => {
                e.stopPropagation();
                select.value = option.value;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                button.textContent = option.text;
                list.querySelectorAll(".custom-select__item").forEach((i) => i.removeAttribute("aria-selected"));
                item.setAttribute("aria-selected", "true");
                wrapper.classList.remove("open");
                button.setAttribute("aria-expanded", "false");
            });

            list.appendChild(item);
        });

        button.addEventListener("click", (e) => {
            e.stopPropagation();
            const isOpen = wrapper.classList.contains("open");
            document.querySelectorAll(".custom-select.open").forEach((s) => {
                s.classList.remove("open");
                s.querySelector(".custom-select__button")?.setAttribute("aria-expanded", "false");
            });
            wrapper.classList.toggle("open");
            button.setAttribute("aria-expanded", (!isOpen).toString());
            if (!isOpen) positionDropdown(wrapper, button, list);
        });

        button.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                button.click();
            }
        });

        select.classList.add("custom-select__native");
        select.style.display = "none";

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(button);
        wrapper.appendChild(list);
        wrapper.appendChild(select);
    });

    if (!globalClickHandlerInitialized) {
        document.addEventListener("click", (e) => {
            if (!e.target.closest(".custom-select")) {
                document.querySelectorAll(".custom-select.open").forEach((s) => {
                    s.classList.remove("open");
                    s.querySelector(".custom-select__button")?.setAttribute("aria-expanded", "false");
                });
            }
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                document.querySelectorAll(".custom-select.open").forEach((s) => {
                    s.classList.remove("open");
                    s.querySelector(".custom-select__button")?.setAttribute("aria-expanded", "false");
                });
            }
        });
        globalClickHandlerInitialized = true;
    }
}

/* === Позиционирование выпадашки === */
function positionDropdown(wrapper, button, list) {
    const r = button.getBoundingClientRect();
    const vh = window.innerHeight;
    const spaceBelow = vh - r.bottom;
    const dropdownHeight = 260;

    const openDown = spaceBelow > dropdownHeight || spaceBelow > r.top;
    if (openDown) {
        list.style.top = `${r.bottom + 6}px`;
        list.style.bottom = "auto";
    } else {
        list.style.bottom = `${vh - r.top + 6}px`;
        list.style.top = "auto";
    }
    list.style.left = `${r.left}px`;
    list.style.minWidth = `${r.width}px`;
    list.style.width = "auto";
    list.style.position = "fixed";
}

/* === Debounced resize handler === */
let __resizeStickyTimer;
let __lastWidth = window.innerWidth;

window.addEventListener("resize", () => {
    clearTimeout(__resizeStickyTimer);
    __resizeStickyTimer = setTimeout(() => {
        const currentWidth = window.innerWidth;

        // ✅ Обновляем мобильный блок при ресайзе
        console.log('📞 Ресайз окна, вызываем updateMobileSticky()');
        updateMobileSticky();

        const wasDesktop = __lastWidth > 680;
        const isDesktop = currentWidth > 680;

        if (wasDesktop !== isDesktop && products.length > 0) {
            console.log("📱 Переключение desktop/mobile режима, перерендер (batched)...");
            clearPriceTypesCache();
            requestRender(); // 🔸 батч-рендер вместо прямого render()
        }

        __lastWidth = currentWidth;
    }, 250);
});

/* === Экспорт для использования в других модулях === */
if (typeof window !== "undefined") {
    window.render = render;
    window.clearPriceTypesCache = clearPriceTypesCache;
    window.isMobile = isMobile;
    window.updateMobileSticky = updateMobileSticky;
}

/* =========================
   БИТРИКС-ПРОФИЛЬ + СЕМАФОР
   ========================= */

window.setBitrixProfileBadge = function(isUpToDate){
    const btn = document.querySelector('.btn-bitrix-profile');
    if (!btn) {
        console.warn('⚠️ Кнопка .btn-bitrix-profile не найдена');
        return;
    }
    btn.classList.remove('state-fresh','state-stale');
    btn.classList.add(isUpToDate ? 'state-fresh' : 'state-stale');
    console.log(isUpToDate ? '🟢 Профиль актуален' : '🔴 Профиль устарел');
};

async function safePost(action, payload = {}) {
    try {
        const res = await post(action, payload);
        if (typeof res !== "object") throw new Error("Ответ не JSON");
        return res;
    } catch (e) {
        console.error(`❌ Ошибка POST (${action}):`, e);
        if (typeof window.toast === "function") toast("Ошибка запроса к серверу", true);
        return { success: false, error: "NETWORK_ERROR" };
    }
}

window.loadFromBitrixProfile = async function () {
    const btn = document.querySelector('.btn-bitrix-profile');

    // Показываем индикатор загрузки
    if (btn) {
        btn.disabled = true;
        btn.classList.add('loading');
        console.log('⏳ Загрузка данных из профиля Битрикс...');
    }

    // Сначала пробуем загрузить из data-атрибутов кнопки
    if (btn && btn.dataset && (btn.dataset.managerName || btn.dataset.managerEmail || btn.dataset.managerPhone || btn.dataset.managerCompany)) {
        const d = btn.dataset;
        const set = (id, v) => {
            const el = document.getElementById(id);
            if (el && v) el.value = v;
        };
        set('managerName',    (d.managerName || '').trim());
        set('managerEmail',   (d.managerEmail || '').trim());
        set('managerPhone',   (d.managerPhone || '').trim());
        set('managerCompany', (d.managerCompany || '').trim());
    }

    // Затем загружаем актуальные данные с сервера
    const res = await safePost("get_bitrix_profile", {});

    // Убираем индикатор загрузки
    if (btn) {
        btn.disabled = false;
        btn.classList.remove('loading');
    }

    if (!res.success) {
        if (typeof window.toast === "function") {
            toast(res.error || "Ошибка загрузки данных из профиля Битрикс", true);
        }
        setBitrixProfileBadge(false);
        return;
    }

    const d = res.data || {};
    const setFieldValue = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val || "";
    };

    setFieldValue("managerName", d.manager_name);
    setFieldValue("managerPosition", d.manager_position || "");
    setFieldValue("managerPhone", d.manager_phone);
    setFieldValue("managerEmail", d.manager_email);
    setFieldValue("managerCompany", d.manager_company);

    window.currentProfileId = 0;
    const select = document.getElementById("profileSelect");
    if (select) select.value = 0;

    // ✅ КРИТИЧЕСКИ ВАЖНО: Устанавливаем зелёную иконку после успешной загрузки
    setBitrixProfileBadge(true);

    if (typeof window.toast === "function") {
        toast("✅ Данные загружены из профиля Битрикс");
    }
};

/* =========================
   ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ
   ========================= */
document.addEventListener('DOMContentLoaded', () => {
    // Изначально кнопка в состоянии "данные устарели"
    setBitrixProfileBadge(false);
    console.log('🔴 Инициализация: профиль устарел (по умолчанию)');

    // Если нужно автоматически загрузить данные при старте, раскомментируйте:
    // loadFromBitrixProfile();
});