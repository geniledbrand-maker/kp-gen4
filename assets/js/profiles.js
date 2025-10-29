/**
 * ============================================================
 * ЗАГРУЗКА ИЗ ПРОФИЛЯ БИТРИКС - profiles.js (Geniled KP)
 * ============================================================
 */

console.log("🔵 profiles.js загружается...");

/**
 * Универсальный POST-запрос (с обработкой ошибок JSON)
 */
async function safePost(action, payload = {}) {
    try {
        const res = await post(action, payload);
        if (typeof res !== "object") throw new Error("Ответ не JSON");
        return res;
    } catch (e) {
        console.error(`❌ Ошибка POST (${action}):`, e);
        toast("Ошибка запроса к серверу", true);
        return { success: false, error: "NETWORK_ERROR" };
    }
}

/**
 * Загрузить данные из профиля Битрикс (версия из profiles.js)
 */
window.loadFromBitrixProfile = async function () {
    console.log("📥 Загрузка данных из профиля Битрикс...");

    try {
        const res = await safePost("get_bitrix_profile", {});
        if (!res.success) {
            if (typeof window.toast === "function") toast(res.error || "Ошибка загрузки данных из профиля Битрикс", true);
            if (typeof window.setBitrixProfileBadge === "function") setBitrixProfileBadge(false); // 🔴
            return;
        }

        const d = res.data || {};
        // Заполняем поля
        setFieldValue("managerName", d.manager_name);
        setFieldValue("managerPosition", d.manager_position || "");
        setFieldValue("managerPhone", d.manager_phone);
        setFieldValue("managerEmail", d.manager_email);
        setFieldValue("managerCompany", d.manager_company);

        // Сбрасываем выбранный локальный профиль (если есть селект)
        window.currentProfileId = 0;
        const select = document.getElementById("profileSelect");
        if (select) select.value = 0;

        // 🟢 семафор: данные актуализированы
        if (typeof window.setBitrixProfileBadge === "function") setBitrixProfileBadge(true);

        // Сохраняем информацию о загрузке профиля
        if (typeof window.markBitrixProfileLoaded === "function") markBitrixProfileLoaded();

        // Обновляем видимость кнопки после загрузки профиля
        if (typeof window.updateBitrixButtonVisibility === "function") updateBitrixButtonVisibility();

        if (typeof window.toast === "function") toast("✅ Данные загружены из профиля Битрикс");
    } catch (e) {
        console.error(e);
        if (typeof window.toast === "function") toast("Ошибка запроса к серверу", true);
        if (typeof window.setBitrixProfileBadge === "function") setBitrixProfileBadge(false); // 🔴
    }
};

/**
 * Отладка: показать все поля пользователя Битрикс
 */
window.debugBitrixFields = async function () {
    console.log("🔍 Запрос полей пользователя Битрикс...");

    const res = await safePost("debug_user_fields", {});
    if (res.success) {
        console.log("📋 Все поля пользователя Битрикс:", res.fields);
        console.table(res.fields);
        alert("✅ Данные выведены в консоль браузера (F12)");
    } else {
        console.error("❌ Ошибка:", res.error);
        toast("Ошибка получения полей: " + res.error, true);
    }
};

// === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===

function setFieldValue(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val || "";
}

console.log("✅ profiles.js загружен");

/* ============================================================
   АВТОЗАГРУЗКА ПРОФИЛЯ ПРИ СТАРТЕ СТРАНИЦЫ
   ============================================================ */

/**
 * Автоматически загружаем профиль Битрикс при загрузке страницы
 * (только если НЕ загружается сохранённое КП из URL)
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Проверка необходимости автозагрузки профиля...');

    // Проверяем, есть ли kp_id в URL
    const params = new URLSearchParams(window.location.search);
    const kpId = parseInt(params.get('kp_id') || '0', 10);

    if (kpId > 0) {
        console.log(`⏭️ Пропускаем автозагрузку профиля (загружается КП #${kpId})`);
        return;
    }

    // Если НЕТ kp_id - автоматически загружаем профиль Битрикс
    console.log('✅ Запускаем автозагрузку профиля Битрикс');

    // Небольшая задержка для полной загрузки всех скриптов
    setTimeout(() => {
        if (typeof window.loadFromBitrixProfile === 'function') {
            window.loadFromBitrixProfile();
        } else {
            console.error('❌ Функция loadFromBitrixProfile не найдена!');
        }
    }, 500);
});