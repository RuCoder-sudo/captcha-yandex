<?php
/**
 * Plugin Name: Wp captcha yandex 
 * Plugin URI: https://github.com/RuCoder-sudo/captcha-yandex
 * Description: Яндекс-капча защищает вас от спама и других видов автоматических злоупотреблений. С помощью модуля интеграции Яндекс-капчи вы можете блокировать отправку форм спам-ботами.
 * Version: 1.1.0
 * Author: RuCoder
 * Author URI: https://рукодер.рф
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: RuCoder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CY_VERSION', '1.1.0' );
define( 'CY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CY_PLUGIN_FILE', __FILE__ );

require_once CY_PLUGIN_DIR . 'includes/class-captcha-yandex-logger.php';
require_once CY_PLUGIN_DIR . 'includes/class-captcha-yandex-verify.php';
require_once CY_PLUGIN_DIR . 'includes/class-captcha-yandex-frontend.php';
require_once CY_PLUGIN_DIR . 'includes/class-captcha-yandex-admin.php';
require_once CY_PLUGIN_DIR . 'includes/class-captcha-yandex-integrations.php';

/**
 * Main plugin class.
 */
final class Captcha_Yandex {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( CY_PLUGIN_FILE, array( $this, 'activate' ) );
    }

    public function init() {
        load_plugin_textdomain( 'RuCoder', false, dirname( plugin_basename( CY_PLUGIN_FILE ) ) . '/languages' );

        Captcha_Yandex_Admin::get_instance();
        Captcha_Yandex_Frontend::get_instance();
        Captcha_Yandex_Integrations::get_instance();
    }

    public function activate() {
        $defaults = array(
            'client_key'      => '',
            'server_key'      => '',
            'on_comments'     => '1',
            'on_login'        => '1',
            'on_register'     => '1',
            'on_lostpassword' => '1',
            'on_cf7'          => '1',
            'on_elementor'    => '0',
            'invisible'       => '0',
            'language'        => 'ru',
        );
        add_option( 'captcha_yandex_settings', $defaults );

        Captcha_Yandex_Logger::info( 'plugin', 'Плагин Captcha Yandex активирован (v' . CY_VERSION . ')' );
    }
}

Captcha_Yandex::get_instance();
