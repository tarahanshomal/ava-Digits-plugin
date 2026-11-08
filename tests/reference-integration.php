<?php
/**
 * Optional integration acceptance using a local licensed Digits reference and
 * REAL WordPress in a disposable SQLite fixture. Nothing is downloaded here.
 *
 * Run inside an empty network namespace with process/mail transports disabled:
 * php tests/reference-integration.php --wp-root=/tmp/your-fixture/wordpress \
 *     --refdir=/tmp/your-fixture/wordpress/wp-content/plugins/digits
 * AVA_DIGITS_WP_ROOT / AVA_DIGITS_REFERENCE_DIR may supply those paths instead.
 * The fixture must block HTTP/mail, and may record disabled direct transports
 * in $GLOBALS['qa_direct_network_blocked']. Do not use a production installation.
 */
if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}
$args = getopt( '', array( 'wp-root:', 'refdir:' ) );
$wp_root = realpath( $args['wp-root'] ?? getenv( 'AVA_DIGITS_WP_ROOT' ) ?: '' );
$refdir = realpath( $args['refdir'] ?? getenv( 'AVA_DIGITS_REFERENCE_DIR' ) ?: '' );
if ( ! $wp_root || ! $refdir || 0 !== strpos( $wp_root, sys_get_temp_dir() . '/' ) ||
    0 !== strpos( $refdir, dirname( $wp_root ) . '/' ) ||
    ! is_file( $wp_root . '/wp-load.php' ) || ! is_file( $refdir . '/digit.php' ) ) {
    fwrite( STDERR, "Explicit disposable WordPress root and installed local Digits --refdir are required.\n" );
    exit( 1 );
}
$GLOBALS['ava_reference_complete'] = false;
register_shutdown_function( static function () {
    if ( empty( $GLOBALS['ava_reference_complete'] ) ) {
        fwrite( STDERR, "Reference integration aborted before the final assertion summary.\n" );
        exit( 1 );
    }
} );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8167';
$_SERVER['SERVER_NAME'] = 'ava-reference-fixture.invalid';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=digits_settings&tab=api';
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'IR';
$_SERVER['HTTP_USER_AGENT'] = 'Ava synthetic reference acceptance';
define( 'WP_ADMIN', true );
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$checks = array();
$failures = array();
function reference_check( $condition, $label ) {
    global $checks, $failures;
    $checks[] = $label;
    if ( ! $condition ) {
        $failures[] = $label;
        fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL );
    }
}
function reference_transport_count() {
    return count( $GLOBALS['qa_direct_network_blocked'] ?? array() );
}
if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE || ! defined( 'DB_PATH' ) ||
    0 !== strpos( realpath( DB_PATH ), dirname( $wp_root ) . '/' ) ||
    'local' !== wp_get_environment_type() || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ||
    ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON || function_exists( 'mail' ) ||
    function_exists( 'exec' ) || function_exists( 'shell_exec' ) || ini_get( 'allow_url_fopen' ) ) {
    throw new RuntimeException( 'Disposable SQLite, local environment, HTTP/cron/mail/process isolation are required.' );
}
reference_check( is_wp_error( wp_remote_get( 'https://external.synthetic.invalid/denied' ) ), 'all unmocked WordPress HTTP is blocked' );
reference_check( true === wp_mail( 'no-delivery@synthetic.invalid', 'synthetic', 'never delivered' ), 'WordPress email is preempted by fixture' );
if ( ! function_exists( 'digits_version' ) || ! class_exists( '\AvaDigits\Gateway' ) ) {
    throw new RuntimeException( 'Activate supplied Digits and the Ava integration inside the fixture first.' );
}
$reference_source = new ReflectionFunction( 'digits_version' );
reference_check( realpath( dirname( $reference_source->getFileName() ) ) === $refdir, 'actual installed Digits functions come from explicit reference directory' );
digits_load_gateways();
reference_check( function_exists( 'digit_send_otp' ) && function_exists( 'digit_send_message' ), 'actual native OTP rendering and gateway dispatch are loaded' );
$administrators = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( empty( $administrators ) ) {
    throw new RuntimeException( 'A synthetic administrator is required in the fixture.' );
}
$admin_id = $administrators[0]->ID;
wp_set_current_user( $admin_id );
// Match real wp-admin registration/sanitization before using the native saver.
do_action( 'admin_init' );
$gateways = getGateWayArray();
reference_check( isset( $gateways['ava_digits'] ) && 714032 === (int) $gateways['ava_digits']['value'], 'native Digits gateway list exposes Ava numeric ID' );
reference_check( has_action( 'digits_save_settings_data' ) !== false, 'native Digits settings-save action has integration listener' );

