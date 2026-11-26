<?php
/**
 * Optional scoped phone-only LearnPress/Digits integration. Destructive to its DISPOSABLE
 * synthetic SQLite fixture only; never run against a production installation.
 *
 * AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini \
 *   tests/checkout-phone-integration.php --wp-root=/tmp/fixture/wordpress --mode=frontend
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
    ! is_file( $wp_root . '/wp-load.php' ) || ! in_array( $mode, array( 'frontend', 'outside', 'admin', 'ajax', 'signup', 'blocked_registration', 'security' ), true ) ) {
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
if ( in_array( $mode, array( 'ajax', 'signup', 'admin', 'blocked_registration', 'security' ), true ) ) {
    define( 'WP_ADMIN', true );
}
if ( in_array( $mode, array( 'ajax', 'signup', 'blocked_registration', 'security' ), true ) ) {
    define( 'DOING_AJAX', true );
}
$_SERVER['HTTP_HOST'] = $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' );
$_SERVER['SERVER_NAME'] = $origin['host'];
$_SERVER['REQUEST_METHOD'] = in_array( $mode, array( 'ajax', 'signup', 'blocked_registration', 'security' ), true ) ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = in_array( $mode, array( 'ajax', 'signup', 'blocked_registration', 'security' ), true ) ? '/wp-admin/admin-ajax.php' : ( $checkout['path'] ?? '/' );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'IR';
$_SERVER['HTTP_USER_AGENT'] = 'Ava synthetic checkout integration';
if ( in_array( $mode, array( 'ajax', 'signup', 'blocked_registration', 'security' ), true ) ) {
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

$checks = 0;
function phone_check( $condition, $label ) {
    ++$GLOBALS['checks'];
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL );
        exit( 1 );
    }
}
function phone_native_snapshot() {
    global $wpdb;
    return $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'dig%' OR option_name IN ('defaultuserrole','users_can_register','default_role','learn_press_instructor_registration') ORDER BY option_name", ARRAY_A );
}
function phone_option_override( $key, $value ) {
    add_filter( 'pre_option_' . $key, static function () use ( $value ) { return $value; }, 1 );
}
function phone_dom( $html ) {
    $dom = new DOMDocument();
    libxml_use_internal_errors( true );
    $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
    libxml_clear_errors();
    return new DOMXPath( $dom );
}
function phone_render_modal() {
    ob_start();
    digits_new_form_page( true );
    return ob_get_clean();
}
function phone_seed_cart( $user_id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'learnpress_sessions';
    $guest = 'g-synthetic-checkout-qa';
    $wpdb->delete( $table, array( 'session_key' => $guest ) );
    $wpdb->insert( $table, array( 'session_key' => $guest, 'session_value' => maybe_serialize( array( 'cart' => maybe_serialize( array( 'phone-guest-item' => array( 'item_id' => 991003, 'quantity' => 1 ) ) ) ) ), 'session_expiry' => time() + DAY_IN_SECONDS ) );
    LP_Session_Cache::instance()->clear( $guest );
    if ( $user_id ) {
        $wpdb->delete( $table, array( 'session_key' => (string) $user_id ) );
        $wpdb->insert( $table, array( 'session_key' => (string) $user_id, 'session_value' => maybe_serialize( array( 'cart' => maybe_serialize( array( 'phone-existing-item' => array( 'item_id' => 991004, 'quantity' => 1 ) ) ) ) ), 'session_expiry' => time() + DAY_IN_SECONDS ) );
        LP_Session_Cache::instance()->clear( (string) $user_id );
    }
}
function phone_stored_cart( $user_id ) {
    global $wpdb;
    $data = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT session_value FROM {$wpdb->prefix}learnpress_sessions WHERE session_key=%s", (string) $user_id ) ) );
    return maybe_unserialize( $data['cart'] ?? array() );
}
class PhoneFixtureJsonExit extends Error {}
add_filter( 'wp_die_ajax_handler', static function () {
    return static function () { throw new PhoneFixtureJsonExit(); };
} );
function phone_request( $data, $valid_nonce = true ) {
    wp_set_current_user( 0 );
    $data['digits_form'] = $valid_nonce ? wp_create_nonce( 'digits_login_form' ) : 'invalid-synthetic-nonce';
    $data['action'] = 'digits_forms_ajax';
    $data['digits_redirect_page'] = $GLOBALS['fixture']['checkout_url'];
    $_POST = $data;
    $_REQUEST = $data;
    ob_start();
    try {
        do_action( 'wp_ajax_nopriv_digits_forms_ajax' );
    } catch ( PhoneFixtureJsonExit $exit ) {
        // Native JSON termination intercepted after WordPress and Digits handle the request.
    }
    $raw = ob_get_clean();
    $response = json_decode( $raw, true );
    if ( ! is_array( $response ) ) {
        throw new RuntimeException( 'The native fixture endpoint did not return one JSON response.' );
    }
    return $response;
}
if ( 'outside' === $mode ) {
    $_SERVER['REQUEST_URI'] = '/synthetic-profile/';
}
$original_native = phone_native_snapshot();
$original_bridge = get_option( \AvaDigits\Checkout::OPTION, false );
register_shutdown_function( static function () use ( $original_bridge ) {
    if ( false === $original_bridge ) {
        delete_option( \AvaDigits\Checkout::OPTION );
    } else {
        update_option( \AvaDigits\Checkout::OPTION, $original_bridge );
    }
} );
phone_option_override( 'defaultuserrole', 'subscriber' );
update_option( \AvaDigits\Checkout::OPTION, array_merge( \AvaDigits\Checkout::defaults(), array( 'enabled' => 'yes', 'phone_only' => 'no' ) ) );
$original_reg_fields = digit_get_reg_fields();
update_option( \AvaDigits\Checkout::OPTION, array_merge( \AvaDigits\Checkout::defaults(), array( 'enabled' => 'yes', 'phone_only' => 'yes' ) ) );
phone_check( array_key_exists( 'phone_only', \AvaDigits\Checkout::defaults() ), 'installed adapter supplies phone-only mode' );
phone_check( '8.6.2' === digits_version() && '4.4.10' === LEARNPRESS_VERSION, 'actual supplied Digits and LearnPress reference versions loaded' );
// The fixture deliberately retains a password-enabled native base and an instructor
// checkbox. All overrides are process-local; raw stored options are compared below.
$base_login = digit_get_login_fields();
$base_reg = digit_get_reg_fields();
$base_flow = digits_admin_get_user_flow();
$base_skip = get_option( 'dig_reg_skip_otp_verification', 0 );
wp_set_current_user( 0 );
if ( in_array( $mode, array( 'frontend', 'outside' ), true ) ) {
    $GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => 'frontend' === $mode ? $fixture['checkout_page_id'] : 0 ) );
    $GLOBALS['post'] = 'frontend' === $mode ? get_post( $fixture['checkout_page_id'] ) : null;
    if ( 'outside' === $mode ) {
        $GLOBALS['wp_query']->is_page = false;
        $GLOBALS['wp_query']->queried_object_id = 0;
    }
    LP_Settings::instance()->set( 'instructor_registration', 'yes' );
    $html = phone_render_modal();
    $x = phone_dom( $html );
    if ( 'frontend' === $mode ) {
        foreach ( array( 'option_', 'default_option_' ) as $prefix ) {
            add_filter( $prefix . 'dig_reg_skip_otp_verification', static function () { return 1; }, 1 );
        }
        phone_check( 1 === $x->query( '//form[contains(@class,"digloginpage")]//input[@name="digits_phone"]' )->length, 'checkout login asks for a phone' );
        phone_check( 0 === $x->query( '//form[contains(@class,"digloginpage")]//input[@name="digits_email"]' )->length, 'checkout login has no email or username field' );
        phone_check( 1 === $x->query( '//form' )->length, 'checkout renders one native phone journey' );
        phone_check( 0 === $x->query( '//form[contains(@class,"register")]' )->length && 0 === $x->query( '//a[contains(@class,"show_register")]' )->length, 'checkout has no separate registration form or link' );
        phone_check( 0 === $x->query( '//form[contains(@class,"register")]//input[@name="digits_reg_name" or @name="digits_reg_username" or @name="digits_reg_password" or @name="email"]' )->length, 'checkout signup has no name username email or password field' );
        phone_check( false === strpos( $html, 'name="become_teacher"' ), 'checkout signup omits the LearnPress instructor checkbox' );
        $login = digit_get_login_fields();
        $reg = digit_get_reg_fields();
        phone_check( 0 === (int) $login['dig_login_password'] && 1 === (int) $login['dig_login_otp'], 'checkout login allows native OTP without password' );
        phone_check( 2 === (int) $reg['dig_reg_mobilenumber'] && 0 === (int) $reg['dig_reg_email'], 'checkout signup requires phone while email is disabled' );
        phone_check( 0 === (int) get_option( 'dig_reg_skip_otp_verification', 0 ), 'phone-only checkout never skips phone verification' );
        phone_option_override( 'defaultuserrole', 'editor' );
        if ( false === has_action( 'register_form', 'learn_press_user_become_teacher_registration_form' ) ) {
            add_action( 'register_form', 'learn_press_user_become_teacher_registration_form', 10 );
        }
        phone_check( $original_reg_fields === digit_get_reg_fields(), 'privileged default signup role retains all original native fields' );
        phone_check( false === strpos( phone_render_modal(), 'name="become_teacher"' ), 'unified checkout does not render a privileged signup form' );
        phone_option_override( 'defaultuserrole', 'subscriber' );
        phone_check( 0 === (int) digit_get_reg_fields()['dig_reg_password'], 'restoring a safe subscriber signup role restores phone-only fields' );
    } else {
        phone_check( false !== strpos( $html, 'name="become_teacher"' ), 'ordinary registration retains the LearnPress instructor checkbox' );
        phone_check( $base_login === digit_get_login_fields() && $base_reg === digit_get_reg_fields(), 'ordinary Digits form field settings remain unchanged' );
        phone_check( $base_flow === digits_admin_get_user_flow(), 'ordinary Digits authentication factors remain unchanged' );
        phone_check( $base_skip === get_option( 'dig_reg_skip_otp_verification', 0 ), 'ordinary phone verification policy remains unchanged' );
    }
} elseif ( 'admin' === $mode ) {
    wp_set_current_user( $fixture['administrator_id'] );
    phone_check( $base_login === digit_get_login_fields() && $base_reg === digit_get_reg_fields(), 'administrator request retains original Digits fields' );
    phone_check( $base_flow === digits_admin_get_user_flow(), 'administrator request retains original authentication factors' );
    phone_check( $base_skip === get_option( 'dig_reg_skip_otp_verification', 0 ), 'administrator request retains original verification policy' );
} else {
    // Native nonce-protected endpoint tests use only synthetic OTP rows; no send action.
    // Model an installation that previously skipped verification without writing it.
    foreach ( array( 'option_', 'default_option_' ) as $prefix ) {
        add_filter( $prefix . 'dig_reg_skip_otp_verification', static function () { return 1; }, 1 );
    }
    phone_option_override( 'defaultuserrole', 'subscriber' );
    phone_option_override( 'dig_enable_registration', 'blocked_registration' === $mode ? 0 : 1 );
    $phone = '9120000119';
    $country = '+98';
    $otp = '012345';
    $table = $wpdb->prefix . 'digits_otp';
    $phone_existing = getUserFromPhone( $country . $phone );
    if ( $phone_existing ) {
        if ( 0 !== strpos( $phone_existing->user_login, 'synthetic_' ) ) {
            throw new RuntimeException( 'Discard the fixture: the reserved synthetic phone belongs to an unexpected existing account.' );
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $phone_existing->ID );
    }
    $wpdb->delete( $table, array( 'phone' => $phone, 'action_type' => 'sms_otp' ) );
    $data = array( 'type' => 'register', 'action_type' => 'phone', 'digt_countrycode' => $country, 'phone' => $phone, 'digits_process_register' => 1, 'otp_step_1' => 1, 'digits_otp_field' => 1, 'sms_otp' => $otp );
    $bad_nonce = phone_request( $data, false );
    phone_check( false === $bad_nonce['success'], 'native endpoint rejects a bad nonce' );
    if ( 'blocked_registration' === $mode ) {
        $result = phone_request( $data );
        phone_check( false === $result['success'], 'phone-only mode respects disabled native registration' );
        phone_check( ! getUserFromPhone( $country . $phone ), 'disabled signup creates no account' );
        $single = array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'sms_otp' => $otp );
        $blocked = phone_request( $single );
        phone_check( false === $blocked['success'] && ! getUserFromPhone( $country . $phone ) && ! is_user_logged_in(), 'the unified login journey also respects disabled registration' );
        phone_option_override( 'dig_enable_registration', 1 );
        phone_option_override( 'defaultuserrole', 'administrator' );
        $privileged_signup = phone_request( $single );
        phone_check( false === $privileged_signup['success'] && ! getUserFromPhone( $country . $phone ), 'unified checkout cannot create a privileged default registration role' );
    } elseif ( 'signup' === $mode ) {
        $data = array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone );
        $unknown = phone_request( $data );
        phone_check( true === $unknown['success'] && ! empty( $unknown['data']['html'] ), 'unknown phone continues directly to the native OTP step' );
        $step = phone_dom( $unknown['data']['html'] );
        phone_check( 1 === $step->query( '//input[@name="sms_otp"]' )->length && 0 === $step->query( '//input[@type="password"]|//a[contains(@class,"show_register")]' )->length, 'unknown phone needs only OTP without a Register choice' );
        phone_check( ! getUserFromPhone( $country . $phone ), 'phone entry alone creates no account' );
        phone_check( ! is_user_logged_in(), 'phone entry alone creates no login session' );
        $mandatory = static function ( $errors ) { $errors->add( 'synthetic_required', 'Synthetic mandatory registration policy' ); return $errors; };
        add_filter( 'digits_registration_errors', $mandatory );
        $required_result = phone_request( $data );
        phone_check( false === $required_result['success'] && ! getUserFromPhone( $country . $phone ), 'custom mandatory registration policy still blocks creation' );
        remove_filter( 'digits_registration_errors', $mandatory );
        $poll = $data;
        $poll['check_status'] = 1;
        $poll_result = phone_request( $poll );
        phone_check( false === $poll_result['success'] && ! getUserFromPhone( $country . $phone ), 'a polling flag cannot bypass the initial signup checks' );
        // Native OTP generation/resend, with the native debug transport switch:
        // no gateway is called and the network namespace remains isolated.
        add_filter( 'digits_debug', '__return_true' );
        $send = $data;
        $send['sub_action'] = 'sms_otp';
        $wpdb->delete( $wpdb->prefix . 'digits_request_logs', array( 'phone' => $country . $phone ) );
        $first_send = phone_request( $send );
        $send['otp_resend'] = 1;
        $resend = phone_request( $send );
        phone_check( true === $first_send['success'] && isset( $first_send['data']['resend_timer'] ), 'native OTP step retains its resend timer without contacting a provider' );
        phone_check( ! getUserFromPhone( $country . $phone ) && ! is_user_logged_in(), 'initial OTP request and resend create no account or login session' );
        phone_check( ( true === $resend['success'] && isset( $resend['data']['resend_timer'] ) ) ||
            ( false === $resend['success'] && ! empty( $resend['data']['message'] ) ), 'resend returns the native timer or rate-limit message in the same form' );
        remove_filter( 'digits_debug', '__return_true' );
        $wpdb->delete( $table, array( 'phone' => $phone, 'action_type' => 'sms_otp' ) );
        $data['sms_otp'] = $otp;
        $data['digits_otp_field'] = 1;
        $wrong = phone_request( $data );
        phone_check( false === $wrong['success'] && ! getUserFromPhone( $country . $phone ), 'missing or wrong OTP cannot register an account' );
        phone_check( ! is_user_logged_in(), 'invalid OTP creates no login session' );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), 'action_type' => 'sms_otp' ) );
        $expired = phone_request( $data );
        phone_check( false === $expired['success'] && ! getUserFromPhone( $country . $phone ) && ! is_user_logged_in(), 'expired OTP creates neither account nor login' );
        $wpdb->delete( $table, array( 'phone' => $phone, 'action_type' => 'sms_otp' ) );
        $lock_key = '_ava_digits_phone_' . hash_hmac( 'sha256', $country . $phone, wp_salt( 'nonce' ) );
        add_option( $lock_key, (string) time(), '', false );
        $busy = phone_request( $data );
        phone_check( false === $busy['success'] && ! getUserFromPhone( $country . $phone ), 'overlapping request cannot create a duplicate account' );
        delete_option( $lock_key );
        add_option( $lock_key, (string) ( time() - 601 ), '', false );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s' ), 'action_type' => 'sms_otp' ) );
        phone_seed_cart();
        phone_option_override( 'default_role', 'administrator' );
        $initial_roles = array();
        add_action( 'user_register', static function ( $id ) use ( &$initial_roles ) { $initial_roles = get_userdata( $id )->roles; } );
        $result = phone_request( $data );
        $user = getUserFromPhone( $country . $phone );
        phone_check( true === $result['success'] && $user instanceof WP_User, 'native signup verifies the synthetic OTP and creates the phone account' );
        phone_check( false === get_option( $lock_key, false ), 'a stale crashed-request lock recovers and is released after verification' );
        phone_check( array( 'subscriber' ) === $user->roles && '' === $user->user_email, 'phone-only signup assigns an unprivileged account without requiring email' );
        phone_check( array( 'subscriber' ) === $initial_roles, 'account is already ordinary during native user_register even with an elevated WordPress default' );
        phone_check( ! get_user_meta( $user->ID, 'digits_phone_verification_skipped', true ), 'new account phone verification has not been skipped' );
        phone_check( $fixture['checkout_url'] === $result['data']['redirect'], 'verified native signup returns to checkout' );
        phone_check( is_user_logged_in() && get_current_user_id() === $user->ID, 'verified native signup authenticates its own account' );
        phone_check( isset( phone_stored_cart( $user->ID )['phone-guest-item'] ), 'verified native signup preserves the checkout guest cart' );
        phone_check( '0' === $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM $table WHERE phone=%s AND action_type=%s", $phone, 'sms_otp' ) ), 'native signup consumes the verified OTP' );
        $replay = $data;
        $replay['digits_step_1_type'] = 'sms_otp';
        $replay['digits_step_1_value'] = $otp;
        $duplicate = phone_request( $replay );
        phone_check( false === $duplicate['success'] && ! is_user_logged_in(), 'replayed signup OTP cannot authenticate the newly existing account' );
        phone_check( (int) $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->usermeta} WHERE meta_key='digits_phone' AND meta_value=%s", $country . $phone ) ) === 1, 'repeated submission leaves exactly one phone account' );
        phone_check( false === get_option( $lock_key, false ), 'native JSON termination releases the phone lock' );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s' ), 'action_type' => 'sms_otp', 'ref_id' => $user->ID ) );
        $returning = phone_request( $replay );
        phone_check( true === $returning['success'] && get_current_user_id() === $user->ID, 'the same single form logs into the new account on its next valid OTP' );
        // Clean this mode's synthetic account; login mode creates its own fixture.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user->ID );
    } elseif ( 'security' === $mode ) {
        $privileged_password = wp_generate_password( 32 );
        $id = wp_create_user( 'synthetic_privileged_' . wp_generate_password( 8, false ), $privileged_password, '' );
        phone_check( ! is_wp_error( $id ), 'synthetic privileged policy target created' );
        $user = new WP_User( $id );
        $user->set_role( 'administrator' );
        digits_update_mobile( $id, $country, $phone );
        $policy = array( '1fa' => array( 'enable' => true, 'methods' => array( 'password' ) ), '2fa' => array( 'enable' => false, 'methods' => array() ), '3fa' => array( 'enable' => false, 'methods' => array() ) );
        phone_option_override( 'digits_auth_flow', wp_json_encode( $policy ) );
        phone_option_override( 'digits_user_based_flow_enable', 0 );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s' ), 'action_type' => 'sms_otp', 'ref_id' => $id ) );
        $result = phone_request( array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'digits_step_1_type' => 'sms_otp', 'digits_step_1_value' => $otp, 'sms_otp' => $otp ) );
        phone_check( false === $result['success'] && ! is_user_logged_in(), 'spoofed checkout referer cannot replace a privileged password-only policy with SMS login' );
        $password_login = phone_request( array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'digits_step_1_type' => 'password', 'digits_step_1_value' => $privileged_password, 'password' => $privileged_password ) );
        phone_check( true === $password_login['success'] && get_current_user_id() === $id, 'selected administrator can still use the original permitted password factor' );
        $factors = $policy;
        $factors['2fa'] = array( 'enable' => true, 'methods' => array( '2fa_app' ) );
        $factors['3fa'] = array( 'enable' => true, 'methods' => array( 'platform' ) );
        $admin_flow = apply_filters( 'digits_user_login_flow', $factors, $id );
        $customer_flow = apply_filters( 'digits_user_login_flow', $factors, $fixture['subscriber_id'] );
        phone_check( $admin_flow === $factors, 'selected administrator retains all original factor policies in checkout' );
        phone_check( $customer_flow['1fa']['methods'] === array( 'sms_otp' ), 'selected customer uses native SMS as primary factor' );
        phone_check( $customer_flow['2fa'] === $factors['2fa'] && $customer_flow['3fa'] === $factors['3fa'], 'customer enrolled second and third factors remain unchanged' );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $id );
        foreach ( array( 'install_plugins', 'update_plugins' ) as $extra_capability ) {
            $direct_password = wp_generate_password( 32 );
            $direct_id = wp_create_user( 'synthetic_directcap_' . wp_generate_password( 8, false ), $direct_password, '' );
            phone_check( ! is_wp_error( $direct_id ), 'synthetic customer with a direct privileged capability created' );
            $direct_user = new WP_User( $direct_id );
            $direct_user->set_role( 'subscriber' );
            $direct_user->add_cap( $extra_capability, true );
            digits_update_mobile( $direct_id, $country, $phone );
            $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s' ), 'action_type' => 'sms_otp', 'ref_id' => $direct_id ) );
            $direct_sms = phone_request( array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'digits_step_1_type' => 'sms_otp', 'digits_step_1_value' => $otp, 'sms_otp' => $otp ) );
            phone_check( false === $direct_sms['success'] && ! is_user_logged_in(), 'forged checkout referer cannot add SMS to a subscriber with ' . $extra_capability );
            $direct_login = phone_request( array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'digits_step_1_type' => 'password', 'digits_step_1_value' => $direct_password, 'password' => $direct_password ) );
            phone_check( true === $direct_login['success'] && get_current_user_id() === $direct_id, 'subscriber with ' . $extra_capability . ' retains the permitted password factor' );
            wp_delete_user( $direct_id );
        }
        $reset_password = wp_generate_password( 32 );
        $reset_id = wp_create_user( 'synthetic_reset_' . wp_generate_password( 8, false ), $reset_password, 'reset@phone-fixture.invalid' );
        phone_check( ! is_wp_error( $reset_id ), 'synthetic privileged reset policy target created' );
        $reset_user = new WP_User( $reset_id );
        $reset_user->set_role( 'administrator' );
        digits_update_mobile( $reset_id, $country, $phone );
        $reset_fields = array( 'dig_login_username' => 1, 'dig_login_email' => 1, 'dig_login_mobilenumber' => 0, 'dig_login_otp' => 0, 'dig_login_password' => 1, 'dig_login_captcha' => 0, 'dig_login_recaptcha' => 0 );
        foreach ( array( 'option_', 'default_option_' ) as $prefix ) {
            add_filter( $prefix . 'dig_login_fields', static function () use ( $reset_fields ) { return $reset_fields; }, 1 );
        }
        $password_hash = $wpdb->get_var( $wpdb->prepare( "SELECT user_pass FROM {$wpdb->users} WHERE ID=%d", $reset_id ) );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s' ), 'action_type' => 'sms_otp', 'ref_id' => $reset_id ) );
        $reset = phone_request( array( 'type' => 'forgot', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'sms_otp' => $otp, 'password' => wp_generate_password( 32 ) ) );
        phone_check( 0 === (int) digit_get_login_fields()['dig_login_mobilenumber'], 'forged checkout referer cannot add SMS to the original forgot-password policy' );
        phone_check( false === $reset['success'] && ! is_user_logged_in() && $password_hash === $wpdb->get_var( $wpdb->prepare( "SELECT user_pass FROM {$wpdb->users} WHERE ID=%d", $reset_id ) ), 'forged SMS forgot-password request cannot reset or authenticate a protected account' );
        $unknown_type = phone_request( array( 'type' => 'synthetic-unknown-type', 'action_type' => 'phone', 'digits_phone' => $phone ) );
        phone_check( false === $unknown_type['success'] && 0 === (int) digit_get_login_fields()['dig_login_mobilenumber'], 'unknown AJAX form type retains original native phone policy' );
        wp_delete_user( $reset_id );
    } elseif ( 'ajax' === $mode ) {
        $id = wp_create_user( 'synthetic_phone_' . wp_generate_password( 8, false ), wp_generate_password( 32 ), '' );
        phone_check( ! is_wp_error( $id ), 'synthetic existing phone account created' );
        $user = new WP_User( $id );
        $user->set_role( 'subscriber' );
        digits_update_mobile( $id, $country, $phone );
        $login = array( 'type' => 'login', 'action_type' => 'phone', 'login_digt_countrycode' => $country, 'digits_phone' => $phone, 'digits_step_1_type' => 'sms_otp', 'digits_step_1_value' => $otp, 'sms_otp' => $otp );
        $initial = $login;
        $initial['digits_step_1_type'] = '';
        $initial['digits_step_1_value'] = '';
        unset( $initial['sms_otp'] );
        $next = phone_request( $initial );
        phone_check( true === $next['success'] && ! empty( $next['data']['html'] ), 'native existing-phone first step returns the OTP interface' );
        $step = phone_dom( $next['data']['html'] );
        phone_check( 0 === $step->query( '//input[@type="password"]' )->length && 1 === $step->query( '//input[@name="sms_otp"]' )->length, 'native next authentication step asks for SMS OTP without password' );
        $wrong = phone_request( $login );
        phone_check( false === $wrong['success'] && ! is_user_logged_in(), 'existing phone account rejects an unissued or wrong OTP' );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), 'action_type' => 'sms_otp', 'ref_id' => $id ) );
        $expired = phone_request( $login );
        phone_check( false === $expired['success'] && ! is_user_logged_in(), 'existing account rejects an expired OTP without a login session' );
        $wpdb->delete( $table, array( 'phone' => $phone, 'action_type' => 'sms_otp' ) );
        $wpdb->insert( $table, array( 'countrycode' => '98', 'phone' => $phone, 'otp' => $otp, 'time' => gmdate( 'Y-m-d H:i:s' ), 'action_type' => 'sms_otp', 'ref_id' => $id ) );
        phone_seed_cart( $id );
        $result = phone_request( $login );
        phone_check( true === $result['success'] && get_current_user_id() === $id, 'native phone-only existing-account OTP login succeeds' );
        phone_check( $fixture['checkout_url'] === $result['data']['redirect'], 'verified native phone login returns to checkout' );
        $cart = phone_stored_cart( $id );
        phone_check( isset( $cart['phone-guest-item'], $cart['phone-existing-item'] ), 'verified phone login preserves both guest and existing account carts' );
        phone_check( '0' === $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM $table WHERE ref_id=%d AND action_type=%s", $id, 'sms_otp' ) ), 'native login consumes its OTP' );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $id );
    }
}
phone_check( $original_native === phone_native_snapshot(), 'all original native Digits and registration options remain byte-for-byte unchanged' );
phone_check( empty( $GLOBALS['qa_direct_network_blocked'] ) && empty( $GLOBALS['qa_http_blocked'] ), 'native phone-only tests attempted no external transport' );
echo 'PASS phone-only ' . $mode . ': ' . $checks . ' native checks; no real messages or orders.' . PHP_EOL;
