/**
 * ============================================================
 * МОДАЛЬНОЕ ОКНО МАССОВОГО ДОБАВЛЕНИЯ - КОНТРОЛЛЕР
 * ============================================================
 */

(function() {
    'use strict';

    // Шаблоны артикулов
    const templates = {
        led: `APT001 x 10
APT002 x 5
APT003
APT004 x 2
APT005 x 8
APT006 x 3
APT007 x 15
APT008 x 4`,
        outdoor: `APT101 x 20
APT102 x 15
APT103 x 10
APT104 x 8
APT105 x 12
APT106 x 6
APT107 x 25
APT108 x 18
APT109 x 10
APT110 x 14
APT111 x 7
APT112 x 9`,
        warehouse: `APT201 x 30
APT202 x 25
APT203 x 20
APT204 x 15
APT205 x 40
APT206 x 10
APT207 x 35
APT208 x 22
APT209 x 18
APT210 x 28
APT211 x 12
APT212 x 16
APT213 x 24
APT214 x 19
APT215 x 11`
    };

    // Элементы DOM
    let modal, textarea, wrapper, lineCount, articleCount, previewSection, previewList;
    let fileInput, templatesMenu;

    /**
     * Инициализация модального окна
     */
    function init() {
        modal = document.getElementById('bulkAddModal');
        textarea = document.getElementById('bulkModalTextarea');
        wrapper = document.getElementById('bulkTextareaWrapper');
        lineCount = document.getElementById('bulkLineCount');
        articleCount = document.getElementById('bulkArticleCount');
        previewSection = document.getElementById('bulkPreviewSection');
        previewList = document.getElementById('bulkPreviewList');
        fileInput = document.getElementById('bulkFileInput');
        templatesMenu = document.getElementById('bulkTemplatesMenu');

        if (!modal || !textarea) {
            console.warn('Bulk modal elements not found');
            return;
        }

        attachEventListeners();
        console.log('✅ Bulk modal initialized');
    }

    /**
     * Подключение обработчиков событий
     */
    function attachEventListeners() {
        // Обновление счётчиков при вводе
        textarea.addEventListener('input', updateStats);

        // Drag & Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            wrapper.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            wrapper.addEventListener(eventName, () => wrapper.classList.add('dragover'));
        });

        ['dragleave', 'drop'].forEach(eventName => {
            wrapper.addEventListener(eventName, () => wrapper.classList.remove('dragover'));
        });

        wrapper.addEventListener('drop', handleDrop);

        // Кнопки
        document.getElementById('btnBulkUploadFile')?.addEventListener('click', () => fileInput.click());
        document.getElementById('btnBulkClearText')?.addEventListener('click', clearText);
        document.getElementById('btnBulkFormatText')?.addEventListener('click', formatText);
        document.getElementById('btnBulkTemplates')?.addEventListener('click', toggleTemplatesMenu);
        document.getElementById('btnBulkClosePreview')?.addEventListener('click', () => {
            previewSection?.classList.remove('visible');
        });
        document.getElementById('btnBulkAddFromList')?.addEventListener('click', addFromList);

        // Загрузка файлов
        fileInput?.addEventListener('change', handleFileSelect);

        // Шаблоны
        document.querySelectorAll('.template-item').forEach(item => {
            item.addEventListener('click', function() {
                const template = this.dataset.template;
                if (templates[template]) {
                    textarea.value = templates[template];
                    updateStats();
                    templatesMenu?.classList.remove('open');
                }
            });
        });

        // Автоматический превью
        const autoValidate = document.getElementById('bulkAutoValidate');
        autoValidate?.addEventListener('change', () => {
            if (autoValidate.checked && textarea.value.trim()) {
                showPreview();
            } else {
                previewSection?.classList.remove('visible');
            }
        });

        // Закрытие меню шаблонов при клике вне его
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.templates-dropdown')) {
                templatesMenu?.classList.remove('open');
            }
        });

        // Закрытие модалки по клику на overlay
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeBulkAddModal();
            }
        });

        // Горячие клавиши
        textarea?.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'Enter') {
                addFromList();
            }
        });
    }

    /**
     * Предотвращение дефолтного поведения для Drag & Drop
     */
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    /**
     * Обработка Drop файла
     */
    function handleDrop(e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    }

    /**
     * Обработка выбора файла
     */
    function handleFileSelect(e) {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    }

    /**
     * Чтение содержимого файла
     */
    function handleFile(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            textarea.value = e.target.result;
            updateStats();
            if (typeof window.toast === 'function') {
                window.toast(`Файл "${file.name}" загружен!\nНайдено строк: ${textarea.value.split('\n').length}`);
            }
        };
        reader.readAsText(file);
    }

    /**
     * Обновление статистики
     */
    function updateStats() {
        const text = textarea.value.trim();
        const lines = text ? text.split('\n').filter(l => l.trim()).length : 0;
        const articles = lines;

        if (lineCount) {
            lineCount.textContent = `${lines} ${lines === 1 ? 'строка' : lines < 5 ? 'строки' : 'строк'}`;
        }

        if (articleCount) {
            articleCount.textContent = `${articles} ${articles === 1 ? 'артикул' : articles < 5 ? 'артикула' : 'артикулов'}`;
        }

        if (text) {
            wrapper?.classList.add('has-content');
        } else {
            wrapper?.classList.remove('has-content');
        }

        // Автоматический превью
        const autoValidate = document.getElementById('bulkAutoValidate');
        if (autoValidate?.checked && text) {
            showPreview();
        }
    }

    /**
     * Очистка текста
     */
    function clearText() {
        if (confirm('Очистить поле ввода?')) {
            textarea.value = '';
            updateStats();
            previewSection?.classList.remove('visible');
        }
    }

    /**
     * Форматирование текста
     */
    function formatText() {
        const lines = textarea.value.split('\n');
        const formatted = lines
            .map(line => line.trim())
            .filter(line => line)
            .map(line => {
                // Нормализуем формат к "АРТИКУЛ x КОЛИЧЕСТВО"
                const match = line.match(/^(.+?)[\s;x*]+(\d+)$/i);
                if (match) {
                    return `${match[1].trim()} x ${match[2]}`;
                }
                return line;
            })
            .join('\n');

        textarea.value = formatted;
        updateStats();

        if (typeof window.toast === 'function') {
            window.toast('Текст отформатирован');
        }
    }

    /**
     * Переключение меню шаблонов
     */
    function toggleTemplatesMenu(e) {
        e?.stopPropagation();
        templatesMenu?.classList.toggle('open');
    }

    /**
     * Показать превью
     */
    function showPreview() {
        const text = textarea.value.trim();
        if (!text || !previewList) {
            previewSection?.classList.remove('visible');
            return;
        }

        const lines = text.split('\n').filter(l => l.trim());
        previewList.innerHTML = '';

        lines.forEach((line) => {
            const item = document.createElement('div');
            item.className = 'preview-item';

            let article = line.trim();
            let quantity = 1;
            const match = line.match(/^(.+?)[\s;x*]+(\d+)$/i);
            if (match) {
                article = match[1].trim();
                quantity = parseInt(match[2], 10);
            }

            // Проверка (имитация - проверяет, есть ли товар в массиве products)
            let exists = false;
            if (typeof window.products !== 'undefined' && Array.isArray(window.products)) {
                exists = window.products.some(p => {
                    const pArticle = String(p.article || p.xml_id || p.code || '').trim();
                    return pArticle === article;
                });
            }

            if (exists) {
                item.classList.add('exists');
            }

            item.innerHTML = `
                <div>
                    <span class="preview-article">${escapeHtml(article)}</span>
                    <span class="preview-qty">× ${quantity}</span>
                </div>
                ${exists ? '<span class="preview-status exists">Уже в КП</span>' : '<span class="preview-status">Новый</span>'}
            `;

            previewList.appendChild(item);
        });

        previewSection?.classList.add('visible');
    }

    /**
     * Экранирование HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Добавление из списка
     */
    function addFromList() {
        const text = textarea.value.trim();
        if (!text) {
            if (typeof window.toast === 'function') {
                window.toast('Введите артикулы!', true);
            } else {
                alert('Введите артикулы!');
            }
            return;
        }

        const lines = text.split('\n').filter(l => l.trim()).length;
        const confirmMessage = `Добавить ${lines} ${lines === 1 ? 'позицию' : lines < 5 ? 'позиции' : 'позиций'}?`;

        if (confirm(confirmMessage)) {
            // Вызываем существующую функцию addMultiple() из product-manager.js
            if (typeof window.addMultiple === 'function') {
                // Устанавливаем значение в основное поле
                const bulkTextarea = document.getElementById('bulkTextarea');
                if (bulkTextarea) {
                    bulkTextarea.value = textarea.value;
                }

                // Синхронизируем чекбокс
                const separateCheckbox = document.getElementById('addAsSeparate');
                const modalSeparateCheckbox = document.getElementById('bulkAddAsSeparate');
                if (separateCheckbox && modalSeparateCheckbox) {
                    separateCheckbox.checked = modalSeparateCheckbox.checked;
                }

                // Вызываем функцию добавления
                window.addMultiple();

                // Очищаем и закрываем модалку
                textarea.value = '';
                updateStats();
                previewSection?.classList.remove('visible');
                closeBulkAddModal();
            } else {
                console.error('Функция addMultiple() не найдена');
                if (typeof window.toast === 'function') {
                    window.toast('Ошибка: функция addMultiple() не найдена', true);
                } else {
                    alert('Ошибка: функция addMultiple() не найдена');
                }
            }
        }
    }

    /**
     * Открытие модального окна
     */
    window.openBulkAddModal = function() {
        if (!modal) {
            console.error('Bulk modal not found');
            return;
        }

        modal.classList.add('show');
        textarea?.focus();

        // Синхронизируем текст из основного поля (если есть)
        const mainTextarea = document.getElementById('bulkTextarea');
        if (mainTextarea && mainTextarea.value.trim()) {
            textarea.value = mainTextarea.value;
            updateStats();
        }

        console.log('✅ Bulk modal opened');
    };

    /**
     * Закрытие модального окна
     */
    window.closeBulkAddModal = function() {
        if (!modal) return;

        // Проверяем, есть ли несохранённый текст
        if (textarea.value.trim() && !confirm('Закрыть без добавления?')) {
            return;
        }

        modal.classList.remove('show');
        console.log('✅ Bulk modal closed');
    };

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();