# Contributing

Open an issue with the observed and expected behavior, or submit a focused pull request. Do not include API keys, one-time codes, customer records or private backend code.

Target PHP 7.4+ and WordPress 6.5+. Maintain the documented Digits gateway contract. Administrator writes require `manage_options` and a nonce. Validate input, escape output and keep credentials and OTP values out of logs and responses. Never automatically repeat an SMS send when the previous outcome is unknown.

Run the local checks:

```sh
php tests/run.php
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 scripts/package.py
```

Use synthetic fixtures and mocked HTTP. Validate WordPress integration in a disposable local installation. Real SMS and production customer data must not be used in automated tests. Keep the plugin header, stable tag, release tag and changelog consistent, and describe the exact versions tested.
