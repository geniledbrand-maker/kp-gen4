/**
 * Регистрация Service Worker
 * ===========================
 * Добавьте этот скрипт в конец index.php перед </body>
 */

(function() {
    'use strict';

    // Проверяем поддержку Service Worker
    if (!('serviceWorker' in navigator)) {
        console.log('Service Worker не поддерживается в этом браузере');
        return;
    }

    // Регистрируем Service Worker после загрузки страницы
    window.addEventListener('load', () => {
        registerServiceWorker();
        checkForUpdates();
    });

    /**
     * Регистрация Service Worker
     */
    async function registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register(
                '/local/tools/kp_gen4/service-worker.js',
                { scope: '/local/tools/kp_gen4/' }
            );

            console.log('✅ Service Worker зарегистрирован:', registration.scope);

            // Обработка обновлений
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                console.log('🔄 Найдено обновление Service Worker');

                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // Новая версия готова
                        showUpdateNotification(newWorker);
                    }
                });
            });

            // Проверяем обновления каждые 5 минут
            setInterval(() => {
                registration.update();
            }, 5 * 60 * 1000);

        } catch (error) {
            console.error('❌ Ошибка регистрации Service Worker:', error);
        }
    }

    /**
     * Проверка статуса Service Worker
     */
    function checkForUpdates() {
        navigator.serviceWorker.ready.then(registration => {
            // Проверяем обновления при каждой загрузке
            registration.update();
        });
    }

    /**
     * Показываем уведомление о новой версии
     */
    function showUpdateNotification(newWorker) {
        // Создаём уведомление
        const notification = document.createElement('div');
        notification.className = 'update-notification';
        notification.innerHTML = `
            <div class="update-content">
                <div class="update-icon">🔄</div>
                <div class="update-text">
                    <strong>Доступна новая версия!</strong>
                    <p>Обновите страницу, чтобы получить последние улучшения</p>
                </div>
                <button class="btn-update" onclick="window.swUpdateApp()">Обновить</button>
                <button class="btn-dismiss" onclick="this.closest('.update-notification').remove()">×</button>
            </div>
        `;

        // Добавляем стили
        const style = document.createElement('style');
        style.textContent = `
            .update-notification {
                position: fixed;
                bottom: 20px;
                left: 20px;
                right: 20px;
                max-width: 500px;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                z-index: 100000;
                animation: slideUp 0.3s ease;
            }
            
            @keyframes slideUp {
                from { transform: translateY(100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            .update-content {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px;
            }
            
            .update-icon {
                font-size: 32px;
                flex-shrink: 0;
            }
            
            .update-text {
                flex: 1;
            }
            
            .update-text strong {
                display: block;
                font-size: 16px;
                color: #222;
                margin-bottom: 4px;
            }
            
            .update-text p {
                font-size: 14px;
                color: #666;
                margin: 0;
            }
            
            .btn-update {
                background: #722e85;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
                white-space: nowrap;
            }
            
            .btn-update:hover {
                background: #5b3a72;
            }
            
            .btn-dismiss {
                background: none;
                border: none;
                font-size: 24px;
                color: #999;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: background 0.2s;
            }
            
            .btn-dismiss:hover {
                background: #f0f0f0;
            }
            
            @media (max-width: 680px) {
                .update-notification {
                    left: 12px;
                    right: 12px;
                    bottom: 12px;
                }
                
                .update-content {
                    flex-wrap: wrap;
                    gap: 12px;
                }
                
                .update-text {
                    flex: 1 1 100%;
                }
                
                .btn-update {
                    flex: 1;
                }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(notification);

        // Глобальная функция обновления
        window.swUpdateApp = () => {
            newWorker.postMessage({ type: 'SKIP_WAITING' });

            navigator.serviceWorker.addEventListener('controllerchange', () => {
                window.location.reload();
            });
        };
    }

    /**
     * Обработка offline/online событий
     */
    window.addEventListener('online', () => {
        console.log('🌐 Подключение восстановлено');
        showConnectionStatus('online');
    });

    window.addEventListener('offline', () => {
        console.log('📡 Соединение потеряно');
        showConnectionStatus('offline');
    });

    /**
     * Показываем статус подключения
     */
    function showConnectionStatus(status) {
        const existing = document.querySelector('.connection-status');
        if (existing) existing.remove();

        const notification = document.createElement('div');
        notification.className = `connection-status ${status}`;
        notification.textContent = status === 'online'
            ? '🌐 Подключение восстановлено'
            : '📡 Работа в офлайн режиме';

        const style = document.createElement('style');
        style.textContent = `
            .connection-status {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                padding: 12px 24px;
                border-radius: 24px;
                font-size: 14px;
                font-weight: 600;
                z-index: 100000;
                animation: fadeInDown 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .connection-status.online {
                background: #4ade80;
                color: white;
            }
            
            .connection-status.offline {
                background: #f59e0b;
                color: white;
            }
            
            @keyframes fadeInDown {
                from { transform: translate(-50%, -100%); opacity: 0; }
                to { transform: translate(-50%, 0); opacity: 1; }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeInDown 0.3s ease reverse';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Утилита для очистки кэша (для отладки)
     */
    window.clearServiceWorkerCache = async () => {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;

            // Отправляем сообщение SW
            const messageChannel = new MessageChannel();

            return new Promise((resolve) => {
                messageChannel.port1.onmessage = (event) => {
                    if (event.data.success) {
                        console.log('✅ Кэш очищен');
                        resolve(true);
                    }
                };

                registration.active.postMessage(
                    { type: 'CLEAR_CACHE' },
                    [messageChannel.port2]
                );
            });
        }
    };

    console.log('📦 Service Worker регистратор загружен');
})();