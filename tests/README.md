# Running the tests

The normal regression suite uses synthetic fixtures and mocked WordPress HTTP. It needs PHP 7.4 or newer and Python 3.9 or newer:

```sh
php tests/run.php
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

## Current 1.2.0 packages: actual supplied Digits reference

The user-supplied Digits 8.6.2 IPPanel distribution was inspected and activated in a separate disposable WordPress 7.1.4 / SQLite 3.1.3 / PHP 8.5.4 fixture. `reference-integration.php` requires explicit local fixture paths and refuses runtime environments without isolation. The supplied source includes direct cURL calls, so WordPress HTTP mocks alone are not sufficient: run inside a fresh network namespace, disable process/mail and URL transports, and install the fixture's offline MU-plugin before WordPress loads. Do not use production data or keys.

Current automated checks cover actual native Digits functions, the explicit Ava save button and its AJAX endpoint with own nonce/capabilities, the native autosave path, raw OTP dispatch, immediate standard SMS, custom patterns, voice response states, text sender, blank-key preservation, local data retention, unknown outcomes, and avoidance of unintended IPPanel transports. The 1.2.0 regressions add public site-name/hostname metadata, HTML and Unicode handling, exclusion of private URL components, leading-zero preservation, unchanged custom/text/voice payloads, allowlisted machine errors, and safe generic fallback for unknown or mismatched codes. No upstream error text, API key, recipient or code is reflected in an error message.

Actual WordPress browser fixtures also check pattern/text/voice controls, persistence of the chosen Ava gateway and mode, and local full-bundle assets. The reference has now been tested directly; the initial-release simulated-only limitation above describes historical 1.0 validation. All automated messages and calls remain mocked; a successful local suite does not establish operator delivery latency or live delivery of the new three-line text.

The full builder is separately checked for checksum pinning, archive traversal/symlink/duplicate rejection, retention of original core/notices, replacement of the provider, release-specific bootstrap notices and exclusion of private/development artifacts. Only the explicitly allowlisted Persian setup guide is copied from docs.

On 2026-11-08, version **1.2.0** passed **347 mocked PHP behavioral checks**, **9 Python packaging tests**, PHP syntax checks, and **52 native reference checks** on WordPress 7.1.4 / Digits 8.6.2 / PHP 8.5.4 in the isolated fixture. The native checks use actual WordPress title/home URL APIs and Digits raw-OTP dispatch. All automated transports remain blocked or mocked; no real SMS or call was sent. A comparison with the previous full ZIP also confirmed that the Digits core files were unchanged; only Ava's bootstrap and its own integration/documentation changed.

## Manual service acceptance — 2026-11-07

A separate manual check used the Ava panel and a test WordPress installation to send real standard SMS OTP and voice OTP. Correct code receipt, including preservation of leading zeros, was confirmed. Human listening also accepted the improved Persian voice quality. No recipient, code, account identity, job identifier or credential from that check is published here.

This manual check used adapter **1.1.1** and the full **Digits 8.6.2 / Ava 1.1.1** package. The voice improvement is applied centrally by Ava and does not require reinstalling those versions. Upgrade to **1.2.0** for the standard SMS site-name/domain metadata. Standard OTP avoids per-code and system-template content approval around the clock for an eligible, funded account. Account/service permissions, resend intervals, expiry, credit and rate limits still apply; custom template approval remains separate.

This manual acceptance is distinct from the isolated automated suites. It confirms those observed deliveries and pronunciation, not a guaranteed delivery time, operator capacity or response by future recipients. API acceptance, message delivery and hearing a voice code are separate observations.

User setup and report links are in the [Persian setup guide](../docs/SETUP-FA.md): [public downloads](https://ava.tarpardaz.com/plugins), [API keys](https://ava.tarpardaz.com/panel#developers), and [OTP reports](https://ava.tarpardaz.com/panel#otp).
