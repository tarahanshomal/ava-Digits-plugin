<?php
/** Standalone behavior tests. All HTTP is mocked; no SMS is sent. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['options'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['http'] = array();
$GLOBALS['can_manage'] = true;
$GLOBALS['uuid'] = 0;
$GLOBALS['registered'] = array();

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code, $message, $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['errors'][] = array( $setting, $code, $message ); }
function wp_parse_url( $v ) { return parse_url( $v ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_safe_remote_request( $url, $args ) { $GLOBALS['http'][] = array( $url, $args ); return $GLOBALS['next_http']; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-' . str_pad( (string) ++$GLOBALS['uuid'], 12, '0', STR_PAD_LEFT ); }
function wp_salt( $scheme ) { return 'synthetic-test-salt'; }
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][ $name ][] = array( $callback, $priority, $accepted_args ); }
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) { add_filter( $name, $callback, $priority, $accepted_args ); }
function is_admin() { return true; }
function plugin_basename( $file ) { return basename( $file ); }
function register_setting( $group, $name, $args ) { $GLOBALS['registered'][ $name ] = array( $group, $args ); }
function add_options_page( ...$args ) { $GLOBALS['menu'] = $args; }
function wp_enqueue_style( ...$args ) { $GLOBALS['style'] = $args; }
function plugins_url( $path, $file ) { return 'https://wordpress.example/plugins/' . $path; }
function admin_url( $path ) { return 'https://wordpress.example/wp-admin/' . $path; }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $domain ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function checked( $value, $expected ) { echo $value === $expected ? ' checked' : ''; }
function disabled( $value ) { echo $value ? ' disabled' : ''; }
function settings_fields( $group ) { echo '<input type="hidden" name="_wpnonce" value="synthetic-nonce">'; }
function settings_errors( $setting ) {}
function submit_button( $label ) { echo '<button>' . esc_attr( $label ) . '</button>'; }

require dirname( __DIR__ ) . '/ava-digits-plugin.php';

use AvaDigits\Settings;
use AvaDigits\Phone;
use AvaDigits\Api;
use AvaDigits\Gateway;
use AvaDigits\Admin;

$checks = 0;
function check( $condition, $name ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: $name\n" ); exit( 1 ); } }
function same( $actual, $expected, $name ) { check( $actual === $expected, $name ); }
function response( $status = 202, $body = null ) { return array( 'response' => array( 'code' => $status ), 'body' => null === $body ? '{"id":"synthetic-job-1","status":"pending"}' : $body ); }
function configured() { return array_merge( Settings::defaults(), array( 'enabled' => 'yes', 'api_key' => 'ava_live_' . str_repeat( 'a', 24 ), 'template_id' => 'synthetic-template-1' ) ); }
function calls() { return count( $GLOBALS['http'] ); }

same( Settings::get()['enabled'], 'no', 'new installations disabled' );
same( Settings::get()['api_url'], 'https://ava.tarpardaz.com', 'default panel URL' );
foreach ( array( '09123456789', '+989123456789', '00989123456789', '989123456789', '9123456789', '۰۹۱۲۳۴۵۶۷۸۹', '٠٩١٢٣٤٥٦٧٨٩', '(0912) 345-6789' ) as $phone ) {
    same( Phone::normalize( $phone ), '09123456789', 'Iran phone normalization' );
}
foreach ( array( '02112345678', '+14155552671', '', array(), '09123456789oops' ) as $phone ) {
    check( is_wp_error( Phone::normalize( $phone ) ), 'invalid phone rejected' );
}
same( Phone::normalize( '9123456789', '+98' ), '09123456789', 'Digits national number' );
check( is_wp_error( Phone::normalize( '9123456789', '+1' ) ), 'non-Iran country rejected even if number resembles Iran' );
check( is_wp_error( Phone::normalize( '9123456789', array() ) ), 'malformed country rejected' );
same( Phone::code( '۰۰۰۷' ), '0007', 'Persian OTP preserves leading zeros' );
same( Phone::code( '٠١٢٣٤٥' ), '012345', 'Arabic OTP normalization' );
foreach ( array( '123', '123456789', 'Code 1234', '{OTP}', '1234 5678', 1234, array() ) as $code ) {
    check( is_wp_error( Phone::code( $code ) ), 'invalid code or rendered message rejected' );
}
same( Settings::valid_url( 'https://AVA.example/' ), 'https://ava.example', 'canonical endpoint' );
foreach ( array( 'http://ava.example', 'https://127.0.0.1', 'https://localhost', 'https://user:pass@ava.example', 'https://ava.example/api/v1', 'https://ava.example?x=1', 'https://ava.example#x', 'https://ava.example:8443', array() ) as $url ) {
    same( Settings::valid_url( $url ), false, 'unsafe endpoint rejected' );
}

$GLOBALS['options'][ Settings::OPTION ] = configured();
$good_input = array( 'enabled' => 'yes', 'api_url' => 'https://panel.example', 'api_key' => '', 'template_id' => 'template_2', 'brand' => 'آوا' );
$clean = Settings::sanitize( $good_input );
same( $clean['api_key'], configured()['api_key'], 'blank key preserved' );
same( $clean['template_id'], 'template_2', 'template configured' );
same( $clean['brand'], 'آوا', 'brand preserved' );
$bad_input = array_merge( $good_input, array( 'api_url' => 'javascript:evil', 'api_key' => "bad\r\nkey", 'template_id' => '../../secret', 'brand' => "bad\nbrand" ) );
$clean = Settings::sanitize( $bad_input );
same( $clean['api_url'], configured()['api_url'], 'invalid URL preserves prior' );
same( $clean['api_key'], configured()['api_key'], 'invalid key preserves prior' );
same( $clean['template_id'], configured()['template_id'], 'invalid template preserves prior' );
same( $clean['brand'], '', 'invalid brand preserves prior' );
same( Settings::sanitize( array_merge( $good_input, array( 'delete_api_key' => 'yes' ) ) )['api_key'], '', 'explicit key deletion' );
same( Settings::sanitize( array( 'api_url' => 'https://panel.example' ) )['enabled'], 'no', 'unchecked enabled disabled' );
check( ! Settings::valid_brand( str_repeat( 'a', 81 ) ), 'long brand rejected' );
check( ! Settings::valid_brand( "\xff" ), 'non-UTF8 brand rejected' );

$GLOBALS['next_http'] = response();
$result = Api::send( '+989123456789', '001234', 'synthetic_request_1234' );
same( $result, array( 'id' => 'synthetic-job-1', 'status' => 'pending' ), 'valid response accepted' );
$request = end( $GLOBALS['http'] );
same( $request[0], 'https://ava.tarpardaz.com/api/v1/sms-otp', 'SMS OTP API path' );
$args = $request[1];
same( $args['headers']['Authorization'], 'Bearer ' . configured()['api_key'], 'Bearer authentication' );
same( $args['headers']['Idempotency-Key'], 'synthetic_request_1234', 'idempotency header' );
same( json_decode( $args['body'], true ), array( 'phone' => '09123456789', 'code' => '001234', 'template_id' => 'synthetic-template-1' ), 'exact Ava JSON payload' );
same( $args['redirection'], 0, 'credential redirects forbidden' );
same( $args['sslverify'], true, 'TLS verification required' );
same( $args['reject_unsafe_urls'], true, 'private IP DNS resolution guarded by WP HTTP' );
same( $args['limit_response_size'], 65536, 'response bounded' );
$s = configured(); $s['brand'] = 'آوا';
Api::send( '09123456789', '0123', 'synthetic_request_1234', $s );
$request = end( $GLOBALS['http'] );
same( json_decode( $request[1]['body'], true )['params'], array( 'brand' => 'آوا' ), 'approved brand parameter' );
$count = calls();
foreach ( array( Settings::defaults(), array_merge( configured(), array( 'api_key' => 'bad' ) ), array_merge( configured(), array( 'template_id' => '' ) ) ) as $s ) {
    check( is_wp_error( Api::send( '09123456789', '0123', 'synthetic_request_1234', $s ) ), 'invalid configuration blocks send' );
}
check( is_wp_error( Api::send( '09123456789', '1234', 'short' ) ), 'invalid idempotency rejected' );
same( calls(), $count, 'invalid input never calls HTTP' );
foreach ( array( 400, 401, 403, 404, 409, 429 ) as $status ) {
    $GLOBALS['next_http'] = response( $status, '{"error":"secret-and-code-must-not-leak"}' );
    $error = Api::send( '09123456789', '0123', 'synthetic_request_1234' );
    same( $error->get_error_code(), 'ava_http_' . $status, 'HTTP error classification' );
    check( false === strpos( $error->get_error_message(), 'secret-and-code' ), 'upstream body never exposed' );
}
foreach ( array( new WP_Error( 'timeout', 'secret-and-code' ), response( 500, 'secret-and-code' ), response( 302, 'redirect' ), response( 202, '{}' ), response( 200, '<html>bad</html>' ), response( 202, '{"id":"secret/<script>","status":"pending"}' ) ) as $reply ) {
    $GLOBALS['next_http'] = $reply;
    $before = calls();
    $error = Api::send( '09123456789', '0123', 'synthetic_request_1234' );
    same( $error->get_error_code(), 'ava_uncertain', 'ambiguous response stays uncertain' );
    same( calls(), $before + 1, 'uncertain send never retried automatically' );
    same( $error->get_error_data()['uncertain'], true, 'uncertainty retained' );
}
foreach ( array( 'pending', 'sending', 'accepted', 'sent', 'delivered' ) as $state ) {
    $GLOBALS['next_http'] = response( 200, json_encode( array( 'id' => 'synthetic-job', 'status' => $state ) ) );
    check( ! is_wp_error( Api::send( '09123456789', '0123', 'synthetic_request_1234' ) ), 'accepted stored state recognized' );
}
foreach ( array( 'unknown', 'expired', 'rejected', 'unexpected' ) as $state ) {
    $GLOBALS['next_http'] = response( 200, json_encode( array( 'id' => 'synthetic-job', 'status' => $state ) ) );
    check( is_wp_error( Api::send( '09123456789', '0123', 'synthetic_request_1234' ) ), 'negative or unknown state never accepted' );
}

same( $GLOBALS['filters']['digits_sms_gateways'][0][1], 100, 'Digits gateway registration priority' );
same( $GLOBALS['filters']['unitedover_send_sms'][0][2], 8, 'Digits send hook argument count' );
$gateways = Gateway::register( array( 'other' => array( 'value' => 9001 ) ) );
same( $gateways['other']['value'], 9001, 'other gateway retained' );
same( $gateways[ Gateway::SLUG ]['value'], Gateway::ID, 'Ava gateway ID' );
$collision = array( 'other' => array( 'value' => Gateway::ID ) );
same( Gateway::register( $collision ), $collision, 'existing numeric gateway ID never overwritten' );
same( Gateway::send( 'other-handler', 'digits', Gateway::ID, '+98', '9123456789', '1234' ), 'other-handler', 'conflicting numeric ID never intercepted' );
Gateway::register( array() );
same( Gateway::send( 'sentinel', 'digits', 9001, '+98', '9123456789', '1234' ), 'sentinel', 'unrelated gateway send untouched' );
$GLOBALS['next_http'] = response();
$before = calls();
same( Gateway::send( false, 'digits', (string) Gateway::ID, '+98', '9123456789', '4321' ), true, 'Digits normal callback boolean success' );
same( Gateway::send( false, 'digits', Gateway::ID, '+98', '9123456789', '4321' ), true, 'repeated hook same request cached' );
same( calls(), $before + 1, 'same request does not duplicate HTTP sends' );
$request = end( $GLOBALS['http'] );
check( 1 === preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $request[1]['headers']['Idempotency-Key'] ), 'gateway generated valid request key' );
$before = calls();
same( Gateway::send( false, 'digits', Gateway::ID, '+98', '9123456789', 'Your code is 4321' ), false, 'Digits template must contain code alone' );
same( Gateway::send( false, 'digits', Gateway::ID, '+1', '9123456789', '4321' ), false, 'Digits country guard' );
same( calls(), $before, 'invalid Digits input never sends' );
$GLOBALS['can_manage'] = false;
same( Gateway::send( false, 'digits', Gateway::ID, '+98', '9123456789', '5321', true ), false, 'Digits admin test denies non-admin' );
$GLOBALS['can_manage'] = true;
$test = json_decode( Gateway::send( false, 'digits', Gateway::ID, '+98', '9123456789', '4321', true ), true );
same( $test['status'], 'pending', 'Digits test returns safe status string' );
check( ! isset( $test['phone'], $test['code'], $test['api_key'] ), 'Digits test excludes secrets and OTP' );
$GLOBALS['next_http'] = new WP_Error( 'timeout', 'must-not-leak' );
$before = calls();
same( Gateway::send( false, 'digits', Gateway::ID, '+98', '9123456789', '3321' ), false, 'transport failure denied' );
same( Gateway::send( false, 'digits', Gateway::ID, '+98', '9123456789', '3321' ), false, 'uncertain result cached within request' );
same( calls(), $before + 1, 'uncertain duplicate callback never retried' );

Admin::register();
same( $GLOBALS['registered'][ Settings::OPTION ][0], 'ava_digits', 'isolated settings option group' );
same( $GLOBALS['registered'][ Settings::OPTION ][1]['show_in_rest'], false, 'secrets not exposed via REST' );
Admin::menu();
same( $GLOBALS['menu'][2], 'manage_options', 'settings menu requires admin permission' );
ob_start(); Admin::page(); $html = ob_get_clean();
check( false === strpos( $html, configured()['api_key'] ), 'saved API key absent from rendered HTML' );
check( false !== strpos( $html, 'name="_wpnonce"' ), 'settings form uses WP nonce' );
check( false !== strpos( $html, 'value="" autocomplete="new-password"' ), 'key input blank' );
check( false !== strpos( $html, '{OTP}' ) && false !== strpos( $html, 'sms_otp:send' ), 'setup instructions match protocol' );
$GLOBALS['can_manage'] = false;
ob_start(); Admin::page(); $html = ob_get_clean();
same( $html, '', 'settings page denies non-admin' );
$GLOBALS['can_manage'] = true;
$GLOBALS['options'][ Settings::OPTION ]['brand'] = '<script>synthetic</script>';
ob_start(); Admin::page(); $html = ob_get_clean();
check( false === strpos( $html, 'value="<script>' ), 'stored settings escaped in admin output' );
define( 'AVA_DIGITS_API_KEY', 'ava_live_' . str_repeat( 'z', 24 ) );
same( Settings::get()['api_key'], AVA_DIGITS_API_KEY, 'wp-config key override' );
same( Settings::sanitize( $good_input )['api_key'], configured()['api_key'], 'constant secret never copied to database option' );
ob_start(); Admin::page(); $html = ob_get_clean();
check( false === strpos( $html, AVA_DIGITS_API_KEY ), 'constant secret absent from HTML' );

echo "PASS: $checks behavioral checks; HTTP mocked, no SMS sent.\n";
