/* Ava-only settings UI. Validation, authorization and nonce checks stay server-side. */
(() => {
    'use strict';
    const gateway = () => document.querySelector('#digit_tapp, select[name="digit_tapp"]');
    function sync() {
        document.querySelectorAll('.ava-digits-native, .ava-digits').forEach(panel => {
            const native = panel.classList.contains('ava-digits-native');
            const selected = gateway();
            const active = !native || !selected || String(selected.value) === '714032';
            if (native) {
                panel.hidden = !active;
                panel.querySelectorAll('input, select, textarea').forEach(input => {
                    if (input.id.endsWith('api-key') && input.dataset.avaConstant === 'yes') return;
                    input.disabled = !active;
                });
            }
            const mode = panel.querySelector('[id$="-mode"]');
            const value = mode ? mode.value : 'pattern';
            panel.querySelectorAll('[data-ava-mode]').forEach(row => {
                row.hidden = row.dataset.avaMode !== value;
            });
        });
    }
    const nativeStatus = (panel, message, state) => {
        const status = panel.querySelector('#ava-digits-native-save-status');
        if (!status) return;
        status.textContent = message;
        status.dataset.state = state;
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
    };
    async function saveNative(button) {
        const panel = button.closest('.ava-digits-native');
        if (!panel || panel.hidden || button.disabled) return;
        const params = new URLSearchParams();
        panel.querySelectorAll('input[name], select[name], textarea[name]').forEach(input => {
            const ownField = /^ava_digits_settings\[[a-z_]+\]$/.test(input.name);
            if (!ownField && !['ava_digits_native_present', 'ava_digits_native_nonce'].includes(input.name)) return;
            if (input.disabled || (['checkbox', 'radio'].includes(input.type) && !input.checked)) return;
            params.append(input.name, input.value);
        });
        if (!params.get('ava_digits_native_nonce') || params.get('ava_digits_native_present') !== '1') {
            nativeStatus(panel, 'نشست تنظیمات آماده نیست؛ صفحه را تازه کنید.', 'error');
            return;
        }
        let endpoint;
        try {
            const configured = window.ajaxurl || (window.digsetobj && window.digsetobj.ajax_url) || 'admin-ajax.php';
            endpoint = new URL(configured, window.location.href);
            if (endpoint.origin !== window.location.origin || !endpoint.pathname.endsWith('/admin-ajax.php')) throw new Error();
        } catch (_) {
            nativeStatus(panel, 'نشانی ذخیرهٔ وردپرس در دسترس نیست؛ صفحه را تازه کنید.', 'error');
            return;
        }
        // Never serialize Digits' outer form, purchase code or another gateway's credentials.
        params.set('action', 'ava_digits_save_settings');
        if (gateway() && String(gateway().value) === '714032') params.set('ava_digits_native_activate_gateway', '714032');
        const keyInput = panel.querySelector('[name="ava_digits_settings[api_key]"]');
        const submittedKey = keyInput ? keyInput.value : '';
        const deletingKey = params.get('ava_digits_settings[delete_api_key]') === 'yes';
        button.disabled = true;
        panel.setAttribute('aria-busy', 'true');
        nativeStatus(panel, 'در حال ذخیرهٔ تنظیمات آوا…', 'saving');
        try {
            const response = await fetch(endpoint.href, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: params.toString()
            });
            let result;
            try { result = await response.json(); } catch (_) { result = null; }
            if (!response.ok || !result || result.success !== true) {
                const message = result && result.data && typeof result.data.message === 'string'
                    ? result.data.message : 'تنظیمات ذخیره نشد؛ نشست و دسترسی مدیریت را بررسی و دوباره تلاش کنید.';
                nativeStatus(panel, message, 'error');
                return;
            }
            // A blank key preserves the stored value. Clear only the value just submitted.
            if (keyInput && keyInput.value === submittedKey) {
                keyInput.value = '';
                if (deletingKey) keyInput.placeholder = 'ava_live_…';
                else if (submittedKey) keyInput.placeholder = 'ذخیره شده؛ برای حفظ کلید خالی بگذارید';
            }
            const deleteInput = panel.querySelector('[name="ava_digits_settings[delete_api_key]"]');
            if (deleteInput && deletingKey) deleteInput.checked = false;
            const message = result.data && typeof result.data.message === 'string'
                ? result.data.message : 'تنظیمات آوا ذخیره شد.';
            nativeStatus(panel, message, 'success');
        } catch (_) {
            nativeStatus(panel, 'پاسخ ذخیره دریافت نشد؛ اتصال را بررسی و دوباره تلاش کنید.', 'error');
        } finally {
            button.disabled = false;
            panel.removeAttribute('aria-busy');
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest && event.target.closest('#ava-digits-native-save');
        if (!button) return;
        event.preventDefault();
        saveNative(button);
    });
    const markNativeChanged = event => {
        const input = event.target;
        const panel = input.closest && input.closest('.ava-digits-native');
        if (panel && input.name && input.name.startsWith('ava_digits_settings[') && panel.getAttribute('aria-busy') !== 'true') {
            nativeStatus(panel, 'تغییرات آوا هنوز ذخیره نشده‌اند؛ دکمهٔ ذخیرهٔ آوا را بزنید.', 'changed');
        }
    };
    document.addEventListener('input', markNativeChanged);
    document.addEventListener('change', event => {
        if (event.target.matches('#digit_tapp, select[name="digit_tapp"], [id$="-mode"]')) sync();
        markNativeChanged(event);
    });
    // Digits' enhanced selects emit jQuery events rather than native changes.
    if (window.jQuery) window.jQuery(document).on('change', '#digit_tapp, select[name="digit_tapp"], [id$="-mode"]', event => {
        sync();
        markNativeChanged(event);
    });
    if (window.jQuery) window.jQuery.ajaxPrefilter(options => {
        if (!options.data) return;
        let target;
        try { target = new URL(options.url, window.location.href); } catch (_) { return; }
        if (target.origin !== window.location.origin || !target.pathname.endsWith('/admin-ajax.php')) return;
        const params = typeof options.data === 'string' ? new URLSearchParams(options.data) : new URLSearchParams(options.data);
        if (params.get('action') !== 'digits_test_api' || params.get('gateway') !== '714032') return;
        const nonce = document.querySelector('[name="ava_digits_native_test_nonce"]');
        if (!nonce) return;
        params.set('ava_digits_native_test_nonce', nonce.value);
        options.data = params.toString();
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync);
    else sync();
})();
