<?php
/** Synthetic wallet API and both administrator surfaces; HTTP remains mocked. */
use AvaDigits\Settings;
use AvaDigits\Wallet;
use AvaDigits\Admin;

$GLOBALS['transients'] = array();
$GLOBALS['options'][ Settings::OPTION ] = Settings::defaults();
$before = calls();
check( is_wp_error( Wallet::get() ), 'wallet needs saved valid credentials' );
same( calls(), $before, 'missing wallet key never calls HTTP' );
$GLOBALS['options'][ Settings::OPTION ] = array_merge( configured(), array( 'enabled' => 'no' ) );
$wallet = array( 'balance_irr' => 123450, 'held_irr' => 3450, 'available_irr' => 120000, 'currency' => 'IRR' );
$GLOBALS['next_http'] = response( 200, json_encode( array_merge( $wallet, array( 'private' => 'must-not-cache' ) ) ) );
same( Wallet::get(), $wallet, 'wallet is available while sending is disabled and removes unknown fields' );
$request = end( $GLOBALS['http'] );
same( $request[0], configured()['api_url'] . '/api/v1/wallet', 'wallet reads the account endpoint' );
same( $request[1]['method'], 'GET', 'wallet transport is only GET' );
same( $request[1]['headers']['Authorization'], 'Bearer ' . configured()['api_key'], 'wallet reuses the saved key' );
same( $request[1]['timeout'], 5, 'wallet request has short timeout' );
same( $request[1]['redirection'], 0, 'wallet never follows credential redirects' );
same( $request[1]['sslverify'], true, 'wallet verifies TLS' );
same( $request[1]['reject_unsafe_urls'], true, 'wallet uses WordPress unsafe URL guard' );
same( $request[1]['limit_response_size'], 16384, 'wallet response is bounded' );
check( ! isset( $request[1]['body'], $request[1]['headers']['Idempotency-Key'] ), 'wallet has no send payload or send idempotency key' );
$before = calls();
same( Wallet::get(), $wallet, 'wallet cached snapshot reused' );
same( calls(), $before, 'wallet cache prevents another HTTP request' );
$entry = reset( $GLOBALS['transients'] );
same( $entry[1] - $GLOBALS['wallet_clock'], 60, 'wallet cache lives for sixty seconds' );
check( false === strpos( json_encode( $GLOBALS['transients'] ), configured()['api_key'] ), 'cache key and data never expose saved key' );
check( false === strpos( json_encode( $GLOBALS['transients'] ), 'must-not-cache' ), 'cache stores only the validated balance fields' );

$GLOBALS['options'][ Settings::OPTION ]['api_key'] = 'ava_live_' . str_repeat( 'b', 24 );
$second = array( 'balance_irr' => 800, 'held_irr' => 0, 'available_irr' => 800, 'currency' => 'IRR' );
$GLOBALS['next_http'] = response( 200, json_encode( $second ) );
same( Wallet::get(), $second, 'changed key cannot reuse a previous account balance' );
same( calls(), $before + 1, 'changed key makes a fresh read' );
$GLOBALS['options'][ Settings::OPTION ]['api_url'] = 'https://other-panel.example';
$third = array( 'balance_irr' => -500, 'held_irr' => 100, 'available_irr' => -600, 'currency' => 'IRR' );
$GLOBALS['next_http'] = response( 200, json_encode( $third ) );
same( Wallet::get(), $third, 'signed negative balances are preserved on a changed panel' );
same( calls(), $before + 2, 'changed origin makes a fresh read' );
$GLOBALS['options'][ Settings::OPTION ]['api_key'] = '';
check( is_wp_error( Wallet::get() ), 'deleted key cannot reveal an earlier cached snapshot' );
same( calls(), $before + 2, 'deleted key blocks HTTP before cache lookup' );
$GLOBALS['options'][ Settings::OPTION ] = configured();
$GLOBALS['wallet_clock'] += 61;
$GLOBALS['next_http'] = response( 200, json_encode( $second ) );
same( Wallet::get(), $second, 'expired snapshots are fetched again' );
same( calls(), $before + 3, 'expired cache causes exactly one read' );

foreach ( array( 301, 401, 403, 404, 429, 500 ) as $status ) {
    $GLOBALS['next_http'] = response( $status, '{"error":"private-wallet-message","key":"secret"}' );
    $before = calls();
    $error = Wallet::get( true );
    check( is_wp_error( $error ), 'wallet non-200 response is an explicit error' );
    check( false === strpos( $error->get_error_message(), 'private-wallet-message' ), 'wallet never reflects upstream error content' );
    same( calls(), $before + 1, 'wallet error never retries automatically' );
    check( is_wp_error( Wallet::get() ), 'failed refresh removes a previously cached balance' );
}
$GLOBALS['next_http'] = new WP_Error( 'timeout', 'private-network-diagnostic' );
$error = Wallet::get( true );
same( $error->get_error_code(), 'ava_wallet_network', 'wallet network error classified' );
check( false === strpos( $error->get_error_message(), 'private-network-diagnostic' ), 'wallet network detail stays private' );
$invalid = array(
    '{"balance_irr":100,"held_irr":0,"available_irr":100}',
    '{"balance_irr":"100","held_irr":0,"available_irr":100,"currency":"IRR"}',
    '{"balance_irr":100.0,"held_irr":0,"available_irr":100,"currency":"IRR"}',
    '{"balance_irr":true,"held_irr":0,"available_irr":1,"currency":"IRR"}',
    '{"balance_irr":100,"held_irr":-1,"available_irr":101,"currency":"IRR"}',
    '{"balance_irr":100,"held_irr":50,"available_irr":100,"currency":"IRR"}',
    '{"balance_irr":100,"held_irr":0,"available_irr":100,"currency":"IRT"}',
    '{"balance_irr":100,"held_irr":null,"available_irr":100,"currency":"IRR"}',
    '{"balance_irr":1e40,"held_irr":0,"available_irr":1e40,"currency":"IRR"}',
    'null', 'not-json',
    json_encode( array( 'balance_irr' => PHP_INT_MIN, 'held_irr' => 1, 'available_irr' => PHP_INT_MIN, 'currency' => 'IRR' ) ),
);
foreach ( $invalid as $body ) {
    $GLOBALS['next_http'] = response( 200, $body );
    $error = Wallet::get( true );
    check( is_wp_error( $error ) && 'ava_wallet_response' === $error->get_error_code(), 'malformed, rounded, wrong-currency or inconsistent money is rejected' );
}
$GLOBALS['next_http'] = response( 200, '{"balance_irr":0,"held_irr":0,"available_irr":0,"currency":"IRR"}' );
same( Wallet::get( true )['available_irr'], 0, 'a genuine zero wallet balance is accepted' );

