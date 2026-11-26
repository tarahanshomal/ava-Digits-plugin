# Running the tests

The normal regression suite uses synthetic fixtures and mocked WordPress HTTP. It needs PHP 7.4 or newer and Python 3.9 or newer:

```sh
php tests/run.php
php tests/checkout.php
php tests/checkout-admin.php
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 scripts/package.py
```

The initial release passed **134 behavioral PHP checks** and **3 packaging tests**. No automated test sends real SMS.

## Optional real WordPress smoke

`integration-smoke.php` runs against a real WordPress installation through WP-CLI. Use a **disposable installation containing no production data, real Ava credentials, or unrelated plugins**. Install Ava under `wp-content/plugins/ava-Digits-plugin/`. SQLite Database Integration from WordPress.org can provide an isolated database without a database server; a disposable MySQL installation also works.

Set the WordPress home URL and site URL to a synthetic hostname ending in `.invalid`, for example `https://ava-digits-test.invalid`. In the disposable installation's `wp-config.php`, define:

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISABLE_WP_CRON', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
```

Do not define `AVA_DIGITS_API_KEY`. Leave Ava disabled with no stored API key. The smoke refuses configured or enabled Ava installations, nonlocal environments, real domains, enabled WP-Cron, and missing explicit opt-in. Replace both absolute paths below:

```sh
AVA_DIGITS_RUN_INTEGRATION=1 wp --path=/absolute/disposable-wordpress eval-file /absolute/plugin-source/tests/integration-smoke.php
```

The script preempts every WordPress HTTP request and email after it starts. Only its synthetic Ava endpoint receives generated mock responses; all other HTTP requests return a local error before transport. It activates/deactivates Ava, changes its settings, renders its administrator page, invokes its gateway filters, and exercises uninstall cleanup without deleting plugin files. It creates randomly named synthetic administrator/subscriber users and removes them on completion or caught failure. The original blank Ava settings and activation state are restored. Other WordPress state may change during initialization; discard the installation after testing.

### Recorded validation

On 2026-11-04, the portable smoke passed **45 checks** on **WordPress 7.1.4**, **SQLite Database Integration 3.1.3**, and **PHP 8.5.4**. It intercepted **5 synthetic Ava HTTP requests**, sent no real SMS or email, and left the WordPress debug log empty. Coverage includes real option registration/sanitization, blank-key preservation and explicit deletion, administrator permissions and secret concealment, the settings nonce, gateway registration and numeric-ID conflicts, Persian phone/code normalization, leading-zero preservation, Bearer authentication and idempotency headers, duplicate prevention, uncertain outcomes, safe test responses, and activation/deactivation/uninstall behavior.

**The licensed Digits plugin was not available in this test environment.** Its filter invocation was simulated from the author-published [Akay Digits integration source](https://plugins.svn.wordpress.org/akay-digits/trunk/akay-digits.php). This smoke verifies Ava with real WordPress APIs and the observed filter contract; it does not establish compatibility with a particular commercial or localized Digits version or complete a browser login. Verify the intended Digits installation in a staging environment before using it for customers.

## Current 1.3.0 packages: actual supplied Digits reference

The user-supplied Digits 8.6.2 IPPanel distribution was inspected and activated in a separate disposable WordPress 7.1.4 / SQLite 3.1.3 / PHP 8.5.4 fixture. `reference-integration.php` requires explicit local fixture paths and refuses runtime environments without isolation. The supplied source includes direct cURL calls, so WordPress HTTP mocks alone are not sufficient: run inside a fresh network namespace, disable process/mail and URL transports, and install the fixture's offline MU-plugin before WordPress loads. Do not use production data or keys.

Current automated checks cover actual native Digits functions, the explicit Ava save button and its AJAX endpoint with own nonce/capabilities, the native autosave path, raw OTP dispatch, immediate standard SMS, custom patterns, voice response states, text sender, blank-key preservation, local data retention, unknown outcomes, and avoidance of unintended IPPanel transports. The 1.2.1 regressions verify public business-name-only metadata, zero home URL reads, rejection of linked/domain-like titles before truncation or markup removal, HTML and Unicode handling, leading-zero preservation, unchanged custom/text/voice payloads, allowlisted machine errors, and safe generic fallback for unknown or mismatched codes. No upstream error text, API key, recipient or code is reflected in an error message.

Actual WordPress browser fixtures also check pattern/text/voice controls, persistence of the chosen Ava gateway and mode, and local full-bundle assets. The reference has now been tested directly; the initial-release simulated-only limitation above describes historical 1.0 validation. All automated messages and calls remain mocked; a successful local suite does not establish operator delivery latency or live delivery of the new two-line text.

The full builder is separately checked for checksum pinning, archive traversal/symlink/duplicate rejection, retention of original core/notices, replacement of the provider, release-specific bootstrap notices and exclusion of private/development artifacts. Only the explicitly allowlisted Persian setup guide is copied from docs.

On 2026-11-08, version **1.2.0** passed **347 mocked PHP behavioral checks**, **9 Python packaging tests**, PHP syntax checks, and **52 native reference checks** on WordPress 7.1.4 / Digits 8.6.2 / PHP 8.5.4 in the isolated fixture. The native checks use actual WordPress title/home URL APIs and Digits raw-OTP dispatch. All automated transports remain blocked or mocked; no real SMS or call was sent. A comparison with the previous full ZIP also confirmed that the Digits core files were unchanged; only Ava's bootstrap and its own integration/documentation changed.

Version **1.2.1** passed **365 mocked PHP behavioral checks**, **9 Python packaging tests**, PHP syntax checks and **54 native reference checks** in the same isolated WordPress/Digits environment. The standard payload is exactly phone, code and site_name; title domains/URLs fail before transport. The unit suite makes any home_url() call fail, proving that standard OTP never collects the home URL. Custom, text and voice payloads and native OTP masking remain intact. All test sends and calls are mocked.

## Manual service acceptance — 2026-11-07

A separate manual check used the Ava panel and a test WordPress installation to send real standard SMS OTP and voice OTP. Correct code receipt, including preservation of leading zeros, was confirmed. Human listening also accepted the improved Persian voice quality. No recipient, code, account identity, job identifier or credential from that check is published here.

This manual check used adapter **1.1.1** and the full **Digits 8.6.2 / Ava 1.1.1** package. The voice improvement is applied centrally by Ava and does not require reinstalling those versions. Upgrade to **1.2.1** for the standard SMS business-name/code format without a domain. Standard OTP avoids per-code and system-template content approval around the clock for an eligible, funded account. Account/service permissions, resend intervals, expiry, credit and rate limits still apply; custom template approval remains separate.

This manual acceptance is distinct from the isolated automated suites. It confirms those observed deliveries and pronunciation, not a guaranteed delivery time, operator capacity or response by future recipients. API acceptance, message delivery and hearing a voice code are separate observations.

User setup and report links are in the [Persian setup guide](../docs/SETUP-FA.md): [public downloads](https://ava.tarpardaz.com/plugins), [API keys](https://ava.tarpardaz.com/panel#developers), and [OTP reports](https://ava.tarpardaz.com/panel#otp).

## Wallet 1.3.0 validation

`php tests/run.php` now includes `wallet.php`: strict integer rial/currency/arithmetic checks (including negative balances and overflow), GET-only transport, short timeout, 60-second cache, changed URL/key isolation, removed credentials, independent send enablement, force refresh invalidation, errors without zero/stale fallback, both settings surfaces and capability/nonce/method checks. HTTP is mocked throughout. `reference-integration.php` and `integration-smoke.php` count mocked wallet GETs separately from paid-send requests and cover the wallet using real WordPress option, transient and capability APIs.

On 2026-11-08, PHP 8.5.4 passed **455 mocked behavioral checks** and Python 3.14 passed **9 packaging tests** for 1.3.0. PHP syntax checks also passed. The native-reference/browser fixture acceptance is recorded separately by the integrating task; this unit result does not claim a real delivery or an actual browser login.

Version **1.3.0** passed **455 mocked PHP behavioral checks**, **9 Python distribution tests** and all PHP/JavaScript syntax checks. Its full ZIP passed **65 native WordPress 7.1.4 / Digits 8.6.2 checks**. A separate Firefox fixture containing WooCommerce 11.2.1 passed **32 checks** across native Digits, standalone Ava settings and WooCommerce settings at desktop/mobile widths, including read-only refresh, nonce rejection, invalid responses and cache separation after changing a key. HTTP, email and direct transports were blocked or mocked; no real message or call was sent. Existing supplied Digits font warnings in PHP 8.5 are separate from these checks; no browser JavaScript exception occurred.

## LearnPress checkout 1.4.0

`php tests/checkout.php` exercises the opt-in template bridge, dependency and logged-in guards, settings validation, escaping, trusted checkout redirects and session/cart migration with synthetic WordPress/LearnPress stubs. `php tests/checkout-admin.php` covers the dedicated Settings API registration, administrator permissions, nonce-bearing form, escaped controls and native Digits customization links. These tests are separate from the existing gateway and wallet suite and run in every CI PHP matrix entry and before release packaging.

No automated check sends a real OTP or creates a production order. Real WordPress/LearnPress fixture results, when available, are recorded separately below; mocked checks alone do not prove live theme compatibility or message delivery.

### Reproduce native LearnPress integration

`checkout-integration.php` runs against an explicitly prepared **disposable** WordPress/SQLite fixture with LearnPress **4.4.10** and the supplied full Digits **8.6.2** bundle containing the current Ava adapter. It changes fixture options and synthetic session/cart rows. Do not use production data or a real Ava key: Ava sending must remain disabled with an empty key. The administrator and subscriber must have `.invalid` email addresses; configure a published LearnPress checkout page and default country `Iran` before loading Digits. Use separate PHP processes for the four modes so the AJAX modes exercise LearnPress's native uninitialized administrator session.

Before WordPress loads, install an offline MU-plugin with these hooks and optional stubs for the disabled cURL functions:

```php
<?php
$GLOBALS['qa_http_blocked'] = array();
$GLOBALS['qa_mail_blocked'] = array();
$GLOBALS['qa_direct_network_blocked'] = array();
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
    $GLOBALS['qa_http_blocked'][] = $url;
    return new WP_Error( 'qa_offline', 'Fixture blocks all HTTP.' );
}, PHP_INT_MAX, 3 );
add_filter( 'pre_wp_mail', static function ( $pre, $args ) {
    $GLOBALS['qa_mail_blocked'][] = $args;
    return true;
}, PHP_INT_MAX, 2 );
if ( ! function_exists( 'curl_exec' ) ) {
    function curl_exec( $handle ) {
        $GLOBALS['qa_direct_network_blocked'][] = 'curl_exec';
        return false;
    }
}
if ( ! function_exists( 'curl_multi_exec' ) ) {
    function curl_multi_exec( $handle, &$running ) {
        $GLOBALS['qa_direct_network_blocked'][] = 'curl_multi_exec';
        return 1;
    }
}
```

The fixture's `wp-config.php` must define `WP_ENVIRONMENT_TYPE=local`, `WP_HTTP_BLOCK_EXTERNAL=true`, `DISABLE_WP_CRON=true`, and matching `WP_HOME`/`WP_SITEURL` on a synthetic `.invalid` hostname. The SQLite `DB_DIR` must be inside the fixture directory. Put this marker **beside** the fixture's `wordpress/` directory as `ava-digits-checkout-fixture.json`, replacing the example IDs and URLs with that disposable installation's values:

```json
{
  "fixture": "ava-digits-checkout-fixture-v1",
  "home_url": "http://ava-checkout.synthetic.invalid",
  "checkout_url": "http://ava-checkout.synthetic.invalid/lp-checkout/",
  "administrator_id": 1,
  "subscriber_id": 2,
  "checkout_page_id": 4
}
```

Put these settings in the fixture's `offline.ini`. The script checks them **before** loading WordPress; it also requires a network namespace different from PID 1's namespace:

```ini
allow_url_fopen=0
disable_functions=curl_exec,curl_multi_exec,mail,exec,shell_exec,passthru,system,popen,proc_open,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect
```

Run each mode sequentially with the fixture under the system temporary directory. Replace the example paths:

```sh
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-integration.php --wp-root=/tmp/fixture/wordpress --mode=frontend
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-integration.php --wp-root=/tmp/fixture/wordpress --mode=admin
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-integration.php --wp-root=/tmp/fixture/wordpress --mode=ajax
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-integration.php --wp-root=/tmp/fixture/wordpress --mode=signup
```

The frontend mode renders the actual LearnPress form and checks native-form replacement, preserved payment nonce, absence of nested forms, guest checkout and authenticated behavior. The administrator mode uses the real Settings API and capability checks. AJAX login and signup modes use actual WordPress auth-cookie hooks; login additionally emits `wp_login`, matching native Digits behavior. Both assert the initially uninitialized LearnPress AJAX session, trusted absolute and root-relative checkout return, and preservation of guest plus existing user cart items. Successful authentication is simulated with the synthetic subscriber; these checks do not submit or verify an OTP, call a payment gateway, or create an order.

On **2026-11-26**, the four native modes passed **67 assertions** on **WordPress 7.1.5 / SQLite Database Integration 3.1.3 / LearnPress 4.4.10 / Digits 8.6.2 / PHP 8.5.4**: frontend 22, administrator 11, AJAX login 17 and signup 17. Both AJAX modes explicitly started with LearnPress's native empty customer ID and preserved guest plus existing account cart items through real WordPress authentication-cookie hooks. Separate prebootstrap checks confirmed rejection without the explicit opt-in, required transport isolation or an existing fixture directory.

A separate **Firefox 157.0** test passed **21 browser assertions** at **1280×900** and **390×844**. It checked the custom checkout button, configured color and readable text on a light accent, lack of horizontal overflow, native Digits modal opening on the checkout page, the visible `+98` phone field, absence of a password in the first step, separate checkout/modal forms and zero JavaScript exceptions. The browser did not submit authentication or payment. No real SMS, call, OTP verification or order was created. The supplied Digits dependencies still emit existing PHP 8.5 deprecation notices; these results do not claim a clean upstream debug log or compatibility with the production site's theme.

## Phone-only checkout candidate 1.4.1

The `phone_only` option is off by default. It changes modern Digits form reads only on the configured LearnPress checkout or its trusted `digits_forms_ajax` login/register requests. Existing subscriber/customer accounts must have only ordinary read capabilities before their primary factor is restricted to SMS OTP. Administrator, custom-role and capability-elevated accounts keep their original factors. Legacy, forgot-password, unknown requests and direct WordPress administration keep their native policies. Ordinary signup keeps native registration enablement, requires phone verification, and retains CAPTCHA, extra security factors and required custom fields. The optional instructor checkbox is omitted only for ordinary checkout signup.

Local PHP 8.5.4 checks passed **455 existing behavioral checks**, **200 checkout regressions**, **20 administrator checks** and **9 Python distribution tests**. The optional native reproducer is `checkout-phone-integration.php`; it reuses the isolated synthetic WordPress/SQLite fixture, marker, offline MU-plugin and disabled transports described above. Its seven modes are `frontend`, `outside`, `admin`, `blocked_registration`, `signup`, `ajax` and `security`. For example:

```sh
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-phone-integration.php --wp-root=/tmp/fixture/wordpress --mode=frontend
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-phone-integration.php --wp-root=/tmp/fixture/wordpress --mode=signup
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-phone-integration.php --wp-root=/tmp/fixture/wordpress --mode=ajax
AVA_DIGITS_RUN_CHECKOUT_INTEGRATION=1 unshare -n php -c /tmp/fixture/offline.ini tests/checkout-phone-integration.php --wp-root=/tmp/fixture/wordpress --mode=security
```

Run the remaining modes with the same command and their mode names. On **2026-11-26**, the final candidate passed **106 native assertions** on WordPress 7.1.5 / LearnPress 4.4.10 / Digits 8.6.2 / SQLite Database Integration 3.1.3 / PHP 8.5.4. This uses the real nonce-protected Digits endpoint and synthetic OTP rows: wrong codes fail, valid codes are consumed, ordinary signup/login authenticates and returns to checkout with the cart. Original native option bytes remain unchanged. Separate cases reject attempts to replace privileged password-only policies through a forged checkout referer, including subscriber accounts with direct plugin-management capabilities; original password authentication still works. Forgot-password and unknown request types preserve their original reset channels. Nonordinary default registration roles retain their original native field policy and cannot create accounts through the unified checkout journey. The journey renders a single native phone form, routes an unknown number directly to native OTP verification, and serializes requests with an atomic per-phone option lock. It tests wrong/expired codes for both paths, creation only after valid verification, repeated submission without duplicate accounts, consumed-code replay rejection, a returning login into the newly created account, native debug-only resend without account/session creation, mandatory registration policy, disabled registration, initial and final ordinary roles, overlapping requests and stale-lock recovery. The request lock has a ten-minute crash-recovery window and is released on native JSON termination. No OTP generation, verification, expiry or rate-limit implementation is replaced.

These checks do not send real messages or create production orders. A passing fixture does not establish delivery by a live provider or acceptance of a particular production theme.

The final frozen candidate also passed **31 Firefox 157.0 assertions** at 1280×900 and 390×844: one phone form without a Register link or separate registration form, omitted standard credentials/name/instructor fields, direct transition from a new phone to native OTP, editable OTP input after a synthetic invalid code, preserved ordinary registration outside checkout, no overflow and zero JavaScript exceptions. The browser used the native debug transport switch with isolated networking; only synthetic invalid OTP was submitted in-browser. Valid verification/account creation/cart preservation was exercised through the real native endpoint above, with all transports blocked. These are local integration results, not a claim of production installation, real SMS delivery or production-theme acceptance.
