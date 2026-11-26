<?php
/** Isolated checkout regressions: no network, messages, real users or orders. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'AVA_DIGITS_VERSION', 'test' );
define( 'AVA_DIGITS_FILE', dirname( __DIR__ ) . '/ava-digits-plugin.php' );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['options'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['admin'] = false;
$GLOBALS['checkout_page'] = true;
$GLOBALS['digits_version'] = '8.6.2';
$GLOBALS['checkout_url'] = 'https://shop.example/lp-checkout/';
$GLOBALS['referer'] = '/lp-checkout/';
$GLOBALS['user'] = (object) array( 'ID' => 0, 'user_login' => '' );
$GLOBALS['guest_enabled'] = false;
$GLOBALS['ip_sessions'] = false;
$GLOBALS['enqueued'] = array();
$GLOBALS['doing_ajax'] = false;
$GLOBALS['new_forms'] = true;
$GLOBALS['known_users'] = array(
    42 => (object) array( 'ID' => 42, 'roles' => array( 'subscriber' ), 'caps' => array() ),
    1 => (object) array( 'ID' => 1, 'roles' => array( 'administrator' ), 'caps' => array( 'manage_options' => true ) ),
    2 => (object) array( 'ID' => 2, 'roles' => array( 'editor' ), 'caps' => array( 'edit_posts' => true ) ),
    3 => (object) array( 'ID' => 3, 'roles' => array( 'subscriber' ), 'caps' => array( 'manage_options' => true ) ),
    4 => (object) array( 'ID' => 4, 'roles' => array( 'custom_role' ), 'caps' => array() ),
    5 => (object) array( 'ID' => 5, 'roles' => array( 'customer' ), 'caps' => array() ),
    6 => (object) array( 'ID' => 6, 'roles' => array( 'customer', 'administrator' ), 'caps' => array() ),
);
foreach ( $GLOBALS['known_users'] as $fixture_user ) {
    $fixture_user->allcaps = array_merge( array( 'read' => true, 'level_0' => true ), array_fill_keys( $fixture_user->roles, true ), $fixture_user->caps );
}
$GLOBALS['filtered_caps'] = array();
$GLOBALS['super_admins'] = array();
$GLOBALS['additional_login_methods'] = array();
$GLOBALS['role_capabilities'] = array(
    'administrator' => array_fill_keys( array( 'read', 'level_0', 'manage_options', 'install_plugins', 'update_plugins', 'edit_theme_options', 'create_users', 'delete_users', 'synthetic_new_admin_capability' ), true ),
    'subscriber' => array( 'read' => true, 'level_0' => true ),
    'customer' => array( 'read' => true, 'level_0' => true ),
    'editor' => array( 'read' => true, 'edit_posts' => true ),
    'custom_role' => array( 'read' => true ),
);

function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? apply_filters( 'option_' . $key, $GLOBALS['options'][ $key ], $key ) : apply_filters( 'default_option_' . $key, $default, $key, false ); }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { add_filter( $name, $callback, $priority, $args ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = array( $callback, $priority, $args ); }
function apply_filters( $name, $value, ...$args ) { $rows = $GLOBALS['hooks'][ $name ] ?? array(); usort( $rows, function ( $a, $b ) { return $a[1] <=> $b[1]; } ); foreach ( $rows as $row ) { $value = call_user_func_array( $row[0], array_slice( array_merge( array( $value ), $args ), 0, $row[2] ) ); } return $value; }
function remove_action( $name, $callback, $priority = 10 ) { foreach ( $GLOBALS['hooks'][ $name ] ?? array() as $i => $row ) { if ( $row[0] === $callback && $row[1] === $priority ) { unset( $GLOBALS['hooks'][ $name ][ $i ] ); } } }
function has_action( $name, $callback ) { foreach ( $GLOBALS['hooks'][ $name ] ?? array() as $row ) { if ( $row[0] === $callback ) { return $row[1]; } } return false; }
function do_action( $name, ...$args ) { $rows = $GLOBALS['hooks'][ $name ] ?? array(); usort( $rows, function ( $a, $b ) { return $a[1] <=> $b[1]; } ); foreach ( $rows as $row ) { call_user_func_array( $row[0], array_slice( $args, 0, $row[2] ) ); } }
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return $GLOBALS['doing_ajax']; }
function is_user_logged_in() { return ! empty( $GLOBALS['user']->ID ); }
function wp_get_current_user() { return $GLOBALS['user']; }
function get_userdata( $id ) { return $GLOBALS['known_users'][ $id ] ?? false; }
function user_can( $user, $capability ) { return ! empty( $user->allcaps[ $capability ] ) || ! empty( $GLOBALS['filtered_caps'][ $user->ID ][ $capability ] ); }
function get_role( $role ) { return isset( $GLOBALS['role_capabilities'][ $role ] ) ? (object) array( 'capabilities' => $GLOBALS['role_capabilities'][ $role ] ) : null; }
function is_super_admin( $id ) { return ! empty( $GLOBALS['super_admins'][ $id ] ); }
function learn_press_is_checkout() { return $GLOBALS['checkout_page']; }
function learn_press_get_checkout_url() { return $GLOBALS['checkout_url']; }
function digits_version() { return $GLOBALS['digits_version']; }
function digits_use_new_form_style() { return $GLOBALS['new_forms']; }
function dig_login_contents() { return '<span>native modal trigger</span>'; }
function home_url( $path = '' ) { return 'https://shop.example' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_get_referer() { return $GLOBALS['referer']; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) ); }
function add_settings_error( ...$args ) { $GLOBALS['errors'][] = $args; }
function maybe_unserialize( $value ) { if ( ! is_string( $value ) ) { return $value; } $decoded = @unserialize( $value, array( 'allowed_classes' => false ) ); return false === $decoded && 'b:0;' !== $value ? $value : $decoded; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function plugins_url( $path, $file ) { return 'https://shop.example/plugins/' . $path; }
function wp_enqueue_style( ...$args ) { $GLOBALS['enqueued'][] = $args; }
function wp_enqueue_script( ...$args ) { $GLOBALS['enqueued'][] = $args; }
function digits_admin_default_auth_flow() { return array( '1fa' => array( 'enable' => true, 'methods' => array( 'password', 'email_otp', 'sms_otp', 'whatsapp_otp' ) ), '2fa' => array( 'enable' => true, 'methods' => array( '2fa_app' ) ), '3fa' => array( 'enable' => false, 'methods' => array() ) ); }
function learn_press_user_become_teacher_registration_form() { echo '<input name="become_teacher">'; }
function synthetic_required_registration_hook() { echo '<input name="mandatory_terms">'; }

class WP_Error {
    private $errors = array();
    public function add( $code, $message ) { $this->errors[ $code ] = $message; }
    public function has_errors() { return ! empty( $this->errors ); }
    public function get_error_code() { return array_key_first( $this->errors ) ?? ''; }
}
class SyntheticDigitsUserSettings {
    public static function get_user_methods( $user, $step ) {
        return $GLOBALS['additional_login_methods'][ $user->ID ][ $step ] ?? array();
    }
}
class_alias( SyntheticDigitsUserSettings::class, 'DigitsUserFormHandler\\UserSettingsHandler' );

class LP_Settings { public static function is_store_ip_customer() { return $GLOBALS['ip_sessions']; } }
class LP_Helper { public static function get_client_ip() { return '192.0.2.8'; } }
class SyntheticCheckout { public function is_enable_guest_checkout() { return $GLOBALS['guest_enabled']; } }
class LearnPress {
    public $session;
    public static $instance;
    public static function instance() { if ( ! self::$instance ) { self::$instance = new self(); } return self::$instance; }
    public function checkout() { return new SyntheticCheckout(); }
}
class SyntheticSession {
    public $rows = array();
    public $cookie = 'g-synthetic-123';
    public $customer_id = '';
    public $data = array();
    public $saves = 0;
    public $expiration = 0;
    public function get_cookie_data() { return $this->cookie; }
    public function get_session_by_customer_id( $id ) { return $this->rows[ $id ] ?? array(); }
    public function get_customer_id() { return (string) $this->customer_id; }
    public function set( $key, $value ) { $this->data[ $key ] = serialize( $value ); }
    public function set_session_expiration( $duration ) { $this->expiration = $duration; }
    public function save_data() { $this->rows[ $this->customer_id ] = $this->data; ++$this->saves; }
    public function handle_when_user_login_success( $login, $user ) {
        // Match LP's native callback: deletes guest, changes ID, does not save.
        $this->cookie = '';
        if ( 0 === strpos( (string) $this->customer_id, 'g-' ) ) { unset( $this->rows[ $this->customer_id ] ); }
        $this->customer_id = (string) $user->ID;
        $GLOBALS['user'] = $user;
    }
}

require dirname( __DIR__ ) . '/includes/class-checkout.php';
use AvaDigits\Checkout;
$checks = 0;
function check( $condition, $label ) { ++$GLOBALS['checks']; if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $label . "\n" ); exit( 1 ); } }
function same( $actual, $expected, $label ) { check( $actual === $expected, $label ); }
function render_checkout() { ob_start(); Checkout::render(); return ob_get_clean(); }
function enable_checkout() { $GLOBALS['options'][ Checkout::OPTION ] = array_merge( Checkout::defaults(), array( 'enabled' => 'yes' ) ); }
function session_fixture() {
    $session = new SyntheticSession();
    $session->rows = array(
        'g-synthetic-123' => array( 'cart' => serialize( array( 'course-new' => array( 'item_id' => 123, 'quantity' => 1 ), 'duplicate' => array( 'item_id' => 456, 'quantity' => 1 ) ) ) ),
        '42' => array( 'cart' => serialize( array( 'course-old' => array( 'item_id' => 789, 'quantity' => 1 ), 'duplicate' => array( 'item_id' => 456, 'quantity' => 2 ) ) ), 'existing-setting' => serialize( 'preserved' ) ),
    );
    LearnPress::instance()->session = $session;
    return $session;
}

Checkout::init();
same( Checkout::get()['enabled'], 'no', 'upgrades leave bridge disabled' );
same( Checkout::get()['phone_only'], 'no', 'existing bridge users keep their native authentication policy by default' );
same( render_checkout(), '', 'disabled checkout does not add content' );
same( Checkout::template( '/native.php', 'checkout/account-login.php' ), '/native.php', 'disabled checkout retains native form' );
enable_checkout();
check( Checkout::available(), 'verified dependencies available' );
check( false !== strpos( Checkout::template( '/native.php', 'checkout/account-login.php' ), '/templates/checkout-account.php' ), 'login replaced when enabled' );
check( false !== strpos( Checkout::template( '/native.php', 'checkout/account-register.php' ), '/templates/checkout-account.php' ), 'signup replaced when enabled' );
same( Checkout::template( '/native.php', 'checkout/payment.php' ), '/native.php', 'payment and policy templates remain native' );
$html = render_checkout();
check( false !== strpos( $html, '<a class="ava-digits-checkout-button digits-login-modal"' ), 'native Digits modal uses keyboard-accessible anchor' );
check( false !== strpos( $html, 'login=true' ) && false !== strpos( $html, 'redirect_to=' ), 'anchor has real page fallback and checkout destination' );
check( false === strpos( $html, '<form' ) && false === strpos( $html, 'name="username"' ), 'bridge cannot nest forms or submit native credentials' );
$GLOBALS['options'][ Checkout::OPTION ]['hide_native_forms'] = 'no';
same( Checkout::template( '/native.php', 'checkout/account-login.php' ), '/native.php', 'administrator can keep native forms' );
$GLOBALS['options'][ Checkout::OPTION ]['phone_only'] = 'yes';
check( false !== strpos( Checkout::template( '/native.php', 'checkout/account-login.php' ), '/templates/checkout-account.php' ), 'phone-only mode always hides the separate native login form' );
$GLOBALS['options'][ Checkout::OPTION ]['phone_only'] = 'no';
$GLOBALS['guest_enabled'] = true;
same( render_checkout(), '', 'guest checkout stays as configured without requiring login' );
$_GET['is_form_login'] = '1';
check( '' !== render_checkout(), 'guest can explicitly choose Digits login' );
unset( $_GET['is_form_login'] );
$GLOBALS['guest_enabled'] = false;
$GLOBALS['user']->ID = 42;
same( render_checkout(), '', 'authenticated customers do not see another login prompt' );
$GLOBALS['user']->ID = 0;
$GLOBALS['digits_version'] = '';
same( Checkout::template( '/native.php', 'checkout/account-login.php' ), '/native.php', 'missing Digits fails open to native form' );
same( render_checkout(), '', 'missing Digits has no broken trigger' );
$GLOBALS['digits_version'] = '8.6.2';
$GLOBALS['admin'] = true;
same( render_checkout(), '', 'backend is not rewritten' );
$GLOBALS['admin'] = false;
$GLOBALS['checkout_page'] = false;
same( render_checkout(), '', 'other frontend pages unchanged' );
$GLOBALS['checkout_page'] = true;

$clean = Checkout::sanitize( array( 'enabled' => 'yes', 'hide_native_forms' => 'yes', 'button_label' => '<b>ادامه خرید</b>', 'message' => '', 'accent_color' => '#ABCDEF', 'unknown' => 'discarded' ) );
same( $clean['button_label'], 'ادامه خرید', 'settings are plain text' );
same( $clean['message'], '', 'explanation is optional' );
same( $clean['accent_color'], '#abcdef', 'validated color normalized' );
check( ! isset( $clean['unknown'] ), 'unrecognized settings are not persisted' );
$clean = Checkout::sanitize( array( 'button_label' => str_repeat( 'آ', 81 ), 'message' => array(), 'accent_color' => '#123456;display:none' ) );
same( $clean['button_label'], Checkout::defaults()['button_label'], 'overlong Unicode label preserves prior setting' );
same( $clean['accent_color'], Checkout::defaults()['accent_color'], 'CSS injection rejected' );
check( count( $GLOBALS['errors'] ) >= 2, 'invalid changes produce visible settings errors' );
$GLOBALS['options'][ Checkout::OPTION ]['button_label'] = '<img src=x onerror=alert(1)>';
$GLOBALS['options'][ Checkout::OPTION ]['message'] = '<script>alert(1)</script>';
$GLOBALS['options'][ Checkout::OPTION ]['accent_color'] = 'red;display:none';
$html = render_checkout();
check( false === strpos( $html, '<script>' ) && false === strpos( $html, '<img' ) && false === strpos( $html, 'red;display' ), 'render escapes even manually corrupted saved settings' );
enable_checkout();
$GLOBALS['options'][ Checkout::OPTION ]['accent_color'] = '#ffff00';
check( false !== strpos( render_checkout(), '--ava-digits-label:#1d2327' ), 'light accent uses a contrasting dark label' );
$GLOBALS['options'][ Checkout::OPTION ]['accent_color'] = '#0171f1';
check( false !== strpos( render_checkout(), '--ava-digits-label:#fff' ), 'dark accent uses a contrasting white label' );
Checkout::enqueue();
check( count( $GLOBALS['enqueued'] ) === 3, 'checkout and native modal assets enqueued' );

$_REQUEST = array( 'action' => 'digits_forms_ajax' );
foreach ( array( '/lp-checkout/', '/lp-checkout/?login=true', 'https://shop.example/lp-checkout/?no-cache=synthetic' ) as $source ) {
    $GLOBALS['referer'] = $source;
    same( Checkout::redirect( 'https://elsewhere.example/account/' ), $GLOBALS['checkout_url'], 'native relative and absolute checkout referers return to trusted checkout' );
}
foreach ( array( false, '//evil.example/lp-checkout/', '/\\evil.example/lp-checkout/', 'https://evil.example/lp-checkout/', 'http://shop.example/lp-checkout/', 'https://shop.example:444/lp-checkout/', 'https://user@shop.example/lp-checkout/', '/profile/', "\n/lp-checkout/", 'lp-checkout/' ) as $source ) {
    $GLOBALS['referer'] = $source;
    same( Checkout::redirect( 'unchanged' ), 'unchanged', 'untrusted or unrelated return context is ignored' );
}
$GLOBALS['referer'] = '/lp-checkout/';
$_REQUEST['action'] = 'unrelated_login';
same( Checkout::redirect( 'unchanged' ), 'unchanged', 'other auth integrations keep their redirect' );
$_REQUEST['action'] = 'digits_forms_ajax';
$GLOBALS['checkout_url'] = 'https://shop.example/?page_id=7';
$GLOBALS['referer'] = '/';
same( Checkout::redirect( 'unchanged' ), 'unchanged', 'plain-permalink checkout does not match homepage' );
$GLOBALS['referer'] = '/?page_id=7&login=true';
same( Checkout::redirect( 'other' ), $GLOBALS['checkout_url'], 'plain-permalink checkout supported' );
$GLOBALS['checkout_url'] = 'https://evil.example/lp-checkout/';
same( Checkout::checkout_url(), '', 'external configured checkout cannot become return destination' );
same( render_checkout(), '', 'invalid destination preserves native path' );
$GLOBALS['checkout_url'] = 'https://shop.example/lp-checkout/';
$GLOBALS['referer'] = '/lp-checkout/';

// Real LP admin-ajax has an empty in-memory session; the guest cart must be read before LP deletes it.
$GLOBALS['admin'] = true;
$customer = (object) array( 'ID' => 42, 'user_login' => 'synthetic-customer' );
$session = session_fixture();
Checkout::capture_cart( $customer->user_login, $customer );
$session->handle_when_user_login_success( $customer->user_login, $customer );
Checkout::restore_cart( $customer->user_login, $customer );
$saved_cart = maybe_unserialize( $session->rows['42']['cart'] );
check( isset( $saved_cart['course-new'], $saved_cart['course-old'] ), 'AJAX login merges guest and existing customer carts' );
same( $saved_cart['duplicate']['quantity'], 1, 'guest selection replaces duplicate without doubling purchases' );
same( maybe_unserialize( $session->rows['42']['existing-setting'] ), 'preserved', 'existing session data retained' );
same( $session->expiration, 6 * DAY_IN_SECONDS, 'restored AJAX session has a future logged-in lifetime' );
same( $session->saves, 1, 'migration explicitly saves once after native login transition' );
Checkout::restore_cart( $customer->user_login, $customer );
same( $session->saves, 1, 'snapshot is consumed once' );

$session = session_fixture();
$GLOBALS['user'] = $customer;
do_action( 'set_logged_in_cookie', 'synthetic-cookie', 0, 0, 42 );
check( isset( maybe_unserialize( $session->rows['42']['cart'] )['course-new'] ), 'authenticated signup without wp_login preserves cart' );
same( $session->customer_id, '42', 'signup uses native LP customer transition' );
same( $session->saves, 1, 'authenticated signup saves once' );
$session = session_fixture();
$GLOBALS['user'] = (object) array( 'ID' => 0 );
do_action( 'set_logged_in_cookie', 'synthetic-cookie', 0, 0, 42 );
same( $session->saves, 0, 'unverified signup without current-user authentication cannot migrate' );
$GLOBALS['user'] = $customer;
do_action( 'set_logged_in_cookie', 'synthetic-cookie', 0, 0, 99 );
same( $session->saves, 0, 'cookie user mismatch cannot migrate' );
$session = session_fixture();
$session->cookie = '99';
$session->rows['99'] = array( 'cart' => serialize( array( 'private-user-cart' => 1 ) ) );
do_action( 'set_logged_in_cookie', 'synthetic-cookie', 0, 0, 42 );
same( $session->saves, 0, 'forged numeric guest cookie cannot copy another account session' );
$session = session_fixture();
$GLOBALS['referer'] = 'https://evil.example/lp-checkout/';
do_action( 'set_logged_in_cookie', 'synthetic-cookie', 0, 0, 42 );
same( $session->saves, 0, 'foreign origin cannot trigger cart migration' );
$GLOBALS['referer'] = '/lp-checkout/';
$session = session_fixture();
$GLOBALS['ip_sessions'] = true;
$session->rows['192.0.2.8'] = $session->rows['g-synthetic-123'];
$session->cookie = '';
do_action( 'set_logged_in_cookie', 'synthetic-cookie', 0, 0, 42 );
same( $session->saves, 1, 'LearnPress IP-based guest storage also preserves checkout cart' );
check( isset( maybe_unserialize( $session->rows['42']['cart'] )['course-new'] ), 'IP guest cart transferred into user session' );

// Phone-only mode is a per-request view of native settings, never a persistent migration.
$GLOBALS['admin'] = false;
$GLOBALS['doing_ajax'] = false;
$GLOBALS['checkout_page'] = true;
$GLOBALS['ip_sessions'] = false;
$_SERVER['REQUEST_URI'] = '/lp-checkout/';
$_REQUEST = array();
enable_checkout();
$login = array( 'dig_login_username' => 1, 'dig_login_email' => 1, 'dig_login_password' => 1, 'dig_login_mobilenumber' => 0, 'dig_login_otp' => 0, 'dig_login_captcha' => 1, 'dig_login_recaptcha' => 1, 'custom_protection' => array( 'required' => true ) );
$registration = array( 'dig_reg_name' => 2, 'dig_reg_uname' => 2, 'dig_reg_email' => 2, 'dig_reg_password' => 2, 'dig_reg_mobilenumber' => 0, 'custom_required_field' => array( 'required' => true ) );
$flow = array( '1fa' => array( 'enable' => true, 'methods' => array( 'password', 'email_otp' ), 'metadata' => 'retained' ), '2fa' => array( 'enable' => true, 'methods' => array( '2fa_app', 'platform' ) ), '3fa' => array( 'enable' => true, 'methods' => array( 'cross-platform' ) ), 'security_metadata' => 'retained' );
$role_flows = array( array_merge( $flow, array( 'users' => array( 'ug_customer' => 'Customer' ) ) ), array_merge( $flow, array( 'users' => array( 'ug_administrator' => 'Administrator', 'user_id_42' => 'Synthetic user' ) ) ) );
$GLOBALS['options']['dig_login_fields'] = $login;
$GLOBALS['options']['dig_reg_fields'] = $registration;
$GLOBALS['options']['dig_reg_skip_otp_verification'] = 1;
$GLOBALS['options']['dig_enable_registration'] = 0;
$GLOBALS['options']['digits_auth_flow'] = json_encode( $flow );
$GLOBALS['options']['digits_auth_user_based_flow'] = json_encode( $role_flows );
$GLOBALS['options']['digits_user_based_flow_enable'] = true;
same( get_option( 'dig_login_fields' ), $login, 'phone-only is separately opt-in even when checkout bridge is enabled' );
$GLOBALS['options'][ Checkout::OPTION ]['phone_only'] = 'yes';
$persisted_options = $GLOBALS['options'];
check( Checkout::phone_only_context(), 'explicit phone-only applies on LearnPress checkout' );
$actual_login = get_option( 'dig_login_fields' );
same( array_intersect_key( $actual_login, array_flip( array( 'dig_login_username', 'dig_login_email', 'dig_login_password' ) ) ), array( 'dig_login_username' => 0, 'dig_login_email' => 0, 'dig_login_password' => 0 ), 'checkout removes built-in credential alternatives' );
same( $actual_login['dig_login_mobilenumber'], 1, 'checkout offers native mobile login' );
same( $actual_login['dig_login_otp'], 1, 'checkout offers native OTP login' );
same( $actual_login['dig_login_captcha'], 1, 'existing captcha remains required' );
same( $actual_login['dig_login_recaptcha'], 1, 'existing recaptcha remains required' );
same( $actual_login['custom_protection'], $login['custom_protection'], 'unknown login protections retained' );
$actual_registration = get_option( 'dig_reg_fields' );
same( $actual_registration['dig_reg_mobilenumber'], 2, 'signup requires a phone number' );
foreach ( array( 'dig_reg_name', 'dig_reg_uname', 'dig_reg_email', 'dig_reg_password' ) as $field ) {
    same( $actual_registration[ $field ], 0, 'checkout does not ask for native ' . $field );
}
same( $actual_registration['custom_required_field'], $registration['custom_required_field'], 'mandatory custom signup fields are not silently removed' );
same( get_option( 'dig_reg_skip_otp_verification' ), 0, 'previously skipped signup verification is required within checkout' );
same( get_option( 'dig_enable_registration' ), 0, 'disabled registration is not silently enabled' );
same( get_option( 'digits_user_based_flow_enable' ), true, 'role flow policy is not disabled' );
$actual_flow = apply_filters( 'digits_user_login_flow', json_decode( get_option( 'digits_auth_flow' ), true ), 42 );
same( $actual_flow['1fa']['methods'], array( 'sms_otp' ), 'native configured first factor uses phone OTP only' );
same( $actual_flow['1fa']['metadata'], 'retained', 'first-factor metadata retained' );
same( $actual_flow['2fa'], $flow['2fa'], 'native second factor is unchanged' );
same( $actual_flow['3fa'], $flow['3fa'], 'native third factor is unchanged' );
same( $actual_flow['security_metadata'], 'retained', 'other security configuration retained' );
$actual_roles = json_decode( get_option( 'digits_auth_user_based_flow' ), true );
foreach ( $actual_roles as $i => $actual_role ) {
    same( $actual_role['1fa']['methods'], $role_flows[ $i ]['1fa']['methods'], 'raw role first factors remain unchanged before identity selection' );
    same( $actual_role['users'], $role_flows[ $i ]['users'], 'role and individual-user selectors remain intact' );
    same( $actual_role['2fa'], $role_flows[ $i ]['2fa'], 'role second-factor security remains intact' );
    same( $actual_role['3fa'], $role_flows[ $i ]['3fa'], 'role third-factor security remains intact' );
}
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'sms_otp' ), 'native method selection prevents password fallback' );
same( apply_filters( 'digits_user_login_methods', array( '2fa_app' ), 42, 2 ), array( '2fa_app' ), 'native second-factor selection remains unchanged' );
same( apply_filters( 'digits_user_login_methods', array( 'cross-platform' ), 42, 3 ), array( 'cross-platform' ), 'native third-factor selection remains unchanged' );
same( get_option( 'digits_auth_flow' ), $persisted_options['digits_auth_flow'], 'raw global first factors remain unchanged before identity selection' );
foreach ( array( 0, 1, 2, 3, 4, 6, 999 ) as $target_id ) {
    same( apply_filters( 'digits_user_login_flow', $flow, $target_id ), $flow, 'privileged, custom or unknown target keeps all native factors: ' . $target_id );
    same( apply_filters( 'digits_user_login_methods', array( 'password' ), $target_id, 1 ), array( 'password' ), 'non-customer target never acquires OTP from forged context: ' . $target_id );
}
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 5, 1 ), array( 'sms_otp' ), 'ordinary WooCommerce customers can use phone OTP' );
foreach ( array( 'install_plugins', 'update_plugins', 'edit_theme_options', 'create_users', 'delete_users', 'manage_network', 'custom_unknown_capability' ) as $direct_capability ) {
    $GLOBALS['known_users'][42]->allcaps[ $direct_capability ] = true;
    same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'password' ), 'direct capability excludes ordinary-role account: ' . $direct_capability );
    same( apply_filters( 'digits_user_login_flow', $flow, 42 ), $flow, 'direct capability leaves all raw factors intact: ' . $direct_capability );
    unset( $GLOBALS['known_users'][42]->allcaps[ $direct_capability ] );
}
$GLOBALS['known_users'][42]->allcaps['custom_disabled_capability'] = false;
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'sms_otp' ), 'explicitly denied custom capability does not count as a grant' );
unset( $GLOBALS['known_users'][42]->allcaps['custom_disabled_capability'] );
foreach ( array( 'install_plugins', 'manage_network', 'synthetic_new_admin_capability' ) as $filtered_capability ) {
    $GLOBALS['filtered_caps'][42][ $filtered_capability ] = true;
    same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'password' ), 'filtered administrator or network grant retains native policy: ' . $filtered_capability );
    unset( $GLOBALS['filtered_caps'][42][ $filtered_capability ] );
}
$GLOBALS['super_admins'][42] = true;
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'password' ), 'super administrator retains native authentication despite ordinary role labels' );
unset( $GLOBALS['super_admins'][42] );
same( apply_filters( 'digits_user_login_flow', 'malformed', 42 ), 'malformed', 'malformed flow is not replaced by weaker fallback' );
same( $GLOBALS['options'], $persisted_options, 'scoped options and auth hooks never write global configuration' );
add_action( 'register_form', 'learn_press_user_become_teacher_registration_form', 10 );
add_action( 'register_form', 'synthetic_required_registration_hook', 20 );
Checkout::registration_form();
same( has_action( 'register_form', 'learn_press_user_become_teacher_registration_form' ), false, 'only verified LearnPress teacher prompt is removed' );
same( has_action( 'register_form', 'synthetic_required_registration_hook' ), 20, 'other registration hooks and mandatory terms remain registered' );

// Missing native options must pass through WordPress default_option hooks too.
unset( $GLOBALS['options']['dig_login_fields'], $GLOBALS['options']['dig_reg_fields'], $GLOBALS['options']['dig_reg_skip_otp_verification'], $GLOBALS['options']['digits_auth_flow'], $GLOBALS['options']['digits_auth_user_based_flow'] );
same( get_option( 'dig_login_fields', false )['dig_login_username'], 0, 'missing login option cannot re-enable legacy username default' );
same( get_option( 'dig_reg_fields', false )['dig_reg_password'], 0, 'missing signup option cannot re-enable legacy password default' );
same( get_option( 'dig_reg_skip_otp_verification', 1 ), 0, 'missing skip-verification option requires OTP' );
same( get_option( 'digits_auth_flow', false ), false, 'missing raw auth option retains native default behavior' );
$default_flow = apply_filters( 'digits_user_login_flow', digits_admin_default_auth_flow(), 42 );
same( $default_flow['1fa']['methods'], array( 'sms_otp' ), 'missing auth option uses native defaults with phone primary factor' );
same( $default_flow['2fa'], digits_admin_default_auth_flow()['2fa'], 'missing auth option retains native default second factor' );
same( get_option( 'digits_auth_user_based_flow', $role_flows ), $role_flows, 'native array-valued role default remains entirely unchanged' );

$GLOBALS['options'] = $persisted_options;
$GLOBALS['checkout_page'] = false;
$_SERVER['REQUEST_URI'] = '/lp-checkout/?login=true&type=register';
check( Checkout::phone_only_context(), 'early Digits full-page renderer is scoped before WP query exists' );
same( get_option( 'dig_reg_fields' )['dig_reg_password'], 0, 'full-page fallback signup also excludes password' );
$_SERVER['REQUEST_URI'] = '/wp-login.php?redirect_to=/lp-checkout/';
check( ! Checkout::phone_only_context(), 'native WordPress login is not scoped by its return destination' );
same( get_option( 'dig_login_fields' ), $login, 'native WordPress login retains saved methods' );
add_action( 'register_form', 'learn_press_user_become_teacher_registration_form', 10 );
Checkout::registration_form();
same( has_action( 'register_form', 'learn_press_user_become_teacher_registration_form' ), 10, 'teacher registration hook remains on unrelated login pages' );
$GLOBALS['admin'] = true;
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=digits_settings';
same( get_option( 'dig_reg_fields' ), $registration, 'Digits administrator sees original registration fields' );
same( get_option( 'digits_auth_flow' ), $persisted_options['digits_auth_flow'], 'administrator sees original security configuration' );
same( get_option( 'dig_reg_skip_otp_verification' ), 1, 'administrator sees saved verification setting' );
$_REQUEST['action'] = 'digits_forms_ajax';
$_REQUEST['type'] = 'login';
$GLOBALS['referer'] = '/lp-checkout/';
check( ! Checkout::phone_only_context(), 'forged action on a non-AJAX admin page does not scope options' );
$GLOBALS['doing_ajax'] = true;
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
check( Checkout::phone_only_context(), 'known Digits AJAX with trusted checkout referer is scoped' );
same( get_option( 'dig_reg_skip_otp_verification' ), 0, 'native AJAX signup must verify OTP' );
same( get_option( 'dig_login_fields' )['dig_login_password'], 0, 'server rejects password in scoped checkout AJAX' );
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'sms_otp' ), 'identified customer still cannot choose password as a primary factor' );
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 1, 1 ), array( 'password' ), 'forged trusted Referer does not add OTP to administrator methods' );
same( apply_filters( 'digits_user_login_flow', $flow, 1 ), $flow, 'administrator raw primary and secondary policies survive forged trusted Referer' );
foreach ( array( 1, 2, 3, 4, 6 ) as $target_id ) {
    $result = apply_filters( 'digits_check_user_login', new WP_Error(), $GLOBALS['known_users'][ $target_id ] );
    same( $result->get_error_code(), 'ava_digits_checkout_standard_login', 'protected account must use normal sign-in: ' . $target_id );
}
foreach ( array( 2, 3 ) as $step ) {
    $GLOBALS['additional_login_methods'][42][ $step ] = array( 'password' );
    $result = apply_filters( 'digits_check_user_login', new WP_Error(), $GLOBALS['known_users'][42] );
    same( $result->get_error_code(), 'ava_digits_checkout_standard_login', 'account with an effective additional factor must use normal sign-in: ' . $step );
    unset( $GLOBALS['additional_login_methods'][42][ $step ] );
}
$errors = new WP_Error();
same( apply_filters( 'digits_check_user_login', $errors, $GLOBALS['known_users'][42] )->get_error_code(), '', 'ordinary account without additional factors may continue to checkout OTP' );
$errors->add( 'existing_policy', 'Existing native login validation error' );
$retained = apply_filters( 'digits_check_user_login', $errors, $GLOBALS['known_users'][1] );
same( $retained->get_error_code(), 'existing_policy', 'checkout does not override an existing native login error' );
$GLOBALS['referer'] = '/profile/';
same( apply_filters( 'digits_check_user_login', new WP_Error(), $GLOBALS['known_users'][1] )->get_error_code(), '', 'protected accounts can continue to normal sign-in outside checkout' );
same( get_option( 'dig_login_fields' )['dig_login_password'], 1, 'normal sign-in retains its original password option' );
$GLOBALS['referer'] = '/lp-checkout/';
foreach ( array( 'digits_submit_form', 'digits_verifyotp_login', 'digits_login_user' ) as $legacy_action ) {
    $_REQUEST['action'] = $legacy_action;
    same( get_option( 'dig_login_fields' ), $login, 'legacy handler keeps original global login gates: ' . $legacy_action );
    same( get_option( 'dig_reg_skip_otp_verification' ), 1, 'legacy handler keeps original registration policy: ' . $legacy_action );
}
$_REQUEST['action'] = 'digits_forms_ajax';
foreach ( array( 'forgot', 'forgot-password', 'unknown', array( 'login' ) ) as $unsupported_type ) {
    $_REQUEST['type'] = $unsupported_type;
    same( get_option( 'dig_login_fields' ), $login, 'password reset or unknown modern request keeps original mobile gate' );
    same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'password' ), 'unsupported request does not acquire new OTP authentication' );
}
unset( $_REQUEST['type'] );
same( get_option( 'dig_login_fields' ), $login, 'missing modern request type retains original policy' );
$_REQUEST['type'] = 'login';
foreach ( array( false, '/profile/', '//evil.example/lp-checkout/', 'https://evil.example/lp-checkout/' ) as $source ) {
    $GLOBALS['referer'] = $source;
    same( get_option( 'dig_reg_fields' ), $registration, 'AJAX from unrelated or untrusted source leaves signup policy intact' );
    same( get_option( 'digits_auth_user_based_flow' ), $persisted_options['digits_auth_user_based_flow'], 'untrusted AJAX cannot change role methods' );
}
$GLOBALS['referer'] = '/lp-checkout/';
$_REQUEST['action'] = 'other_plugin_login';
same( get_option( 'dig_login_fields' ), $login, 'unrelated plugin AJAX retains native login policy' );
$_REQUEST['action'] = 'digits_forms_ajax';
$GLOBALS['options'][ Checkout::OPTION ]['enabled'] = 'no';
same( get_option( 'dig_reg_skip_otp_verification' ), 1, 'disabling bridge also disables scoped phone policy' );
$GLOBALS['options'][ Checkout::OPTION ]['enabled'] = 'yes';
$GLOBALS['options'][ Checkout::OPTION ]['phone_only'] = 'no';
same( get_option( 'dig_login_fields' ), $login, 'disabling phone-only preserves legacy checkout authentication' );
$GLOBALS['options'][ Checkout::OPTION ]['phone_only'] = 'yes';
$GLOBALS['doing_ajax'] = false;
$GLOBALS['admin'] = false;
$GLOBALS['checkout_page'] = true;
$_SERVER['REQUEST_URI'] = '/lp-checkout/';
foreach ( array( 'administrator', 'editor', 'custom_role', 'missing_role' ) as $unsafe_default_role ) {
    $GLOBALS['options']['defaultuserrole'] = $unsafe_default_role;
    same( get_option( 'dig_reg_fields' ), $registration, 'signup fields remain native for privileged or unknown configured role: ' . $unsafe_default_role );
    add_action( 'register_form', 'learn_press_user_become_teacher_registration_form', 10 );
    Checkout::registration_form();
    same( has_action( 'register_form', 'learn_press_user_become_teacher_registration_form' ), 10, 'unsafe-role signup keeps its original registration hooks: ' . $unsafe_default_role );
}
$GLOBALS['options']['defaultuserrole'] = 'customer';
$GLOBALS['role_capabilities']['customer']['install_plugins'] = true;
same( get_option( 'dig_reg_fields' ), $registration, 'customer-labeled privileged signup role retains password and email requirements' );
Checkout::registration_form();
same( has_action( 'register_form', 'learn_press_user_become_teacher_registration_form' ), 10, 'elevated customer signup retains native hooks' );
same( get_option( 'dig_reg_skip_otp_verification' ), 0, 'requiring OTP remains a strengthening for unsafe signup roles' );
unset( $GLOBALS['role_capabilities']['customer']['install_plugins'] );
$GLOBALS['options']['defaultuserrole'] = 'subscriber';
same( get_option( 'dig_reg_fields' )['dig_reg_password'], 0, 'ordinary subscriber signup can use phone-only fields' );
Checkout::registration_form();
same( has_action( 'register_form', 'learn_press_user_become_teacher_registration_form' ), false, 'ordinary signup removes only optional instructor prompt again' );
unset( $GLOBALS['options']['defaultuserrole'] );
$GLOBALS['new_forms'] = false;
same( get_option( 'dig_login_fields' ), $login, 'classic native form keeps its original login UI and policy' );
same( get_option( 'dig_reg_fields' ), $registration, 'classic native form keeps its original signup UI and policy' );
same( apply_filters( 'digits_user_login_methods', array( 'password' ), 42, 1 ), array( 'password' ), 'classic form does not acquire a new factor policy' );
require dirname( __DIR__ ) . '/includes/class-checkout-phone.php';
$GLOBALS['new_forms'] = true;
$GLOBALS['user']->ID = 0;
$GLOBALS['guest_enabled'] = false;
foreach ( array( array(), array( 'reg_email' => 'synthetic@example.invalid' ), array( 'username' => 'synthetic' ), array( 'guest_email' => 'synthetic@example.invalid' ) ) as $legacy_post ) {
    $_POST = $legacy_post;
    $blocked = false;
    try { \AvaDigits\CheckoutPhone::require_account(); } catch ( Exception $error ) { $blocked = true; }
    check( $blocked, 'phone-only checkout rejects anonymous legacy checkout before account or order processing' );
}
$GLOBALS['user']->ID = 42;
$_POST = array();
\AvaDigits\CheckoutPhone::require_account();
check( true, 'authenticated customer can continue native checkout' );
$_POST = array( 'reg_email' => 'synthetic@example.invalid' );
$blocked = false;
try { \AvaDigits\CheckoutPhone::require_account(); } catch ( Exception $error ) { $blocked = true; }
check( $blocked, 'a logged-in checkout cannot switch to native inline account creation' );
$GLOBALS['options'][ Checkout::OPTION ]['phone_only'] = 'no';
$GLOBALS['user']->ID = 0;
\AvaDigits\CheckoutPhone::require_account();
check( true, 'turning off phone-only retains the original native checkout policy' );
echo 'OK: ' . $checks . " checkout checks (all synthetic; no SMS or orders).\n";
