<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Logger for Captcha Yandex.
 * Stores up to 200 entries in wp_options.
 */
class Captcha_Yandex_Logger {

    const OPTION_KEY = 'captcha_yandex_logs';
    const MAX_LOGS   = 200;

    const TYPE_INFO    = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR   = 'error';

    /**
     * Add a log entry.
     *
     * @param string $type    One of TYPE_* constants.
     * @param string $source  Which integration triggered the log (e.g. 'elementor', 'cf7', 'comments').
     * @param string $message Human-readable message.
     * @param array  $context Optional extra data.
     */
    public static function log( $type, $source, $message, $context = array() ) {
        $logs = get_option( self::OPTION_KEY, array() );

        if ( ! is_array( $logs ) ) {
            $logs = array();
        }

        $entry = array(
            'time'    => current_time( 'mysql' ),
            'type'    => $type,
            'source'  => $source,
            'message' => $message,
            'ip'      => Captcha_Yandex_Verify::get_user_ip(),
            'url'     => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '',
        );

        if ( ! empty( $context ) ) {
            $entry['context'] = $context;
        }

        array_unshift( $logs, $entry );

        if ( count( $logs ) > self::MAX_LOGS ) {
            $logs = array_slice( $logs, 0, self::MAX_LOGS );
        }

        update_option( self::OPTION_KEY, $logs, false );
    }

    public static function info( $source, $message, $context = array() ) {
        self::log( self::TYPE_INFO, $source, $message, $context );
    }

    public static function success( $source, $message, $context = array() ) {
        self::log( self::TYPE_SUCCESS, $source, $message, $context );
    }

    public static function warning( $source, $message, $context = array() ) {
        self::log( self::TYPE_WARNING, $source, $message, $context );
    }

    public static function error( $source, $message, $context = array() ) {
        self::log( self::TYPE_ERROR, $source, $message, $context );
    }

    /**
     * Get all logs, optionally filtered by type.
     */
    public static function get_logs( $type_filter = '' ) {
        $logs = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $logs ) ) {
            return array();
        }
        if ( $type_filter ) {
            $logs = array_filter( $logs, function( $entry ) use ( $type_filter ) {
                return $entry['type'] === $type_filter;
            } );
        }
        return array_values( $logs );
    }

    /**
     * Clear all logs.
     */
    public static function clear() {
        update_option( self::OPTION_KEY, array(), false );
    }

    /**
     * Count logs by type.
     */
    public static function count_by_type() {
        $logs  = self::get_logs();
        $count = array(
            self::TYPE_INFO    => 0,
            self::TYPE_SUCCESS => 0,
            self::TYPE_WARNING => 0,
            self::TYPE_ERROR   => 0,
        );
        foreach ( $logs as $entry ) {
            if ( isset( $count[ $entry['type'] ] ) ) {
                $count[ $entry['type'] ]++;
            }
        }
        return $count;
    }
}
