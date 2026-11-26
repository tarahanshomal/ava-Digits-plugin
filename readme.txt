=== Ava SMS and Voice OTP for Digits ===
Contributors: tarahanshomal
Tags: digits, sms, otp, persian, ava
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

اتصال پیامک رمز یک‌بارمصرف آوا به دیجیتس، توسعه‌یافته توسط طراحان شمال.

== Description ==

Ava gateway for Digits with native settings inside Digits, standard SMS OTP with the public business name and code, approved SMS patterns, voice OTP and plain-text SMS with an optional permitted sender. Install and activate Digits separately through its provider before installing the Ava adapter.

Configure Digits > Authentication > SMS Gateway, select Ava and enter your HTTPS panel URL and API key. For standard SMS OTP choose pattern mode and leave template ID blank: Ava renders the fixed two-line business name and code message. The name comes from the WordPress title in General Settings. The home URL is never read and no domain is sent. A title containing a domain or URL fails clearly rather than being disguised. For voice choose voice mode. Standard OTP is available around the clock without per-code or system-template review for eligible, funded accounts. Resend intervals, expiry, wallet balance and rate limits still apply. Custom SMS patterns retain their review process.

API scopes: sms_otp:send for SMS OTP, otp:send for voice OTP, sms:send for plain-text SMS. Digits creates and verifies codes. The Ava integration preserves leading zeros, reads the real raw-OTP gateway argument and does not automatically retry uncertain requests. New installations remain disabled until configured.

= LearnPress checkout and customization =

Opt in under Settings > Ava Forms and Checkout to replace LearnPress checkout account forms with a native Digits login/register button. Customize the button text, explanatory text and accent color; optionally retain the original account forms. Successful Digits login or registration returns to the trusted checkout URL without changing the cart. Digits must remain active. The feature is disabled by default and does not rewrite native login or registration settings.

Enable Phone only in checkout on the same settings page. This mode supports the modern native Digits form and standard subscriber/customer accounts. In 1.4.2, phone-only checkout directs accounts with elevated permissions or additional required verification factors to the regular sign-in page before a password prompt can appear. Regular sign-in and global Digits settings preserve their existing authentication policies. Standard name, username, email and password fields and LearnPress's optional teacher checkbox are omitted in phone-only checkout. Registration OTP verification stays enabled. CAPTCHA and required custom fields remain authoritative. One phone-and-code form logs existing customers in and creates one ordinary account for a new number only after native OTP verification. There is no Register link or separate registration form. Per-phone serialization prevents duplicate creation through overlapping requests from this form. Failed or expired codes leave the form usable without an account or login. Enable Digits registration with a subscriber/customer default role, review any mandatory custom fields before enabling this journey, and disable LearnPress guest checkout if purchase must require an account. A phone entry alone never authenticates or creates an account. With guest checkout disabled, direct legacy LearnPress account/guest submissions are rejected before account or order processing.

= Wallet balance =

Both Ava settings pages display available, total and reserved account balance in rials to administrators. The plugin reads GET /api/v1/wallet using the saved key even when sending is disabled. Existing send-scoped keys work; wallet:read is also accepted. The snapshot is cached for 60 seconds separately for each panel and key. A nonce-protected refresh bypasses cache without creating a paid SMS or voice call. Connection and response errors show a clear error instead of a fabricated zero or stale balance. The recharge link opens /panel#wallet at the configured panel.

= External service =

The plugin sends phone number, code or rendered message, optional template/sender/brand and Bearer credentials to your selected HTTPS Ava panel (default https://ava.tarpardaz.com). Standard SMS also sends the public business name as site_name; its only fields are phone, code and site_name. No home URL or site_domain is collected. Custom pattern, text and voice payloads do not gain metadata. Sending endpoints are POST /api/v1/sms-otp, /api/v1/voice-otp and /api/v1/sms. Administrator wallet reads use GET /api/v1/wallet with the saved Bearer key and no message/recipient payload. Only validated amounts and currency are cached; the secret and raw upstream response are not cached. An accepted request is not proof of delivery or an answered call. Paid tests create real SMS or calls; automated tests use mocks only. The adapter has no local message log; native Digits text-mode logs can contain rendered SMS. Pattern and voice log messages are masked on the verified Digits 8.6.2 path. Known error codes map to fixed safe messages; upstream error text is not displayed.

== Installation ==

1. Install and activate Digits through its provider.
2. Download the Ava adapter ZIP from GitHub Releases and install it in WordPress.
3. Open Digits > Authentication > SMS Gateway, select Ava and configure URL/key/mode.
4. Use sms_otp:send for SMS OTP, otp:send for voice, sms:send for text.
5. Save. Test sends incur charges. Read README.md for complete instructions.

== Troubleshooting ==

= Business name contains a domain or link =

Version 1.2.1 adds no domain and rejects linked or domain-like WordPress titles before sending. Enter the actual business name without a link. If a linked name from another client meets an Ariantel MessageContainsLink rejection, the provider must review the account and sender permissions. Provider-specific OtpAccess is not a general requirement for this standard SMS path; the Ava API key requires sms_otp:send. Reinstalling the WordPress plugin does not activate operator permissions.

Ava API acceptance is not operator acceptance or SMS delivery. Check the final Ava report. Automated checks verify the 1.2.1 two-line name/code behavior; handset receipt of that new text is not claimed here. Historical live SMS and voice acceptance described in README.md used 1.1.1.

== Changelog ==

= 1.4.2 =
* Offer only a verification code in phone-only LearnPress checkout. Direct accounts that require their usual sign-in method away from checkout before displaying any password prompt.
* Publish the standalone Ava adapter ZIP and its checksum.

= 1.4.1 =
* Add optional phone-only login/signup scoped to LearnPress checkout and its trusted native Digits requests.
* Preserve global options, administrator login, additional security factors and registration OTP verification.
* Hide standard credential/name fields and the optional LearnPress teacher checkbox only in that checkout flow.

= 1.4.0 =
* Add optional LearnPress checkout integration using native Digits login and registration.
* Configure checkout button text, message, color and whether to retain the original account forms.
* Preserve Digits authentication and LearnPress cart/session; return successful sign-in to checkout.
* Keep integration disabled by default and preserve native account forms when Digits is unavailable.

= 1.3.0 =
* Display available, total and reserved account balance in rials inside Digits and standalone Ava settings.
* Read wallet with existing credentials and a short cache isolated by panel and key, independently of send enablement.
* Add protected read-only refresh, recharge link and safe errors without fabricated zeroes.

= 1.2.1 =
* Limit standard SMS to the public business name and raw code; stop collecting the home URL or sending a domain.
* Reject linked or domain-like business names with clear guidance before sending, without obfuscating them.
* Preserve custom pattern, text, voice, account protections, resend intervals and OTP expiry.

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
