/**
 * Управление товарами и перерасчёт скидок
 * ---------------------------------------
 * Все комментарии оставлены на русском по просьбе пользователя.
 *
 * ✅ ИСПРАВЛЕНИЯ:
 * - Добавлен вызов markAddedInLiveSearch() после рендеров
 * - Добавлен вызов setBitrixProfileBadge() при изменении товаров
 * ✅ ОПТИМИЗАЦИЯ:
 * - Батч-рендер через requestAnimationFrame/PriceMath.schedule для input-операций
 */

function numeric(value) {
    if (typeof value === 'string') {
        const cleaned = value.replace(/\s+/g, '').replace(',', '.');
        const parsed = Number(cleaned);
        return Number.isFinite(parsed) ? parsed : 0;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

const PRICE_MATH = (typeof PriceMath !== 'undefined')
    ? PriceMath
    : {
        resolvePricingBase: () => ({ baseToUse: 0, baseToStore: 0, micToStore: 0 }),
        calculateDiscountPercent: () => 0,
        schedule: (cb) => requestAnimationFrame(() => cb && cb()),
    };

/* ------------------ Батч-рендер для интерактивных апдейтов ------------------ */
const __schedule = (PRICE_MATH && typeof PRICE_MATH.schedule === 'function')
    ? PRICE_MATH.schedule
    : (cb) => requestAnimationFrame(() => cb && cb());

function requestRender() {
    __schedule(render);
}
window.requestRender = window.requestRender || requestRender;

/**
 * Добавление товара по артикулу (без поля ввода).
 * ✅ ИСПРАВЛЕНО: Проверка, чтобы не увеличивать количество при toggle
 */
async function addProductByArticle(article, qty = 1, forceSeparate = false) {
    const art = String(article || '').trim();
    if (!art) {
        toast('Введите артикул', true);
        return;
    }

    try {
        const res = await post('get_product', { article: art });
        if (res.success && res.data) {
            // ✅ Передаем данные в applyProduct, которая сама решит, что делать
            applyProduct(res.data, qty, forceSeparate);
            await render();
            toast('Товар добавлен');

            // ✅ Обновляем метки "Уже в КП" в живом поиске
            setTimeout(() => {
                if (typeof window.markAddedInLiveSearch === 'function') {
                    window.markAddedInLiveSearch();
                }
            }, 250);

            // ✅ Проверяем актуальность профиля после добавления
            if (typeof window.setBitrixProfileBadge === 'function') {
                window.setBitrixProfileBadge(false);
                console.log('🔴 Профиль устарел (товар добавлен)');
            }

            await refreshGlobalPriceTypes();
        } else {
            toast(res.error || 'Товар не найден', true);
        }
    } catch (e) {
        console.error(e);
        toast('Ошибка сети', true);
    }
}


/**
 * Добавление товара через поле ввода.
 */
async function addProduct() {
    const articleInput = document.getElementById('articleInput');
    if (!articleInput) {
        toast('Используйте подбор из списка выше', true);
        return;
    }

    const article = articleInput.value.trim();
    if (!article) {
        toast('Введите артикул', true);
        return;
    }

    await addProductByArticle(article, 1, false);
    articleInput.value = '';
}

/**
 * Массовое добавление товаров (списком).
 */
async function addMultiple() {
    const textarea = document.getElementById('bulkTextarea');
    const separateCheckbox = document.getElementById('addAsSeparate');
    if (!textarea) return;

    const forceSeparate = separateCheckbox ? separateCheckbox.checked : true;
    const { items, errors } = parseBulkInput(textarea.value);

    if (!items.length) {
        toast(errors.length ? 'Не удалось разобрать список' : 'Список пуст', true);
        return;
    }

    let added = 0;
    const issues = [...errors];

    for (const item of items) {
        try {
            const res = await post('get_product', { article: item.article });
            if (res.success && res.data) {
                applyProduct(res.data, item.quantity, forceSeparate);
                added++;
            } else {
                issues.push({ line: item.article, value: res.error || 'Нет данных' });
            }
        } catch (error) {
            issues.push({ line: item.article, value: error.message });
        }
    }

    if (added > 0) {
        await render(); // ✅ Ждем завершения рендера
        toast(`Добавлено позиций: ${added}`);

        // ✅ Обновляем метки "Уже в КП" в живом поиске
        setTimeout(() => window.markAddedInLiveSearch?.(), 250);

        // ✅ Проверяем актуальность профиля после массового добавления
        if (typeof window.setBitrixProfileBadge === 'function') {
            window.setBitrixProfileBadge(false);
            console.log('🔴 Профиль устарел (массовое добавление)');
        }

        await refreshGlobalPriceTypes();
    }

    if (issues.length) {
        console.warn('bulk add issues', issues);
        toast(`Не обработано: ${issues.length}`, true);
    }
}

/**
 * Разбор списка артикулов из текста.
 */
function parseBulkInput(text) {
    const items = [];
    const errors = [];
    const lines = text.split(/[\n\r]+/).map(s => s.trim()).filter(Boolean);

    for (const line of lines) {
        let article = line;
        let quantity = 1;
        const match = line.match(/^(.+?)[\s;x*]+(\d+)$/i);
        if (match) {
            article = match[1].trim();
            quantity = parseInt(match[2], 10) || 1;
        }

        if (article) {
            items.push({ article, quantity });
        } else {
            errors.push({ line, value: 'Не удалось распознать' });
        }
    }

    return { items, errors };
}

/**
 * Унификация параметров товара к массиву {name, value}.
 */
function normalizeProps(raw) {
    if (!raw) return [];

    if (Array.isArray(raw) && raw.every(p => typeof p === 'object' && ('name' in p || 'NAME' in p))) {
        return raw
            .map(p => ({
                name: String(p.name ?? p.NAME ?? p.code ?? p.CODE ?? '').trim(),
                value: String(p.value ?? p.VALUE ?? p.val ?? '').trim(),
            }))
            .filter(p => p.name || p.value);
    }

    if (typeof raw === 'object' && !Array.isArray(raw)) {
        return Object.entries(raw)
            .map(([key, value]) => ({
                name: String(key ?? '').trim(),
                value: String(value ?? '').trim(),
            }))
            .filter(p => p.name || p.value);
    }

    if (typeof raw === 'string') {
        return raw
            .split(/[;,]\s*/)
            .map(s => s.trim())
            .filter(Boolean)
            .map(part => {
                const match = part.match(/^(.+?)\s*[:=]\s*(.+)$/);
                if (match) {
                    return { name: match[1].trim(), value: match[2].trim() };
                }
                return { name: '', value: part };
            });
    }

    return [];
}

/**
 * Добавление либо обновление товара в массиве.
 */
function applyProduct(data, qty = 1, forceSeparate = false) {
    const amount = Number(qty);
    const safeQty = Number.isFinite(amount) && amount > 0 ? amount : 1;
    const normalizedProps = normalizeProps(data.props);

    if (!forceSeparate) {
        const existing = products.find(item => item.id === data.id);
        if (existing) {
            const sourceMicExisting = numeric(data.mic_price ?? data.base_price ?? data.price ?? existing.mic_price ?? 0);
            const sourceBaseExisting = numeric(data.base_price ?? data.mic_price ?? data.price ?? existing.base_price ?? 0);
            const sourcePriceExisting = numeric(data.price ?? existing.price ?? 0);
            const discountExisting = sourceBaseExisting > 0 && sourcePriceExisting > 0
                ? Math.max(0, Number((((sourceBaseExisting - sourcePriceExisting) * 100) / sourceBaseExisting).toFixed(2)))
                : existing.discount_percent;

            existing.quantity = Number(existing.quantity) + safeQty;
            existing.price = sourcePriceExisting;
            existing.currency = data.currency || existing.currency || 'RUB';
            existing.price_type_id = data.price_type_id || existing.price_type_id;
            existing.props = normalizedProps.length ? normalizedProps : (existing.props || []);
            existing.measure = data.measure || existing.measure;
            existing.image = data.image || existing.image;
            existing.url = data.url || existing.url;
            if (!existing.original_mic && sourceMicExisting > 0) {
                existing.original_mic = sourceMicExisting;
            }
            if (sourceMicExisting > 0) existing.mic_price = sourceMicExisting;
            if (sourceBaseExisting > 0) existing.base_price = sourceBaseExisting;
            if (discountExisting !== null && discountExisting !== undefined) {
                existing.discount_percent = discountExisting;
            }
            return false;
        }
    }

    const isMic = Number(data.price_type_id) === 2;
    const sourceMic = numeric(data.mic_price ?? data.base_price ?? data.price ?? 0);
    const sourceBase = numeric(data.base_price ?? data.mic_price ?? data.price ?? 0);
    const sourcePrice = numeric(data.price ?? 0);
    const initialBase = sourceBase > 0 ? sourceBase : undefined;
    const initialMic = sourceMic > 0 ? sourceMic : undefined;
    const initialDiscount = initialBase > 0 && sourcePrice > 0
        ? Math.max(0, Number((((initialBase - sourcePrice) / initialBase) * 100).toFixed(2)))
        : null;

    const newItem = {
        ...data,
        article: (data.article || data.xml_id || data.code || data.ID || '').trim(),
        quantity: safeQty,
        rowId: ++counter,
        currency: data.currency || 'RUB',
        price: sourcePrice,
        mic_price: isMic ? sourcePrice : initialMic,
        base_price: isMic ? sourcePrice : initialBase,
        original_mic: initialMic ?? sourceMic ?? sourcePrice,
        discount_percent: data.discount_percent ?? initialDiscount,
        props: normalizedProps,
    };

    products.push(newItem);

    if (!isMic && newItem.id) {
        (async () => {
            try {
                const res = await post('change_price_type', { product_id: newItem.id, price_type: 2 });
                if (res?.success) {
                    const mic = numeric(res.price);
                    const resolved = PRICE_MATH.resolvePricingBase({
                        currentBase: newItem.base_price,
                        currentMic: newItem.mic_price,
                        fetchedMic: mic,
                        newPriceTypeId: 2,
                        fallbackPrice: newItem.price,
                    });
                    newItem.mic_price = resolved.micToStore;
                    if (resolved.baseToStore > 0) {
                        newItem.base_price = resolved.baseToStore;
                    }
                    if (!newItem.original_mic && resolved.micToStore > 0) {
                        newItem.original_mic = resolved.micToStore;
                    }
                    if (!newItem.manual_price) {
                        const discount = PRICE_MATH.calculateDiscountPercent(resolved.baseToUse, newItem.price);
                        newItem.discount_percent = Math.max(0, Number(discount.toFixed(2)));
                    }
                    await render(); // ✅ Перерисовываем после получения МЦ

                    // ✅ Обновляем метки "Уже в КП" в живом поиске
                    setTimeout(() => window.markAddedInLiveSearch?.(), 250);
                }
            } catch (e) {
                console.warn('Не удалось получить МЦ для товара', e);
            }
        })();
    }

    console.log('Добавлен товар:', newItem.article);
    return true;
}

/**
 * Удаление товара из списка.
 */
async function removeProduct(rowId) {
    products = products.filter(p => p.rowId !== rowId);
    await render(); // ✅ Ждем завершения рендера
    toast('Товар удалён');

    // ✅ Обновляем метки "Уже в КП" в живом поиске
    setTimeout(() => window.markAddedInLiveSearch?.(), 200);

    // ✅ Проверяем актуальность профиля после изменений
    if (typeof window.setBitrixProfileBadge === 'function') {
        window.setBitrixProfileBadge(false);
        console.log('🔴 Профиль устарел (товар удалён)');
    }

    await refreshGlobalPriceTypes();
}

/**
 * Смена типа цены у товара.
 */
async function changePriceType(rowId, newPriceTypeId) {
    const product = products.find(p => p.rowId === rowId);
    if (!product) return;

    if (product.manual_price) {
        const confirmed = confirm('Цена изменена вручную. Заменить на цену из каталога?');
        if (!confirmed) {
            await render();
            return;
        }
    }

    let fetchedMic = 0;
    try {
        const micRes = await post('change_price_type', {
            product_id: product.id,
            price_type: 2,
        });
        if (micRes?.success) {
            fetchedMic = numeric(micRes.price) || 0;
        }
    } catch (e) {
        console.warn('Не удалось получить МЦ перед переключением', e);
    }

    try {
        const res = await post('change_price_type', {
            product_id: product.id,
            price_type: newPriceTypeId,
        });

        if (res.success) {
            const newPrice = numeric(res.price);

            const resolved = PRICE_MATH.resolvePricingBase({
                currentBase: product.base_price,
                currentMic: product.mic_price,
                fetchedMic,
                newPriceTypeId,
                fallbackPrice: newPrice,
            });

            product.mic_price = resolved.micToStore;
            if (resolved.baseToStore > 0 || Number(product.base_price ?? 0) <= 0) {
                product.base_price = resolved.baseToStore;
            }
            if (!product.original_mic && resolved.micToStore > 0) {
                product.original_mic = resolved.micToStore;
            }

            const baseReference = numeric(product.original_mic ?? resolved.baseToUse ?? resolved.micToStore ?? product.base_price ?? newPrice);
            let discount = PRICE_MATH.calculateDiscountPercent(baseReference, newPrice);
            product.price = newPrice;
            product.price_type_id = Number(newPriceTypeId);

            if (Number(newPriceTypeId) === 2) {
                product.base_price = baseReference;
                product.mic_price = baseReference;
                product.original_mic = baseReference;
                discount = 0;
            } else if (resolved.micToStore > 0) {
                product.original_mic = resolved.micToStore;
            }

            product.discount_percent = Number(Math.max(0, discount).toFixed(2));
            product.manual_price = false;

            await render();
            toast('Цена и скидка обновлены');
        } else {
            if (res.not_found) {
                removeUnavailablePriceType(newPriceTypeId);
                toast('Тип цены недоступен', true);
            } else {
                toast(res.error || 'Ошибка', true);
            }
        }
    } catch (e) {
        console.error(e);
        toast('Ошибка сети', true);
    }
}

/**
 * Массовая смена типа цен.
 */
async function changeAllPriceTypes(newPriceTypeId) {
    if (!newPriceTypeId || products.length === 0) {
        toast('Нет товаров для изменения', true);
        return;
    }

    const priceType = ALLOWED_PRICE_OPTIONS.find(pt => pt.id == newPriceTypeId);
    const priceTypeName = priceType ? priceType.name : 'выбранный тип';

    showConfirmModal(
        `Изменить тип цены на "${priceTypeName}" для всех ${products.length} товаров?`,
        async () => await performPriceTypeChange(newPriceTypeId, priceTypeName),
    );
}

/**
 * Выполнение массового переключения типа цены.
 */
async function performPriceTypeChange(newPriceTypeId, priceTypeName) {
    const select = document.getElementById('globalPriceTypeSelect');
    if (select) select.disabled = true;

    let changed = 0;
    let typeNotFound = false;
    const updatedProducts = [];

    for (const product of products) {
        try {
            product.manual_price = false;

            let fetchedMic = 0;
            try {
                const micRes = await post('change_price_type', {
                    product_id: product.id,
                    price_type: 2,
                });
                if (micRes?.success) {
                    fetchedMic = numeric(micRes.price) || 0;
                }
            } catch (e) {
                console.warn('Не удалось получить МЦ при массовом обновлении', e);
            }

            const res = await post('change_price_type', {
                product_id: product.id,
                price_type: newPriceTypeId,
            });

            if (res.success) {
                const newPrice = numeric(res.price);
                const resolved = PRICE_MATH.resolvePricingBase({
                    currentBase: product.base_price,
                    currentMic: product.mic_price,
                    fetchedMic,
                    newPriceTypeId,
                    fallbackPrice: newPrice,
                });

                const baseReference = numeric(product.original_mic ?? resolved.baseToUse ?? resolved.micToStore ?? product.base_price ?? newPrice);
                let discount = PRICE_MATH.calculateDiscountPercent(baseReference, newPrice);

                const updated = { ...product };
                updated.price = newPrice;
                updated.price_type_id = Number(newPriceTypeId);
                updated.mic_price = resolved.micToStore;
                if (resolved.baseToStore > 0 || Number(product.base_price ?? 0) <= 0) {
                    updated.base_price = resolved.baseToStore;
                }
                if (Number(newPriceTypeId) === 2) {
                    updated.base_price = baseReference;
                    updated.mic_price = baseReference;
                    updated.original_mic = baseReference;
                    discount = 0;
                } else if (resolved.micToStore > 0) {
                    updated.original_mic = resolved.micToStore;
                }

                updated.discount_percent = Number(Math.max(0, discount).toFixed(1));
                updated.manual_price = false;

                updatedProducts.push(updated);
                changed++;
            } else if (res.not_found) {
                typeNotFound = true;
                const fallback = { ...product };
                const resolvedFallback = PRICE_MATH.resolvePricingBase({
                    currentBase: fallback.base_price,
                    currentMic: fallback.mic_price,
                    fetchedMic,
                    newPriceTypeId,
                    fallbackPrice: fallback.price,
                });
                const fallbackDiscount = PRICE_MATH.calculateDiscountPercent(resolvedFallback.baseToUse, numeric(fallback.price));
                fallback.mic_price = resolvedFallback.micToStore;
                if (resolvedFallback.baseToStore > 0 || Number(fallback.base_price ?? 0) <= 0) {
                    fallback.base_price = resolvedFallback.baseToStore;
                }
                fallback.discount_percent = Math.max(0, Number(fallbackDiscount.toFixed(1)));
                updatedProducts.push(fallback);
            } else {
                updatedProducts.push({ ...product });
            }
        } catch (e) {
            console.error('Ошибка при смене типа цены товара', product.id, e);
            updatedProducts.push({ ...product });
        }
    }

    if (select) select.disabled = false;

    if (updatedProducts.length === products.length) {
        products = updatedProducts;
    }

    if (typeNotFound) {
        removeUnavailablePriceType(newPriceTypeId);
        toast('Тип цены недоступен и удалён', true);
    } else if (changed > 0) {
        await render();
        showPriceChangeModal?.(priceTypeName);
    } else {
        toast('Не удалось изменить тип цены', true);
    }

    await refreshGlobalPriceTypes();
}

/**
 * Сброс цены до МЦ.
 */
async function resetPrice(rowId) {
    const product = products.find(p => p.rowId === rowId);
    if (!product) return;

    const storedMic = numeric(product.original_mic ?? product.mic_price ?? 0);
    if (storedMic > 0) {
        product.price = storedMic;
        product.mic_price = storedMic;
        product.base_price = storedMic;
        product.price_type_id = 2;
        product.discount_percent = 0;
        product.manual_price = false;
        await render();
        toast('Цена сброшена до МЦ');
        return;
    }

    try {
        const res = await post('change_price_type', {
            product_id: product.id,
            price_type: 2,
        });

        if (res.success) {
            const newPrice = numeric(res.price);
            product.original_mic = newPrice;
            product.mic_price = newPrice;
            product.base_price = newPrice;
            product.price = newPrice;
            product.price_type_id = 2;
            product.discount_percent = 0;
            product.manual_price = false;

            await render();
            toast('Цена сброшена до МЦ');
        } else {
            toast(res.error || 'Не удалось получить МЦ', true);
        }
    } catch (e) {
        console.error(e);
        toast('Ошибка сети', true);
    }
}

/**
 * Ручное изменение цены.
 * 🔸 ТОЛЬКО В ЭТОЙ ФУНКЦИИ: заменили синхронный render() на батч-рендер
 */
function changePrice(rowId, newPrice) {
    const product = products.find(p => p.rowId === rowId);
    if (!product) return;

    const parsedPrice = numeric(newPrice);
    product.price = parsedPrice;
    product.manual_price = true;

    const baseReference = numeric(product.original_mic ?? product.mic_price ?? product.base_price ?? product.price);

    if (Number(product.price_type_id) === 2) {
        product.mic_price = parsedPrice;
    }

    if (baseReference > 0 && parsedPrice > 0) {
        const discount = ((baseReference - parsedPrice) / baseReference) * 100;
        product.discount_percent = Number(Math.max(0, discount).toFixed(2));
    } else {
        product.discount_percent = 0;
    }

    requestRender(); // 🔸 батч-рендер вместо прямого render()
}

/**
 * Ручное изменение скидки.
 * 🔸 ТОЛЬКО В ЭТОЙ ФУНКЦИИ: заменили синхронный render() на батч-рендер
 */
function changeDiscount(rowId, discountPercent) {
    const product = products.find(p => p.rowId === rowId);
    if (!product) return;

    const d = Math.max(0, Math.min(99, parseFloat(discountPercent) || 0));
    const basePrice = numeric(product.original_mic ?? product.base_price ?? product.mic_price ?? product.price);
    const newPrice = basePrice * (1 - d / 100);

    product.discount_percent = d;
    product.price = newPrice;
    product.manual_price = true;
    requestRender(); // 🔸 батч-рендер
}

/**
 * Изменение количества.
 * 🔸 ТОЛЬКО В ЭТОЙ ФУНКЦИИ: заменили синхронный render() на батч-рендер
 */
function changeQuantity(rowId, newQuantity) {
    const product = products.find(p => p.rowId === rowId);
    if (!product) return;
    const qty = parseInt(newQuantity, 10);
    product.quantity = Number.isFinite(qty) && qty > 0 ? qty : 1;
    requestRender(); // 🔸 батч-рендер
}

/**
 * Обновление списка доступных типов цен.
 */
async function refreshGlobalPriceTypes(mode = 'all') {
    try {
        const productIds = products.map(p => p.id);
        if (!productIds.length) return;

        const res = await post('get_available_price_types', {
            product_ids: JSON.stringify(productIds),
            mode,
        });

        if (!res.success || !res.types) return;

        const selectWrap = document.getElementById('globalPriceTypeSelectWrap');
        if (!selectWrap) return;

        const ul = selectWrap.querySelector('.custom-select__list');
        const select = selectWrap.querySelector('select.custom-select__native');
        if (!ul || !select) return;

        ul.innerHTML = '';
        select.innerHTML = '<option value="">Тип цены для всех</option>';

        res.types.forEach(opt => {
            const li = document.createElement('li');
            li.className = 'custom-select__item';
            li.dataset.value = opt.id;
            li.textContent = opt.name;
            li.onclick = function () {
                selectGlobalPriceType(this);
            };
            ul.appendChild(li);

            const option = document.createElement('option');
            option.value = opt.id;
            option.textContent = opt.name;
            select.appendChild(option);
        });

        console.log('Обновлены доступные типы цен:', res.types);
    } catch (e) {
        console.error('refreshGlobalPriceTypes error', e);
        toast('Ошибка сети при обновлении типов цен', true);
    }
}

/**
 * Обновление видимости таблицы и итогового блока
 */
function updateTableVisibility() {
    const kpWrap = document.getElementById('kpWrap');
    const tbody = document.getElementById('tbody');
    const mobileTotalBlock = document.getElementById('mobileTotalBlock');

    const hasProducts = tbody && tbody.children.length > 0;

    if (kpWrap) {
        if (hasProducts) {
            kpWrap.classList.add('has-products');
            kpWrap.style.display = 'block';
        } else {
            kpWrap.classList.remove('has-products');
            kpWrap.style.display = 'none';
        }
    }

    if (mobileTotalBlock) {
        if (hasProducts) {
            mobileTotalBlock.classList.add('has-products');
        } else {
            mobileTotalBlock.classList.remove('has-products');
        }
    }

    console.log('✅ updateTableVisibility: товаров =', hasProducts ? tbody.children.length : 0);
}

// Экспортируем глобально
window.updateTableVisibility = updateTableVisibility;