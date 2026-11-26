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

function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { add_filter( $name, $callback, $priority, $args ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = array( $callback, $priority, $args ); }
function do_action( $name, ...$args ) { $rows = $GLOBALS['hooks'][ $name ] ?? array(); usort( $rows, function ( $a, $b ) { return $a[1] <=> $b[1]; } ); foreach ( $rows as $row ) { call_user_func_array( $row[0], array_slice( $args, 0, $row[2] ) ); } }
function is_admin() { return $GLOBALS['admin']; }
function is_user_logged_in() { return ! empty( $GLOBALS['user']->ID ); }
function wp_get_current_user() { return $GLOBALS['user']; }
function learn_press_is_checkout() { return $GLOBALS['checkout_page']; }
function learn_press_get_checkout_url() { return $GLOBALS['checkout_url']; }
function digits_version() { return $GLOBALS['digits_version']; }
function dig_login_contents() { return '<span>native modal trigger</span>'; }
function home_url( $path = '' ) { return 'https://shop.example' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_get_referer() { return $GLOBALS['referer']; }
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
echo 'OK: ' . $checks . " checkout checks (all synthetic; no SMS or orders).\n";
