=== Captcha Yandex ===
Contributors: rucoder
Tags: captcha, yandex, smartcaptcha, spam, contact form 7, elementor, antispam
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Яндекс Smart Captcha для WordPress: защита комментариев, авторизации, регистрации, CF7 и Elementor Forms от спама.

== Description ==

**Captcha Yandex** интегрирует [Яндекс Smart Captcha](https://cloud.yandex.ru/services/smartcaptcha) в формы WordPress.

= Поддерживаемые формы =

* Форма комментариев WordPress
* Форма авторизации (wp-login.php)
* Форма регистрации
* Форма восстановления пароля
* Contact Form 7
* Elementor Forms
* Произвольные формы через шорткод `[yandex_captcha]`

= Возможности =

* Простая настройка — вставьте два ключа и выберите формы.
* Видимая и невидимая капча.
* Выбор языка виджета.
* Встроенная документация прямо в панели плагина.

== Installation ==

1. Загрузите папку `captcha-yandex` в директорию `/wp-content/plugins/`.
2. Активируйте плагин в меню «Плагины» WordPress.
3. Перейдите в **Captcha Yandex → Интеграция** и введите клиентский и серверный ключи.
4. Откройте **Captcha Yandex → Настройки** и выберите, где показывать капчу.

== Frequently Asked Questions ==

= Где получить ключи? =
В Яндекс Cloud Console: https://console.yandex.cloud/smartcaptcha

= Как добавить капчу в Contact Form 7? =
Включите опцию «Contact Form 7» в настройках плагина. Тег `[yandex-captcha]` добавится автоматически при валидации. Можно добавить тег вручную в редакторе CF7.

= Как вставить капчу в произвольную форму? =
Используйте шорткод `[yandex_captcha]` или PHP: `echo do_shortcode('[yandex_captcha]');`

== Changelog ==

= 1.0.0 =
* Первый релиз.
* Интеграция с WP комментариями, авторизацией, регистрацией, восстановлением пароля.
* Интеграция с Contact Form 7.
* Интеграция с Elementor Forms.
* Панель управления с тремя вкладками: Интеграция, Настройки, Документация.
* Видимая и невидимая капча.
* Мультиязычная поддержка виджета.
