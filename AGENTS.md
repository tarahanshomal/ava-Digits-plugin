# Ava Digits plugin

Standalone public WordPress gateway adapter. Keep private backend code, credentials, server topology, customer data and OTP contents out of this repository. Automated tests use synthetic fixtures and mocked HTTP only; never send real SMS.

Target PHP 7.4+ and WordPress 6.5+. Digits must be installed separately. `digits_sms_gateways` registers the gateway; `unitedover_send_sms` receives the rendered message, which must contain only a 4–8 digit code. Digits owns code generation, verification, expiry and authentication protections.

Administrator settings require `manage_options` and a nonce. Never expose saved API keys or upstream response bodies. Preserve blank key input. Use the approved SMS OTP API with `sms_otp:send`; text SMS and voice OTP scopes differ. Uncertain requests are not automatically repeated. Keep runtime secrets and generated files out of the installable ZIP.

Run `php tests/run.php`, `python3 -m unittest discover -s tests -p 'test_*.py' -v`, and PHP syntax checks. Package with `python3 scripts/package.py --tag vX.Y.Z`. Match version headers, stable tag and release tag. Document exact tested versions and distinguish real WordPress integration from a simulated Digits hook invocation.
