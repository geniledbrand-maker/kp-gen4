/* ============================================================================
 * price-math.js — v4.4 PERFORMANCE
 * - Безопасный numeric() для "1 234,56 ₽", NBSP и пр.
 * - Точно сохранена логика resolvePricingBase / calculateDiscountPercent
 * - Добавлен кадровый батчинг schedule() для дешёвых пересчётов UI
 * - Пара утилит (clamp, round2, priceFromPercent) — не ломают совместимость
 * ============================================================================ */
(function (global) {
    'use strict';

    /* -------------------------- Fast helpers -------------------------- */

    // Предкомпилированные регэкспы, чтобы не создавать их на каждый вызов
    const RE_WS = /[\s\u00A0]/g;      // обычные пробелы и NBSP
    const RE_COMMA = /,/g;            // запятые -> точки
    const RE_NON_NUM = /[^\d.\-]/g;   // всё, что не цифра/точка/минус (валюта и пр.)

    function numeric(value) {
        if (value == null) return 0;
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }
        // Нормализуем строку: убираем пробелы/NBSP, меняем запятые на точки,
        // и вычищаем валютные символы/буквы
        const s = String(value)
            .replace(RE_WS, '')
            .replace(RE_COMMA, '.')
            .replace(RE_NON_NUM, '');
        const n = Number(s);
        return Number.isFinite(n) ? n : 0;
    }

    const clamp = (x, min, max) => (x < min ? min : x > max ? max : x);
    const round2 = (x) => Math.round((x + Number.EPSILON) * 100) / 100;

    /* ---------------------- Business Logic (исходная) ---------------------- */

    function resolvePricingBase(options) {
        const currentBase = numeric(options?.currentBase);
        const currentMic = numeric(options?.currentMic);
        const fetchedMic = numeric(options?.fetchedMic);
        const fallbackPrice = numeric(options?.fallbackPrice);
        const newPriceTypeId = Number(options?.newPriceTypeId) || 0;

        let baseToStore = currentBase;
        let micToStore = currentMic;

        if (fetchedMic > 0) {
            micToStore = fetchedMic;
        }

        if (newPriceTypeId === 2 && fetchedMic > 0) {
            baseToStore = fetchedMic;
        } else if (baseToStore <= 0 && fetchedMic > 0) {
            baseToStore = fetchedMic;
        }

        let baseToUse = baseToStore;
        if (baseToUse <= 0) {
            baseToUse = micToStore > 0 ? micToStore : fallbackPrice;
        }

        return {
            baseToUse,
            baseToStore,
            micToStore,
        };
    }

    function calculateDiscountPercent(basePrice, price) {
        const base = numeric(basePrice);
        const targetPrice = numeric(price);
        if (base <= 0) return 0;
        return ((base - targetPrice) / base) * 100;
    }

    /* ---------------------- Extra safe utils (optional) ---------------------- */

    // Цена по проценту скидки (например, 15% от базовой)
    function priceFromPercent(basePrice, percent) {
        const base = numeric(basePrice);
        const p = clamp(numeric(percent), -100, 100); // допускаем отрицательные "наценки"
        return round2(base * (1 - p / 100));
    }

    // Процент скидки по двум ценам с авто-округлением
    function discountPercentFromPrices(basePrice, price) {
        return round2(calculateDiscountPercent(basePrice, price));
    }

    /* -------------------- RAF-батчинг пересчётов UI -------------------- */

    // schedule(cb) — вызовет cb максимум 1 раз на кадр.
    // Используй для oninput qty/price/discount, чтобы не дергать layout каждую букву.
    let rafId = 0;
    function schedule(cb) {
        if (typeof cb !== 'function') return;
        if (rafId) cancelAnimationFrame(rafId);
        rafId = requestAnimationFrame(() => {
            rafId = 0;
            cb();
        });
    }

    // Мягкий хелпер: обернуть обработчик input так, чтобы он батчился.
    function bindBatchedInput(el, handler) {
        if (!el || typeof handler !== 'function') return;
        el.addEventListener('input', () => schedule(handler));
        el.addEventListener('change', () => schedule(handler));
    }

    /* --------------------------- Public API --------------------------- */

    const api = {
        // исходные
        resolvePricingBase,
        calculateDiscountPercent,

        // утилиты (необязательные, но полезные)
        numeric,
        clamp,
        round2,
        priceFromPercent,
        discountPercentFromPrices,

        // производительность
        schedule,
        bindBatchedInput,
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
    global.PriceMath = api;

})(typeof window !== 'undefined' ? window : globalThis);
