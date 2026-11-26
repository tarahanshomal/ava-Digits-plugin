<?php
/**
 * Optional native LearnPress/Digits integration. Destructive to its DISPOSABLE
 * synthetic SQLite fixture only; never run against a production installation.
 *
 * AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini \
 *   tests/checkout-integration.php --wp-root=/tmp/fixture/wordpress --mode=frontend
 * Repeat with --mode=admin, --mode=ajax and --mode=signup. See tests/README.md.
 */
if ( PHP_SAPI !== 'cli' || '1' !== getenv( 'AVA_DIGITS_RUN_CHECKOUT_INTEGRATION' ) ) {
    fwrite( STDERR, "Explicit CLI fixture opt-in is required.\n" );
    exit( 1 );
}
// Check direct transports BEFORE loading any WordPress or plugin code. The
// offline MU-plugin may define safe stubs for disabled cURL functions later.
$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
$required_disabled = array( 'curl_exec', 'curl_multi_exec', 'mail', 'exec', 'shell_exec', 'passthru', 'system', 'popen', 'proc_open', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_create', 'socket_connect' );
if ( ini_get( 'allow_url_fopen' ) || array_diff( $required_disabled, $disabled ) ||
    ! function_exists( 'readlink' ) || ! is_link( '/proc/self/ns/net' ) ||
    readlink( '/proc/self/ns/net' ) === readlink( '/proc/1/ns/net' ) ) {
    throw new RuntimeException( 'An isolated network namespace and disabled URL/cURL/process/mail/socket transports are required before bootstrap.' );
}
$args = getopt( '', array( 'wp-root:', 'mode:' ) );
$wp_root = realpath( $args['wp-root'] ?? '' );
$mode = $args['mode'] ?? 'frontend';
if ( ! $wp_root || 0 !== strpos( $wp_root, realpath( sys_get_temp_dir() ) . '/' ) ||
    ! is_file( $wp_root . '/wp-load.php' ) || ! in_array( $mode, array( 'frontend', 'admin', 'ajax', 'signup' ), true ) ) {
    throw new RuntimeException( 'Explicit disposable --wp-root under the temporary directory and a supported --mode are required.' );
}
$fixture_root = dirname( $wp_root );
$marker = $fixture_root . '/ava-digits-checkout-fixture.json';
$fixture = is_file( $marker ) ? json_decode( file_get_contents( $marker ), true ) : null;
if ( ! is_array( $fixture ) || 'ava-digits-checkout-fixture-v1' !== ( $fixture['fixture'] ?? '' ) ) {
    throw new RuntimeException( 'The external synthetic-fixture marker is required.' );
}
foreach ( array( 'administrator_id', 'subscriber_id', 'checkout_page_id' ) as $key ) {
    if ( ! isset( $fixture[ $key ] ) || ! is_int( $fixture[ $key ] ) || $fixture[ $key ] < 1 ) {
        throw new RuntimeException( 'Fixture marker must explicitly identify its synthetic users and checkout page.' );
    }
}
$origin = parse_url( $fixture['home_url'] ?? '' );
$checkout = parse_url( $fixture['checkout_url'] ?? '' );
if ( ! is_array( $origin ) || ! is_array( $checkout ) ||
    ! preg_match( '/\.invalid$/D', $origin['host'] ?? '' ) ||
    ! in_array( $origin['scheme'] ?? '', array( 'http', 'https' ), true ) ||
    ( $origin['scheme'] ?? '' ) !== ( $checkout['scheme'] ?? '' ) ||
    ( $origin['host'] ?? '' ) !== ( $checkout['host'] ?? '' ) ||
    ( $origin['port'] ?? null ) !== ( $checkout['port'] ?? null ) ||
    isset( $origin['user'] ) || isset( $origin['pass'] ) || isset( $checkout['user'] ) || isset( $checkout['pass'] ) ) {
    throw new RuntimeException( 'Only a same-origin checkout on an explicit synthetic .invalid site is accepted.' );
}
if ( in_array( $mode, array( 'ajax', 'signup', 'admin' ), true ) ) {
    define( 'WP_ADMIN', true );
}
if ( in_array( $mode, array( 'ajax', 'signup' ), true ) ) {
    define( 'DOING_AJAX', true );
}
$_SERVER['HTTP_HOST'] = $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' );
$_SERVER['SERVER_NAME'] = $origin['host'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = in_array( $mode, array( 'ajax', 'signup' ), true ) ? '/wp-admin/admin-ajax.php' : ( $checkout['path'] ?? '/' );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'IR';
$_SERVER['HTTP_USER_AGENT'] = 'Ava synthetic checkout integration';
if ( in_array( $mode, array( 'ajax', 'signup' ), true ) ) {
    $_COOKIE['lp_session_guest'] = 'g-synthetic-checkout-qa';
    $_SERVER['HTTP_REFERER'] = $fixture['checkout_url'];
    $_POST['action'] = 'digits_forms_ajax';
    $_REQUEST['action'] = 'digits_forms_ajax';
    $_POST['digits_redirect_page'] = $fixture['checkout_url'];
    $_REQUEST['digits_redirect_page'] = $fixture['checkout_url'];
}
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$db_dir = defined( 'DB_DIR' ) ? realpath( DB_DIR ) : false;
if ( 'local' !== wp_get_environment_type() || ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ||
    ! $db_dir || 0 !== strpos( $db_dir . '/', $fixture_root . '/' ) ||
    ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ||
    ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ||
    rtrim( home_url(), '/' ) !== rtrim( $fixture['home_url'], '/' ) ||
    rtrim( site_url(), '/' ) !== rtrim( $fixture['home_url'], '/' ) ||
    defined( 'AVA_DIGITS_API_KEY' ) || ! class_exists( '\\AvaDigits\\Settings' ) ) {
    throw new RuntimeException( 'Only the declared local SQLite fixture without real credentials is accepted.' );
}
$ava_settings = \AvaDigits\Settings::get();
if ( 'no' !== $ava_settings['enabled'] || '' !== $ava_settings['api_key'] ||
    ! isset( $GLOBALS['qa_http_blocked'], $GLOBALS['qa_mail_blocked'], $GLOBALS['qa_direct_network_blocked'] ) ) {
    throw new RuntimeException( 'Blank disabled Ava settings and the offline fixture MU-plugin must be present.' );
}
foreach ( array( 'administrator_id', 'subscriber_id' ) as $key ) {
    $synthetic_user = get_user_by( 'id', $fixture[ $key ] );
    if ( ! $synthetic_user || ! preg_match( '/@[^@]+\.invalid$/D', $synthetic_user->user_email ) ) {
        throw new RuntimeException( 'Fixture users must have synthetic .invalid email addresses.' );
    }
}
if ( ! user_can( $fixture['administrator_id'], 'manage_options' ) ||
    user_can( $fixture['subscriber_id'], 'manage_options' ) ||
    (int) get_option( 'learn_press_checkout_page_id' ) !== $fixture['checkout_page_id'] ||
    ! class_exists( 'AvaDigits\\Checkout' ) ||
    \AvaDigits\Checkout::checkout_url() !== $fixture['checkout_url'] ) {
    throw new RuntimeException( 'Fixture capability or checkout-page marker does not match its installation.' );
}

$checks=0;$failures=[];
function qa_check($v,$s){global $checks,$failures;++$checks;if(!$v){$failures[]=$s;fwrite(STDERR,'FAIL '.$s."\n");}}
function qa_render($t='checkout/form.php'){ob_start();learn_press_get_template($t);return ob_get_clean();}
function qa_inputs($s,$name){return strpos($s,'name="'.$name.'"')!==false;}
function qa_lp_option($key,$value){update_option('learn_press_'.$key,$value);LP_Settings::instance()->set($key,$value);}
function qa_option($values){update_option(\AvaDigits\Checkout::OPTION,array_merge(\AvaDigits\Checkout::defaults(),$values));}
function qa_dom($html){$d=new DOMDocument;libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="utf-8" ?>'.$html);libxml_clear_errors();return new DOMXPath($d);}
qa_check(function_exists('digits_version')&&digits_version()==='8.6.2','actual Digits 8.6.2 loaded');
qa_check(LEARNPRESS_VERSION==='4.4.10','actual LearnPress 4.4.10 loaded');
qa_check(\AvaDigits\Checkout::available(),'native Digits dependency detected');
qa_check(is_wp_error(wp_remote_get('https://never.synthetic.invalid')) && count( $GLOBALS['qa_http_blocked'] ) > 0,'WordPress HTTP intercepted by offline fixture');
qa_check(true===wp_mail('never@synthetic.invalid','fixture','no delivery') && count( $GLOBALS['qa_mail_blocked'] ) > 0,'email preempted by offline fixture');
wp_set_current_user(0);
update_option('users_can_register',1); update_option('learn_press_enable_registration_checkout','yes'); update_option('learn_press_enable_login_checkout','yes');qa_lp_option('guest_checkout','no');
if($mode==='frontend'){
 $GLOBALS['wp_query']=new WP_Query( array( 'page_id' => $fixture['checkout_page_id'] ) );$GLOBALS['post']=get_post( $fixture['checkout_page_id'] );
 qa_check(LP_Page_Controller::is_page_checkout(),'actual LearnPress checkout page query detected');
 qa_option(['enabled'=>'no']);
 $html=qa_render();
 qa_check(qa_inputs($html,'username')&&qa_inputs($html,'password'),'disabled integration preserves native LearnPress login');
 qa_option(['enabled'=>'yes']);
 $html=qa_render();
 qa_check(!qa_inputs($html,'username')&&!qa_inputs($html,'password'),'enabled integration replaces native username/password login');
 qa_check(strpos($html,\AvaDigits\Checkout::defaults()['button_label'])!==false,'enabled checkout renders configured Digits trigger label');
 $x=qa_dom($html);
 qa_check($x->query('//form')->length===1,'checkout contains exactly original LearnPress form');
 qa_check($x->query('//form//form')->length===0,'checkout has no nested forms');
 qa_check(qa_inputs($html,'learn-press-checkout-nonce'),'checkout payment nonce retained');
 $_GET['is_form_register']='1';
 $html=qa_render();
 qa_check(!qa_inputs($html,'reg_username')&&!qa_inputs($html,'reg_password'),'enabled integration replaces native signup credential fields');
 qa_check(strpos($html,\AvaDigits\Checkout::defaults()['button_label'])!==false,'register view has Digits trigger');
 unset($_GET['is_form_register']);
 qa_option(['enabled'=>'yes','hide_native_forms'=>'no']);
 $html=qa_render();
 qa_check(qa_inputs($html,'username')&&qa_inputs($html,'password'),'add-on mode retains native login controls');
 qa_check(substr_count($html,\AvaDigits\Checkout::defaults()['button_label'])===1,'add-on mode renders one Digits trigger');
 qa_option(['enabled'=>'yes']);
 qa_lp_option('guest_checkout','yes');
 $html=qa_render();
 qa_check(qa_inputs($html,'guest_email'),'guest checkout remains available');
 qa_check(!qa_inputs($html,'username'),'guest checkout does not force native account login');
 qa_check(strpos($html,\AvaDigits\Checkout::defaults()['button_label'])===false,'guest checkout default does not force Digits login');
 qa_lp_option('guest_checkout','no');
 wp_set_current_user( $fixture['subscriber_id'] );$html=qa_render();
 qa_check(!qa_inputs($html,'username')&&!qa_inputs($html,'password'),'logged-in checkout has no login credentials');
 qa_check(strpos($html,\AvaDigits\Checkout::defaults()['button_label'])===false,'logged-in checkout does not show Digits trigger');
}elseif($mode==='admin'){
 wp_set_current_user( $fixture['administrator_id'] ); do_action('admin_init');
 $registered=get_registered_settings();
 qa_check(isset($registered[\AvaDigits\Checkout::OPTION]),'checkout Settings API option registered');
 qa_check(empty($registered[\AvaDigits\Checkout::OPTION]['show_in_rest']),'checkout option excluded from REST');
 ob_start();\AvaDigits\CheckoutAdmin::page();$html=ob_get_clean();
 qa_check(qa_inputs($html,'_wpnonce'),'actual admin form has WordPress nonce');
 qa_check(strpos($html,'options.php')!==false,'actual admin form uses Settings API handler');
 wp_set_current_user( $fixture['subscriber_id'] );
 add_filter('wp_die_handler',function(){return function($m,$t,$a){throw new RuntimeException('qa-die:'.($a['response']??''));};});
 $denied=false;try{\AvaDigits\CheckoutAdmin::page();}catch(RuntimeException $e){$denied=$e->getMessage()==='qa-die:403';}
 qa_check($denied,'actual subscriber cannot render admin checkout page');
 qa_option(['enabled'=>'yes']);
}elseif(in_array($mode,['ajax','signup'],true)){
 qa_option(['enabled'=>'yes']);
 qa_check(is_admin(),'AJAX reproduces admin session loading');
 $session=LearnPress::instance()->session;
 qa_check( '' === $session->get_customer_id(), 'native LearnPress admin-AJAX session starts uninitialized' );
 qa_check($session instanceof LP_Session_Handler,'actual LearnPress session available in AJAX');
 $guest='g-synthetic-checkout-qa';$user=get_user_by( 'id', $fixture['subscriber_id'] );$guestcart=['synthetic-guest-item'=>['item_id'=>991001,'quantity'=>1]];$usercart=['synthetic-existing-item'=>['item_id'=>991002,'quantity'=>1]];
 $wpdb->delete($wpdb->prefix.'learnpress_sessions',['session_key'=>$guest],['%s']); $wpdb->delete($wpdb->prefix.'learnpress_sessions',['session_key'=>(string) $fixture['subscriber_id']],['%s']);
 $wpdb->replace($wpdb->prefix.'learnpress_sessions',['session_key'=>$guest,'session_value'=>maybe_serialize(['cart'=>maybe_serialize($guestcart),'checkout-email'=>maybe_serialize('guest@synthetic.invalid')]),'session_expiry'=>time()+86400],['%s','%s','%d']);
 $wpdb->replace($wpdb->prefix.'learnpress_sessions',['session_key'=>(string) $fixture['subscriber_id'],'session_value'=>maybe_serialize(['cart'=>maybe_serialize($usercart)]),'session_expiry'=>time()+86400],['%s','%s','%d']);
 LP_Session_Cache::instance()->clear($guest);LP_Session_Cache::instance()->clear( (string) $fixture['subscriber_id'] );
 $url=\AvaDigits\Checkout::checkout_url();
 qa_check(apply_filters('digits_login_redirect',$fixture['home_url'] . '/profile/', $fixture['subscriber_id'])===$url,'native Digits login redirect returns to trusted checkout');
 qa_check(apply_filters('digits_register_redirect',$fixture['home_url'] . '/profile/', $fixture['subscriber_id'])===$url,'native Digits signup redirect returns to trusted checkout');
 $_REQUEST['_wp_http_referer'] = wp_make_link_relative( $fixture['checkout_url'] );
 qa_check(apply_filters('digits_login_redirect','/profile/', $fixture['subscriber_id'])===$url,'native relative hidden referer resolves to trusted checkout');
 $_REQUEST['_wp_http_referer']='//evil.synthetic.invalid/lp-checkout/';
 qa_check(apply_filters('digits_login_redirect','/profile/', $fixture['subscriber_id'])==='/profile/','protocol-relative hidden referer cannot trigger checkout redirect');
 unset($_REQUEST['_wp_http_referer']);
 $_SERVER['HTTP_REFERER']='https://evil.synthetic.invalid/lp-checkout/';
 qa_check(apply_filters('digits_login_redirect','/profile/', $fixture['subscriber_id'])==='/profile/','cross-origin referer does not alter login destination');
 $_SERVER['HTTP_REFERER']=$fixture['checkout_url'];
 wp_set_current_user($user->ID); wp_set_auth_cookie($user->ID); if($mode==='ajax')do_action('wp_login',$user->user_login,$user);
 $stored=maybe_unserialize($wpdb->get_var($wpdb->prepare("SELECT session_value FROM {$wpdb->prefix}learnpress_sessions WHERE session_key=%s", (string) $fixture['subscriber_id'])));
 $cart=maybe_unserialize($stored['cart']??[]);
 qa_check(isset($cart['synthetic-guest-item']),'guest cart survives actual LearnPress cookie/login migration');
 qa_check(isset($cart['synthetic-existing-item']),'existing user cart retained while guest cart migrates');
 qa_check($session->get_customer_id() === (string) $fixture['subscriber_id'],'actual LearnPress session belongs to authenticated user after migration');
}
qa_check(empty($GLOBALS['qa_direct_network_blocked']),'no direct upstream transport attempted');
echo 'QA '.$mode.' '.$checks.' checks; '.count($failures).' failures; WP '.$wp_version.' / LearnPress '.LEARNPRESS_VERSION.' / Digits '.digits_version().' / PHP '.PHP_VERSION.PHP_EOL;
if($failures)exit(1);
