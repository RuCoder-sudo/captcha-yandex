(function () {
    'use strict';

    var config = window.CaptchaYandex || {};
    var clientKey = config.clientKey || '';
    var isInvisible = config.invisible || false;
    var language = config.language || 'ru';

    function onCaptchaYandexSuccess(token) {
        var inputs = document.querySelectorAll('input[name="smart-token"]');
        inputs.forEach(function (input) {
            input.value = token;
        });
    }

    window.onCaptchaYandexSuccess = onCaptchaYandexSuccess;

    function initWidgets() {
        if (typeof window.smartCaptcha === 'undefined') {
            return;
        }

        var containers = document.querySelectorAll('.smart-captcha[data-sitekey]');
        containers.forEach(function (container) {
            if (container.dataset.cyInited) {
                return;
            }
            container.dataset.cyInited = '1';

            var opts = {
                sitekey: clientKey || container.dataset.sitekey,
                callback: onCaptchaYandexSuccess,
                language: language || container.dataset.language || 'ru',
            };

            if (isInvisible || container.dataset.invisible === 'true') {
                opts.invisible = true;
            }

            window.smartCaptcha.render(container, opts);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var scriptInterval = setInterval(function () {
            if (typeof window.smartCaptcha !== 'undefined') {
                clearInterval(scriptInterval);
                initWidgets();
            }
        }, 100);

        setTimeout(function () {
            clearInterval(scriptInterval);
        }, 10000);
    });

    document.addEventListener('wpcf7mailsent', function () {
        var inputs = document.querySelectorAll('input[name="smart-token"]');
        inputs.forEach(function (input) {
            input.value = '';
        });
        var widgets = document.querySelectorAll('.smart-captcha[data-cy-inited]');
        widgets.forEach(function (widget) {
            if (typeof window.smartCaptcha !== 'undefined') {
                window.smartCaptcha.reset(widget);
            }
        });
    });
})();
