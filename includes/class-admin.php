<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

final class Admin {
    private static $native_error_status = 403;

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'digits_api_settings', array( __CLASS__, 'native_panel' ) );
        add_action( 'digits_save_settings_data', array( __CLASS__, 'native_save' ) );
        add_action( 'wp_ajax_ava_digits_save_settings', array( __CLASS__, 'native_ajax_save' ) );
        add_action( 'wp_ajax_ava_digits_refresh_wallet', array( __CLASS__, 'refresh_wallet' ) );
        add_action( 'wp_ajax_digits_test_api', array( __CLASS__, 'native_test_guard' ), 0 );
    }

    public static function menu() {
        add_options_page( 'آوا برای دیجیتس', 'آوا برای دیجیتس', 'manage_options', 'ava-digits', array( __CLASS__, 'page' ) );
    }

    public static function register() {
        register_setting( 'ava_digits', Settings::OPTION, array(
            'type' => 'array', 'sanitize_callback' => array( Settings::class, 'sanitize' ), 'show_in_rest' => false,
        ) );
    }

    public static function assets( $hook ) {
        if ( 'settings_page_ava-digits' === $hook || false !== strpos( (string) $hook, 'digits' ) ) {
            wp_enqueue_style( 'ava-digits-admin', plugins_url( 'assets/admin.css', AVA_DIGITS_FILE ), array(), AVA_DIGITS_VERSION );
            wp_enqueue_script( 'ava-digits-admin', plugins_url( 'assets/admin.js', AVA_DIGITS_FILE ), array( 'jquery' ), AVA_DIGITS_VERSION, true );
        }
    }

    public static function native_save() {
        self::$native_error_status = 403;
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['ava_digits_native_present'] ) ||
            '1' !== $_POST['ava_digits_native_present'] || ! isset( $_POST['ava_digits_native_nonce'] ) ||
            ! is_string( $_POST['ava_digits_native_nonce'] ) ||
            ! wp_verify_nonce( wp_unslash( $_POST['ava_digits_native_nonce'] ), 'ava_digits_native_save' ) ||
            ! isset( $_POST['ava_digits_settings'] ) || ! is_array( $_POST['ava_digits_settings'] ) ) {
            return false;
        }
        $errors_before = count( get_settings_errors( Settings::OPTION ) );
        $clean = Settings::sanitize( wp_unslash( $_POST['ava_digits_settings'] ) );
        if ( count( get_settings_errors( Settings::OPTION ) ) > $errors_before ) {
            self::$native_error_status = 400;
            return false;
        }
        // update_option delegates to add_option on the first save. Persist this
        // already-sanitized value once: a second blank-key sanitization could
        // otherwise undo an explicit key deletion on an existing installation.
        $callback = array( Settings::class, 'sanitize' );
        $filter = 'sanitize_option_' . Settings::OPTION;
        $priority = has_filter( $filter, $callback );
        if ( false !== $priority ) {
            remove_filter( $filter, $callback, $priority );
        }
        try {
            update_option( Settings::OPTION, $clean, false );
        } finally {
            if ( false !== $priority ) {
                add_filter( $filter, $callback, $priority );
            }
        }
        self::$native_error_status = 500;
        return get_option( Settings::OPTION, array() ) === $clean;
    }

    public static function native_ajax_save() {
        if ( self::native_save() ) {
            // The native panel is visible after selecting Ava. Persist only
            // that explicit gateway choice; do not save other Digits controls.
            if ( isset( $_POST['ava_digits_native_activate_gateway'] ) &&
                (string) Gateway::ID === $_POST['ava_digits_native_activate_gateway'] ) {
                update_option( 'digit_tapp', (string) Gateway::ID );
                if ( (string) Gateway::ID !== (string) get_option( 'digit_tapp' ) ) {
                    wp_send_json_error( array( 'message' => 'اتصال آوا ذخیره شد، اما انتخاب درگاه ثبت نشد؛ دوباره ذخیره کنید.' ), 500 );
                }
            }
            wp_send_json_success( array( 'message' => 'تنظیمات آوا ذخیره شد.' ) );
        } else {
            $message = 400 === self::$native_error_status
                ? 'تنظیمات ذخیره نشد؛ نشانی پنل، ساختار کلید، روش ارسال و مقادیر قالب یا خط فرستنده را بررسی کنید.'
                : 'تنظیمات ذخیره نشد؛ دسترسی مدیر لازم است. صفحه را تازه کنید و دوباره ذخیره کنید.';
            wp_send_json_error( array( 'message' => $message ), self::$native_error_status );
        }
    }

    public static function native_test_guard() {
        $gateway = $_POST['gateway'] ?? null;
        if ( ! is_scalar( $gateway ) || (string) Gateway::ID !== (string) $gateway ) {
            return;
        }
        // The supplied Digits test endpoint has no native CSRF nonce. Add a
        // nonce only for Ava's paid sends; other providers keep their own flow.
        $nonce = $_POST['ava_digits_native_test_nonce'] ?? null;
        if ( ! current_user_can( 'manage_options' ) || ! is_string( $nonce ) ||
            ! wp_verify_nonce( wp_unslash( $nonce ), 'ava_digits_native_test' ) ) {
            wp_die( esc_html__( 'برای آزمون هزینه‌دار آوا، صفحهٔ تنظیمات را تازه کنید و دوباره ارسال کنید.', 'ava-digits-plugin' ), '', array( 'response' => 403 ) );
        }
    }

    public static function native_panel() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <section class="ava-digits ava-digits-card ava-digits-native dig_sens_data" dir="rtl">
            <h2>اتصال درگاه آوا</h2>
            <p>تنظیمات آوا را همین‌جا وارد کنید و دکمهٔ «ذخیرهٔ تنظیمات آوا» را بزنید. این دکمه روش ارسال و اتصال آوا را جداگانه ذخیره می‌کند.</p>
            <input class="dig_sens_data" type="hidden" name="ava_digits_native_present" value="1">
            <input class="dig_sens_data" type="hidden" name="ava_digits_native_nonce" value="<?php echo esc_attr( wp_create_nonce( 'ava_digits_native_save' ) ); ?>">
            <input class="dig_sens_data" type="hidden" name="ava_digits_native_test_nonce" value="<?php echo esc_attr( wp_create_nonce( 'ava_digits_native_test' ) ); ?>">
            <?php settings_errors( Settings::OPTION ); self::fields( Settings::get(), true ); ?>
            <button type="button" class="button button-primary" id="ava-digits-native-save">ذخیرهٔ تنظیمات آوا</button>
            <p id="ava-digits-native-save-status" role="status" aria-live="polite"></p>
            <?php self::wallet_panel(); ?>
            <?php self::help(); ?>
        </section>
        <?php
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $s = Settings::get();
        ?>
        <div class="wrap ava-digits" dir="rtl">
            <div class="ava-digits-header"><div><span class="ava-digits-eyebrow">AVA / DIGITS</span><h1>آوا برای دیجیتس</h1><p>ارسال رمز دیجیتس با پیامک یا تماس صوتی آوا.</p></div><span class="ava-digits-badge"><?php echo 'yes' === $s['enabled'] ? 'درگاه فعال است' : 'درگاه خاموش است'; ?></span></div>
            <?php settings_errors( Settings::OPTION ); ?>
            <?php self::wallet_panel(); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( 'ava_digits' ); ?>
                <section class="ava-digits-card">
                    <h2>اتصال به آوا</h2>
                    <?php self::fields( $s, false ); submit_button( 'ذخیرهٔ تنظیمات' ); ?>
                </section>
            </form>
            <section class="ava-digits-card"><?php self::help(); ?></section>
            <p class="ava-digits-footer">آوا · طراحان شمال · <a href="https://github.com/tarahanshomal/ava-Digits-plugin" target="_blank" rel="noopener noreferrer">راهنما و نسخه‌ها</a></p>
        </div>
        <?php
    }

    public static function refresh_wallet() {
        $nonce = $_POST['nonce'] ?? null;
        if ( ! current_user_can( 'manage_options' ) || ! is_string( $nonce ) ||
            ! wp_verify_nonce( wp_unslash( $nonce ), 'ava_digits_refresh_wallet' ) ) {
            wp_send_json_error( array( 'message' => 'دریافت مانده مجاز نیست؛ صفحه را تازه کنید و با دسترسی مدیر تلاش کنید.' ), 403 );
        }
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            wp_send_json_error( array( 'message' => 'روش تازه‌سازی مانده معتبر نیست؛ از دکمهٔ تازه‌سازی استفاده کنید.' ), 405 );
        }
        $wallet = Wallet::get( true );
        if ( is_wp_error( $wallet ) ) {
            wp_send_json_error( array( 'message' => $wallet->get_error_message() ), 502 );
        }
        ob_start();
        self::wallet_content( $wallet );
        wp_send_json_success( array( 'html' => ob_get_clean() ) );
    }

    private static function wallet_panel() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?><section class="ava-digits-wallet" aria-label="ماندهٔ حساب آوا" dir="rtl" data-wallet-nonce="<?php echo esc_attr( wp_create_nonce( 'ava_digits_refresh_wallet' ) ); ?>">
            <?php self::wallet_content( Wallet::get() ); ?>
        </section><?php
    }

    private static function wallet_content( $wallet ) {
        $url = Settings::valid_url( Settings::get()['api_url'] ?? null );
        ?><h2>ماندهٔ حساب آوا</h2>
        <div class="ava-digits-wallet-values" role="status" aria-live="polite">
            <?php if ( is_wp_error( $wallet ) ) : ?>
                <p class="ava-digits-wallet-error"><?php echo esc_attr( $wallet->get_error_message() ); ?></p>
            <?php else : ?>
                <p class="ava-digits-wallet-available">ماندهٔ قابل مصرف: <strong dir="ltr"><?php echo esc_attr( self::amount( $wallet['available_irr'] ) ); ?></strong> ریال</p>
                <p>موجودی کل: <bdi><?php echo esc_attr( self::amount( $wallet['balance_irr'] ) ); ?></bdi> ریال · رزروشده: <bdi><?php echo esc_attr( self::amount( $wallet['held_irr'] ) ); ?></bdi> ریال</p>
            <?php endif; ?>
        </div>
        <p class="description">ماندهٔ حساب با تنظیمات ذخیره‌شده؛ این نمایش تا ۶۰ ثانیه ذخیره می‌شود. تازه‌سازی هزینه‌ای ندارد.</p>
        <button type="button" class="button ava-digits-wallet-refresh">تازه‌سازی مانده</button>
        <?php if ( $url ) : ?><a class="button" href="<?php echo esc_url( $url . '/panel#wallet' ); ?>" target="_blank" rel="noopener noreferrer">شارژ حساب آوا</a><?php endif;
    }

    private static function amount( $value ) {
        // Group integer digits without converting money through floating point.
        return preg_replace( '/\B(?=(\d{3})+(?!\d))/', ',', (string) $value );
    }

    private static function fields( $s, $native ) {
        $prefix = $native ? 'ava-digits-native-' : 'ava-digits-';
        ?>
        <p>در روش الگوی پیامکی، کلید به مجوز <code>sms_otp:send</code> نیاز دارد؛ در روش متنی، <code>sms:send</code> و در روش رمز صوتی، <code>otp:send</code>. فعال‌بودن سرویس و اعتبار حساب برای همهٔ روش‌ها لازم است.</p>
        <table class="form-table" role="presentation"><tbody>
            <tr><th scope="row"><label for="<?php echo esc_attr( $prefix . 'enabled' ); ?>">ارسال آوا</label></th><td><label><input class="dig_sens_data" id="<?php echo esc_attr( $prefix . 'enabled' ); ?>" type="checkbox" name="ava_digits_settings[enabled]" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>> درگاه آوا برای ارسال رمزهای دیجیتس فعال شود</label><p class="description">پس از ذخیرهٔ اتصال، درگاه «آوا / Ava OTP (پیامکی و صوتی)» را در دیجیتس انتخاب کنید.</p></td></tr>
            <tr><th scope="row"><label for="<?php echo esc_attr( $prefix . 'mode' ); ?>">روش ارسال</label></th><td><select class="regular-text dig_sens_data" id="<?php echo esc_attr( $prefix . 'mode' ); ?>" name="ava_digits_settings[mode]"><option value="pattern" <?php echo 'pattern' === $s['mode'] ? 'selected' : ''; ?>>الگوی رمز پیامکی (پیشنهادی)</option><option value="text" <?php echo 'text' === $s['mode'] ? 'selected' : ''; ?>>پیامک متنی با متن دیجیتس</option><option value="voice" <?php echo 'voice' === $s['mode'] ? 'selected' : ''; ?>>رمز صوتی با تماس آوا</option></select><p class="description">در روش الگو، قالب آوا متن را می‌سازد. در روش متنی، متن دیجیتس ارسال می‌شود. در روش صوتی، آوا کد را با تماس می‌خواند و به قالب متنی نیاز ندارد.</p></td></tr>
            <tr><th scope="row"><label for="<?php echo esc_attr( $prefix . 'api-url' ); ?>">نشانی پنل آوا</label></th><td><input class="regular-text code dig_sens_data" id="<?php echo esc_attr( $prefix . 'api-url' ); ?>" dir="ltr" type="url" name="ava_digits_settings[api_url]" value="<?php echo esc_attr( $s['api_url'] ); ?>" dig-optional="1"><p class="description">فقط دامنهٔ HTTPS پنل؛ بدون مسیر <code>/api/v1</code>. برای پنل نمایندگی، دامنهٔ همان پنل را وارد کنید.</p></td></tr>
            <tr><th scope="row"><label for="<?php echo esc_attr( $prefix . 'api-key' ); ?>">کلید API</label></th><td><input class="regular-text code dig_sens_data" id="<?php echo esc_attr( $prefix . 'api-key' ); ?>" dir="ltr" type="password" name="ava_digits_settings[api_key]" value="" autocomplete="new-password" spellcheck="false" dig-optional="1" <?php if ( defined( 'AVA_DIGITS_API_KEY' ) ) { echo 'disabled data-ava-constant="yes"'; } ?> placeholder="<?php echo esc_attr( empty( $s['api_key'] ) ? 'ava_live_…' : 'ذخیره شده؛ برای حفظ کلید خالی بگذارید' ); ?>"><p class="description"><?php echo defined( 'AVA_DIGITS_API_KEY' ) ? 'کلید از ثابت AVA_DIGITS_API_KEY در wp-config.php خوانده می‌شود.' : 'کلید ذخیره‌شده نمایش داده نمی‌شود. فیلد خالی، کلید قبلی را نگه می‌دارد.'; ?></p><?php if ( ! defined( 'AVA_DIGITS_API_KEY' ) && ! empty( $s['api_key'] ) ) : ?><label><input class="dig_sens_data" type="checkbox" name="ava_digits_settings[delete_api_key]" value="yes" dig-optional="1"> حذف کلید ذخیره‌شده</label><?php endif; ?></td></tr>
            <tr data-ava-mode="pattern"><th scope="row"><label for="<?php echo esc_attr( $prefix . 'template-id' ); ?>">شناسهٔ قالب آوا</label></th><td><input class="regular-text code dig_sens_data" id="<?php echo esc_attr( $prefix . 'template-id' ); ?>" dir="ltr" name="ava_digits_settings[template_id]" value="<?php echo esc_attr( $s['template_id'] ); ?>" maxlength="100" dig-optional="1"><p class="description"><strong>خالی: الگوی آمادهٔ آوا با نام کسب‌وکار و کد؛ بدون نیاز به تأیید هر رمز.</strong> نام از عنوان سایت در تنظیمات عمومی وردپرس خوانده می‌شود؛ دامنه ارسال نمی‌شود. برای متن اختصاصی، شناسهٔ قالب تأییدشده را وارد کنید؛ متغیر رمز در آن ثابت و برابر <code>{code}</code> است. خط فرستنده را آوا از قالب انتخاب می‌کند.</p></td></tr>
            <tr data-ava-mode="pattern"><th scope="row"><label for="<?php echo esc_attr( $prefix . 'brand' ); ?>">برند قالب</label></th><td><input class="regular-text dig_sens_data" id="<?php echo esc_attr( $prefix . 'brand' ); ?>" name="ava_digits_settings[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>" maxlength="80" dig-optional="1"><p class="description">فقط برای قالب اختصاصی؛ اختیاری و دقیقاً برابر برند تأییدشدهٔ قالب. در الگوی آمادهٔ فوری، این مقدار ارسال نمی‌شود.</p></td></tr>
            <tr data-ava-mode="text"><th scope="row"><label for="<?php echo esc_attr( $prefix . 'sender' ); ?>">خط فرستنده</label></th><td><input class="regular-text code dig_sens_data" id="<?php echo esc_attr( $prefix . 'sender' ); ?>" dir="ltr" name="ava_digits_settings[sender]" value="<?php echo esc_attr( $s['sender'] ); ?>" maxlength="64" dig-optional="1"><p class="description">یکی از خطوط مجاز حساب آوا؛ خالی بگذارید تا آوا از خط پیش‌فرض استفاده کند.</p></td></tr>
        </tbody></table>
        <?php
    }

    private static function help() {
        ?>
        <h3>راه‌اندازی در دیجیتس</h3>
        <p>درگاه پیامک را روی <strong>آوا / Ava OTP (پیامکی و صوتی)</strong> بگذارید. برای پیامک استاندارد، روش الگو را انتخاب کنید و شناسهٔ قالب را خالی بگذارید. نام کسب‌وکار از عنوان سایت در تنظیمات عمومی وردپرس خوانده می‌شود؛ دامنه جمع‌آوری یا ارسال نمی‌شود. متن ثابت را آوا می‌سازد و تأیید جداگانهٔ هر رمز لازم نیست:</p>
        <pre>نام کسب و کار - {NAME}
کد تایید - {OTP}</pre>
        <p>نام کسب‌وکار باید عمومی، تک‌خطی و حداکثر ۶۰ نویسه باشد؛ دامنه، URL، شمارهٔ تماس یا متن تبلیغاتی در آن قرار ندهید. اگر عنوان سایت خودش دامنه یا لینک باشد، عنوان درست کسب‌وکار را در تنظیمات عمومی وردپرس وارد کنید؛ افزونه لینک را مخفی یا تغییرشکل نمی‌دهد. در دیجیتس ۸٫۶٫۲، کد اصلی جدا از متن به آوا می‌رسد و تغییر متن دیجیتس، متن استاندارد آوا را تغییر نمی‌دهد. برای نسخه‌های قدیمی که کد جداگانه نمی‌دهند، متن دیجیتس را فقط <code dir="ltr">{OTP}</code> قرار دهید.</p>
        <p>در روش متنی، متن پیام دیجیتس را با <code dir="ltr">{OTP}</code>، <code dir="ltr">{NAME}</code> و <code dir="ltr">{DOMAIN}</code> تنظیم کنید؛ پس از جایگزینی متغیرها، همان متن ارسال می‌شود. از طول ۴ تا ۸ رقم برای رمز و پیش‌شمارهٔ ایران <code dir="ltr">+98</code> استفاده کنید.</p>
        <p>برای رمز صوتی، روش «رمز صوتی با تماس آوا» را انتخاب کنید؛ فقط شماره و کد ۴ تا ۸ رقمی ارسال می‌شود. صدای خواندن کد را آوا آماده می‌کند؛ قالب متنی، خط پیامکی یا فایل صوتی دلخواه در این روش فرستاده نمی‌شود.</p>
        <p>آزمون ارسال در دیجیتس <strong>پیامک یا تماس واقعی و هزینه‌دار</strong> ایجاد می‌کند. پذیرش درخواست، دریافت یا شنیدن کد را ثابت نمی‌کند؛ نتیجه را در گزارش پنل آوا بررسی کنید. نتیجهٔ نامشخص خودکار تکرار نمی‌شود؛ انتخاب «ارسال مجدد» درخواست تازه و احتمال هزینهٔ تازه ایجاد می‌کند.</p>
        <p class="description">افزونه گزارش محلی شامل کد یا گیرنده نمی‌سازد. دیجیتس ممکن است گزارش درخواست خود را نگه دارد؛ در روش الگو و صوتی روی دیجیتس ۸٫۶٫۲ به بعد، متن گزارش آوا با <code>[Ava OTP]</code> پوشیده می‌شود. در روش متنی، تنظیم نگهداری گزارش‌های دیجیتس را مطابق سیاست حریم خصوصی سایت مدیریت کنید. تولید و بررسی رمز، انقضا و محدودیت درخواست بر عهدهٔ دیجیتس است.</p>
        <?php
    }
}
