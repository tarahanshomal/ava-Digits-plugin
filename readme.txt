=== Ava SMS and Voice OTP for Digits ===
Contributors: tarahanshomal
Tags: digits, sms, otp, persian, ava
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

اتصال پیامک رمز یک‌بارمصرف آوا به دیجیتس، توسعه‌یافته توسط طراحان شمال.

== Description ==

Ava gateway for Digits with native settings inside Digits, standard SMS OTP with the public site name and domain, approved SMS patterns, voice OTP and plain-text SMS with an optional permitted sender. The standalone ZIP requires Digits separately; a full Digits 8.6.2 + Ava ZIP is available from GitHub Releases with separate third-party notices.

Configure Digits > Authentication > SMS Gateway, select Ava and enter your HTTPS panel URL and API key. For standard SMS OTP choose pattern mode and leave template ID blank: Ava renders the fixed three-line site name, domain and code message. The title comes from WordPress General Settings; only the home URL hostname is shared. For voice choose voice mode. Standard OTP is available around the clock without per-code or system-template review for eligible, funded accounts. Resend intervals, expiry, wallet balance and rate limits still apply. Custom SMS patterns retain their review process.

API scopes: sms_otp:send for SMS OTP, otp:send for voice OTP, sms:send for plain-text SMS. Digits creates and verifies codes. The Ava integration preserves leading zeros, reads the real raw-OTP gateway argument and does not automatically retry uncertain requests. New installations remain disabled until configured.

= External service =

The plugin sends phone number, code or rendered message, optional template/sender/brand and Bearer credentials to your selected HTTPS Ava panel (default https://ava.tarpardaz.com). Standard SMS also sends the public site_name and site_domain; custom pattern, text and voice payloads do not include them. Endpoints are POST /api/v1/sms-otp, /api/v1/voice-otp and /api/v1/sms. An accepted request is not proof of delivery or an answered call. Paid tests create real SMS or calls; automated tests use mocks only. The adapter has no local message log; native Digits text-mode logs can contain rendered SMS. Pattern and voice log messages are masked on the verified Digits 8.6.2 path. Known error codes map to fixed safe messages; upstream error text is not displayed.

== Installation ==

1. Choose the full ZIP or the adapter ZIP from GitHub Releases.
2. Install in WordPress. Disable the old standalone Ava plugin before installing the full bundle.
3. Open Digits > Authentication > SMS Gateway, select Ava and configure URL/key/mode.
4. Use sms_otp:send for SMS OTP, otp:send for voice, sms:send for text.
5. Save. Test sends incur charges. Read README.md for complete instructions.

== Troubleshooting ==

= Operator rejects a domain or link =

Ariantel can treat the domain in the fixed SMS text as a link and reject it with MessageContainsLink. The provider must review and enable domain/link permissions for the Ariantel account and sender. Its own OTP route also requires provider-side OtpAccess activation. These provider permissions are separate from Ava API scopes such as sms_otp:send and otp:send. Reinstalling or replacing the WordPress plugin does not activate an operator account or sender permission.

Ava API acceptance is not operator acceptance or SMS delivery. Check the final Ava report. The 1.2.0 site metadata and API admission were verified, but the domain-bearing text met an operator rejection; successful handset receipt of that new text has not been confirmed. Historical live SMS and voice acceptance described in README.md used 1.1.1.

== Changelog ==

= 1.2.0 =
* Add public WordPress site name and hostname to the standard SMS OTP request for Ava's fixed three-line message.
* Explain known account, API scope, wallet, service and metadata errors without reflecting upstream error text.
* Retain custom pattern, text, voice, disabled-by-default settings and Digits resend/expiry protections.
* Include the Persian setup guide in both packages and match the full-bundle compatibility notice to its embedded version.

= 1.1.1 =
* Native Digits settings and full distribution packaging based on the pinned reference.
* Immediate standard SMS OTP, voice OTP, custom pattern and plain-text modes.
* Real raw OTP argument, scoped IPPanel compatibility, native settings and test nonces, credential concealment, local dependencies and synthetic integration checks.

= 1.0.0 =
* Initial standalone Ava SMS OTP adapter.
