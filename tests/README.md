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

## Version 1.1.0: actual supplied Digits reference

The user-supplied Digits8.6.2 IPPanel distribution was inspected and activated in a separate disposable WordPress7.1.4 / SQLite3.1.3 / PHP8.5.4 fixture. `reference-integration.php` requires explicit local fixture paths and refuses runtime environments without isolation. The supplied source includes direct cURL calls, so WordPress HTTP mocks alone are not sufficient: run inside a fresh network namespace, disable process/mail and URL transports, and install the fixture's offline MU-plugin before WordPress loads. Do not use production data or keys.

Current checks cover actual native Digits functions, native settings autosave POST with own nonce/capabilities, raw OTP dispatch, immediate standard SMS, custom patterns, voice response states, text sender, blank-key preservation, local data retention, unknown outcomes, and avoidance of unintended IPPanel transports. An actual WordPress browser fixture also checks pattern/text/voice controls and local full-bundle assets. The reference has now been tested directly; the initial-release simulated-only limitation above describes historical1.0validation. All messages and calls remain mocked; a successful local suite does not establish operator delivery latency.

The full builder is separately checked for checksum pinning, archive traversal/symlink/duplicate rejection, retention of original core/notices, replacement of the provider and exclusion of private/development artifacts.
