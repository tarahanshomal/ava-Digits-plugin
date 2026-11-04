<?php
/**
 * Optional real WordPress smoke. Run only against a disposable local installation.
 * AVA_DIGITS_RUN_INTEGRATION=1 wp --path=/absolute/disposable-wordpress eval-file /absolute/plugin-source/tests/integration-smoke.php
 * Digits filter inputs are simulated; no commercial Digits package is bundled.
 */
if (!defined('WP_CLI') || !WP_CLI || getenv('AVA_DIGITS_RUN_INTEGRATION') !== '1') {
    throw new RuntimeException('Requires WP-CLI and AVA_DIGITS_RUN_INTEGRATION=1.');
}
$home_host = wp_parse_url(home_url(), PHP_URL_HOST);
$site_host = wp_parse_url(site_url(), PHP_URL_HOST);
if (wp_get_environment_type() !== 'local' || !defined('DISABLE_WP_CRON') || DISABLE_WP_CRON !== true ||
    !is_string($home_host) || substr($home_host, -8) !== '.invalid' ||
    !is_string($site_host) || substr($site_host, -8) !== '.invalid') {
    WP_CLI::error('Use a disposable local WordPress with .invalid home/site domains and DISABLE_WP_CRON=true.');
}
$plugin_file = 'ava-Digits-plugin/ava-digits-plugin.php';
if (!is_file(WP_PLUGIN_DIR . '/' . $plugin_file) || defined('AVA_DIGITS_API_KEY')) {
    WP_CLI::error('Install the Ava plugin and remove AVA_DIGITS_API_KEY from this disposable fixture.');
}
$original_settings = get_option('ava_digits_settings', false);
if (is_array($original_settings) && (!empty($original_settings['api_key']) || ($original_settings['enabled'] ?? 'no') === 'yes')) {
    WP_CLI::error('Refusing a configured or enabled Ava installation. Use a disposable empty fixture.');
}
require_once ABSPATH . 'wp-admin/includes/admin.php';
$checks = 0;
$labels = array();
$created_users = array();
$original_user = get_current_user_id();
$original_screen = isset($GLOBALS['current_screen']) ? $GLOBALS['current_screen'] : null;
$original_active = is_plugin_active($plugin_file);
$original_blogname = get_option('blogname');
$suffix = substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);
$requests = array();
$mode = 'blocked';
$failure = null;
$check = static function ($condition, $label) use (&$checks, &$labels) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    $labels[] = 'PASS: ' . $label;
};
$send = static function ($mobile, $code, $test = false, $country = '+98', $id = 714032) {
    return apply_filters('unitedover_send_sms', false, 'digits', $id, $country, $mobile, $code, $test, null);
};
// Always preempt transport. Only the synthetic Ava endpoint gets a mock response.
$mock_http = static function ($pre, $args, $url) use (&$requests, &$mode) {
    if ($mode === 'blocked' || $url !== 'https://ava-fixture.invalid/api/v1/sms-otp') {
        return new WP_Error('ava_integration_offline', 'External HTTP disabled by integration smoke.');
    }
    $requests[] = array('url'=>$url, 'args'=>$args);
    if ($mode === 'timeout') return new WP_Error('http_request_failed', 'synthetic timeout');
    if ($mode === 'rejected') return array('headers'=>array(), 'body'=>'{"error":"<script>012345 ava_live_PRIVATE</script>"}', 'response'=>array('code'=>403, 'message'=>'Forbidden'), 'cookies'=>array());
    return array('headers'=>array(), 'body'=>wp_json_encode(array('id'=>str_repeat('b', 32), 'status'=>$mode === 'sent' ? 'sent' : 'pending')), 'response'=>array('code'=>202, 'message'=>'Accepted'), 'cookies'=>array());
};
add_filter('pre_http_request', $mock_http, PHP_INT_MAX, 3);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
try {
$check(defined('WP_CLI') && WP_CLI && getenv('AVA_DIGITS_RUN_INTEGRATION') === '1', 'explicit WP-CLI integration opt-in');
$check(wp_get_environment_type() === 'local' && DISABLE_WP_CRON, 'local environment and disabled cron guard');
$check(substr(wp_parse_url(home_url(), PHP_URL_HOST), -8) === '.invalid' && substr(wp_parse_url(site_url(), PHP_URL_HOST), -8) === '.invalid', 'synthetic home and site domains');
$check(is_wp_error(wp_remote_get('https://example.invalid/blocked')), 'runtime external HTTP blocked');
$check(wp_mail('nobody@example.invalid','synthetic','no delivery') === true, 'runtime mail preempted');
deactivate_plugins('ava-Digits-plugin/ava-digits-plugin.php');
delete_option('ava_digits_settings');
$result = activate_plugin('ava-Digits-plugin/ava-digits-plugin.php');
$check(!is_wp_error($result), 'plugin activates under real WordPress');
$check(is_plugin_active('ava-Digits-plugin/ava-digits-plugin.php'), 'active plugin recorded in real options');
$check(\AvaDigits\Settings::get()['enabled'] === 'no', 'activation defaults to disabled');
$admin_id = wp_insert_user(array('user_login'=>'ava_digits_smoke_admin_' . $suffix, 'user_pass'=>wp_generate_password(32), 'user_email'=>'ava-digits-admin-' . $suffix . '@example.invalid', 'role'=>'administrator'));
if (is_wp_error($admin_id)) throw new RuntimeException('Unable to create synthetic administrator');
$created_users[] = $admin_id;
wp_set_current_user($admin_id);
$check(current_user_can('manage_options'), 'synthetic administrator capabilities available');
set_current_screen('settings_page_ava-digits');
if (!has_action('admin_init', array('AvaDigits\Admin', 'register'))) \AvaDigits\Admin::init();
remove_action('admin_init', '_maybe_update_core');
remove_action('admin_init', '_maybe_update_plugins');
remove_action('admin_init', '_maybe_update_themes');
do_action('admin_init');
$check(isset($GLOBALS['wp_registered_settings']['ava_digits_settings']), 'WordPress setting registered');
$check($GLOBALS['wp_registered_settings']['ava_digits_settings']['show_in_rest'] === false, 'credentials excluded from REST settings');
$gateway = apply_filters('digits_sms_gateways', array('existing'=>array('value'=>99,'label'=>'Existing')));
$check(isset($gateway['ava_digits']) && $gateway['ava_digits']['value'] === 714032, 'named Ava gateway registered with numeric ID');
$conflicting = apply_filters('digits_sms_gateways', array('other'=>array('value'=>714032, 'label'=>'Other')));
$conflict_result = apply_filters('unitedover_send_sms', 'collision-preserved', 'digits', 714032, '+98', '9120000001', '012345', false, null);
$check(isset($gateway['existing']) && !isset($conflicting['ava_digits']) && $conflict_result === 'collision-preserved', 'existing and conflicting gateway routes preserved');
apply_filters('digits_sms_gateways', array());
$fixture = array('enabled'=>'yes','api_url'=>'https://ava-fixture.invalid','api_key'=>'ava_live_' . str_repeat('x',32),'template_id'=>str_repeat('a',32),'brand'=>'نمونه');
update_option('ava_digits_settings', $fixture);
$check(\AvaDigits\Settings::get()['api_key'] === $fixture['api_key'], 'settings persist in real WordPress database');
$blank = $fixture; $blank['api_key']='';
update_option('ava_digits_settings', $blank);
$check(\AvaDigits\Settings::get()['api_key'] === $fixture['api_key'], 'blank key retains stored key via registered sanitizer');
$invalid=$blank; $invalid['api_url']='http://127.0.0.1'; $invalid['template_id']='bad/<script>';
update_option('ava_digits_settings', $invalid);
$check(\AvaDigits\Settings::get()['api_url'] === $fixture['api_url'] && \AvaDigits\Settings::get()['template_id'] === $fixture['template_id'], 'invalid endpoint/template cannot replace stored valid settings');
$check(count(get_settings_errors('ava_digits_settings')) >= 2, 'invalid settings create WordPress error notices');
update_option('ava_digits_settings', $fixture);
ob_start(); \AvaDigits\Admin::page(); $html=ob_get_clean();
$check(strpos($html,$fixture['api_key'])===false, 'admin form never redisplays stored key');
$check(strpos($html,'name="_wpnonce"')!==false, 'admin settings form includes WordPress nonce');
$check(strpos($html, 'ava_digits')!==false && strpos($html, 'option_page')!==false, 'form uses registered WordPress settings group');
$check(strpos($html,'options.php')!==false, 'settings submit through WordPress permission and nonce handler');
$mode='success';
$check($send('۹۱۲۰۰۰۰۰۰۱','۰۱۲۳۴۵')===true, 'real filter dispatch accepts Persian phone/code via mocked Ava API');
$check(count($requests)===1, 'one HTTP request for admitted send');
$args=$requests[0]['args']; $payload=json_decode($args['body'],true);
$check($requests[0]['url']==='https://ava-fixture.invalid/api/v1/sms-otp', 'exact OTP endpoint used');
$check($payload['phone']==='09120000001' && $payload['code']==='012345' && $payload['template_id']===$fixture['template_id'], 'correct API JSON and leading zero preserved');
$check($args['headers']['Authorization']==='Bearer '.$fixture['api_key'] && preg_match('/^[A-Za-z0-9_-]{16,100}$/D',$args['headers']['Idempotency-Key']), 'Bearer auth and valid idempotency header applied');
$check($args['sslverify']===true && $args['redirection']===0 && $args['reject_unsafe_urls']===true, 'HTTPS validation and redirect restrictions passed to real HTTP API');
$check($send('9120000001','012345')===true && count($requests)===1, 'same request duplicate is memoized');
$check($send('9120000002','123456',false,'+1')===false && count($requests)===1, 'foreign country fails before HTTP');
$check($send('9120000002','رمز 123456')===false && count($requests)===1, 'free-form message fails before HTTP');
$check(apply_filters('unitedover_send_sms','previous-result','digits',99,'+98','9120000002','123456',false,null)==='previous-result' && count($requests)===1, 'other gateways untouched');
$mode='sent';
$check($send('9120000003','234567')===true, 'already sent provider response treated as accepted');
$mode='timeout';
$check($send('9120000004','345678')===false, 'uncertain transport fails without automatic retry');
$before=count($requests); $send('9120000004','345678');
$check(count($requests)===$before, 'uncertain duplicate cannot retry within request');
$mode='rejected';
$test=$send('9120000005','456789',true);
$check(is_string($test) && isset(json_decode($test,true)['error']), 'administrator test yields structured safe error string');
$check(strpos($test,'ava_live_PRIVATE')===false && strpos($test,'<script>')===false && strpos($test,'456789')===false, 'test error hides upstream reflection and OTP');
$mode='success';
$test=$send('9120000006','567890',true);
$decoded=json_decode($test,true);
$check(is_string($test) && $decoded['status']==='pending' && isset($decoded['id']), 'administrator test returns safe request status');
$check(strpos($test,'567890')===false && strpos($test,$fixture['api_key'])===false, 'test success never exposes OTP/key');
$subscriber=wp_create_user('ava_digits_smoke_sub_' . $suffix, wp_generate_password(32), 'ava-digits-sub-' . $suffix . '@example.invalid');
if (is_wp_error($subscriber)) throw new RuntimeException('Unable to create synthetic subscriber');
$created_users[] = $subscriber;
wp_set_current_user($subscriber); $before=count($requests);
$check($send('9120000007','678901',true)===false && count($requests)===$before, 'nonadministrator cannot trigger test callback');
ob_start(); \AvaDigits\Admin::page(); $unauthorized=ob_get_clean();
$check($unauthorized==='', 'nonadministrator cannot render settings page');
wp_set_current_user($admin_id);
$off=$fixture; $off['enabled']='no'; update_option('ava_digits_settings',$off); $before=count($requests);
$check($send('9120000008','789012')===false && count($requests)===$before, 'disabled integration rejects before HTTP');
$deleted=$fixture; $deleted['delete_api_key']='yes'; update_option('ava_digits_settings',$deleted);
$check(\AvaDigits\Settings::get()['api_key']==='', 'explicit delete clears stored credential');
update_option('ava_digits_settings',$fixture);
define('WP_UNINSTALL_PLUGIN','ava-Digits-plugin/ava-digits-plugin.php');
require WP_PLUGIN_DIR . '/ava-Digits-plugin/uninstall.php';
$check(get_option('ava_digits_settings',false)===false, 'uninstall deletes only plugin setting');
$check(get_option('blogname')===$original_blogname, 'uninstall preserves unrelated WordPress settings');
deactivate_plugins('ava-Digits-plugin/ava-digits-plugin.php');
$check(!is_plugin_active('ava-Digits-plugin/ava-digits-plugin.php'), 'plugin deactivates cleanly');

} catch (Throwable $error) {
    $failure = $error;
} finally {
    // Cleanup only synthetic users and restore the pre-smoke blank plugin state.
    wp_set_current_user($original_user);
    $GLOBALS['current_screen'] = $original_screen;
    foreach ($created_users as $created_user) wp_delete_user($created_user);
    remove_filter('sanitize_option_ava_digits_settings', array('AvaDigits\Settings', 'sanitize'));
    if ($original_settings === false) delete_option('ava_digits_settings');
    else update_option('ava_digits_settings', $original_settings);
    if ($original_active) activate_plugin($plugin_file, '', false, true);
    else deactivate_plugins($plugin_file, true);
}
if ($failure) WP_CLI::error($failure->getMessage());
echo implode(PHP_EOL, $labels) . PHP_EOL;
echo 'RESULT: ' . $checks . ' real WordPress checks passed; ' . count($requests) . ' mocked HTTP calls; no real SMS or mail.' . PHP_EOL;
