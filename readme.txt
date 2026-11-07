=== Ava SMS and Voice OTP for Digits ===
Contributors: tarahanshomal
Tags: digits, sms, otp, persian, ava
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

اتصال پیامک رمز یک‌بارمصرف آوا به دیجیتس، توسعه‌یافته توسط طراحان شمال.

== Description ==

Ava gateway for Digits with native settings inside Digits, immediate standard SMS OTP, approved SMS patterns, voice OTP and plain-text SMS with an optional permitted sender. The standalone ZIP requires Digits separately; a full Digits8.6.2+Ava ZIP is available from GitHub Releases with separate third-party notices.

Configure Digits > Authentication > SMS Gateway, select Ava and enter your HTTPS panel URL and API key. For immediate standard SMS OTP choose pattern mode and leave template ID blank. For voice choose voice mode. Account/service eligibility, wallet balance and rate limits still apply. Custom SMS patterns retain their review process.

API scopes: sms_otp:send for SMS OTP, otp:send for voice OTP, sms:send for plain-text SMS. Digits creates and verifies codes. The Ava integration preserves leading zeros, reads the real raw-OTP gateway argument and does not automatically retry uncertain requests. New installations remain disabled until configured.

= External service =

The plugin sends phone number, code or rendered message, optional template/sender/brand and Bearer credentials to your selected HTTPS Ava panel (default https://ava.tarpardaz.com). Endpoints are POST /api/v1/sms-otp, /api/v1/voice-otp and /api/v1/sms. An accepted request is not proof of delivery or an answered call. Paid tests create real SMS or calls; automated tests use mocks only. The adapter has no local message log; native Digits text-mode logs can contain rendered SMS. Approved-pattern and voice log messages are masked on the verified Digits8.6.2 path.

== Installation ==

1. Choose the full ZIP or the adapter ZIP from GitHub Releases.
2. Install in WordPress. Disable the old standalone Ava plugin before installing the full bundle.
3. Open Digits > Authentication > SMS Gateway, select Ava and configure URL/key/mode.
4. Use sms_otp:send for SMS OTP, otp:send for voice, sms:send for text.
5. Save. Test sends incur charges. Read README.md for complete instructions.

== Changelog ==

= 1.1.0 =
* Native Digits settings and full distribution packaging based on the pinned reference.
* Immediate standard SMS OTP, voice OTP, custom pattern and plain-text modes.
* Real raw OTP argument, scoped IPPanel compatibility, native settings and test nonces, credential concealment, local dependencies and synthetic integration checks.

= 1.0.0 =
* Initial standalone Ava SMS OTP adapter.
