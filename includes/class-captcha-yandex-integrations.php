<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all form integrations: WP core forms, CF7, Elementor.
 */
class Captcha_Yandex_Integrations {

    private static $instance = null;
    private $options         = array();

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'captcha_yandex_settings', array() );

        if ( ! $this->is_configured() ) {
            return;
        }

        $this->init_wp_forms();
        $this->init_cf7();
        $this->init_elementor();
    }

    private function is_configured() {
        return ! empty( $this->options['client_key'] ) && ! empty( $this->options['server_key'] );
    }

    private function opt( $key ) {
        return ! empty( $this->options[ $key ] ) && $this->options[ $key ] === '1';
    }

    /* -----------------------------------------------------------------------
     * WordPress core forms
     * -------------------------------------------------------------------- */

    private function init_wp_forms() {
        if ( $this->opt( 'on_comments' ) ) {
            add_filter( 'comment_form_submit_button', array( $this, 'comments_add_widget' ), 10, 2 );
            add_filter( 'preprocess_comment', array( $this, 'comments_verify' ) );
        }

        if ( $this->opt( 'on_login' ) ) {
            add_action( 'login_form', array( $this, 'login_add_widget' ) );
            add_filter( 'authenticate', array( $this, 'login_verify' ), 30, 3 );
        }

        if ( $this->opt( 'on_register' ) ) {
            add_action( 'register_form', array( $this, 'login_add_widget' ) );
            add_filter( 'registration_errors', array( $this, 'register_verify' ), 10, 3 );
        }

        if ( $this->opt( 'on_lostpassword' ) ) {
            add_action( 'lostpassword_form', array( $this, 'login_add_widget' ) );
            add_action( 'lostpassword_post', array( $this, 'lostpassword_verify' ), 10, 1 );
        }
    }

    /* --- Comments --- */

    public function comments_add_widget( $submit_button, $args ) {
        $widget = Captcha_Yandex_Frontend::get_instance()->render_widget();
        return $widget . $submit_button;
    }

    public function comments_verify( $commentdata ) {
        $token  = sanitize_text_field( $_POST['smart-token'] ?? '' );
        $result = Captcha_Yandex_Verify::verify( $token, 'comments' );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Ошибка капчи', 'RuCoder' ), array( 'back_link' => true ) );
        }
        return $commentdata;
    }

    /* --- Login --- */

    public function login_add_widget() {
        echo Captcha_Yandex_Frontend::get_instance()->render_widget(); // phpcs:ignore
    }

    public function login_verify( $user, $username, $password ) {
        if ( ! empty( $username ) && isset( $_POST['smart-token'] ) ) {
            $token  = sanitize_text_field( $_POST['smart-token'] );
            $result = Captcha_Yandex_Verify::verify( $token, 'login' );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        return $user;
    }

    /* --- Registration --- */

    public function register_verify( $errors, $sanitized_user_login, $user_email ) {
        $token  = sanitize_text_field( $_POST['smart-token'] ?? '' );
        $result = Captcha_Yandex_Verify::verify( $token, 'register' );
        if ( is_wp_error( $result ) ) {
            $errors->add( 'cy_failed', $result->get_error_message() );
        }
        return $errors;
    }

    /* --- Lost password --- */

    public function lostpassword_verify( $errors ) {
        $token  = sanitize_text_field( $_POST['smart-token'] ?? '' );
        $result = Captcha_Yandex_Verify::verify( $token, 'lostpassword' );
        if ( is_wp_error( $result ) ) {
            if ( $errors instanceof WP_Error ) {
                $errors->add( 'cy_failed', $result->get_error_message() );
            }
        }
    }

    /* -----------------------------------------------------------------------
     * Contact Form 7
     * -------------------------------------------------------------------- */

    private function init_cf7() {
        if ( ! $this->opt( 'on_cf7' ) ) {
            return;
        }

        if ( ! class_exists( 'WPCF7' ) ) {
            return;
        }

        add_action( 'wpcf7_init', array( $this, 'cf7_add_tag' ) );
        add_filter( 'wpcf7_validate_yandex-captcha',  array( $this, 'cf7_validate_tag' ), 10, 2 );
        add_filter( 'wpcf7_validate_yandex-captcha*', array( $this, 'cf7_validate_tag' ), 10, 2 );
        add_action( 'wpcf7_before_send_mail', array( $this, 'cf7_validate_submission' ) );
    }

    public function cf7_add_tag() {
        wpcf7_add_form_tag(
            array( 'yandex-captcha', 'yandex-captcha*' ),
            array( $this, 'cf7_render_tag' ),
            array( 'name-attr' => true )
        );
    }

    public function cf7_render_tag( $tag ) {
        return Captcha_Yandex_Frontend::get_instance()->render_widget();
    }

    public function cf7_validate_tag( $result, $tag ) {
        $token  = sanitize_text_field( $_POST['smart-token'] ?? '' );
        $verify = Captcha_Yandex_Verify::verify( $token, 'cf7' );
        if ( is_wp_error( $verify ) ) {
            $result->invalidate( $tag, $verify->get_error_message() );
        }
        return $result;
    }

    public function cf7_validate_submission( $contact_form ) {
        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return;
        }

        $token  = sanitize_text_field( $_POST['smart-token'] ?? '' );
        $result = Captcha_Yandex_Verify::verify( $token, 'cf7' );

        if ( is_wp_error( $result ) ) {
            $submission->set_response( $result->get_error_message() );
            $submission->set_status( 'validation_failed' );
        }
    }

    /* -----------------------------------------------------------------------
     * Elementor Forms
     * Капча внедряется через JavaScript перед кнопкой «Отправить».
     * Серверная проверка — через хук elementor_pro/forms/validation.
     * -------------------------------------------------------------------- */

    private function init_elementor() {
        if ( ! $this->opt( 'on_elementor' ) ) {
            return;
        }

        $elementor_active = defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( 'ElementorPro\Plugin' );

        if ( ! $elementor_active ) {
            return;
        }

        add_action( 'elementor_pro/forms/validation', array( $this, 'elementor_validate' ), 10, 2 );
        add_action( 'wp_footer', array( $this, 'elementor_inject_template' ) );
    }

    /**
     * Server-side validation for Elementor Pro forms.
     */
    public function elementor_validate( $record, $ajax_handler ) {
        $token  = sanitize_text_field( $_POST['smart-token'] ?? '' );
        $result = Captcha_Yandex_Verify::verify( $token, 'elementor' );

        if ( is_wp_error( $result ) ) {
            $ajax_handler->add_error_message( $result->get_error_message() );
            $ajax_handler->set_success( false );
        }
    }

    /**
     * Output a hidden captcha template + JS to inject it into all Elementor forms.
     * The JS inserts the widget before the submit button in every .elementor-widget-form.
     */
    public function elementor_inject_template() {
        if ( ! is_singular() && ! is_page() ) {
            return;
        }

        $opts       = get_option( 'captcha_yandex_settings', array() );
        $client_key = esc_attr( $opts['client_key'] ?? '' );
        $invisible  = ( $opts['invisible'] ?? '0' ) === '1';
        $language   = esc_attr( $opts['language'] ?? 'ru' );

        if ( empty( $client_key ) ) {
            return;
        }

        $invisible_opt = $invisible ? 'true' : 'false';
        ?>
        <script>
        (function () {
            var CY_KEY      = '<?php echo $client_key; ?>';
            var CY_LANG     = '<?php echo $language; ?>';
            var CY_INVIS    = <?php echo $invisible_opt; ?>;

            /*
             * onCaptchaYandexSuccess — Яндекс вызывает этот callback и сам пишет
             * токен в input[data-testid="smart-token"] внутри виджета.
             * Мы дополнительно копируем токен во ВСЕ остальные
             * input[name="smart-token"] формы (на случай нескольких форм).
             */
            window.onCaptchaYandexSuccess = function (token) {
                document.querySelectorAll('input[name="smart-token"]').forEach(function (el) {
                    el.value = token;
                });
            };

            function cyRenderWidget(widgetDiv) {
                if ( widgetDiv.dataset.cyInited ) return;
                widgetDiv.dataset.cyInited = '1';

                var opts = {
                    sitekey:  CY_KEY,
                    callback: window.onCaptchaYandexSuccess,
                    language: CY_LANG,
                };
                if (CY_INVIS) opts.invisible = true;

                window.smartCaptcha.render(widgetDiv, opts);
            }

            function cyInjectElementorForms() {
                var forms = document.querySelectorAll('.elementor-widget-form form.elementor-form');
                forms.forEach(function (form, idx) {
                    if (form.querySelector('.cy-captcha-container')) return;

                    /* Создаём контейнер */
                    var container  = document.createElement('div');
                    container.className = 'cy-captcha-container';

                    /* Виджет-div — именно в него Яндекс рендерит iframes */
                    var widgetDiv = document.createElement('div');
                    widgetDiv.className   = 'smart-captcha cy-widget';
                    widgetDiv.id          = 'cy-widget-el-' + idx + '-' + Math.random().toString(36).substr(2,5);
                    widgetDiv.setAttribute('data-sitekey', CY_KEY);
                    widgetDiv.setAttribute('data-language', CY_LANG);
                    widgetDiv.setAttribute('data-callback', 'onCaptchaYandexSuccess');
                    if (CY_INVIS) widgetDiv.setAttribute('data-invisible', 'true');

                    container.appendChild(widgetDiv);

                    var submitGroup = form.querySelector('.elementor-field-type-submit');
                    if (submitGroup) {
                        submitGroup.parentNode.insertBefore(container, submitGroup);
                    } else {
                        form.appendChild(container);
                    }

                    /* Рендерим если API уже загружен, иначе ждём */
                    if (typeof window.smartCaptcha !== 'undefined') {
                        cyRenderWidget(widgetDiv);
                    }
                });
            }

            function waitAndInject() {
                if (typeof window.smartCaptcha !== 'undefined') {
                    cyInjectElementorForms();
                } else {
                    var t = setInterval(function () {
                        if (typeof window.smartCaptcha !== 'undefined') {
                            clearInterval(t);
                            cyInjectElementorForms();
                        }
                    }, 150);
                    setTimeout(function () { clearInterval(t); }, 15000);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', waitAndInject);
            } else {
                waitAndInject();
            }

            /* Elementor popups */
            document.addEventListener('elementor/popup/show', function () {
                setTimeout(waitAndInject, 300);
            });

            /* После успешной отправки CF7 — сброс токенов */
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
        </script>
        <?php
    }
}
