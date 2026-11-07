# Ava Digits plugin

Public WordPress gateway adapter plus a full-edition assembly script. Keep private backend code, credentials, server topology, customer data and OTP contents out of this repository. Automated tests use synthetic fixtures and mocked HTTP only; never send real SMS.

Target PHP 7.4+ and WordPress 6.5+. The adapter ZIP needs Digits separately; package_full.py assembles the separately authorized, pinned supplied core without committing its source into this repository. `digits_sms_gateways` registers the gateway; `unitedover_send_sms` receives the rendered message, and the actual raw OTP as argument eight. Prefer raw OTP for pattern/voice; old hook contracts need a code-only rendered message. Digits owns code generation, verification, expiry and authentication protections.

Administrator settings require `manage_options` and a nonce. Never expose saved API keys or upstream response bodies. Preserve blank key input. Use SMS OTP with `sms_otp:send` (blank template selects the immediate safe default), voice OTP with `otp:send`, and text SMS with `sms:send`. Never automatically approve arbitrary custom templates. Uncertain requests are not automatically repeated. Keep runtime secrets and generated files out of the installable ZIP.

Run `php tests/run.php`, `python3 -m unittest discover -s tests -p 'test_*.py' -v`, and PHP syntax checks. Package with `python3 scripts/package.py --tag vX.Y.Z`. Match version headers, stable tag and release tag. Document exact tested versions and distinguish real WordPress integration from a simulated Digits hook invocation.
