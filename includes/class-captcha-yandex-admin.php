<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Captcha_Yandex_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_cy_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_cy_clear_logs', array( $this, 'clear_logs' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Captcha Yandex', 'RuCoder' ),
            __( 'Captcha Yandex', 'RuCoder' ),
            'manage_options',
            'captcha-yandex',
            array( $this, 'render_page' ),
            'dashicons-shield',
            85
        );
    }

    public function register_settings() {
        register_setting( 'captcha_yandex_group', 'captcha_yandex_settings', array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    public function sanitize_settings( $input ) {
        $clean = array();
        $clean['client_key']      = sanitize_text_field( $input['client_key'] ?? '' );
        $clean['server_key']      = sanitize_text_field( $input['server_key'] ?? '' );
        $clean['on_comments']     = ! empty( $input['on_comments'] ) ? '1' : '0';
        $clean['on_login']        = ! empty( $input['on_login'] ) ? '1' : '0';
        $clean['on_register']     = ! empty( $input['on_register'] ) ? '1' : '0';
        $clean['on_lostpassword'] = ! empty( $input['on_lostpassword'] ) ? '1' : '0';
        $clean['on_cf7']          = ! empty( $input['on_cf7'] ) ? '1' : '0';
        $clean['on_elementor']    = ! empty( $input['on_elementor'] ) ? '1' : '0';
        $clean['invisible']       = ! empty( $input['invisible'] ) ? '1' : '0';
        $clean['language']        = sanitize_text_field( $input['language'] ?? 'ru' );
        return $clean;
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Нет доступа.', 'RuCoder' ) );
        }
        check_admin_referer( 'cy_save_settings_nonce' );

        $input = $_POST['captcha_yandex_settings'] ?? array();
        $clean = $this->sanitize_settings( $input );
        update_option( 'captcha_yandex_settings', $clean );

        Captcha_Yandex_Logger::info( 'admin', 'Настройки плагина сохранены.' );

        wp_redirect( add_query_arg( array( 'page' => 'captcha-yandex', 'tab' => 'integration', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function clear_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Нет доступа.', 'RuCoder' ) );
        }
        check_admin_referer( 'cy_clear_logs_nonce' );
        Captcha_Yandex_Logger::clear();

        wp_redirect( add_query_arg( array( 'page' => 'captcha-yandex', 'tab' => 'logs', 'cleared' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_captcha-yandex' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'cy-admin-style', CY_PLUGIN_URL . 'assets/css/admin.css', array(), CY_VERSION );
    }

    public function render_page() {
        $options = get_option( 'captcha_yandex_settings', array() );
        $tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'integration';
        $saved   = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
        $cleared = isset( $_GET['cleared'] ) && '1' === $_GET['cleared'];

        $logs_count = Captcha_Yandex_Logger::count_by_type();
        $error_count = $logs_count[ Captcha_Yandex_Logger::TYPE_ERROR ] + $logs_count[ Captcha_Yandex_Logger::TYPE_WARNING ];
        ?>
        <div class="wrap cy-wrap">
            <h1 class="cy-title">
                <span class="cy-logo">&#128737;</span>
                <?php esc_html_e( 'Captcha Yandex', 'RuCoder' ); ?>
            </h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Настройки сохранены.', 'RuCoder' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $cleared ) : ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php esc_html_e( 'Логи очищены.', 'RuCoder' ); ?></p>
                </div>
            <?php endif; ?>

            <nav class="cy-tabs">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=captcha-yandex&tab=integration' ) ); ?>"
                   class="cy-tab <?php echo $tab === 'integration' ? 'cy-tab--active' : ''; ?>">
                    <?php esc_html_e( 'Интеграция', 'RuCoder' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=captcha-yandex&tab=settings' ) ); ?>"
                   class="cy-tab <?php echo $tab === 'settings' ? 'cy-tab--active' : ''; ?>">
                    <?php esc_html_e( 'Настройки', 'RuCoder' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=captcha-yandex&tab=logs' ) ); ?>"
                   class="cy-tab <?php echo $tab === 'logs' ? 'cy-tab--active' : ''; ?>">
                    <?php esc_html_e( 'Логи', 'RuCoder' ); ?>
                    <?php if ( $error_count > 0 ) : ?>
                        <span class="cy-tab-badge cy-tab-badge--error"><?php echo (int) $error_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=captcha-yandex&tab=docs' ) ); ?>"
                   class="cy-tab <?php echo $tab === 'docs' ? 'cy-tab--active' : ''; ?>">
                    <?php esc_html_e( 'Документация', 'RuCoder' ); ?>
                </a>
            </nav>

            <div class="cy-panel">
                <?php
                if ( 'integration' === $tab ) {
                    $this->render_tab_integration( $options );
                } elseif ( 'settings' === $tab ) {
                    $this->render_tab_settings( $options );
                } elseif ( 'logs' === $tab ) {
                    $this->render_tab_logs();
                } elseif ( 'docs' === $tab ) {
                    $this->render_tab_docs();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_tab_integration( $options ) {
        $client_key = $options['client_key'] ?? '';
        $server_key = $options['server_key'] ?? '';
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'cy_save_settings_nonce' ); ?>
            <input type="hidden" name="action" value="cy_save_settings">
            <input type="hidden" name="captcha_yandex_settings[on_comments]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_login]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_register]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_lostpassword]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_cf7]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_elementor]" value="0">
            <input type="hidden" name="captcha_yandex_settings[invisible]" value="0">

            <div class="cy-section">
                <h2><?php esc_html_e( 'API-ключи Яндекс Smart Captcha', 'RuCoder' ); ?></h2>
                <p class="cy-desc"><?php esc_html_e( 'Получите ключи в Яндекс Cloud Console и вставьте их ниже. Подробная инструкция — во вкладке «Документация».', 'RuCoder' ); ?></p>

                <table class="form-table cy-form-table">
                    <tr>
                        <th scope="row">
                            <label for="cy_client_key"><?php esc_html_e( 'Клиентский ключ (Client Key)', 'RuCoder' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="cy_client_key"
                                   name="captcha_yandex_settings[client_key]"
                                   value="<?php echo esc_attr( $client_key ); ?>"
                                   class="regular-text cy-input"
                                   placeholder="ysc1_XXXXXXXXXXXXXXXXXXXXXX">
                            <p class="description"><?php esc_html_e( 'Ключ для подключения виджета капчи на странице (используется в JS-коде).', 'RuCoder' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cy_server_key"><?php esc_html_e( 'Серверный ключ (Server Key)', 'RuCoder' ); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="cy_server_key"
                                   name="captcha_yandex_settings[server_key]"
                                   value="<?php echo esc_attr( $server_key ); ?>"
                                   class="regular-text cy-input"
                                   placeholder="ysc2_XXXXXXXXXXXXXXXXXXXXXX">
                            <p class="description"><?php esc_html_e( 'Секретный ключ для серверной проверки токена (не отображайте его публично).', 'RuCoder' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php if ( ! empty( $client_key ) && ! empty( $server_key ) ) : ?>
                    <div class="cy-status cy-status--ok">
                        &#10003; <?php esc_html_e( 'Ключи введены. Капча активна.', 'RuCoder' ); ?>
                    </div>
                <?php else : ?>
                    <div class="cy-status cy-status--warn">
                        &#9888; <?php esc_html_e( 'Ключи не введены. Капча отключена.', 'RuCoder' ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="cy-section">
                <h2><?php esc_html_e( 'Сохранённые настройки формы', 'RuCoder' ); ?></h2>
                <?php
                $all = get_option( 'captcha_yandex_settings', array() );
                $map = array(
                    'on_comments'     => 'Форма комментариев',
                    'on_login'        => 'Форма авторизации',
                    'on_register'     => 'Форма регистрации',
                    'on_lostpassword' => 'Восстановление пароля',
                    'on_cf7'          => 'Contact Form 7',
                    'on_elementor'    => 'Elementor Forms',
                    'invisible'       => 'Невидимая капча',
                );
                echo '<ul class="cy-summary">';
                foreach ( $map as $key => $label ) {
                    $val = ! empty( $all[ $key ] ) && $all[ $key ] === '1';
                    echo '<li><span class="cy-dot ' . ( $val ? 'cy-dot--on' : 'cy-dot--off' ) . '"></span> ' . esc_html( $label ) . ': <strong>' . ( $val ? 'Вкл' : 'Выкл' ) . '</strong></li>';
                }
                echo '</ul>';
                ?>
            </div>

            <?php submit_button( __( 'Сохранить ключи', 'RuCoder' ), 'primary cy-btn-save', 'submit', true ); ?>
        </form>
        <?php
    }

    private function render_tab_settings( $options ) {
        $on_comments     = ! empty( $options['on_comments'] )     && $options['on_comments']     === '1';
        $on_login        = ! empty( $options['on_login'] )        && $options['on_login']        === '1';
        $on_register     = ! empty( $options['on_register'] )     && $options['on_register']     === '1';
        $on_lostpassword = ! empty( $options['on_lostpassword'] ) && $options['on_lostpassword'] === '1';
        $on_cf7          = ! empty( $options['on_cf7'] )          && $options['on_cf7']          === '1';
        $on_elementor    = ! empty( $options['on_elementor'] )    && $options['on_elementor']    === '1';
        $invisible       = ! empty( $options['invisible'] )       && $options['invisible']       === '1';
        $language        = $options['language'] ?? 'ru';
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'cy_save_settings_nonce' ); ?>
            <input type="hidden" name="action" value="cy_save_settings">
            <input type="hidden" name="captcha_yandex_settings[client_key]" value="<?php echo esc_attr( $options['client_key'] ?? '' ); ?>">
            <input type="hidden" name="captcha_yandex_settings[server_key]" value="<?php echo esc_attr( $options['server_key'] ?? '' ); ?>">

            <input type="hidden" name="captcha_yandex_settings[on_comments]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_login]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_register]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_lostpassword]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_cf7]" value="0">
            <input type="hidden" name="captcha_yandex_settings[on_elementor]" value="0">
            <input type="hidden" name="captcha_yandex_settings[invisible]" value="0">

            <div class="cy-section">
                <h2><?php esc_html_e( 'Где показывать капчу', 'RuCoder' ); ?></h2>

                <table class="form-table cy-form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'WordPress формы', 'RuCoder' ); ?></th>
                        <td>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[on_comments]" value="1" <?php checked( $on_comments ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Форма комментариев', 'RuCoder' ); ?>
                            </label><br>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[on_login]" value="1" <?php checked( $on_login ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Форма авторизации (wp-login.php)', 'RuCoder' ); ?>
                            </label><br>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[on_register]" value="1" <?php checked( $on_register ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Форма регистрации', 'RuCoder' ); ?>
                            </label><br>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[on_lostpassword]" value="1" <?php checked( $on_lostpassword ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Форма восстановления пароля', 'RuCoder' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Плагины форм', 'RuCoder' ); ?></th>
                        <td>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[on_cf7]" value="1" <?php checked( $on_cf7 ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Contact Form 7', 'RuCoder' ); ?>
                                <?php if ( ! class_exists( 'WPCF7' ) ) : ?>
                                    <span class="cy-badge cy-badge--warn"><?php esc_html_e( 'CF7 не установлен', 'RuCoder' ); ?></span>
                                <?php else : ?>
                                    <span class="cy-badge cy-badge--ok"><?php esc_html_e( 'CF7 активен', 'RuCoder' ); ?></span>
                                <?php endif; ?>
                            </label><br>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[on_elementor]" value="1" <?php checked( $on_elementor ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Elementor Forms', 'RuCoder' ); ?>
                                <?php if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) && ! class_exists( 'ElementorPro\Plugin' ) ) : ?>
                                    <span class="cy-badge cy-badge--warn"><?php esc_html_e( 'Elementor Pro не найден', 'RuCoder' ); ?></span>
                                <?php else : ?>
                                    <span class="cy-badge cy-badge--ok"><?php esc_html_e( 'Elementor Pro активен', 'RuCoder' ); ?></span>
                                <?php endif; ?>
                            </label>
                            <p class="description" style="margin-top:6px;">
                                <?php esc_html_e( 'Требуется Elementor Pro. Капча автоматически добавляется перед кнопкой «Отправить» на всех Elementor-формах страницы.', 'RuCoder' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Режим капчи', 'RuCoder' ); ?></th>
                        <td>
                            <label class="cy-toggle">
                                <input type="checkbox" name="captcha_yandex_settings[invisible]" value="1" <?php checked( $invisible ); ?>>
                                <span class="cy-toggle__slider"></span>
                                <?php esc_html_e( 'Невидимая капча (без виджета, проверка в фоне)', 'RuCoder' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'При включённом режиме виджет не отображается — проверка происходит автоматически при отправке формы.', 'RuCoder' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cy_language"><?php esc_html_e( 'Язык виджета', 'RuCoder' ); ?></label>
                        </th>
                        <td>
                            <select id="cy_language" name="captcha_yandex_settings[language]">
                                <option value="ru" <?php selected( $language, 'ru' ); ?>>Русский</option>
                                <option value="en" <?php selected( $language, 'en' ); ?>>English</option>
                                <option value="be" <?php selected( $language, 'be' ); ?>>Беларуская</option>
                                <option value="kk" <?php selected( $language, 'kk' ); ?>>Қазақша</option>
                                <option value="tt" <?php selected( $language, 'tt' ); ?>>Татарча</option>
                                <option value="uk" <?php selected( $language, 'uk' ); ?>>Українська</option>
                                <option value="uz" <?php selected( $language, 'uz' ); ?>>O'zbekcha</option>
                                <option value="tr" <?php selected( $language, 'tr' ); ?>>Türkçe</option>
                                <option value="de" <?php selected( $language, 'de' ); ?>>Deutsch</option>
                                <option value="fr" <?php selected( $language, 'fr' ); ?>>Français</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button( __( 'Сохранить настройки', 'RuCoder' ), 'primary cy-btn-save', 'submit', true ); ?>
        </form>
        <?php
    }

    private function render_tab_logs() {
        $filter  = isset( $_GET['log_type'] ) ? sanitize_key( $_GET['log_type'] ) : '';
        $logs    = Captcha_Yandex_Logger::get_logs( $filter );
        $counts  = Captcha_Yandex_Logger::count_by_type();
        $total   = array_sum( $counts );

        $type_labels = array(
            ''        => 'Все (' . $total . ')',
            'success' => 'Успех (' . $counts['success'] . ')',
            'info'    => 'Инфо (' . $counts['info'] . ')',
            'warning' => 'Предупреждения (' . $counts['warning'] . ')',
            'error'   => 'Ошибки (' . $counts['error'] . ')',
        );

        $source_labels = array(
            'init'          => 'Инициализация',
            'admin'         => 'Админ',
            'plugin'        => 'Плагин',
            'comments'      => 'Комментарии',
            'login'         => 'Авторизация',
            'register'      => 'Регистрация',
            'lostpassword'  => 'Восст. пароля',
            'cf7'           => 'Contact Form 7',
            'elementor'     => 'Elementor',
            'integrations'  => 'Интеграции',
            'unknown'       => 'Неизвестно',
        );
        ?>
        <div class="cy-section cy-logs-section">
            <div class="cy-logs-header">
                <h2 style="margin:0;"><?php esc_html_e( 'Журнал событий', 'RuCoder' ); ?></h2>
                <div class="cy-logs-actions">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                        <?php wp_nonce_field( 'cy_clear_logs_nonce' ); ?>
                        <input type="hidden" name="action" value="cy_clear_logs">
                        <button type="submit" class="button cy-btn-clear" onclick="return confirm('Очистить все логи?');">
                            &#128465; <?php esc_html_e( 'Очистить логи', 'RuCoder' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="cy-log-filters">
                <?php foreach ( $type_labels as $type_key => $type_label ) :
                    $url = add_query_arg( array( 'page' => 'captcha-yandex', 'tab' => 'logs', 'log_type' => $type_key ), admin_url( 'admin.php' ) );
                    $active = $filter === $type_key;
                    $cls = 'cy-filter-btn';
                    if ( $active ) $cls .= ' cy-filter-btn--active';
                    if ( $type_key === 'error' ) $cls .= ' cy-filter-btn--error';
                    if ( $type_key === 'warning' ) $cls .= ' cy-filter-btn--warn';
                    if ( $type_key === 'success' ) $cls .= ' cy-filter-btn--success';
                    if ( $type_key === 'info' ) $cls .= ' cy-filter-btn--info';
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $type_label ); ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ( empty( $logs ) ) : ?>
                <div class="cy-logs-empty">
                    <span class="dashicons dashicons-list-view"></span>
                    <p><?php esc_html_e( 'Логов пока нет. События появятся здесь после первого использования капчи.', 'RuCoder' ); ?></p>
                </div>
            <?php else : ?>
                <div class="cy-log-table-wrap">
                    <table class="cy-log-table">
                        <thead>
                            <tr>
                                <th class="cy-log-col-time"><?php esc_html_e( 'Время', 'RuCoder' ); ?></th>
                                <th class="cy-log-col-type"><?php esc_html_e( 'Тип', 'RuCoder' ); ?></th>
                                <th class="cy-log-col-source"><?php esc_html_e( 'Источник', 'RuCoder' ); ?></th>
                                <th class="cy-log-col-message"><?php esc_html_e( 'Сообщение', 'RuCoder' ); ?></th>
                                <th class="cy-log-col-ip"><?php esc_html_e( 'IP', 'RuCoder' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $entry ) :
                                $type   = $entry['type']    ?? 'info';
                                $source = $entry['source']  ?? 'unknown';
                                $msg    = $entry['message'] ?? '';
                                $time   = $entry['time']    ?? '';
                                $ip     = $entry['ip']      ?? '';
                                $ctx    = $entry['context'] ?? array();
                                $source_label = $source_labels[ $source ] ?? $source;
                            ?>
                                <tr class="cy-log-row cy-log-row--<?php echo esc_attr( $type ); ?>">
                                    <td class="cy-log-col-time">
                                        <span class="cy-log-time"><?php echo esc_html( $time ); ?></span>
                                    </td>
                                    <td class="cy-log-col-type">
                                        <span class="cy-log-type cy-log-type--<?php echo esc_attr( $type ); ?>">
                                            <?php
                                            $type_icon = array(
                                                'success' => '✓',
                                                'info'    => 'ℹ',
                                                'warning' => '⚠',
                                                'error'   => '✕',
                                            );
                                            echo esc_html( ( $type_icon[ $type ] ?? '•' ) . ' ' . ucfirst( $type ) );
                                            ?>
                                        </span>
                                    </td>
                                    <td class="cy-log-col-source">
                                        <span class="cy-log-source"><?php echo esc_html( $source_label ); ?></span>
                                    </td>
                                    <td class="cy-log-col-message">
                                        <?php echo esc_html( $msg ); ?>
                                        <?php if ( ! empty( $ctx ) ) : ?>
                                            <details class="cy-log-context">
                                                <summary>Детали</summary>
                                                <pre><?php echo esc_html( json_encode( $ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td class="cy-log-col-ip">
                                        <span class="cy-log-ip"><?php echo esc_html( $ip ?: '—' ); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="cy-logs-note">
                    <?php printf( esc_html__( 'Показано %d записей. Хранится последних %d.', 'RuCoder' ), count( $logs ), Captcha_Yandex_Logger::MAX_LOGS ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_tab_docs() {
        ?>
        <div class="cy-section cy-docs">
            <h2><?php esc_html_e( 'Документация — Яндекс Smart Captcha', 'RuCoder' ); ?></h2>

            <div class="cy-docs-block">
                <h3>&#128073; Как получить API-ключи</h3>
                <ol>
                    <li>
                        Войдите в <a href="https://console.yandex.cloud/" target="_blank" rel="noopener noreferrer">Яндекс Cloud Console</a>.
                        Если у вас нет аккаунта — создайте его бесплатно.
                    </li>
                    <li>
                        В левом меню выберите <strong>Smart Captcha</strong> (или перейдите по
                        <a href="https://console.yandex.cloud/smartcaptcha" target="_blank" rel="noopener noreferrer">прямой ссылке</a>).
                    </li>
                    <li>Нажмите <strong>«Создать капчу»</strong> и укажите:<br>
                        <ul>
                            <li>Имя (любое, например <code>my-site-captcha</code>)</li>
                            <li>Список разрешённых доменов вашего сайта</li>
                            <li>Выберите тип: Умная (рекомендуется) или стандартная</li>
                        </ul>
                    </li>
                    <li>После создания откройте капчу — скопируйте <strong>Клиентский ключ</strong> (Client key) и <strong>Серверный ключ</strong> (Server key).</li>
                    <li>Вставьте ключи во вкладке <strong>«Интеграция»</strong> и нажмите «Сохранить ключи».</li>
                    <li>Выберите формы во вкладке <strong>«Настройки»</strong>, на которых будет появляться капча.</li>
                </ol>
            </div>

            <div class="cy-docs-block">
                <h3>&#128196; Официальная документация</h3>
                <p>Полная документация Яндекс Smart Captcha доступна по ссылке:</p>
                <a class="cy-docs-link" href="https://yandex.cloud/ru/docs/smartcaptcha/quickstart" target="_blank" rel="noopener noreferrer">
                    yandex.cloud/ru/docs/smartcaptcha/quickstart &rarr;
                </a>
            </div>

            <div class="cy-docs-block">
                <h3>&#9989; Поддерживаемые формы</h3>
                <ul class="cy-docs-list">
                    <li><strong>Форма комментариев WordPress</strong> — стандартная форма на страницах записей.</li>
                    <li><strong>Авторизация (wp-login.php)</strong> — форма входа в сайт.</li>
                    <li><strong>Регистрация (wp-login.php?action=register)</strong> — форма создания нового аккаунта.</li>
                    <li><strong>Восстановление пароля (wp-login.php?action=lostpassword)</strong> — форма сброса пароля.</li>
                    <li><strong>Contact Form 7</strong> — интеграция через тег <code>[yandex-captcha]</code> или автоматически при включённой настройке.</li>
                    <li><strong>Elementor Forms</strong> — автоматически добавляет капчу перед кнопкой «Отправить» через JS-инъекцию. Требуется Elementor Pro.</li>
                </ul>
            </div>

            <div class="cy-docs-block">
                <h3>&#128736; Шорткод для произвольных форм</h3>
                <p>Вставьте капчу в любую форму с помощью шорткода:</p>
                <code class="cy-code">[yandex_captcha]</code>
                <p>Или через PHP в шаблоне:</p>
                <code class="cy-code">&lt;?php echo do_shortcode('[yandex_captcha]'); ?&gt;</code>
            </div>

            <div class="cy-docs-block">
                <h3>&#128203; Вкладка «Логи»</h3>
                <p>В вкладке «Логи» отображается полный журнал работы плагина:</p>
                <ul>
                    <li><strong>Инициализация</strong> — какие интеграции были подключены при загрузке страницы.</li>
                    <li><strong>Проверка токена</strong> — каждый запрос к серверу Яндекс: результат, IP, статус.</li>
                    <li><strong>Ошибки</strong> — ошибки соединения, неверные ключи, отсутствие токена.</li>
                    <li><strong>Предупреждения</strong> — неудачные попытки пройти капчу.</li>
                </ul>
                <p>Хранится последних <?php echo (int) Captcha_Yandex_Logger::MAX_LOGS; ?> записей. Логи можно очистить кнопкой в вкладке.</p>
            </div>

            <div class="cy-docs-block">
                <h3>&#10067; Часто задаваемые вопросы</h3>
                <dl class="cy-faq">
                    <dt>Капча не появляется на сайте</dt>
                    <dd>Убедитесь, что введены оба ключа (клиентский и серверный) и нужные формы включены в «Настройках». Проверьте вкладку «Логи» — там будет причина.</dd>

                    <dt>Капча не проходит проверку (ошибка валидации)</dt>
                    <dd>Проверьте правильность серверного ключа. Убедитесь, что домен вашего сайта добавлен в разрешённые домены в Яндекс Cloud Console.</dd>

                    <dt>Elementor: капча не отображается</dt>
                    <dd>Убедитесь, что установлен и активирован <strong>Elementor Pro</strong> (не бесплатная версия). Включите опцию «Elementor Forms» в настройках плагина.</dd>

                    <dt>CF7: капча не добавляется автоматически</dt>
                    <dd>Добавьте тег <code>[yandex-captcha]</code> вручную в нужную форму в редакторе Contact Form 7.</dd>
                </dl>
            </div>

            <div class="cy-docs-block cy-docs-block--links">
                <h3>&#128279; Полезные ссылки</h3>
                <ul>
                    <li><a href="https://console.yandex.cloud/smartcaptcha" target="_blank" rel="noopener noreferrer">Яндекс Cloud Console — Smart Captcha</a></li>
                    <li><a href="https://yandex.cloud/ru/docs/smartcaptcha/" target="_blank" rel="noopener noreferrer">Документация Яндекс Smart Captcha</a></li>
                    <li><a href="https://yandex.cloud/ru/docs/smartcaptcha/concepts/widget" target="_blank" rel="noopener noreferrer">Как встроить виджет</a></li>
                    <li><a href="https://yandex.cloud/ru/docs/smartcaptcha/concepts/validation" target="_blank" rel="noopener noreferrer">Серверная валидация токена</a></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
