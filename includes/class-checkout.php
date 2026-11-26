<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

/** Optional LearnPress checkout bridge. Digits remains responsible for authentication. */
final class Checkout {
    const OPTION = 'ava_digits_checkout';
    private static $guest_cart = null;

    public static function defaults() {
        return array(
            'enabled' => 'no',
            'phone_only' => 'no',
            'hide_native_forms' => 'yes',
            'button_label' => 'ورود و ادامه خرید با شماره همراه',
            'message' => 'برای ادامه خرید، با شماره همراه خود وارد شوید.',
            'accent_color' => '#0171f1',
        );
    }

    public static function get() {
        $raw = get_option( self::OPTION, array() );
        return array_merge( self::defaults(), is_array( $raw ) ? array_intersect_key( $raw, self::defaults() ) : array() );
    }

    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $out = self::get();
        foreach ( array( 'enabled', 'hide_native_forms', 'phone_only' ) as $key ) {
            $out[ $key ] = isset( $input[ $key ] ) && 'yes' === $input[ $key ] ? 'yes' : 'no';
        }
        foreach ( array( 'button_label' => 80, 'message' => 400 ) as $key => $limit ) {
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) {
                continue;
            }
            $value = sanitize_text_field( $input[ $key ] );
            $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : preg_match_all( '/./us', $value );
            if ( false !== $length && $length <= $limit && ( 'message' === $key || '' !== $value ) ) {
                $out[ $key ] = $value;
            } else {
                add_settings_error( self::OPTION, 'ava_digits_checkout_' . $key, 'متن دکمه باید ۱ تا ۸۰ و توضیح حداکثر ۴۰۰ نویسه باشد؛ مقدار قبلی حفظ شد.' );
            }
        }
        if ( isset( $input['accent_color'] ) ) {
            if ( is_string( $input['accent_color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/D', $input['accent_color'] ) ) {
                $out['accent_color'] = strtolower( $input['accent_color'] );
            } else {
                add_settings_error( self::OPTION, 'ava_digits_checkout_color', 'رنگ باید کد شش‌رقمی مانند #0171f1 باشد؛ رنگ قبلی حفظ شد.' );
            }
        }
        return $out;
    }

    public static function init() {
        add_filter( 'learn_press_get_template', array( __CLASS__, 'template' ), 20, 5 );
        add_action( 'learn-press/after-checkout-form', array( __CLASS__, 'render' ), 25 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter( 'digits_login_redirect', array( __CLASS__, 'redirect' ), 100 );
        add_filter( 'digits_register_redirect', array( __CLASS__, 'redirect' ), 100 );
        add_action( 'set_logged_in_cookie', array( __CLASS__, 'authenticated_cookie' ), 20, 4 );
        add_action( 'wp_login', array( __CLASS__, 'capture_cart' ), 5, 2 );
        add_action( 'wp_login', array( __CLASS__, 'restore_cart' ), 20, 2 );
        // Filter reads only. Saved Digits options and administration login stay intact.
        foreach ( array( 'option_', 'default_option_' ) as $prefix ) {
            add_filter( $prefix . 'dig_login_fields', array( __CLASS__, 'login_fields' ), 100 );
            add_filter( $prefix . 'dig_reg_fields', array( __CLASS__, 'registration_fields' ), 100 );
            add_filter( $prefix . 'dig_reg_skip_otp_verification', array( __CLASS__, 'require_otp' ), 100 );
        }
        add_filter( 'digits_login_fields', array( __CLASS__, 'login_fields' ), 100 );
        add_filter( 'digits_registration_default_fields', array( __CLASS__, 'registration_fields' ), 100 );
        add_filter( 'digits_user_login_flow', array( __CLASS__, 'auth_flow' ), 100, 2 );
        add_filter( 'digits_user_login_methods', array( __CLASS__, 'login_methods' ), 100, 3 );
        add_action( 'register_form', array( __CLASS__, 'registration_form' ), 0 );
    }

    public static function available() {
        return class_exists( 'LearnPress' ) && function_exists( 'learn_press_get_checkout_url' ) &&
            function_exists( 'dig_login_contents' ) && function_exists( 'digits_version' ) &&
            version_compare( \digits_version(), '8.6.2', '>=' );
    }

    private static function enabled() {
        return 'yes' === self::get()['enabled'] && self::available();
    }

    private static function checkout_page() {
        return self::enabled() && ! is_admin() && function_exists( 'learn_press_is_checkout' ) && \learn_press_is_checkout();
    }

    private static function account_mode() {
        $lp = \LearnPress::instance();
        if ( is_callable( array( $lp, 'checkout' ) ) ) {
            $checkout = $lp->checkout();
            if ( is_object( $checkout ) && is_callable( array( $checkout, 'is_enable_guest_checkout' ) ) &&
                $checkout->is_enable_guest_checkout() && ! isset( $_GET['is_form_login'] ) && ! isset( $_GET['is_form_register'] ) ) {
                return false;
            }
        }
        return true;
    }

    /** A configured checkout must remain on this WordPress origin. Never trust Host or redirect_to. */
    public static function checkout_url() {
        if ( ! function_exists( 'learn_press_get_checkout_url' ) ) {
            return '';
        }
        $url = \learn_press_get_checkout_url();
        return self::same_origin( $url, home_url( '/' ) ) ? $url : '';
    }

    private static function same_origin( $first, $second ) {
        if ( ! is_string( $first ) || ! is_string( $second ) ) {
            return false;
        }
        $a = wp_parse_url( $first );
        $b = wp_parse_url( $second );
        foreach ( array( $a, $b ) as $parts ) {
            if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ||
                ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ||
                isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
                return false;
            }
        }
        $port_a = $a['port'] ?? ( 'https' === strtolower( $a['scheme'] ) ? 443 : 80 );
        $port_b = $b['port'] ?? ( 'https' === strtolower( $b['scheme'] ) ? 443 : 80 );
        return strtolower( $a['scheme'] ) === strtolower( $b['scheme'] ) &&
            strtolower( $a['host'] ) === strtolower( $b['host'] ) && $port_a === $port_b;
    }

    private static function from_checkout() {
        return self::matches_checkout( wp_get_referer() );
    }

    private static function matches_checkout( $source ) {
        $target = self::checkout_url();
        // WordPress's native _wp_http_referer field is root-relative and takes
        // precedence over the absolute Referer header in Digits AJAX forms.
        if ( is_string( $source ) && isset( $source[0] ) && '/' === $source[0] &&
            0 !== strpos( $source, '//' ) && ! preg_match( '/[\x00-\x20\x7f\\\\]/', $source ) && '' !== $target ) {
            $origin = wp_parse_url( $target );
            $source = $origin['scheme'] . '://' . $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' ) . $source;
        }
        if ( '' === $target || ! self::same_origin( $source, $target ) ) {
            return false;
        }
        $a = wp_parse_url( $source );
        $b = wp_parse_url( $target );
        if ( rtrim( $a['path'] ?? '/', '/' ) !== rtrim( $b['path'] ?? '/', '/' ) ) {
            return false;
        }
        // With plain permalinks, /?page_id=7 must not also match the homepage.
        parse_str( $a['query'] ?? '', $source_query );
        parse_str( $b['query'] ?? '', $target_query );
        foreach ( $target_query as $key => $value ) {
            if ( ! isset( $source_query[ $key ] ) || $source_query[ $key ] !== $value ) {
                return false;
            }
        }
        return true;
    }

    /** Include Digits' early wp_loaded page renderer, before WordPress resolves is_page(). */
    public static function phone_only_context() {
        if ( ! self::enabled() || 'yes' !== self::get()['phone_only'] || '' === self::checkout_url() ||
            ! function_exists( 'digits_use_new_form_style' ) || ! \digits_use_new_form_style() ) {
            return false;
        }
        $ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
        if ( $ajax ) {
            // Legacy handlers do not consult modern user/factor hooks. Keep
            // their original policy, including OTP-disabled privileged logins.
            return isset( $_REQUEST['action'], $_REQUEST['type'] ) && 'digits_forms_ajax' === $_REQUEST['action'] &&
                in_array( $_REQUEST['type'], array( 'login', 'register' ), true ) && self::from_checkout();
        }
        if ( is_admin() ) {
            return false;
        }
        return ( function_exists( 'learn_press_is_checkout' ) && \learn_press_is_checkout() ) ||
            ( isset( $_SERVER['REQUEST_URI'] ) && self::matches_checkout( $_SERVER['REQUEST_URI'] ) );
    }

    public static function login_fields( $fields ) {
        if ( ! self::phone_only_context() ) {
            return $fields;
        }
        $fields = is_array( $fields ) ? $fields : array();
        $ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
        // The modern handler captures this gate before identifying the user.
        // Preserve it server-side so a protected account can complete its own
        // native password factor. Customer factors are restricted separately,
        // only after Digits has selected their actual user ID.
        $password = $ajax ? ( $fields['dig_login_password'] ?? get_option( 'digpassaccep', 1 ) ) : 0;
        return array_merge( $fields, array(
            'dig_login_mobilenumber' => 1, 'dig_login_otp' => 1,
            'dig_login_username' => 0, 'dig_login_email' => 0, 'dig_login_password' => $password,
        ) );
    }

    public static function registration_fields( $fields ) {
        if ( ! self::phone_only_context() || ! self::ordinary_registration() ) {
            return $fields;
        }
        return array_merge( is_array( $fields ) ? $fields : array(), array(
            'dig_reg_mobilenumber' => 2, 'dig_reg_name' => 0,
            'dig_reg_uname' => 0, 'dig_reg_email' => 0, 'dig_reg_password' => 0,
        ) );
    }

    public static function require_otp( $skip ) {
        return self::phone_only_context() ? 0 : $skip;
    }

    /** Change the primary factor only; retain all additional-factor security and metadata. */
    public static function auth_flow( $flow, $user_id = 0 ) {
        if ( self::phone_only_context() && self::phone_customer( $user_id ) &&
            is_array( $flow ) && isset( $flow['1fa'] ) && is_array( $flow['1fa'] ) ) {
            $flow['1fa']['methods'] = array( 'sms_otp' );
        }
        return $flow;
    }

    public static function login_methods( $methods, $user_id, $step_no ) {
        return self::phone_only_context() && self::phone_customer( $user_id ) &&
            ( 1 === $step_no || '1' === $step_no ) ? array( 'sms_otp' ) : $methods;
    }

    private static function phone_customer( $user_id ) {
        if ( ! is_scalar( $user_id ) || ! ctype_digit( (string) $user_id ) || (int) $user_id < 1 ) {
            return false;
        }
        $user = get_userdata( (int) $user_id );
        // Identity is selected by Digits, never a posted role or the current
        // anonymous user. Unknown/custom or elevated accounts retain their
        // original factors even if an attacker forges a checkout Referer.
        if ( ! $user || ! isset( $user->roles ) || ! is_array( $user->roles ) || empty( $user->roles ) ||
            array_diff( $user->roles, array( 'subscriber', 'customer' ) ) ) {
            return false;
        }
        if ( ! self::ordinary_capabilities( $user->allcaps ?? null ) ) {
            return false;
        }
        if ( function_exists( 'is_super_admin' ) && is_super_admin( (int) $user_id ) ) {
            return false;
        }
        // user_can() also sees filtered grants. Derive the site's administrator
        // primitives instead of relying on a fixed list that misses new caps.
        $privileged = array_fill_keys( array( 'manage_options', 'edit_users', 'promote_users', 'activate_plugins', 'edit_posts', 'manage_woocommerce', 'edit_shop_orders',
            'manage_network', 'manage_sites', 'manage_network_users', 'manage_network_plugins', 'manage_network_themes', 'manage_network_options', 'upgrade_network', 'setup_network', 'create_sites', 'delete_sites' ), true );
        $administrator = function_exists( 'get_role' ) ? get_role( 'administrator' ) : null;
        if ( ! $administrator || ! isset( $administrator->capabilities ) || ! is_array( $administrator->capabilities ) ) {
            return false;
        }
        $privileged += $administrator->capabilities;
        $ordinary = array_fill_keys( array( 'read', 'level_0', 'subscriber', 'customer' ), true );
        foreach ( array_keys( $privileged ) as $capability ) {
            if ( isset( $ordinary[ $capability ] ) ) {
                continue;
            }
            if ( user_can( $user, $capability ) ) {
                return false;
            }
        }
        return true;
    }

    private static function ordinary_capabilities( $capabilities ) {
        if ( ! is_array( $capabilities ) ) {
            return false;
        }
        $ordinary = array_fill_keys( array( 'read', 'level_0', 'subscriber', 'customer' ), true );
        foreach ( $capabilities as $capability => $granted ) {
            if ( $granted && ! isset( $ordinary[ $capability ] ) ) {
                return false;
            }
        }
        return true;
    }

    private static function ordinary_registration() {
        $role = get_option( 'defaultuserrole', 'customer' );
        if ( ! is_string( $role ) || ! in_array( $role, array( 'subscriber', 'customer' ), true ) || ! function_exists( 'get_role' ) ) {
            return false;
        }
        $role = get_role( $role );
        return $role && isset( $role->capabilities ) && self::ordinary_capabilities( $role->capabilities );
    }

    public static function registration_form() {
        if ( self::phone_only_context() && self::ordinary_registration() && function_exists( 'learn_press_user_become_teacher_registration_form' ) ) {
            // This exact LP4.4 callback adds an optional instructor prompt. Keep
            // captcha, terms and all other registration hooks and custom fields.
            remove_action( 'register_form', 'learn_press_user_become_teacher_registration_form', 10 );
        }
    }

    private static function digits_request() {
        $action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
        return in_array( $action, array( 'digits_forms_ajax', 'digits_submit_form', 'digits_verifyotp_login', 'digits_login_user' ), true );
    }

    public static function redirect( $url ) {
        if ( self::enabled() && self::digits_request() && self::from_checkout() ) {
            return self::checkout_url();
        }
        return $url;
    }

    public static function template( $located, $name, $args = array(), $template_path = '', $default_path = '' ) {
        if ( self::checkout_page() && ! is_user_logged_in() && '' !== self::checkout_url() &&
            'yes' === self::get()['hide_native_forms'] &&
            in_array( $name, array( 'checkout/account-login.php', 'checkout/account-register.php' ), true ) ) {
            return __DIR__ . '/templates/checkout-account.php';
        }
        return $located;
    }

    public static function enqueue() {
        if ( ! self::checkout_page() || is_user_logged_in() || '' === self::checkout_url() || ! self::account_mode() ) {
            return;
        }
        wp_enqueue_style( 'ava-digits-checkout', plugins_url( 'assets/checkout.css', AVA_DIGITS_FILE ), array(), AVA_DIGITS_VERSION );
        wp_enqueue_style( 'digits-login-style' );
        wp_enqueue_script( 'digits-login-script' );
    }

    private static function label_color( $color ) {
        $linear = array();
        foreach ( array( 1, 3, 5 ) as $offset ) {
            $channel = hexdec( substr( $color, $offset, 2 ) ) / 255;
            $linear[] = $channel <= 0.04045 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
        }
        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        return $luminance > 0.21 ? '#1d2327' : '#fff';
    }

    /** Only a link lives inside LearnPress's form; Digits owns the modal in wp_footer. */
    public static function render() {
        if ( ! self::checkout_page() || is_user_logged_in() || '' === self::checkout_url() || ! self::account_mode() ) {
            return;
        }
        $settings = self::get();
        $url = add_query_arg( array( 'login' => 'true', 'redirect_to' => self::checkout_url() ), self::checkout_url() );
        $color = is_string( $settings['accent_color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/D', $settings['accent_color'] ) ? $settings['accent_color'] : self::defaults()['accent_color'];
        echo '<div class="ava-digits-checkout" style="--ava-digits-accent:' . esc_attr( $color ) . ';--ava-digits-label:' . self::label_color( $color ) . '">';
        if ( is_string( $settings['message'] ) && '' !== $settings['message'] ) {
            echo '<p class="ava-digits-checkout-message">' . esc_html( $settings['message'] ) . '</p>';
        }
        echo '<a class="ava-digits-checkout-button digits-login-modal" type="1" href="' . esc_url( $url ) . '">' . esc_html( is_string( $settings['button_label'] ) ? $settings['button_label'] : self::defaults()['button_label'] ) . '</a></div>';
    }

    /** LP's admin-ajax session is uninitialized and its wp_login callback deletes the guest session. */
    public static function capture_cart( $login, $user ) {
        self::$guest_cart = null;
        if ( ! self::enabled() || ! self::digits_request() || ! self::from_checkout() || empty( $user->ID ) ) {
            return;
        }
        $session = self::session();
        if ( ! $session || ! is_callable( array( $session, 'get_cookie_data' ) ) || ! is_callable( array( $session, 'get_session_by_customer_id' ) ) ) {
            return;
        }
        $guest_id = $session->get_cookie_data();
        $ip_session = false;
        if ( class_exists( 'LP_Settings' ) && is_callable( array( 'LP_Settings', 'is_store_ip_customer' ) ) &&
            \LP_Settings::is_store_ip_customer() && class_exists( 'LP_Helper' ) && is_callable( array( 'LP_Helper', 'get_client_ip' ) ) ) {
            $guest_id = \LP_Helper::get_client_ip();
            $ip_session = is_string( $guest_id ) && false !== filter_var( $guest_id, FILTER_VALIDATE_IP );
        }
        // Never copy an authenticated user's session from a forged numeric cookie.
        if ( ! is_string( $guest_id ) || ( ! $ip_session && ! preg_match( '/^g-[a-zA-Z0-9_-]{1,100}$/D', $guest_id ) ) ) {
            return;
        }
        $data = $session->get_session_by_customer_id( $guest_id );
        if ( is_array( $data ) && array_key_exists( 'cart', $data ) ) {
            self::$guest_cart = array( 'user_id' => (int) $user->ID, 'cart' => maybe_unserialize( $data['cart'] ) );
        }
    }

    /** Digits signup sets a real auth cookie but does not emit wp_login. Never use user-created. */
    public static function authenticated_cookie( $cookie, $expire, $expiration, $user_id ) {
        $user = wp_get_current_user();
        if ( empty( $user->ID ) || (int) $user->ID !== (int) $user_id ) {
            return;
        }
        self::capture_cart( $user->user_login, $user );
        $session = self::session();
        if ( null === self::$guest_cart || ! $session || ! is_callable( array( $session, 'handle_when_user_login_success' ) ) ) {
            return;
        }
        // Reuse LP's own transition (guest cookie cleanup and customer ID). This
        // does not authenticate anyone: Digits has already selected the user and
        // WordPress is issuing their auth cookie after its existing verification.
        $session->handle_when_user_login_success( $user->user_login, $user );
        self::restore_cart( $user->user_login, $user );
    }

    /** Run after LP's priority-10 login handler has selected the authenticated session. */
    public static function restore_cart( $login, $user ) {
        $saved = self::$guest_cart;
        self::$guest_cart = null;
        if ( null === $saved || empty( $user->ID ) || $saved['user_id'] !== (int) $user->ID || ! is_array( $saved['cart'] ) || empty( $saved['cart'] ) ) {
            return;
        }
        $session = self::session();
        foreach ( array( 'get_customer_id', 'get_session_by_customer_id', 'set_session_expiration', 'set', 'save_data' ) as $method ) {
            if ( ! $session || ! is_callable( array( $session, $method ) ) ) {
                return;
            }
        }
        if ( (string) $user->ID !== $session->get_customer_id() ) {
            return;
        }
        $existing = $session->get_session_by_customer_id( (string) $user->ID );
        foreach ( is_array( $existing ) ? $existing : array() as $key => $value ) {
            $session->set( $key, maybe_unserialize( $value ) );
        }
        $user_cart = is_array( $existing ) && isset( $existing['cart'] ) ? maybe_unserialize( $existing['cart'] ) : array();
        $session->set( 'cart', array_replace( is_array( $user_cart ) ? $user_cart : array(), $saved['cart'] ) );
        $session->set_session_expiration( 6 * DAY_IN_SECONDS );
        $session->save_data();
    }

    private static function session() {
        if ( ! class_exists( 'LearnPress' ) ) {
            return null;
        }
        $lp = \LearnPress::instance();
        return isset( $lp->session ) && is_object( $lp->session ) ? $lp->session : null;
    }
}