update_option( 'blogname', 'فروشگاه &amp; نمونه' );
add_filter( 'home_url', static function () { return 'https://shop.example.test/store/'; } );

$fixture = array(
    'enabled' => 'yes', 'mode' => 'pattern', 'api_url' => 'https://ava-fixture.invalid',
    'api_key' => 'ava_live_' . str_repeat( 'x', 32 ), 'template_id' => str_repeat( 'a', 32 ),
    'sender' => '3000123456', 'brand' => 'نمونه',
);
update_option( 'ava_digits_settings', $fixture );
$native_state = array(
    'digit_tapp' => 714032, 'dig_enable_registration' => 1, 'digforgotpass' => 1,
    'dig_otp_size' => 6, 'dig_messagetemplate' => 'کد ورود {OTP} برای {NAME}',
    'dig_default_ccode' => 'Iran', 'dig_reg_skip_otp_verification' => 0,
);
foreach ( $native_state as $key => $value ) {
    update_option( $key, $value );
}
function reference_save_post( $settings, $nonce, $run_native_handler = false ) {
    $_POST = wp_slash( array(
        'ava_digits_native_present' => '1', 'ava_digits_native_nonce' => $nonce,
        'ava_digits_settings' => $settings,
        'dig_save' => '1', 'digit_tapp' => '714032', 'digit_whatsapp_gateway' => '-1', 'digit_email_gateway' => '-1',
        'dig_enable_registration' => '1', 'dig_enable_forgotpass' => '1', 'dig_overwrite_forgotpass_link' => '0',
        'dig_otp_size' => '6', 'dig_messagetemplate' => 'کد ورود {OTP} برای {NAME}',
        'dig_whatsapp_messagetemplate' => 'کد {OTP}', 'dig_default_ccode' => 'Iran',
        'dig_reg_skip_otp_verification' => 'on',
    ) );
    $_SERVER['REQUEST_METHOD'] = 'POST';
    if ( $run_native_handler ) {
        digits_update_data( 0 );
    } else {
        do_action( 'digits_save_settings_data' );
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = array();
}
$before = get_option( 'ava_digits_settings' );
$changed = $fixture;
$changed['brand'] = 'ذخیره تازه';
reference_save_post( $changed, '' );
reference_check( get_option( 'ava_digits_settings' ) === $before, 'native save without own nonce cannot change connection' );
reference_save_post( $changed, 'invalid-synthetic-nonce' );
reference_check( get_option( 'ava_digits_settings' ) === $before, 'native save with invalid nonce cannot change connection' );
$subscriber = get_user_by( 'login', 'reference_subscriber' );
$subscriber_id = $subscriber ? $subscriber->ID : wp_create_user( 'reference_subscriber', wp_generate_password( 32 ), 'subscriber@synthetic.invalid' );
wp_set_current_user( $subscriber_id );
reference_save_post( $changed, wp_create_nonce( 'ava_digits_native_save' ) );
reference_check( get_option( 'ava_digits_settings' ) === $before, 'native save without manage_options cannot change connection' );
wp_set_current_user( $admin_id );
reference_save_post( $fixture, wp_create_nonce( 'ava_digits_native_save' ), true );
$saved = \AvaDigits\Settings::get();
reference_check( 'pattern' === ( $saved['mode'] ?? null ) && 'yes' === $saved['enabled'], 'real Digits settings POST saves enabled pattern mode' );
reference_check( $fixture['api_key'] === $saved['api_key'] && $fixture['template_id'] === $saved['template_id'], 'real Digits settings POST saves API key and approved template' );
$blank = $fixture;
$blank['api_key'] = '';
$blank['brand'] = 'نام تازه';
reference_save_post( $blank, wp_create_nonce( 'ava_digits_native_save' ), true );
$saved = \AvaDigits\Settings::get();
reference_check( $fixture['api_key'] === $saved['api_key'] && 'نام تازه' === $saved['brand'], 'blank native secret input preserves key while other connection fields update' );
foreach ( $native_state as $key => $value ) {
    reference_check( (string) get_option( $key ) === (string) $value, 'native connection save preserves existing Digits option ' . $key );
}

ob_start();
digits_api_settings();
$native_html = ob_get_clean();
reference_check( false !== strpos( $native_html, 'ava-digits-native-api-key' ), 'actual Digits API tab renders native Ava controls' );
reference_check( false === strpos( $native_html, $fixture['api_key'] ), 'actual Digits native HTML never contains stored API key' );
$dom = new DOMDocument();
$prior_errors = libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $native_html );
libxml_clear_errors();
libxml_use_internal_errors( $prior_errors );
$xpath = new DOMXPath( $dom );
$controls = $xpath->query( '//*[self::input or self::select or self::textarea][starts-with(@name,"ava_digits")]' );
reference_check( $controls->length >= 9, 'native controls include own settings and nonce markers' );
$telemetry_safe = true;
foreach ( $controls as $control ) {
    if ( ! in_array( 'dig_sens_data', preg_split( '/\s+/', $control->getAttribute( 'class' ) ), true ) ) {
        $telemetry_safe = false;
    }
}
reference_check( $telemetry_safe, 'every Ava input/select/textarea including hidden nonce excludes Digits telemetry' );
$secret_control = $xpath->query( '//*[@id="ava-digits-native-api-key"]' )->item( 0 );
reference_check( $secret_control && 'password' === $secret_control->getAttribute( 'type' ) && '' === $secret_control->getAttribute( 'value' ), 'native API key control is a blank password input' );

