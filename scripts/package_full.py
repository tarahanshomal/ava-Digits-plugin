#!/usr/bin/env python3
"""Build the full Ava edition from the explicitly supplied, SHA256-pinned Digits ZIP.

The third-party core is not stored in this source repository. Its activation,
authentication, account data and license behavior are not patched by this builder.
"""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import re
import stat
import urllib.error
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REFERENCE_URL = 'https://cdn.ippanel.com/ippanel/plugin/digits-8.6.2-ippanel-minimize.zip'
REFERENCE_MIRROR = 'https://ava.tarpardaz.com/static/downloads/ava-digits-reference-8.6.2.zip'
REFERENCE_SHA256 = '457eae74c107750425b8eace1886fe4bbab16e7b0282ba760d32e90f9c13b9aa'
MAX_EXPANDED = 100_000_000
TIMESTAMP = (2026, 11, 7, 0, 0, 0)
spec = importlib.util.spec_from_file_location('ava_package', ROOT / 'scripts/package.py')
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)

BOOTSTRAP = '''<?php
/** Ava edition: replace the reference's provider adapter, retain the Digits core. */
defined('ABSPATH') || exit;
register_activation_hook(dirname(__DIR__) . '/digit.php', static function () {
    // The embedded adapter shares its settings with the previous standalone
    // plugin. Deactivate that redundant entry without uninstalling its data.
    if (function_exists('deactivate_plugins')) deactivate_plugins('ava-Digits-plugin/ava-digits-plugin.php', true);
});
require_once __DIR__ . '/ava/ava-digits-plugin.php';
if (version_compare(AVA_DIGITS_VERSION, '1.1.0', '<')) {
    add_action('admin_notices', static function () {
        if (current_user_can('activate_plugins')) echo '<div class="notice notice-warning"><p>برای استفاده از نسخه جدید دیجیتس آوا، افزونه جداگانه و قدیمی Ava Digits را از فهرست افزونه‌ها غیرفعال کنید؛ تنظیمات اتصال حفظ می‌شوند.</p></div>';
    });
}
add_filter('digits_sms_gateways', static function ($gateways) {
    if (!isset($gateways['ava_digits'])) return $gateways;
    $gateway = $gateways['ava_digits'];
    // Digits sorts its default group unconditionally. A single custom group
    // would leave that group absent; put the sole Ava entry in the default.
    unset($gateway['group']);
    return array('ava_digits' => $gateway);
}, PHP_INT_MAX);
add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/digit.php'), static function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=digits_settings&tab=apisettings')) . '">تنظیمات آوا</a>');
    return $links;
});
add_filter('script_loader_src', static function ($url, $handle) {
    $files = array(
        'igorescobar-jquery-mask' => 'jquery-mask-plugin-1.14.16.js',
        'apexcharts' => 'apexcharts-3.54.1.js',
        'libphonenumber-mobile' => 'libphonenumber-js-1.11.20.js',
    );
    return isset($files[$handle]) ? plugins_url('ava/assets/vendor/' . $files[$handle], __FILE__) : $url;
}, 10, 2);
add_filter('style_loader_src', static function ($url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    return 'fonts.googleapis.com' === $host ? plugins_url('ava/assets/vendor/font-fallback.css', __FILE__) : $url;
});
add_filter('script_loader_tag', static function ($tag, $handle) {
    if ('digits-login-script' !== $handle) return $tag;
    $url = plugins_url('ava/assets/vendor/examples.mobile-1.11.20.json', __FILE__);
    $code = 'window.avaDigitsExamplesUrl=' . wp_json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
    return wp_get_inline_script_tag($code, array('id' => 'ava-digits-examples-init')) . $tag;
}, 10, 2);
'''


def download(destination: Path) -> Path:
    destination.parent.mkdir(parents=True, exist_ok=True)
    if not destination.exists():
        content = None
        for url in (REFERENCE_URL, REFERENCE_MIRROR):
            try:
                request = urllib.request.Request(url, headers={'User-Agent': 'AvaDigits-ReleaseBuilder/1.1'})
                with urllib.request.urlopen(request, timeout=30) as response:
                    content = response.read(30_000_001)
                break
            except (urllib.error.URLError, TimeoutError, ConnectionError):
                continue
        if content is None:
            raise RuntimeError('The reviewed reference and its pinned mirror are temporarily unavailable')
        if len(content) > 30_000_000:
            raise ValueError('Reference archive exceeds expected bounds')
        if hashlib.sha256(content).hexdigest() != REFERENCE_SHA256:
            raise ValueError('Reference SHA256 changed; review the new source before building')
        destination.write_bytes(content)
    return destination


