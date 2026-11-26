<?php
/** Standalone administrator boundary checks; no network or WordPress data. */
namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['checkout_admin_can_manage'] = true;
    $GLOBALS['checkout_admin_hooks'] = array();
    $GLOBALS['checkout_admin_nonce_groups'] = array();

    function add_action( $hook, $callback ) { $GLOBALS['checkout_admin_hooks'][ $hook ] = $callback; }
    function add_options_page( ...$args ) { $GLOBALS['checkout_admin_menu'] = $args; }
    function register_setting( $group, $option, $args ) { $GLOBALS['checkout_admin_registration'] = array( $group, $option, $args ); }
    function current_user_can( $capability ) { $GLOBALS['checkout_admin_capability'] = $capability; return $GLOBALS['checkout_admin_can_manage']; }
    function wp_die( $message, $title = '', $args = array() ) { throw new \RuntimeException( 'denied:' . ( $args['response'] ?? 0 ) ); }
    function esc_html__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_textarea( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_url( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
    function admin_url( $path ) { return 'https://wordpress.example/wp-admin/' . $path; }
    function settings_errors( $option ) { $GLOBALS['checkout_admin_errors_for'] = $option; }
    function settings_fields( $group ) { $GLOBALS['checkout_admin_nonce_groups'][] = $group; echo '<input type="hidden" name="_wpnonce" value="synthetic-settings-nonce">'; }
    function checked( $actual, $expected ) { if ( $actual === $expected ) { echo 'checked="checked"'; } }
    function submit_button( $label ) { echo '<button>' . esc_attr( $label ) . '</button>'; }
}

namespace AvaDigits {
    // The administrator surface is tested independently from the real runtime
    // settings tests, including deliberately unsanitized data at the output boundary.
    final class Checkout {
        const OPTION = 'ava_digits_checkout';
        public static $available = true;
        public static $settings = array(
            'enabled' => 'no', 'phone_only' => 'no', 'hide_native_forms' => 'yes', 'message' => 'راهنما',
            'button_label' => 'ورود', 'accent_color' => '#0171f1',
        );
        public static function defaults() { return self::$settings; }
        public static function get() { return self::$settings; }
        public static function sanitize( $input ) { return $input; }
        public static function available() { return self::$available; }
    }
}

namespace {
    require dirname( __DIR__ ) . '/includes/class-checkout-admin.php';

    $checks = 0;
    function checkout_admin_check( $condition, $name ) {
        ++$GLOBALS['checks'];
        if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $name . "\n" ); exit( 1 ); }
    }
    function checkout_admin_render() { ob_start(); \AvaDigits\CheckoutAdmin::page(); return ob_get_clean(); }

    \AvaDigits\CheckoutAdmin::init();
    checkout_admin_check( isset( $GLOBALS['checkout_admin_hooks']['admin_menu'], $GLOBALS['checkout_admin_hooks']['admin_init'] ), 'initialization registers menu and settings hooks' );
    \AvaDigits\CheckoutAdmin::menu();
    checkout_admin_check( 'manage_options' === $GLOBALS['checkout_admin_menu'][2], 'settings menu requires administrator capability' );
    checkout_admin_check( 'ava-digits-checkout' === $GLOBALS['checkout_admin_menu'][3], 'settings page has a separate route' );
    \AvaDigits\CheckoutAdmin::register();
    $registration = $GLOBALS['checkout_admin_registration'];
    checkout_admin_check( array( 'ava_digits_checkout', 'ava_digits_checkout' ) === array_slice( $registration, 0, 2 ), 'separate settings group and option' );
    checkout_admin_check( array( \AvaDigits\Checkout::class, 'sanitize' ) === $registration[2]['sanitize_callback'], 'uses the runtime sanitizer' );
    checkout_admin_check( false === $registration[2]['show_in_rest'], 'settings are not exposed through REST' );

    $GLOBALS['checkout_admin_can_manage'] = false;
    ob_start();
    try {
        \AvaDigits\CheckoutAdmin::page();
        checkout_admin_check( false, 'nonadministrator must be denied' );
    } catch ( \RuntimeException $error ) {
        checkout_admin_check( 'denied:403' === $error->getMessage(), 'nonadministrator receives forbidden status' );
    }
    checkout_admin_check( '' === ob_get_clean(), 'nonadministrator receives no settings markup' );
    checkout_admin_check( 'manage_options' === $GLOBALS['checkout_admin_capability'], 'renderer checks administrator capability' );

    $GLOBALS['checkout_admin_can_manage'] = true;
    \AvaDigits\Checkout::$settings['message'] = '</textarea><script>unsafe</script>';
    \AvaDigits\Checkout::$settings['button_label'] = '" onfocus="unsafe';
    \AvaDigits\Checkout::$settings['accent_color'] = '" oninput="unsafe';
    $html = checkout_admin_render();
    checkout_admin_check( false !== strpos( $html, 'action="https://wordpress.example/wp-admin/options.php"' ), 'form posts through the WordPress Settings API' );
    checkout_admin_check( array( 'ava_digits_checkout' ) === $GLOBALS['checkout_admin_nonce_groups'], 'Settings API supplies the correct nonce and option group' );
    checkout_admin_check( 'ava_digits_checkout' === $GLOBALS['checkout_admin_errors_for'], 'settings errors use separate checkout option' );
    checkout_admin_check( false !== strpos( $html, '&lt;/textarea&gt;&lt;script&gt;unsafe&lt;/script&gt;' ) && false === strpos( $html, '<script>' ), 'buyer message cannot escape the textarea' );
    checkout_admin_check( false === strpos( $html, '" onfocus="unsafe' ) && false !== strpos( $html, '&quot; onfocus=&quot;unsafe' ), 'button label is escaped as an attribute' );
    checkout_admin_check( false === strpos( $html, '" oninput="unsafe' ) && false !== strpos( $html, '&quot; oninput=&quot;unsafe' ), 'color output is escaped as an attribute' );
    checkout_admin_check( false !== strpos( $html, 'ava_digits_checkout[enabled]' ) && false !== strpos( $html, 'ava_digits_checkout[hide_native_forms]' ), 'both opt-in and native-form visibility controls are editable' );
    checkout_admin_check( false !== strpos( $html, 'ava_digits_checkout[phone_only]' ) && false !== strpos( $html, 'حساب‌های دارای دسترسی ویژه یا مرحله احراز هویت اضافی باید از صفحه ورود سایت وارد شوند' ), 'phone-only checkout has its own control and explains the separate sign-in path' );
    checkout_admin_check( false !== strpos( $html, 'tab=login' ) && false !== strpos( $html, 'tab=signup' ) && false !== strpos( $html, 'tab=native_form_style' ), 'native login, registration and styling controls are linked' );
    checkout_admin_check( false === strpos( $html, 'notice-warning' ), 'no dependency warning when Digits is available' );
    \AvaDigits\Checkout::$available = false;
    checkout_admin_check( false !== strpos( checkout_admin_render(), 'notice-warning' ), 'missing Digits support is explained before activation' );

    echo 'PASS: ' . $checks . " checkout administrator checks; no HTTP or paid sends.\n";
}