$requests = array();
$provider_mode = 'success';
add_filter( 'pre_http_request', function ( $pre, $http, $url ) use ( &$requests, &$provider_mode ) {
    if ( ! in_array( $url, array( 'https://ava-fixture.invalid/api/v1/sms-otp', 'https://ava-fixture.invalid/api/v1/sms', 'https://ava-fixture.invalid/api/v1/voice-otp' ), true ) ) {
        return new WP_Error( 'synthetic_network_blocked', 'Only synthetic Ava endpoints may be mocked.' );
    }
    $requests[] = array( 'url' => $url, 'args' => $http, 'payload' => json_decode( $http['body'], true ) );
    if ( 'timeout' === $provider_mode ) {
        return new WP_Error( 'http_request_failed', 'Synthetic timeout' );
    }
    $state_key = false !== strpos( $url, '/voice-otp' ) ? 'state' : 'status';
    $state = 'voice_unknown' === $provider_mode ? 'unknown' : 'pending';
    return array(
        'headers' => array(), 'body' => wp_json_encode( array( 'id' => str_repeat( 'b', 32 ), $state_key => $state ) ),
        'response' => array( 'code' => 202, 'message' => 'Accepted' ), 'cookies' => array(),
    );
}, PHP_INT_MAX, 3 );
function reference_last_log() {
    global $wpdb;
    return $wpdb->get_var( 'SELECT message FROM ' . $wpdb->prefix . 'digits_request_logs ORDER BY request_id DESC LIMIT 1' );
}
$direct_before = reference_transport_count();
$result = digit_send_otp( 714032, '+98', '9120000101', '012345', false, 'login' );
reference_check( true === $result && 1 === count( $requests ), 'real native OTP renderer sends through Ava once with full rendered template' );
reference_check( isset( $requests[0] ) && '/api/v1/sms-otp' === parse_url( $requests[0]['url'], PHP_URL_PATH ) &&
    '012345' === ( $requests[0]['payload']['code'] ?? null ), 'pattern mode uses raw eighth OTP and preserves leading zero' );
