<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

final class Gateway {
    const ID = 714032;
    const SLUG = 'ava_digits';
    private static $results = array();
    private static $conflicted = false;

    public static function init() {
        add_filter( 'digits_sms_gateways', array( __CLASS__, 'register' ), 100 );
        add_filter( 'unitedover_send_sms', array( __CLASS__, 'send' ), 10, 8 );
    }

    public static function register( $gateways ) {
        if ( ! is_array( $gateways ) ) {
            return $gateways;
        }
        self::$conflicted = false;
        // Preserve another integration if it already occupies this numeric ID.
        foreach ( $gateways as $slug => $gateway ) {
            if ( self::SLUG !== $slug && is_array( $gateway ) && isset( $gateway['value'] ) && (string) self::ID === (string) $gateway['value'] ) {
                self::$conflicted = true;
                return $gateways;
            }
        }
        if ( isset( $gateways[ self::SLUG ] ) ) {
            self::$conflicted = ! is_array( $gateways[ self::SLUG ] ) || ! isset( $gateways[ self::SLUG ]['value'] ) || (string) self::ID !== (string) $gateways[ self::SLUG ]['value'];
            return $gateways;
        }
        $gateways[ self::SLUG ] = array(
            'label' => 'آوا / Ava SMS OTP',
            'value' => self::ID,
            'inputs' => array(
                'اتصال آوا' => array(
                    'select' => true, 'name' => 'connection',
                    'options' => array( 'استفاده از تنظیمات ← آوا برای دیجیتس' => 'ava' ),
                ),
            ),
        );
        return $gateways;
    }

    /** Digits passes the already-rendered SMS template, not the OTP placeholder. */
    public static function send( $handled, $option_slug, $gateway_id, $countrycode, $mobile, $message, $test_call = false, $context = null ) {
        if ( self::$conflicted || ! is_scalar( $gateway_id ) || (string) self::ID !== (string) $gateway_id ) {
            return $handled;
        }
        if ( $test_call && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        $phone = Phone::normalize( $mobile, $countrycode );
        $code = Phone::code( $message );
        if ( is_wp_error( $phone ) || is_wp_error( $code ) ) {
            $result = is_wp_error( $phone ) ? $phone : $code;
        } else {
            $settings = Settings::get();
            // Cache only within this PHP request. Digits resend is a new explicit
            // send. Do not store plaintext codes or retry uncertain requests.
            $fingerprint = hash_hmac( 'sha256', wp_json_encode( array( $phone, $code, $settings ) ), wp_salt( 'auth' ) );
            if ( ! array_key_exists( $fingerprint, self::$results ) ) {
                self::$results[ $fingerprint ] = Api::send( $phone, $code, 'ava_digits_' . wp_generate_uuid4(), $settings );
            }
            $result = self::$results[ $fingerprint ];
        }
        if ( $test_call ) {
            if ( is_wp_error( $result ) ) {
                return wp_json_encode( array( 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ), JSON_UNESCAPED_UNICODE );
            }
            return wp_json_encode( array( 'id' => $result['id'], 'status' => $result['status'], 'note' => 'درخواست توسط آوا پذیرفته شد؛ این پاسخ اثبات تحویل نیست.' ), JSON_UNESCAPED_UNICODE );
        }
        return ! is_wp_error( $result );
    }
}
