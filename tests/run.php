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
$GLOBALS['site_name'] = 'فروشگاه آزمایشی &amp; Ava';
$GLOBALS['site_home'] = 'https://SHOP.example/wordpress/';

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
function wp_parse_url( $v, $component = -1 ) { return parse_url( $v, $component ); }
function get_bloginfo( $field ) { return $GLOBALS['site_name']; }
function home_url( $path = '' ) { return $GLOBALS['site_home']; }
function wp_strip_all_tags( $text ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) ) ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function wp_safe_remote_request( $url, $args ) { $GLOBALS['http'][] = array( $url, $args ); return $GLOBALS['next_http']; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-' . str_pad( (string) ++$GLOBALS['uuid'], 12, '0', STR_PAD_LEFT ); }
function wp_salt( $scheme ) { return 'synthetic-test-salt'; }
function current_user_can( $cap ) { return $GLOBALS['can_manage']; }
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][ $name ][] = array( $callback, $priority, $accepted_args ); }
function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) { add_filter( $name, $callback, $priority, $accepted_args ); }
function has_filter( $name, $callback ) { foreach ( $GLOBALS['filters'][ $name ] ?? array() as $row ) { if ( $row[0] === $callback ) { return $row[1]; } } return false; }
function remove_filter( $name, $callback, $priority ) { foreach ( $GLOBALS['filters'][ $name ] ?? array() as $i => $row ) { if ( $row[0] === $callback && $row[1] === $priority ) { unset( $GLOBALS['filters'][ $name ][ $i ] ); return true; } } return false; }
function apply_filters( $name, $value, ...$args ) { $filters = $GLOBALS['filters'][ $name ] ?? array(); usort( $filters, function ( $a, $b ) { return $a[1] <=> $b[1]; } ); foreach ( $filters as $row ) { $value = call_user_func_array( $row[0], array_slice( array_merge( array( $value ), $args ), 0, $row[2] ) ); } return $value; }
function update_option( $name, $value, $autoload = null ) { $value = apply_filters( 'sanitize_option_' . $name, $value ); if ( ! array_key_exists( $name, $GLOBALS['options'] ) ) { $value = apply_filters( 'sanitize_option_' . $name, $value ); } $GLOBALS['options'][ $name ] = $value; return true; }
function wp_create_nonce( $action ) { return 'synthetic-nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return $nonce === wp_create_nonce( $action ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function digits_version() { return $GLOBALS['digits_version'] ?? '8.6.2'; }
function digits_ippanel_send( ...$args ) { ++$GLOBALS['ippanel_calls']; return 'synthetic-ippanel'; }
function is_admin() { return true; }
function plugin_basename( $file ) { return basename( $file ); }
function register_setting( $group, $name, $args ) { $GLOBALS['registered'][ $name ] = array( $group, $args ); add_filter( 'sanitize_option_' . $name, $args['sanitize_callback'] ); }
function add_options_page( ...$args ) { $GLOBALS['menu'] = $args; }
function wp_enqueue_style( ...$args ) { $GLOBALS['style'] = $args; }
function wp_enqueue_script( ...$args ) { $GLOBALS['script'] = $args; }
function plugins_url( $path, $file ) { return 'https://wordpress.example/plugins/' . $path; }
function admin_url( $path ) { return 'https://wordpress.example/wp-admin/' . $path; }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $domain ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function checked( $value, $expected ) { echo $value === $expected ? ' checked' : ''; }
function disabled( $value ) { echo $value ? ' disabled' : ''; }
function settings_fields( $group ) { echo '<input type="hidden" name="_wpnonce" value="synthetic-nonce">'; }
function settings_errors( $setting ) {}
function get_settings_errors( $setting ) { return array_values( array_filter( $GLOBALS['errors'], function ( $error ) use ( $setting ) { return $error[0] === $setting; } ) ); }
function submit_button( $label ) { echo '<button>' . esc_attr( $label ) . '</button>'; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( 'wp_die:' . ( $args['response'] ?? 0 ) ); }
function wp_send_json_success( $data = null, $status = null ) { $GLOBALS['json_reply'] = array( 'success' => true, 'data' => $data, 'status' => null === $status ? 200 : $status ); throw new RuntimeException( 'synthetic_json_reply' ); }
function wp_send_json_error( $data = null, $status = null ) { $GLOBALS['json_reply'] = array( 'success' => false, 'data' => $data, 'status' => null === $status ? 200 : $status ); throw new RuntimeException( 'synthetic_json_reply' ); }

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
foreach ( array( Settings::defaults(), array_merge( configured(), array( 'api_key' => 'bad' ) ), array_merge( configured(), array( 'template_id' => '../../invalid' ) ) ) as $s ) {
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
$machine_errors = array(
    'api_key_invalid' => array( 401, 403 ), 'scope_missing' => array( 403 ), 'forbidden' => array( 403 ),
    'account_inactive' => array( 403 ), 'account_eligibility_required' => array( 403 ),
    'standard_otp_disabled' => array( 403 ), 'service_disabled' => array( 409 ),
    'service_unavailable' => array( 409 ), 'insufficient_credit' => array( 400 ),
    'rate_limited' => array( 429 ), 'validation_error' => array( 400 ),
    'site_metadata_invalid' => array( 400 ), 'request_conflict' => array( 409 ),
    'template_unavailable' => array( 404, 409 ), 'recipient_blocked' => array( 403, 409 ),
);
foreach ( $machine_errors as $code => $statuses ) {
    foreach ( $statuses as $status ) {
        $GLOBALS['next_http'] = response( $status, json_encode( array( 'code' => $code, 'error' => 'private-upstream-000912', 'token' => configured()['api_key'], 'phone' => '09123456789' ) ) );
        $before = calls();
        $error = Api::send( '09123456789', '000912', 'synthetic_request_1234' );
        same( $error->get_error_code(), 'ava_' . $code, 'documented backend machine code classified' );
        same( $error->get_error_data(), array( 'uncertain' => false, 'http_status' => $status, 'backend_code' => $code ), 'machine errors expose only known safe metadata' );
        $serialized = json_encode( array( $error->get_error_message(), $error->get_error_data() ) );
        check( false === strpos( $serialized, 'private-upstream' ) && false === strpos( $serialized, '000912' ) && false === strpos( $serialized, configured()['api_key'] ) && false === strpos( $serialized, '09123456789' ), 'coded errors never expose upstream text, token, recipient or OTP' );
        same( calls(), $before + 1, 'coded rejection never triggers an automatic retry' );
    }
}
$GLOBALS['next_http'] = response( 400, '{"code":"insufficient_credit","error":"private"}' );
check( false !== strpos( Api::send( '09123456789', '000912', 'synthetic_request_1234' )->get_error_message(), 'اعتبار' ), 'credit error explains the wallet issue rather than invalid input' );
$GLOBALS['next_http'] = response( 403, '{"code":"account_inactive","error":"private"}' );
check( false !== strpos( Api::send( '09123456789', '000912', 'synthetic_request_1234' )->get_error_message(), 'حساب آوا فعال نیست' ), 'inactive account distinguished from missing scope' );
foreach ( array( '{"code":"invented_private_code","error":"private"}', '{"error":"account_inactive"}', '{"code":{"nested":"account_inactive"}}', '{"code":"insufficient_credit"}', 'not-json' ) as $body ) {
    $GLOBALS['next_http'] = response( 403, $body );
    $error = Api::send( '09123456789', '000912', 'synthetic_request_1234' );
    same( $error->get_error_code(), 'ava_http_403', 'unknown, legacy, malformed or mismatched machine codes use generic HTTP fallback' );
    check( false === strpos( $error->get_error_message(), 'private' ), 'generic fallback never reflects upstream code or text' );
}
$GLOBALS['next_http'] = response( 503, '{"code":"service_unavailable","error":"private"}' );
same( Api::send( '09123456789', '000912', 'synthetic_request_1234' )->get_error_code(), 'ava_uncertain', 'known code on unexpected server error remains uncertain' );
$GLOBALS['next_http'] = response( 400, '{"code":"insufficient_credit","error":"private-upstream-000913"}' );
$coded_test = json_decode( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', '000913', true ), true );
same( $coded_test['error'], 'ava_insufficient_credit', 'Digits administrator test exposes a safe known machine error' );
same( array_keys( $coded_test ), array( 'error', 'message' ), 'coded administrator test excludes provider body and private fields' );
check( false === strpos( json_encode( $coded_test ), 'private-upstream' ) && false === strpos( json_encode( $coded_test ), '000913' ), 'coded administrator test never leaks upstream OTP content' );
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

same( Settings::defaults()['mode'], 'pattern', 'existing installations default to approved pattern mode' );
same( Settings::sanitize( array_merge( $good_input, array( 'mode' => 'text', 'sender' => '3000505' ) ) )['mode'], 'text', 'text mode saved' );
same( Settings::sanitize( array_merge( $good_input, array( 'mode' => 'bad' ) ) )['mode'], 'pattern', 'invalid mode preserves prior' );
check( ! Settings::valid_sender( "3000\r\nInjected" ), 'sender controls rejected' );
check( ! Settings::valid_sender( array() ), 'nonscalar sender rejected' );
$GLOBALS['next_http'] = response();
$before = calls();
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', 'Your OTP is 000042 @site.example #000042', false, '000042' ), true, 'raw eighth OTP allows full rendered template in pattern mode' );
$request = end( $GLOBALS['http'] );
same( json_decode( $request[1]['body'], true )['code'], '000042', 'raw eighth leading zeros preserved' );
same( calls(), $before + 1, 'raw OTP generates one HTTP request' );
$before = calls();
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', '1234', false, 'invalid' ), false, 'invalid provided raw OTP never falls back to guessed message' );
same( calls(), $before, 'invalid raw OTP blocks network' );
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', 'Text is ignored', false, 123456 ), true, 'integer raw OTP from compatible integrations normalized' );
same( Gateway::redact_pattern( 'Your OTP is 000042', Gateway::ID, '+98', '9123456789', '000042' ), '[Ava OTP]', 'pattern log body redacted on verified native contract' );
same( Gateway::redact_pattern( 'Other provider 000042', 1000, '+98', '9123456789', '000042' ), 'Other provider 000042', 'foreign gateway log untouched' );
$GLOBALS['digits_version'] = '8.5.0';
same( Gateway::redact_pattern( 'Your OTP 000042', Gateway::ID, '+98', '9123456789', '000042' ), 'Your OTP 000042', 'unverified old signature not redacted' );
$GLOBALS['digits_version'] = '8.6.2';
Gateway::register( array( 'other' => array( 'value' => Gateway::ID ) ) );
same( Gateway::redact_pattern( 'Other provider 000042', Gateway::ID, '+98', '9123456789', '000042' ), 'Other provider 000042', 'numeric collision log untouched' );
Gateway::register( array() );
$GLOBALS['options'][ Settings::OPTION ] = array_merge( configured(), array( 'mode' => 'text', 'template_id' => '', 'sender' => '3000505' ) );
$message = 'کد ورود: ۰۰۱۲۳۴' . "\n" . '@example.test #001234';
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', $message, false, '001234' ), true, 'text mode sends rendered Digits message' );
$request = end( $GLOBALS['http'] );
same( $request[0], 'https://ava.tarpardaz.com/api/v1/sms', 'text mode endpoint differs from OTP pattern endpoint' );
same( json_decode( $request[1]['body'], true ), array( 'phone' => '09123456789', 'text' => $message, 'sender' => '3000505' ), 'text mode payload uses full text and selected sender without template fields' );
same( Gateway::redact_pattern( $message, Gateway::ID, '+98', '9123456789', '001234' ), $message, 'text mode payload never redacted' );
$s = Settings::get(); $s['sender'] = '';
Api::send_text( '09123456789', 'Test 1234', 'synthetic_request_1234', $s );
$request = end( $GLOBALS['http'] );
check( ! isset( json_decode( $request[1]['body'], true )['sender'] ), 'blank text sender delegates to Ava default' );
$GLOBALS['next_http'] = response( 403, '{"error":"private"}' );
check( false !== strpos( Api::send_text( '09123456789', 'Test 1234', 'synthetic_request_1234', $s )->get_error_message(), 'sms:send' ), 'text mode error identifies correct scope' );
$before = calls();
foreach ( array( '', str_repeat( 'a', 1001 ), "text\x00code", "\xff", array() ) as $invalid_message ) {
    check( is_wp_error( Api::send_text( '09123456789', $invalid_message, 'synthetic_request_1234', $s ) ), 'invalid text blocked' );
}
same( calls(), $before, 'invalid text has no HTTP effects' );
$GLOBALS['options'][ Settings::OPTION ] = configured();
$GLOBALS['ippanel_calls'] = 0;
add_filter( 'unitedover_send_sms', 'digits_ippanel_send', 10, 8 );
Gateway::isolate_ippanel();
same( has_filter( 'unitedover_send_sms', 'digits_ippanel_send' ), false, 'known unguarded IPPanel handler detached' );
$GLOBALS['next_http'] = response();
same( apply_filters( 'unitedover_send_sms', false, 'digit', Gateway::ID, '+98', '9123456789', 'Full OTP 001299', false, '001299' ), true, 'Ava dispatch succeeds with legacy IPPanel callback installed' );
same( $GLOBALS['ippanel_calls'], 0, 'Ava dispatch never invokes IPPanel' );
same( apply_filters( 'unitedover_send_sms', false, 'digit', 1000, '+98', '9123456789', 'Test', false, '001299' ), 'synthetic-ippanel', 'IPPanel own route remains supported' );
same( $GLOBALS['ippanel_calls'], 1, 'IPPanel invoked only for own route' );
same( apply_filters( 'unitedover_send_sms', 'third-party', 'digit', 9001, '+98', '9123456789', 'Test', false, '001299' ), 'third-party', 'legacy wrapper preserves unrelated handlers' );

$s = array_merge( configured(), array( 'template_id' => '', 'brand' => 'must-not-send-for-system-template' ) );
Api::send( '09123456789', '000734', 'synthetic_request_1234', $s );
$request = end( $GLOBALS['http'] );
same( json_decode( $request[1]['body'], true ), array( 'phone' => '09123456789', 'code' => '000734', 'site_name' => 'فروشگاه آزمایشی & Ava', 'site_domain' => 'shop.example' ), 'blank pattern adds public site identity without template or arbitrary branding' );
$GLOBALS['site_name'] = '  <b>فروشگاه</b> &amp; Ava' . "\n\t" . 'آزمایشی  ';
$GLOBALS['site_home'] = 'https://user:private@SHOP.example:443/wordpress/path?private=query#fragment';
Api::send( '09123456789', '000735', 'synthetic_request_1234', $s );
$request = end( $GLOBALS['http'] );
$payload = json_decode( $request[1]['body'], true );
same( $payload['site_name'], 'فروشگاه & Ava آزمایشی', 'site title is decoded and normalized to one line without markup' );
same( $payload['site_domain'], 'shop.example', 'only home URL hostname is sent, excluding credentials, port, path, query and fragment' );
check( false === strpos( $request[1]['body'], 'private' ), 'URL components outside public hostname never leave the site' );
$GLOBALS['site_name'] = str_repeat( 'آ', 70 );
$GLOBALS['site_home'] = 'https://مثال.ایران/wordpress/';
Api::send( '09123456789', '000736', 'synthetic_request_1234', $s );
$request = end( $GLOBALS['http'] );
$payload = json_decode( $request[1]['body'], true );
same( $payload['site_name'], str_repeat( 'آ', 60 ), 'site title limit counts Unicode characters without splitting UTF-8' );
same( $payload['site_domain'], 'مثال.ایران', 'internationalized host is left to the server IDNA canonicalizer' );
$GLOBALS['site_name'] = 'آوا: فروشگاه — خانواده 👨‍👩‍👧‍👦';
$GLOBALS['site_home'] = 'https://e' . "\u{0301}" . 'xample.example/';
Api::send( '09123456789', '000739', 'synthetic_request_1234', $s );
$request = end( $GLOBALS['http'] );
$payload = json_decode( $request[1]['body'], true );
same( $payload['site_name'], $GLOBALS['site_name'], 'common punctuation and joined emoji site names remain intact for server validation' );
same( $payload['site_domain'], 'e' . "\u{0301}" . 'xample.example', 'decomposed internationalized hostname is delegated to IDNA normalization' );
$before = calls();
foreach ( array( '', "\xff", array() ) as $invalid_title ) {
    $GLOBALS['site_name'] = $invalid_title;
    same( Api::send( '09123456789', '000737', 'synthetic_request_1234', $s )->get_error_code(), 'ava_site_metadata', 'invalid public site title fails locally with a safe configuration error' );
}
$GLOBALS['site_name'] = 'فروشگاه آزمایشی &amp; Ava';
foreach ( array( 'http://localhost/path', 'http://127.0.0.1/path', 'http://[::1]/path', 'not-a-url' ) as $invalid_home ) {
    $GLOBALS['site_home'] = $invalid_home;
    same( Api::send( '09123456789', '000737', 'synthetic_request_1234', $s )->get_error_code(), 'ava_site_metadata', 'nonhostname site identity never sent' );
}
same( calls(), $before, 'invalid site identity never calls the API' );
$GLOBALS['site_name'] = '';
$GLOBALS['next_http'] = response();
Api::send( '09123456789', '000737', 'synthetic_request_1234', configured() );
$request = end( $GLOBALS['http'] );
same( json_decode( $request[1]['body'], true ), array( 'phone' => '09123456789', 'code' => '000737', 'template_id' => 'synthetic-template-1' ), 'custom pattern is independent of invalid WordPress identity and gains no metadata' );
Api::send_text( '09123456789', 'Synthetic text 000737', 'synthetic_request_1234', configured() );
$request = end( $GLOBALS['http'] );
same( json_decode( $request[1]['body'], true ), array( 'phone' => '09123456789', 'text' => 'Synthetic text 000737' ), 'text payload stays independent of site identity' );
$GLOBALS['next_http'] = response( 202, '{"id":"synthetic-voice-job","state":"pending"}' );
Api::send_voice( '09123456789', '000737', 'synthetic_request_1234', configured() );
$request = end( $GLOBALS['http'] );
same( json_decode( $request[1]['body'], true ), array( 'phone' => '09123456789', 'code' => '000737' ), 'voice payload stays independent of site identity and contains only phone/code' );
$GLOBALS['site_name'] = 'فروشگاه آزمایشی &amp; Ava';
$GLOBALS['site_home'] = 'https://SHOP.example/wordpress/';
$GLOBALS['options'][ Settings::OPTION ] = array_merge( $s, array( 'brand' => '' ) );
$GLOBALS['next_http'] = response();
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', 'نام سایت - ignored' . "\n" . 'دامنه - ignored' . "\n" . 'کد تایید - 000738', false, '000738' ), true, 'standard SMS uses raw OTP with a full three-line Digits message' );
$request = end( $GLOBALS['http'] );
$payload = json_decode( $request[1]['body'], true );
same( $payload['code'], '000738', 'standard identity metadata preserves leading-zero raw OTP' );
same( $payload['site_name'], 'فروشگاه آزمایشی & Ava', 'standard site name comes from WordPress instead of rendered message input' );
same( $payload['site_domain'], 'shop.example', 'standard domain comes from WordPress instead of rendered message input' );
$GLOBALS['options'][ Settings::OPTION ] = configured();
same( Settings::sanitize( array_merge( $good_input, array( 'mode' => 'voice' ) ) )['mode'], 'voice', 'voice mode saved' );
$GLOBALS['options'][ Settings::OPTION ] = array_merge( configured(), array( 'mode' => 'voice', 'template_id' => '', 'brand' => 'ignored', 'sender' => '3000505' ) );
$GLOBALS['next_http'] = response( 202, '{"id":"synthetic-voice-job","state":"pending"}' );
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', '[Ava OTP]', false, '000845' ), true, 'voice mode accepts raw OTP and state response' );
$request = end( $GLOBALS['http'] );
same( $request[0], 'https://ava.tarpardaz.com/api/v1/voice-otp', 'voice mode exact supported endpoint' );
same( json_decode( $request[1]['body'], true ), array( 'phone' => '09123456789', 'code' => '000845' ), 'voice payload contains only normalized phone and leading-zero code' );
same( Gateway::redact_pattern( 'Your code 000845', Gateway::ID, '+98', '9123456789', '000845' ), '[Ava OTP]', 'voice native request log redacted' );
foreach ( array( 'pending', 'claimed', 'played' ) as $state ) {
    $GLOBALS['next_http'] = response( 200, json_encode( array( 'id' => 'synthetic-voice-job', 'state' => $state ) ) );
    same( Api::send_voice( '09123456789', '000846', 'synthetic_request_1234' )['status'], $state, 'voice state normalized to common safe status contract' );
}
foreach ( array( 'unknown', 'failed', 'expired', 'resolved', 'unexpected' ) as $state ) {
    $GLOBALS['next_http'] = response( 200, json_encode( array( 'id' => 'synthetic-voice-job', 'state' => $state ) ) );
    check( is_wp_error( Api::send_voice( '09123456789', '000846', 'synthetic_request_1234' ) ), 'unconfirmed voice states never become success' );
}
$GLOBALS['next_http'] = response( 200, '{"id":"synthetic-voice-job","status":"pending"}' );
check( is_wp_error( Api::send_voice( '09123456789', '000846', 'synthetic_request_1234' ) ), 'voice response must contain state rather than SMS status' );
$GLOBALS['next_http'] = response( 403, '{"error":"private"}' );
check( false !== strpos( Api::send_voice( '09123456789', '000846', 'synthetic_request_1234' )->get_error_message(), 'otp:send' ), 'voice scope error identifies otp send' );
$GLOBALS['next_http'] = new WP_Error( 'timeout', 'private-voice-content' );
$before = calls();
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', '[Ava OTP]', false, '000847' ), false, 'uncertain voice response fails without SMS fallback' );
same( Gateway::send( false, 'digit', Gateway::ID, '+98', '9123456789', '[Ava OTP]', false, '000847' ), false, 'voice duplicate callback stays uncertain' );
same( calls(), $before + 1, 'uncertain voice callback never retries or invokes another channel' );
$GLOBALS['options'][ Settings::OPTION ] = configured();

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

$GLOBALS['options'][ Settings::OPTION ] = configured();
ob_start(); Admin::native_panel(); $native = ob_get_clean();
check( false === strpos( $native, '<form' ), 'native panel does not nest forms' );
check( false === strpos( $native, configured()['api_key'] ), 'native panel never renders saved API key' );
check( false !== strpos( $native, 'name="ava_digits_native_nonce"' ), 'native panel emits dedicated save nonce' );
check( false !== strpos( $native, 'type="button" class="button button-primary" id="ava-digits-native-save"' ), 'native Ava save is an explicit button without submitting Digits form' );
check( false !== strpos( $native, 'id="ava-digits-native-save-status" role="status"' ), 'native explicit save has accessible status output' );
preg_match_all( '/<(?:input|select)\b[^>]*>/', $native, $controls );
foreach ( $controls[0] as $control ) {
    check( false !== strpos( $control, 'dig_sens_data' ), 'every native control excluded from Digits telemetry' );
}
Admin::assets( 'toplevel_page_digits_settings' );
same( $GLOBALS['script'][0], 'ava-digits-admin', 'native presentation script enqueued' );
$_POST = array( 'ava_digits_native_present' => '1', 'ava_digits_native_nonce' => wp_create_nonce( 'ava_digits_native_save' ), 'ava_digits_settings' => array_merge( $good_input, array( 'mode' => 'text', 'sender' => '3000505' ) ) );
Admin::native_save();
same( Settings::get()['mode'], 'text', 'native save updates shared mode' );
same( Settings::get()['api_key'], configured()['api_key'], 'native blank key preserves existing key' );
same( get_option( 'digit_ava_digits', false ), false, 'native integration never duplicates key in Digits gateway option' );
check( false !== has_filter( 'sanitize_option_' . Settings::OPTION, array( Settings::class, 'sanitize' ) ), 'native save restores registered settings sanitizer' );
$_POST['ava_digits_settings']['delete_api_key'] = 'yes';
Admin::native_save();
same( Settings::get()['api_key'], '', 'native explicit deletion survives registered sanitizer and update_option' );
$GLOBALS['options'][ Settings::OPTION ] = configured();
$_POST['ava_digits_native_nonce'] = 'bad-nonce';
Admin::native_save();
same( Settings::get()['api_key'], configured()['api_key'], 'native invalid nonce cannot mutate key' );
$_POST['ava_digits_native_nonce'] = wp_create_nonce( 'ava_digits_native_save' );
$GLOBALS['can_manage'] = false;
Admin::native_save();
same( Settings::get()['api_key'], configured()['api_key'], 'native non-admin cannot mutate settings' );
$GLOBALS['can_manage'] = true;
unset( $GLOBALS['options'][ Settings::OPTION ], $_POST['ava_digits_settings']['delete_api_key'] );
$_POST['ava_digits_settings']['api_key'] = configured()['api_key'];
Admin::native_save();
same( Settings::get()['api_key'], configured()['api_key'], 'first native save retains key through add_option sanitization path' );
same( Settings::get()['mode'], 'text', 'first native save retains selected mode' );
$_POST = array( 'gateway' => (string) Gateway::ID );
$denied = false;
try { Admin::native_test_guard(); } catch ( RuntimeException $e ) { $denied = 'wp_die:403' === $e->getMessage(); }
check( $denied, 'Ava paid native test endpoint denies missing nonce' );
$_POST['ava_digits_native_test_nonce'] = 'invalid';
$denied = false;
try { Admin::native_test_guard(); } catch ( RuntimeException $e ) { $denied = 'wp_die:403' === $e->getMessage(); }
check( $denied, 'Ava paid native test endpoint denies invalid nonce' );
$_POST['ava_digits_native_test_nonce'] = wp_create_nonce( 'ava_digits_native_test' );
Admin::native_test_guard();
check( true, 'Ava paid test accepts own valid nonce and permission' );
$GLOBALS['can_manage'] = false;
$denied = false;
try { Admin::native_test_guard(); } catch ( RuntimeException $e ) { $denied = 'wp_die:403' === $e->getMessage(); }
check( $denied, 'Ava paid native test denies non-admin even with nonce' );
$_POST = array( 'gateway' => '1000' );
Admin::native_test_guard();
check( true, 'Ava paid test guard leaves unrelated provider endpoints untouched' );
$GLOBALS['can_manage'] = true;
$GLOBALS['options'][ Settings::OPTION ] = configured();
$_POST = array();
same( Admin::native_save(), false, 'native save returns false for missing authorization marker' );
$GLOBALS['options']['digit_tapp'] = 13;
$GLOBALS['options']['dig_enable_registration'] = 1;
$_POST = array( 'ava_digits_native_present' => '1', 'ava_digits_native_nonce' => wp_create_nonce( 'ava_digits_native_save' ), 'ava_digits_settings' => array_merge( $good_input, array( 'mode' => 'voice' ) ) );
$before = calls();
try { Admin::native_ajax_save(); } catch ( RuntimeException $e ) { same( $e->getMessage(), 'synthetic_json_reply', 'native AJAX responds through WP JSON API' ); }
same( $GLOBALS['json_reply']['success'], true, 'explicit native save reports success' );
same( Settings::get()['mode'], 'voice', 'explicit save persists a mode-only user change' );
same( Settings::get()['api_key'], configured()['api_key'], 'explicit save keeps blank existing key' );
same( get_option( 'digit_tapp' ), 13, 'explicit Ava save leaves Digits gateway choice unchanged' );
same( get_option( 'dig_enable_registration' ), 1, 'explicit Ava save leaves Digits registration settings unchanged' );
same( array_keys( $GLOBALS['json_reply']['data'] ), array( 'message' ), 'explicit save JSON exposes only safe message' );
check( false === strpos( json_encode( $GLOBALS['json_reply'] ), configured()['api_key'] ), 'explicit save JSON excludes saved key' );
same( calls(), $before, 'saving native settings never sends OTP or calls providers' );
same( Admin::native_save(), true, 'repeated unchanged authorized save returns success' );
$_POST['ava_digits_native_activate_gateway'] = (string) Gateway::ID;
try { Admin::native_ajax_save(); } catch ( RuntimeException $e ) {}
same( get_option( 'digit_tapp' ), (string) Gateway::ID, 'explicit Ava save persists selected gateway intent on fresh installations' );
same( get_option( 'dig_enable_registration' ), 1, 'explicit gateway activation leaves other Digits settings unchanged' );
$prior_native_settings = get_option( Settings::OPTION );
$_POST['ava_digits_settings']['api_url'] = 'http://insecure.example';
$_POST['ava_digits_settings']['mode'] = 'text';
try { Admin::native_ajax_save(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['success'], false, 'explicit native save does not report success for invalid fields' );
same( $GLOBALS['json_reply']['status'], 400, 'invalid native fields return a safe validation error' );
same( get_option( Settings::OPTION ), $prior_native_settings, 'invalid native save does not partially change mode or connection' );
$_POST['ava_digits_settings']['api_url'] = $good_input['api_url'];
$_POST['ava_digits_native_nonce'] = 'bad';
try { Admin::native_ajax_save(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['success'], false, 'explicit native save rejects invalid nonce' );
same( $GLOBALS['json_reply']['status'], 403, 'explicit native save invalid nonce returns forbidden' );
same( Settings::get()['mode'], 'voice', 'invalid explicit save preserves previous selected mode' );
$GLOBALS['can_manage'] = false;
$_POST['ava_digits_native_nonce'] = wp_create_nonce( 'ava_digits_native_save' );
try { Admin::native_ajax_save(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['success'], false, 'explicit native save rejects non-admin' );
$GLOBALS['can_manage'] = true;
$GLOBALS['options'][ Settings::OPTION ] = configured();
$_POST = array();
define( 'AVA_DIGITS_API_KEY', 'ava_live_' . str_repeat( 'z', 24 ) );
same( Settings::get()['api_key'], AVA_DIGITS_API_KEY, 'wp-config key override' );
same( Settings::sanitize( $good_input )['api_key'], configured()['api_key'], 'constant secret never copied to database option' );
ob_start(); Admin::page(); $html = ob_get_clean();
check( false === strpos( $html, AVA_DIGITS_API_KEY ), 'constant secret absent from HTML' );

echo "PASS: $checks behavioral checks; HTTP mocked, no SMS sent.\n";
