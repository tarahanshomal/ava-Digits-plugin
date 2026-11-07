# Changelog

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
