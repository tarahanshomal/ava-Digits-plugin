=== Ava SMS OTP for Digits ===
Contributors: tarahanshomal
Tags: digits, sms, otp, persian, ava
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

اتصال پیامک رمز یک‌بارمصرف آوا به دیجیتس، توسعه‌یافته توسط طراحان شمال.

== Description ==

Adds the Ava SMS OTP gateway to Digits. Requires a separately installed licensed Digits plugin, an Ava account, an API key with sms_otp:send and an approved Ava OTP template containing {code}. The plugin does not include Digits.

Configure Settings > آوا برای دیجیتس, enable the connection, then select Ava under Digits > SMS Gateway. Set the Digits message template to exactly {OTP}. The approved Ava template determines the delivered message. Codes must contain 4–8 digits; leading zeros are preserved. Iranian mobile numbers are supported. Sending is disabled after installation.

Digits remains responsible for code generation, verification, expiry, resend limits and authentication. API acceptance does not prove delivery. The plugin does not automatically retry uncertain sends.

= External service: Ava SMS OTP =

When Digits requests an OTP send, the plugin sends the recipient phone number, one-time code, approved template ID and optional template parameters to the configured HTTPS Ava panel, default https://ava.tarpardaz.com, using POST /api/v1/sms-otp. The API key is sent as a Bearer token and requests include an idempotency key. The user needs their own Ava account and must review the service terms and privacy information there. There is no local OTP message log in this plugin. WordPress database and backups may contain the saved connection key. Real test sends from Digits can incur charges.

Service: https://ava.tarpardaz.com

== Installation ==

1. Install licensed Digits separately on WordPress 6.5+ with PHP 7.4+.
2. Upload the ava-Digits-plugin release ZIP and activate it.
3. Create an approved Ava SMS OTP template containing {code} and an API key with sms_otp:send.
4. Configure Settings > آوا برای دیجیتس and enable the connection.
5. Select Ava in Digits > SMS Gateway; set its message template to exactly {OTP}.
6. Validate login on a staging site. Any real test send can incur charges.

== Frequently Asked Questions ==

= Does this package include Digits? =
No. Install the original Digits plugin separately.

= Where do I write the SMS message? =
In the approved Ava SMS OTP template. The Digits template must be exactly {OTP}.

= Which API scope is needed? =
sms_otp:send. Text SMS and voice OTP use different scopes.

= Does an accepted request prove delivery? =
No. Review the final status in Ava.

== Changelog ==

= 1.0.0 =
* Initial Ava SMS OTP gateway for Digits with Persian settings, Iranian phone normalization, approved templates, HTTPS and idempotent requests.
