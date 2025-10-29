"use strict";

/**
 * Drag and Drop для перетаскивания строк таблицы
 */

let sortableInstance = null;

function initDragDrop() {
    const tbody = document.getElementById('tbody');

    if (!tbody) {
        console.warn('tbody not found for drag-drop');
        return;
    }

    // Уничтожаем старый инстанс если есть
    if (sortableInstance) {
        sortableInstance.destroy();
        sortableInstance = null;
    }

    // Инициализируем только на десктопе
    const isMobileView = window.innerWidth <= 900;
    if (isMobileView) {
        console.log('Drag-drop disabled on mobile');
        return;
    }

    // Проверяем наличие Sortable
    if (typeof Sortable === 'undefined') {
        console.error('Sortable library not loaded!');
        return;
    }

    sortableInstance = new Sortable(tbody, {
        animation: 200,
        easing: 'cubic-bezier(0.25, 0.8, 0.25, 1)',
        handle: 'tr', // Вся строка - handle
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',

        onEnd: function(evt) {
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;

            if (oldIndex === newIndex) {
                return;
            }

            console.log(`Moved row from ${oldIndex} to ${newIndex}`);

            // Обновляем массив products
            if (typeof products !== 'undefined' && Array.isArray(products)) {
                const movedItem = products.splice(oldIndex, 1)[0];
                products.splice(newIndex, 0, movedItem);

                // Перерисовываем таблицу
                if (typeof render === 'function') {
                    render();
                }

                // Показываем уведомление
                if (typeof toast === 'function') {
                    toast('✓ Порядок товаров изменен');
                }
            }
        }
    });

    console.log('✅ Drag-drop initialized');
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(initDragDrop, 500);
});

// Переинициализация при ресайзе окна
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        initDragDrop();
    }, 500);
});

// Экспортируем глобально
window.initDragDrop = initDragDrop;