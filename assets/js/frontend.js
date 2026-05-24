(function () {
    'use strict';

    var config = window.CaptchaYandex || {};
    var clientKey = config.clientKey || '';
    var isInvisible = config.invisible || false;
    var language = config.language || 'ru';

    /*
     * onCaptchaYandexSuccess — определяется один раз глобально.
     * Для Elementor его переопределяет elementor_inject_template (в integrations.php).
     * Здесь — для wp-login, комментариев, CF7.
     */
    if (typeof window.onCaptchaYandexSuccess === 'undefined') {
        window.onCaptchaYandexSuccess = function (token) {
            document.querySelectorAll('input[name="smart-token"]').forEach(function (input) {
                input.value = token;
            });
        };
    }

    /*
     * Инициализация виджетов для НЕ-Elementor форм:
     * wp-login, форма комментариев, CF7 (если тег [yandex-captcha] вставлен вручную).
     * Elementor-формы инициализируются отдельно через elementor_inject_template.
     */
    function initStaticWidgets() {
        if (typeof window.smartCaptcha === 'undefined') {
            return;
        }

        var containers = document.querySelectorAll(
            '.smart-captcha[data-sitekey]:not([data-cy-inited]):not(.cy-widget)'
        );
        containers.forEach(function (container) {
            container.dataset.cyInited = '1';

            var opts = {
                sitekey:  clientKey || container.dataset.sitekey,
                callback: window.onCaptchaYandexSuccess,
                language: language || container.dataset.language || 'ru',
            };
            if (isInvisible || container.dataset.invisible === 'true') {
                opts.invisible = true;
            }

            window.smartCaptcha.render(container, opts);
        });
    }

    function waitForApiAndInit() {
        if (typeof window.smartCaptcha !== 'undefined') {
            initStaticWidgets();
            return;
        }
        var t = setInterval(function () {
            if (typeof window.smartCaptcha !== 'undefined') {
                clearInterval(t);
                initStaticWidgets();
            }
        }, 100);
        setTimeout(function () { clearInterval(t); }, 10000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForApiAndInit);
    } else {
        waitForApiAndInit();
    }

    /* Сброс токена после успешной отправки CF7 */
    document.addEventListener('wpcf7mailsent', function () {
        document.querySelectorAll('input[name="smart-token"]').forEach(function (el) {
            el.value = '';
        });
        document.querySelectorAll('.smart-captcha[data-cy-inited]').forEach(function (w) {
            if (typeof window.smartCaptcha !== 'undefined') {
                window.smartCaptcha.reset(w);
            }
        });
    });
})();
