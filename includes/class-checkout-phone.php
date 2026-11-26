<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

/** One checkout journey, using the supplied Digits renderer and authentication handlers. */
final class CheckoutPhone {
    private static $processing = false;
    private static $lock = null;
    private static $registration_role = null;

    public static function init() {
        add_filter( 'dig_hide_forms', array( __CLASS__, 'custom_form' ), 100 );
        add_action( 'digits_render_custom_form', array( __CLASS__, 'render' ) );
        add_action( 'wp_ajax_nopriv_digits_forms_ajax', array( __CLASS__, 'process' ), 0 );
        add_action( 'digits_login_user_not_found', array( __CLASS__, 'new_phone' ) );
        add_filter( 'digits_register_user_role', array( __CLASS__, 'registration_role' ), PHP_INT_MAX );
        add_filter( 'pre_option_default_role', array( __CLASS__, 'initial_role' ), PHP_INT_MAX );
    }

    public static function custom_form( $custom ) {
        return Checkout::phone_only_context() ? 1 : $custom;
    }

    public static function render( $details ) {
        if ( ! Checkout::phone_only_context() ) {
            return;
        }
        $details['page_type'] = 'login';
        $details['users_can_register'] = 0;
        $details['login_title'] = 'ادامه خرید با شماره همراه';
        \digits_render_login_form( $details );
    }

    /** Serialize lookup, native OTP verification and creation for this normalized phone. */
    public static function process() {
        if ( ! Checkout::phone_only_context() || 'login' !== ( $_REQUEST['type'] ?? '' ) ||
            'phone' !== ( $_REQUEST['action_type'] ?? '' ) ) {
            return;
        }
        if ( is_user_logged_in() ) {
            \digits_forms_ajax();
            return;
        }
        if ( ! isset( $_REQUEST['digits_form'] ) || ! is_string( $_REQUEST['digits_form'] ) ||
            ! wp_verify_nonce( $_REQUEST['digits_form'], 'digits_login_form' ) ) {
            wp_send_json_error( array( 'message' => 'صفحه را تازه کنید و دوباره تلاش کنید.' ) );
            return;
        }
        $phone = self::phone();
        if ( ! $phone ) {
            wp_send_json_error( array( 'message' => 'شماره همراه معتبر وارد کنید.' ) );
            return;
        }
        if ( ! self::acquire( $phone[0] . $phone[1] ) ) {
            wp_send_json_error( array( 'message' => 'درخواست قبلی در حال بررسی است. کمی صبر کنید و دوباره تلاش کنید.' ) );
            return;
        }
        self::$processing = true;
        // wp_send_json() exits in WordPress; finally also supports intercepted
        // termination in integration tests. Neither path leaves a normal lock.
        register_shutdown_function( array( __CLASS__, 'release' ) );
        try {
            \digits_forms_ajax();
        } finally {
            self::$processing = false;
            self::$registration_role = null;
            self::release();
        }
    }

    private static function phone() {
        $country = $_REQUEST['login_digt_countrycode'] ?? ( $_REQUEST['digt_countrycode'] ?? ( $_REQUEST['dig_countrycodec'] ?? '' ) );
        $mobile = $_REQUEST['digits_phone'] ?? '';
        if ( ! is_string( $country ) || ! is_string( $mobile ) ) {
            return false;
        }
        $country = \digits_arabic_persian_filter( $country );
        $mobile = \sanitize_mobile_field_dig( $mobile );
        if ( ! preg_match( '/^\+[1-9][0-9]{0,3}$/D', $country ) || ! preg_match( '/^[0-9]{4,14}$/D', $mobile ) ||
            strlen( $country . $mobile ) > 16 ) {
            return false;
        }
        return array( $country, $mobile );
    }

    /** Called only after native login has validated captcha, country and phone policy. */
    public static function new_phone( $login_type ) {
        if ( ! self::$processing || 'phone' !== $login_type ) {
            return;
        }
        if ( ! empty( $_REQUEST['check_status'] ) ) {
            throw new \Exception( 'برای تأیید شماره از همان فرم خرید ادامه دهید.' );
        }
        if ( ! Checkout::ordinary_registration() || ! get_option( 'dig_enable_registration', 1 ) ) {
            throw new \Exception( 'امکان ایجاد حساب مشتری فعال نیست. با پشتیبانی فروشگاه تماس بگیرید.' );
        }
        $role = apply_filters( 'digits_register_user_role', get_option( 'defaultuserrole', 'customer' ) );
        if ( ! Checkout::ordinary_role( $role ) ) {
            throw new \Exception( 'تنظیم نقش حساب مشتری نیاز به بررسی مدیر سایت دارد.' );
        }
        self::$registration_role = $role;
        $phone = self::phone();
        if ( ! $phone ) {
            throw new \Exception( 'شماره همراه معتبر وارد کنید.' );
        }
        // Never forward client-selected roles, email tokens, optional identity,
        // WhatsApp or a flag purporting that verification has already happened.
        $data = array(
            'action_type' => 'phone', 'digt_countrycode' => $phone[0], 'phone' => $phone[1],
            'digits_process_register' => 1, 'signup_otp_mode' => 1,
        );
        foreach ( array( 'sms_otp', 'digits_otp_field', 'otp_resend', 'container' ) as $key ) {
            if ( isset( $_REQUEST[ $key ] ) && is_scalar( $_REQUEST[ $key ] ) ) {
                $data[ $key ] = $_REQUEST[ $key ];
            }
        }
        $sub_action = $_REQUEST['sub_action'] ?? '';
        if ( '' !== $sub_action && 'sms_otp' !== $sub_action ) {
            throw new \Exception( 'برای ادامه خرید از کد پیامکی استفاده کنید.' );
        }
        $data['sub_action'] = $sub_action;
        // Native registration validates all configured custom mandatory fields,
        // generates the password, verifies and consumes OTP BEFORE wp_create_user.
        $registration = new \DigitsFormHandler\UserRegistration( $data );
        $registration->process();
    }

    public static function registration_role( $role ) {
        return null !== self::$registration_role ? self::$registration_role : $role;
    }

    public static function initial_role( $role ) {
        // wp_create_user initially uses WordPress default_role, before Digits
        // applies its final role. Keep that brief initial state ordinary too.
        return null !== self::$registration_role ? self::$registration_role : $role;
    }

    private static function acquire( $phone ) {
        global $wpdb;
        $key = '_ava_digits_phone_' . hash_hmac( 'sha256', $phone, wp_salt( 'nonce' ) );
        $now = time();
        $old = get_option( $key, false );
        if ( is_string( $old ) && ctype_digit( $old ) && (int) $old < $now - 600 ) {
            // Compare-and-delete: never delete a different request's newer lock.
            $wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => $old ) );
            wp_cache_delete( $key, 'options' );
        }
        $value = (string) $now;
        if ( ! add_option( $key, $value, '', false ) ) {
            return false;
        }
        self::$lock = array( $key, $value );
        return true;
    }

    public static function release() {
        if ( null === self::$lock ) {
            return;
        }
        global $wpdb;
        list( $key, $value ) = self::$lock;
        self::$lock = null;
        $wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => $value ) );
        wp_cache_delete( $key, 'options' );
    }
}
