# Security

Security fixes are provided for the latest published version. Keep WordPress, Digits and this plugin updated.

For a suspected vulnerability, use the repository's private **Security → Report a vulnerability** option when available. Otherwise, ask the maintainer for a private reporting channel without posting exploit details publicly. Never include API keys, one-time codes, complete phone numbers or customer records in issues, pull requests or screenshots.

If a key is exposed, revoke it in Ava and replace it with a key limited to the selected method: `sms_otp:send`, `otp:send` or `sms:send`. Restrict access to the WordPress database and backups because the connection settings can contain the API key. The plugin is a gateway adapter; Digits remains responsible for generating and validating codes, expiry, resend limits and login protections.