def reference_files(base: Path, expected_sha: str = REFERENCE_SHA256) -> dict[str, bytes]:
    if hashlib.sha256(base.read_bytes()).hexdigest() != expected_sha:
        raise ValueError('Reference ZIP does not match the reviewed SHA256')
    files = {}
    with zipfile.ZipFile(base) as archive:
        infos = archive.infolist()
        if sum(info.file_size for info in infos) > MAX_EXPANDED:
            raise ValueError('Reference expanded size exceeds expected bounds')
        for info in infos:
            path = Path(info.filename)
            if path.is_absolute() or '..' in path.parts or '\\' in info.filename or (info.external_attr >> 16) & 0o170000 == stat.S_IFLNK:
                raise ValueError('Unsafe reference archive member')
            if not path.parts or path.parts[0] != 'digits':
                raise ValueError('Reference must have one digits/ plugin directory')
            if info.is_dir():
                continue
            if info.filename in files:
                raise ValueError('Duplicate reference archive member')
            files[info.filename] = archive.read(info)
    if 'digits/digit.php' not in files or 'digits/custom/gateways.php' not in files:
        raise ValueError('Missing reviewed Digits entrypoints')
    return files


def build(base: Path, output: Path, root: Path = ROOT, expected_sha: str = REFERENCE_SHA256) -> Path:
    files = reference_files(base, expected_sha)
    # Retain the supplied core byte-for-byte except its WordPress display header.
    main = files['digits/digit.php'].decode('utf-8')
    original = 'Plugin Name: DIGITS: WordPress Mobile Number Signup and Login'
    if original not in main:
        raise ValueError('Unexpected reference plugin header')
    main = main.replace(original, 'Plugin Name: Digits — آوا، ورود و ثبت‌نام با شماره همراه', 1)
    main = main.replace('Requires PHP: 5.5', 'Requires PHP: 7.4', 1)
    main = main.replace(' * Domain Path: /languages', ' * Requires at least: 6.5\n * Domain Path: /languages', 1)
    files['digits/digit.php'] = main.encode('utf-8')
    # The supplied provider callback ignores gateway IDs. Remove it entirely in
    # the Ava edition, so an Ava request cannot be forwarded to another provider.
    files = {name: value for name, value in files.items() if not name.startswith('digits/custom/ippanel/')}
    files['digits/custom/gateways.php'] = BOOTSTRAP.encode('utf-8')
    # Country placeholders are fetched directly by native JS, outside WP's
    # script-loader hooks. Keep the code intact and localize only that URL.
    for name in ('digits/assets/js/login.js', 'digits/assets/js/login.min.js'):
        if name in files:
            text = files[name].decode('utf-8')
            for quote in ("'", '"'):
                text = text.replace(quote + 'https://unpkg.com/libphonenumber-js@latest/examples.mobile.json' + quote, 'window.avaDigitsExamplesUrl')
            files[name] = text.encode('utf-8')
    for name in ('digits/admin/assets/css/wizard.css', 'digits/admin/assets/css/wizard.min.css'):
        if name in files:
            text = files[name].decode('utf-8')
            text = re.sub(r'@import\s+url\([^)]*fonts\.googleapis\.com[^)]*\);?', '', text)
            files[name] = text.encode('utf-8')
    for path in package.files(root):
        files['digits/custom/ava/' + path.relative_to(root).as_posix()] = path.read_bytes()
    files['digits/README-AVA.txt'] = (
        'Digits 8.6.2 / Ava integration ' + package.version(root) + '\n\n'
        'بسته کامل: افزونه Digits و اتصال آوا در یک نصب.\n'
        'قبل از فعال‌سازی این بسته، افزونه جداگانه Ava Digits را غیرفعال کنید.\n'
        'گزینه‌ها و اطلاعات کاربران Digits هنگام نصب این بسته حذف نمی‌شوند.\n'
        'از منوی Digits، بخش Authentication / SMS Gateway، درگاه آوا را تنظیم کنید.\n'
        'راهنمای کامل: https://github.com/tarahanshomal/ava-Digits-plugin\n\n'
        'Provenance: user-supplied IPPanel Digits 8.6.2 distribution; upstream Digits by UnitedOver.\n'
        'The supplied header retains Author: Ippanel to preserve source attribution.\n'
        'Ava integration and package assembly by Tarahan Shomal.\n'
        'Third-party source retains its original notices and separate terms;\n'
        'the GPL license in custom/ava/LICENSE applies to the Ava integration.\n'
        'This is not represented as an official UnitedOver or IPPanel release.\n'
        'Reference URL: ' + REFERENCE_URL + '\nSHA256: ' + REFERENCE_SHA256 + '\n'
    ).encode('utf-8')
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name, content in sorted(files.items()):
            info = zipfile.ZipInfo(name, TIMESTAMP)
            info.create_system = 3
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, content, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix('.zip.sha256').write_text(digest + '  ' + output.name + '\n', encoding='ascii')
    return output


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-zip', type=Path)
    parser.add_argument('--output', type=Path)
    parser.add_argument('--tag')
    args = parser.parse_args()
    version = package.version()
    if args.tag and args.tag != 'v' + version:
        parser.error('Tag must be v' + version)
    base = args.base_zip or download(ROOT / 'build/reference/digits-8.6.2.zip')
    output = args.output or ROOT / 'build' / ('ava-Digits-full-8.6.2-' + version + '.zip')
    if base.resolve() == output.resolve():
        parser.error('Output must not overwrite the source archive')
    build(base, output)
    print(output)
    print('SHA256: ' + hashlib.sha256(output.read_bytes()).hexdigest())


if __name__ == '__main__':
    main()
