<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Captcha_Yandex_Frontend {

    private static $instance    = null;
    private static $widget_added = false;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'yandex_captcha', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
        add_action( 'login_enqueue_scripts', array( $this, 'enqueue_script' ) );

        /* ---------- WP Rocket совместимость ----------
         * Исключаем скрипт Яндекса и наш frontend.js из любой обработки
         * WP Rocket (минификация, объединение, задержка, lazyload iframes).
         * Без этих исключений WP Rocket скачивает captcha.js локально и
         * ломает внутренние URL iframe-ов → вместо капчи показывается сайт.
         */
        add_filter( 'rocket_exclude_js',               array( $this, 'rocket_exclude_js' ) );
        add_filter( 'rocket_delay_js_exclusions',      array( $this, 'rocket_delay_js_exclusions' ) );
        add_filter( 'rocket_minify_excluded_external_js', array( $this, 'rocket_exclude_js' ) );
        add_filter( 'rocket_exclude_async_css',        '__return_false' );
        add_filter( 'rocket_lazyload_excluded_src',    array( $this, 'rocket_lazyload_excluded_src' ) );
    }

    /**
     * Исключаем Яндекс-капчу и наш скрипт из минификации/объединения WP Rocket.
     */
    public function rocket_exclude_js( $excluded ) {
        $excluded[] = 'smartcaptcha.yandexcloud.net';
        $excluded[] = 'captcha-yandex/assets/js/frontend.js';
        return $excluded;
    }

    /**
     * Исключаем Яндекс-капчу и наш скрипт из задержки JS WP Rocket.
     * Паттерны — это части URL (строки, не regex).
     */
    public function rocket_delay_js_exclusions( $excluded ) {
        $excluded[] = 'smartcaptcha\.yandexcloud\.net';
        $excluded[] = 'captcha-yandex\/assets\/js\/frontend\.js';
        return $excluded;
    }

    /**
     * Исключаем iframes Яндекс-капчи из lazyload WP Rocket.
     * (captcha.js создаёт iframes динамически, lazyload их ломает)
     */
    public function rocket_lazyload_excluded_src( $excluded ) {
        $excluded[] = 'smartcaptcha.yandexcloud.net';
        return $excluded;
    }

    private function get_options() {
        return get_option( 'captcha_yandex_settings', array() );
    }

    private function is_configured() {
        $opts = $this->get_options();
        return ! empty( $opts['client_key'] ) && ! empty( $opts['server_key'] );
    }

    public function enqueue_script() {
        if ( ! $this->is_configured() ) {
            return;
        }

        if ( ! wp_script_is( 'yandex-smartcaptcha', 'registered' ) ) {
            wp_register_script(
                'yandex-smartcaptcha',
                'https://smartcaptcha.yandexcloud.net/captcha.js',
                array(),
                null,
                array(
                    'strategy'  => 'defer',
                    'in_footer' => false,
                )
            );
            wp_script_add_data( 'yandex-smartcaptcha', 'async', true );
        }

        wp_enqueue_script( 'yandex-smartcaptcha' );
        wp_enqueue_script(
            'cy-frontend',
            CY_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'yandex-smartcaptcha' ),
            CY_VERSION,
            true
        );
        wp_localize_script( 'cy-frontend', 'CaptchaYandex', array(
            'clientKey' => $this->get_options()['client_key'] ?? '',
            'invisible'  => ( $this->get_options()['invisible'] ?? '0' ) === '1',
            'language'   => $this->get_options()['language'] ?? 'ru',
        ) );
        wp_enqueue_style(
            'cy-frontend-style',
            CY_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CY_VERSION
        );
    }

    /**
     * Render the captcha widget HTML.
     */
    public function render_widget( $container_id = '' ) {
        if ( ! $this->is_configured() ) {
            return '';
        }

        if ( empty( $container_id ) ) {
            $container_id = 'cy-widget-' . wp_generate_uuid4();
        }

        $opts      = $this->get_options();
        $client_key = esc_attr( $opts['client_key'] );
        $invisible  = ( $opts['invisible'] ?? '0' ) === '1';
        $language   = esc_attr( $opts['language'] ?? 'ru' );
        $type       = $invisible ? 'invisible' : 'light';

        $html  = '<div class="cy-captcha-container">';
        $html .= '<div id="' . esc_attr( $container_id ) . '" ';
        $html .= 'class="cy-widget smart-captcha" ';
        $html .= 'data-sitekey="' . $client_key . '" ';
        $html .= 'data-language="' . $language . '" ';
        $html .= 'data-callback="onCaptchaYandexSuccess" ';
        if ( $invisible ) {
            $html .= 'data-invisible="true" ';
        }
        $html .= '></div>';
        $html .= '</div>';
        /* Примечание: Яндекс сам создаёт input[name="smart-token"] внутри виджета.
         * Дополнительный hidden-input не нужен и вызывает конфликт в $_POST. */

        return $html;
    }

    public function render_shortcode( $atts ) {
        return $this->render_widget();
    }
}