$GLOBALS['next_http'] = response( 200, json_encode( $wallet ) );
Wallet::get( true );
ob_start(); Admin::page(); $html = ob_get_clean();
check( false !== strpos( $html, 'ماندهٔ قابل مصرف:' ) && false !== strpos( $html, '>120,000</strong>' ), 'standalone settings emphasizes available balance' );
check( false !== strpos( $html, '123,450' ) && false !== strpos( $html, '3,450' ) && false !== strpos( $html, 'ریال' ), 'standalone settings shows total and reserved amounts in rials' );
check( false !== strpos( $html, configured()['api_url'] . '/panel#wallet' ), 'wallet recharge link uses the selected panel origin' );
check( false !== strpos( $html, 'data-wallet-nonce=' ) && false !== strpos( $html, 'ava-digits-wallet-refresh' ), 'wallet has a nonce-protected refresh button' );
check( false === strpos( $html, configured()['api_key'] ), 'wallet settings HTML keeps key private' );
ob_start(); Admin::native_panel(); $html = ob_get_clean();
check( false !== strpos( $html, '>120,000</strong>' ), 'native Digits settings renders the same wallet snapshot' );
check( false === strpos( $html, '<form' ), 'wallet does not introduce nested native forms' );
check( isset( $GLOBALS['filters']['wp_ajax_ava_digits_refresh_wallet'] ) && ! isset( $GLOBALS['filters']['wp_ajax_nopriv_ava_digits_refresh_wallet'] ), 'wallet refresh registered only for authenticated users' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'nonce' => wp_create_nonce( 'ava_digits_refresh_wallet' ) );
$GLOBALS['can_manage'] = false;
$before = calls();
check( is_wp_error( Wallet::get() ), 'wallet reader denies non-administrators even with cached data' );
ob_start(); Admin::page(); Admin::native_panel(); $html = ob_get_clean();
same( $html, '', 'both wallet surfaces deny non-administrators' );
try { Admin::refresh_wallet(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['status'], 403, 'wallet refresh requires manage_options' );
same( calls(), $before, 'unauthorized reader and refresh never call the provider' );
$GLOBALS['can_manage'] = true;
foreach ( array( array(), array( 'nonce' => 'bad' ), array( 'nonce' => array() ) ) as $post ) {
    $_POST = $post;
    try { Admin::refresh_wallet(); } catch ( RuntimeException $e ) {}
    same( $GLOBALS['json_reply']['status'], 403, 'wallet refresh denies missing, invalid or non-string nonce' );
}
same( calls(), $before, 'nonce rejection never calls the provider' );
$_POST = array( 'nonce' => wp_create_nonce( 'ava_digits_refresh_wallet' ) );
$_SERVER['REQUEST_METHOD'] = 'GET';
try { Admin::refresh_wallet(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['status'], 405, 'browser refresh endpoint requires POST while provider stays GET' );
same( calls(), $before, 'invalid refresh method performs no provider request' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['next_http'] = response( 200, json_encode( $third ) );
try { Admin::refresh_wallet(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['success'], true, 'authorized refresh succeeds' );
same( calls(), $before + 1, 'explicit refresh bypasses the snapshot cache exactly once' );
check( false !== strpos( $GLOBALS['json_reply']['data']['html'], '>-600</strong>' ), 'signed wallet values remain exact in refresh HTML' );
check( false === strpos( json_encode( $GLOBALS['json_reply'] ), configured()['api_key'] ), 'refresh JSON excludes credentials' );
$GLOBALS['next_http'] = response( 500, '{"error":"private-wallet-message"}' );
try { Admin::refresh_wallet(); } catch ( RuntimeException $e ) {}
same( $GLOBALS['json_reply']['success'], false, 'failed refresh returns an explicit error' );
same( array_keys( $GLOBALS['json_reply']['data'] ), array( 'message' ), 'failed refresh returns no fabricated money value' );
ob_start(); Admin::native_panel(); $html = ob_get_clean();
check( false !== strpos( $html, 'ava-digits-wallet-error' ) && false === strpos( $html, 'ava-digits-wallet-available' ), 'failed wallet read shows an error instead of old amounts or zero' );
check( false === strpos( $html, 'private-wallet-message' ), 'wallet UI never exposes raw upstream error text' );
$GLOBALS['options'][ Settings::OPTION ] = configured();
$GLOBALS['transients'] = array();
$_POST = array();
