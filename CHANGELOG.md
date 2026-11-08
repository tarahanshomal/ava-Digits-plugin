# Changelog

## 1.2.0

- Add the public WordPress site name and home URL hostname to standard SMS OTP requests with a blank template ID. Ava renders the fixed three-line site/domain/code message; raw OTP and leading zeros are preserved.
- Map allowlisted backend machine codes to safe account, API scope, wallet, metadata and service explanations. Retain generic legacy error handling and uncertain outcomes without showing upstream text or retrying automatically.
- Preserve custom pattern, text and voice payloads, existing settings, disabled-by-default installations, resend intervals and OTP expiry.
- Include the public Persian setup guide in both ZIPs. Derive the full-bundle old-adapter notice from the embedded release version.
- Add mocked identity/error regressions, 52 actual WordPress/Digits reference checks and distribution-boundary checks. All automated transports remain mocked; the recorded live voice acceptance below predates this metadata feature.

## Service and guide update — 2026-11-07

- Ava now uses a clearer Persian voice for voice OTP. This is a central service update; the installed adapter remains 1.1.1 and needs no reinstall for the new voice.
- Add a step-by-step Persian installation and configuration guide for the full bundle and existing Digits sites.
- Record separate, owner-authorized live acceptance from the customer panel and an isolated WordPress/Digits installation, including handset confirmation and preservation of a leading zero. Automated tests continue to use mocked transports.

## 1.1.1

- Match full-bundle WordPress/PHP requirement headers to the embedded adapter.
- Add a checksum-pinned reference mirror for release builds when the original CDN is unavailable.
- Runtime OTP and native settings behavior is unchanged from 1.1.0.

## 1.1.0

- Full Digits 8.6.2 Ava edition plus the separately installable adapter.
- Native Digits configuration for immediate standard SMS OTP, approved patterns, voice OTP and text messages with an optional sender.
- Correct raw eighth OTP argument, native pattern/voice log masking, scoped reference IPPanel callback and nonce-protected paid tests.
- Local pinned JavaScript dependencies, reproducible full ZIP, retained upstream notices and real supplied-reference integration tests.

## 1.0.0

- Initial Ava SMS OTP gateway for Digits, developed by Tarahan Shomal.
- Persian connection settings, Iranian phone normalization and preservation of leading zeros in one-time codes.
- Approved Ava OTP templates, HTTPS Bearer authentication and idempotent requests.
- Mocked HTTP regression checks and reproducible installable release ZIP with SHA256.
