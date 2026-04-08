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
        $html .= '<input type="hidden" name="smart-token" id="smart-token-' . esc_attr( $container_id ) . '">';
        $html .= '</div>';

        return $html;
    }

    public function render_shortcode( $atts ) {
        return $this->render_widget();
    }
}
