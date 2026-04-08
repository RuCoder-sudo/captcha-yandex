<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Captcha_Yandex_Verify {

    const VERIFY_URL = 'https://smartcaptcha.yandexcloud.net/validate';

    /**
     * Verify a Smart Captcha token.
     *
     * @param string $token  Token from the frontend widget.
     * @param string $source Name of the integration (for logging).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function verify( $token, $source = 'unknown' ) {
        $options    = get_option( 'captcha_yandex_settings', array() );
        $server_key = $options['server_key'] ?? '';

        if ( empty( $server_key ) ) {
            $err = new WP_Error( 'cy_no_key', __( 'Серверный ключ Яндекс капчи не настроен.', 'RuCoder' ) );
            Captcha_Yandex_Logger::error( $source, 'Серверный ключ не настроен. Проверка невозможна.' );
            return $err;
        }

        if ( empty( $token ) ) {
            $err = new WP_Error( 'cy_no_token', __( 'Токен капчи отсутствует. Пройдите проверку.', 'RuCoder' ) );
            Captcha_Yandex_Logger::warning( $source, 'Токен капчи не передан при отправке формы.' );
            return $err;
        }

        $ip = self::get_user_ip();

        Captcha_Yandex_Logger::info( $source, 'Запрос проверки токена.', array( 'ip' => $ip, 'token_len' => strlen( $token ) ) );

        $response = wp_remote_post(
            self::VERIFY_URL,
            array(
                'body'    => array(
                    'secret' => $server_key,
                    'token'  => sanitize_text_field( $token ),
                    'ip'     => $ip,
                ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            Captcha_Yandex_Logger::error( $source, 'Ошибка соединения с сервером Яндекс: ' . $msg );
            return new WP_Error( 'cy_request_failed', __( 'Ошибка соединения с сервером Яндекс капчи.', 'RuCoder' ) );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            Captcha_Yandex_Logger::error( $source, 'Неверный ответ от сервера.', array( 'http_code' => $http_code, 'body' => substr( $body, 0, 200 ) ) );
            return new WP_Error( 'cy_invalid_response', __( 'Неверный ответ от сервера капчи.', 'RuCoder' ) );
        }

        if ( ! empty( $data['status'] ) && $data['status'] === 'ok' ) {
            Captcha_Yandex_Logger::success( $source, 'Проверка капчи пройдена успешно.', array( 'ip' => $ip ) );
            return true;
        }

        $api_msg = $data['message'] ?? '';
        Captcha_Yandex_Logger::warning( $source, 'Проверка капчи не пройдена.', array(
            'status'  => $data['status'] ?? 'unknown',
            'message' => $api_msg,
            'ip'      => $ip,
        ) );

        $message = __( 'Проверка капчи не пройдена. Попробуйте ещё раз.', 'RuCoder' );
        if ( $api_msg ) {
            $message .= ' (' . sanitize_text_field( $api_msg ) . ')';
        }

        return new WP_Error( 'cy_failed', $message );
    }

    /**
     * Get current user IP.
     */
    public static function get_user_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        );
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = trim( explode( ',', sanitize_text_field( $_SERVER[ $header ] ) )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
