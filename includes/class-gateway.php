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
        add_filter( 'dig_messagetemplate', array( __CLASS__, 'redact_pattern' ), PHP_INT_MAX, 5 );
        add_action( 'plugins_loaded', array( __CLASS__, 'isolate_ippanel' ), PHP_INT_MAX );
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
            'label' => 'آوا / Ava OTP (پیامکی و صوتی)',
            'value' => self::ID,
            'group' => 'آوا',
            'inputs' => array(),
        );
        return $gateways;
    }

    /** The supplied Digits 8.6.2 default hook includes raw OTP as argument eight. */
    public static function send( $handled, $option_slug, $gateway_id, $countrycode, $mobile, $message, $test_call = false, $otp = null ) {
        if ( self::$conflicted || ! is_scalar( $gateway_id ) || (string) self::ID !== (string) $gateway_id ) {
            return $handled;
        }
        if ( $test_call && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        $phone = Phone::normalize( $mobile, $countrycode );
        $settings = Settings::get();
        $text_mode = 'text' === $settings['mode'];
        // Old hook contracts without argument eight only support code-only
        // rendered messages in pattern mode. Never guess a code from prose.
        $content = $text_mode ? Api::text( $message ) : Phone::code( null === $otp ? $message : ( is_int( $otp ) ? (string) $otp : $otp ) );
        if ( is_wp_error( $phone ) || is_wp_error( $content ) ) {
            $result = is_wp_error( $phone ) ? $phone : $content;
        } else {
            // Cache only within this PHP request. Digits resend is a new explicit
            // send. Do not store plaintext codes or retry uncertain requests.
            $fingerprint = hash_hmac( 'sha256', wp_json_encode( array( $phone, $content, $settings ) ), wp_salt( 'auth' ) );
            if ( ! array_key_exists( $fingerprint, self::$results ) ) {
                $method = $text_mode ? 'send_text' : ( 'voice' === $settings['mode'] ? 'send_voice' : 'send' );
                self::$results[ $fingerprint ] = Api::$method( $phone, $content, 'ava_digits_' . wp_generate_uuid4(), $settings );
            }
            $result = self::$results[ $fingerprint ];
        }
        if ( $test_call ) {
            if ( is_wp_error( $result ) ) {
                return wp_json_encode( array( 'error' => $result->get_error_code(), 'message' => $result->get_error_message() ), JSON_UNESCAPED_UNICODE );
            }
            return wp_json_encode( array( 'id' => $result['id'], 'status' => $result['status'], 'note' => 'درخواست توسط آوا پذیرفته شد؛ این پاسخ تأیید دریافت یا شنیدن کد نیست.' ), JSON_UNESCAPED_UNICODE );
        }
        return ! is_wp_error( $result );
    }

    public static function redact_pattern( $message, $gateway_id, $countrycode = '', $mobile = '', $otp = null ) {
        // Version 8.6.2's verified send path preserves raw OTP separately and logs
        // this rendered string. Do not redact older, unverified hook contracts.
        if ( ! self::$conflicted && is_scalar( $gateway_id ) && (string) self::ID === (string) $gateway_id &&
            function_exists( 'digits_version' ) && version_compare( \digits_version(), '8.6.2', '>=' ) &&
            in_array( Settings::get()['mode'], array( 'pattern', 'voice' ), true ) && ! is_wp_error( Phone::code( is_int( $otp ) ? (string) $otp : $otp ) ) ) {
            return '[Ava OTP]';
        }
        return $message;
    }

    /** Scope the supplied customization's otherwise global IPPanel callback. */
    public static function isolate_ippanel() {
        if ( function_exists( 'digits_ippanel_send' ) && false !== has_filter( 'unitedover_send_sms', 'digits_ippanel_send' ) ) {
            $priority = has_filter( 'unitedover_send_sms', 'digits_ippanel_send' );
            if ( remove_filter( 'unitedover_send_sms', 'digits_ippanel_send', $priority ) ) {
                add_filter( 'unitedover_send_sms', array( __CLASS__, 'ippanel_only' ), $priority, 8 );
            }
        }
    }

    public static function ippanel_only( $handled, $option_slug, $gateway_id, $countrycode, $mobile, $message, $test_call = false, $otp = null ) {
        if ( ! is_scalar( $gateway_id ) || '1000' !== (string) $gateway_id || ! function_exists( 'digits_ippanel_send' ) ) {
            return $handled;
        }
        return \digits_ippanel_send( $handled, $option_slug, $gateway_id, $countrycode, $mobile, $message, $test_call, $otp );
    }
}