reference_check( ! isset( $requests[0]['payload']['site_name'] ) && ! isset( $requests[0]['payload']['site_domain'] ) &&
    $fixture['template_id'] === ( $requests[0]['payload']['template_id'] ?? null ), 'approved custom template retains legacy request without standard site metadata' );
reference_check( $direct_before === reference_transport_count(), 'Ava dispatch never invokes direct IPPanel/cURL transport' );
$log = reference_last_log();
reference_check( is_string( $log ) && false === strpos( $log, '012345' ), 'native pattern request log does not persist actual OTP' );

$immediate = $fixture;
$immediate['api_key'] = '';
$immediate['template_id'] = '';
reference_save_post( $immediate, wp_create_nonce( 'ava_digits_native_save' ) );
$before_requests = count( $requests );
$result = digit_send_otp( 714032, '+98', '9120000111', '001234', false, 'login' );
$request = end( $requests );
reference_check( true === $result && count( $requests ) === $before_requests + 1, 'empty custom template selects immediate built-in SMS OTP mode' );
reference_check( '/api/v1/sms-otp' === parse_url( $request['url'], PHP_URL_PATH ) && 4 === count( $request['payload'] ) &&
    '001234' === ( $request['payload']['code'] ?? null ) && isset( $request['payload']['phone'] ) &&
    'فروشگاه & نمونه' === ( $request['payload']['site_name'] ?? null ) &&
    'shop.example.test' === ( $request['payload']['site_domain'] ?? null ), 'standard SMS derives constrained site title and hostname without template or brand parameters' );
$log = reference_last_log();
reference_check( is_string( $log ) && false === strpos( $log, '001234' ), 'native standard site SMS log masks raw OTP' );

$text = $fixture;
$text['api_key'] = '';
$text['mode'] = 'text';
$text['template_id'] = '';
reference_save_post( $text, wp_create_nonce( 'ava_digits_native_save' ) );
$saved = \AvaDigits\Settings::get();
reference_check( 'text' === ( $saved['mode'] ?? null ) && $fixture['sender'] === ( $saved['sender'] ?? null ), 'native form saves direct text mode and sender' );
$before_requests = count( $requests );
$direct_before = reference_transport_count();
$result = digit_send_otp( 714032, '+98', '9120000102', '023456', false, 'register' );
$request = end( $requests );
$expected_text = str_replace( array( '{OTP}', '{NAME}' ), array( '023456', get_option( 'blogname' ) ), $native_state['dig_messagetemplate'] );
reference_check( true === $result && count( $requests ) === $before_requests + 1, 'native registration dispatch sends direct text exactly once' );
reference_check( '/api/v1/sms' === parse_url( $request['url'], PHP_URL_PATH ) &&
    $expected_text === ( $request['payload']['text'] ?? null ) && $fixture['sender'] === ( $request['payload']['sender'] ?? null ) &&
    ! isset( $request['payload']['site_name'] ) && ! isset( $request['payload']['site_domain'] ),
    'text API receives full rendered Digits message and chosen sender' );
reference_check( $direct_before === reference_transport_count(), 'text Ava dispatch also never invokes IPPanel/cURL transport' );
$log = reference_last_log();
reference_check( $expected_text === $log, 'native text log retains documented Digits full-message behavior without changing delivery' );

$voice = $fixture;
$voice['api_key'] = '';
$voice['mode'] = 'voice';
$voice['template_id'] = '';
reference_save_post( $voice, wp_create_nonce( 'ava_digits_native_save' ) );
$before_requests = count( $requests );
$direct_before = reference_transport_count();
$result = digit_send_otp( 714032, '+98', '9120000112', '003456', false, 'login' );
$request = end( $requests );
reference_check( true === $result && count( $requests ) === $before_requests + 1, 'native voice OTP accepts queued request using response state field' );
reference_check( '/api/v1/voice-otp' === parse_url( $request['url'], PHP_URL_PATH ) &&
    2 === count( $request['payload'] ) && '003456' === ( $request['payload']['code'] ?? null ) && isset( $request['payload']['phone'] ),
    'voice API receives only normalized phone and raw code without SMS template or sender' );
reference_check( $direct_before === reference_transport_count(), 'voice dispatch never invokes SMS provider fallback' );
$log = reference_last_log();
reference_check( is_string( $log ) && false === strpos( $log, '003456' ), 'native voice request log masks raw OTP' );
$provider_mode = 'voice_unknown';
$before_requests = count( $requests );
reference_check( false === digit_send_otp( 714032, '+98', '9120000113', '004567', false, 'login' ) && count( $requests ) === $before_requests + 1,
    'unknown voice state is not misrepresented as accepted' );
$provider_mode = 'success';

// Capture WordPress termination only; the real priority-zero nonce guard runs
// before the supplied native AJAX send callback and must prevent all HTTP.
class Reference_WP_Die extends RuntimeException {}
$die_handler = static function () {
    return static function ( $message, $title, $options ) {
        throw new Reference_WP_Die( 'blocked_' . ( $options['response'] ?? 0 ) );
    };
};
add_filter( 'wp_die_handler', $die_handler );
foreach ( array( 'missing', 'invalid', 'subscriber' ) as $case ) {
    wp_set_current_user( 'subscriber' === $case ? $subscriber_id : $admin_id );
    $_POST = array( 'gateway' => '714032' );
    if ( 'missing' !== $case ) {
        $_POST['ava_digits_native_test_nonce'] = 'invalid' === $case ? 'invalid-synthetic' : wp_create_nonce( 'ava_digits_native_test' );
    }
    $blocked = false;
    $before_requests = count( $requests );
    try {
        do_action( 'wp_ajax_digits_test_api' );
    } catch ( Reference_WP_Die $error ) {
        $blocked = 'blocked_403' === $error->getMessage();
    }
    reference_check( $blocked && count( $requests ) === $before_requests && $direct_before === reference_transport_count(),
        'native paid test blocks ' . $case . ' authorization before any provider request' );
}
wp_set_current_user( $admin_id );
$_POST = array( 'gateway' => '714032', 'ava_digits_native_test_nonce' => wp_create_nonce( 'ava_digits_native_test' ) );
$before_requests = count( $requests );
\AvaDigits\Admin::native_test_guard();
reference_check( count( $requests ) === $before_requests, 'valid paid-test nonce passes authorization without guard initiating send' );
$_POST = array( 'gateway' => '13' );
\AvaDigits\Admin::native_test_guard();
reference_check( count( $requests ) === $before_requests, 'Ava paid-test authorization leaves other gateway flows untouched' );
$_POST = array();
remove_filter( 'wp_die_handler', $die_handler );

$before_requests = count( $requests );
$direct_before = reference_transport_count();
reference_check( true === digit_send_message( 13, '+98', '9120000103', '034567', 'Synthetic other gateway', false ), 'existing native built-in gateway behavior remains intact' );
reference_check( count( $requests ) === $before_requests && reference_transport_count() === $direct_before, 'other native gateway creates no Ava request or fallback transport' );
$provider_mode = 'timeout';
$result = digit_send_otp( 714032, '+98', '9120000104', '045678', false, 'login' );
reference_check( false === $result && count( $requests ) === $before_requests + 1, 'uncertain native Ava request fails without automatic repeat or fallback' );
reference_check( $direct_before === reference_transport_count(), 'uncertain Ava result never falls back to IPPanel' );
$disabled = $text;
$disabled['enabled'] = 'no';
reference_save_post( $disabled, wp_create_nonce( 'ava_digits_native_save' ) );
$before_requests = count( $requests );
reference_check( false === digit_send_otp( 714032, '+98', '9120000105', '056789', false, 'login' ) && count( $requests ) === $before_requests,
    'disabled connection blocks actual native send before HTTP' );

echo wp_json_encode( array(
    'wordpress' => get_bloginfo( 'version' ), 'digits' => digits_version(), 'ava' => AVA_DIGITS_VERSION,
    'checks' => count( $checks ), 'failures' => $failures, 'http_mocked' => true, 'real_messages_sent' => 0,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
$GLOBALS['ava_reference_complete'] = true;
exit( empty( $failures ) ? 0 : 1 );
