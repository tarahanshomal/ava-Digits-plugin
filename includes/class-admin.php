<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
    }

    public static function menu() {
        add_options_page( 'آوا برای دیجیتس', 'آوا برای دیجیتس', 'manage_options', 'ava-digits', array( __CLASS__, 'page' ) );
    }

    public static function register() {
        // WordPress options.php enforces manage_options and the settings nonce.
        register_setting( 'ava_digits', Settings::OPTION, array(
            'type' => 'array', 'sanitize_callback' => array( Settings::class, 'sanitize' ), 'show_in_rest' => false,
        ) );
    }

    public static function assets( $hook ) {
        if ( 'settings_page_ava-digits' === $hook ) {
            wp_enqueue_style( 'ava-digits-admin', plugins_url( 'assets/admin.css', AVA_DIGITS_FILE ), array(), AVA_DIGITS_VERSION );
        }
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $s = Settings::get();
        ?>
        <div class="wrap ava-digits" dir="rtl">
            <div class="ava-digits-header"><div><span class="ava-digits-eyebrow">AVA / DIGITS</span><h1>آوا برای دیجیتس</h1><p>رمز ورود کاربران، با قالب تأییدشدهٔ پیامکی آوا.</p></div><span class="ava-digits-badge"><?php echo 'yes' === $s['enabled'] ? 'درگاه فعال است' : 'درگاه خاموش است'; ?></span></div>
            <?php settings_errors( Settings::OPTION ); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( 'ava_digits' ); ?>
                <section class="ava-digits-card">
                    <h2>اتصال به آوا</h2>
                    <p>در پنل آوا یک قالب رمز متنی تأییدشده و کلید API با مجوز <code>sms_otp:send</code> آماده کنید. فعال‌بودن سرویس و اعتبار حساب برای ارسال لازم است.</p>
                    <table class="form-table" role="presentation"><tbody>
                        <tr><th scope="row">ارسال رمز</th><td><label><input type="checkbox" name="ava_digits_settings[enabled]" value="yes" <?php checked( $s['enabled'], 'yes' ); ?>> درگاه آوا برای ارسال رمزهای دیجیتس فعال شود</label><p class="description">پس از ذخیرهٔ تنظیمات و انتخاب آوا در دیجیتس، این گزینه را فعال کنید.</p></td></tr>
                        <tr><th scope="row"><label for="ava-digits-url">نشانی پنل آوا</label></th><td><input id="ava-digits-url" class="regular-text code" dir="ltr" type="url" name="ava_digits_settings[api_url]" value="<?php echo esc_attr( $s['api_url'] ); ?>" required><p class="description">فقط دامنهٔ HTTPS پنل؛ بدون مسیر <code>/api/v1</code>. برای پنل نمایندگی، دامنهٔ همان پنل را وارد کنید.</p></td></tr>
                        <tr><th scope="row"><label for="ava-digits-key">کلید API</label></th><td><input id="ava-digits-key" class="regular-text code" dir="ltr" type="password" name="ava_digits_settings[api_key]" value="" autocomplete="new-password" spellcheck="false" <?php disabled( defined( 'AVA_DIGITS_API_KEY' ) ); ?> placeholder="<?php echo esc_attr( empty( $s['api_key'] ) ? 'ava_live_…' : 'ذخیره شده؛ برای حفظ کلید خالی بگذارید' ); ?>"><p class="description"><?php echo defined( 'AVA_DIGITS_API_KEY' ) ? 'کلید از ثابت AVA_DIGITS_API_KEY در wp-config.php خوانده می‌شود.' : 'کلید ذخیره‌شده نمایش داده نمی‌شود. فیلد خالی، کلید قبلی را نگه می‌دارد.'; ?></p><?php if ( ! defined( 'AVA_DIGITS_API_KEY' ) && ! empty( $s['api_key'] ) ) : ?><label><input type="checkbox" name="ava_digits_settings[delete_api_key]" value="yes"> حذف کلید ذخیره‌شده</label><?php endif; ?></td></tr>
                        <tr><th scope="row"><label for="ava-digits-template">شناسهٔ قالب آوا</label></th><td><input id="ava-digits-template" class="regular-text code" dir="ltr" name="ava_digits_settings[template_id]" value="<?php echo esc_attr( $s['template_id'] ); ?>" maxlength="100" required><p class="description">شناسهٔ قالب رمز متنی تأییدشده از پنل آوا. قالب آوا باید متغیر <code>{code}</code> داشته باشد؛ خط فرستنده از همین قالب انتخاب می‌شود.</p></td></tr>
                        <tr><th scope="row"><label for="ava-digits-brand">برند قالب</label></th><td><input id="ava-digits-brand" class="regular-text" name="ava_digits_settings[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>" maxlength="80"><p class="description">اختیاری؛ خالی بماند تا برند تأییدشدهٔ قالب استفاده شود. اگر وارد می‌کنید، دقیقاً با برند قالب یکسان باشد.</p></td></tr>
                    </tbody></table>
                    <?php submit_button( 'ذخیرهٔ تنظیمات' ); ?>
                </section>
            </form>
            <section class="ava-digits-card">
                <h2>سه مرحله در دیجیتس</h2>
                <ol><li>افزونهٔ اصلی <strong>Digits</strong> باید جداگانه نصب و فعال باشد. در تنظیمات درگاه پیامک دیجیتس، <strong>آوا / Ava SMS OTP</strong> را انتخاب کنید.</li><li>فیلد «اتصال آوا» را روی <strong>استفاده از تنظیمات ← آوا برای دیجیتس</strong> بگذارید و تنظیمات دیجیتس را ذخیره کنید.</li><li>قالب پیام دیجیتس را دقیقاً <code dir="ltr">{OTP}</code> قرار دهید و طول رمز را بین ۴ تا ۸ رقم انتخاب کنید. متن کامل پیامک در قالب تأییدشدهٔ آوا ساخته می‌شود.</li></ol>
                <p>پیش‌شمارهٔ کشور باید ایران <code dir="ltr">+98</code> باشد. آزمون ارسال در دیجیتس <strong>پیامک واقعی و هزینه‌دار</strong> ایجاد می‌کند. کد آزمایش نیز باید ۴ تا ۸ رقم باشد.</p>
                <p>پذیرش درخواست، تحویل پیامک به گوشی را ثابت نمی‌کند؛ نتیجه را در گزارش رمز متنی پنل آوا بررسی کنید. نتیجهٔ نامشخص خودکار تکرار نمی‌شود؛ فشردن «ارسال مجدد» در دیجیتس درخواست جدید و احتمال هزینهٔ تازه ایجاد می‌کند.</p>
                <p class="description">افزونه کد، شمارهٔ گیرنده یا پاسخ کامل سرویس را در گزارش محلی ذخیره نمی‌کند. حفاظت فرم ورود، محدودیت درخواست، تولید و بررسی رمز بر عهدهٔ دیجیتس است.</p>
            </section>
            <p class="ava-digits-footer">آوا · طراحان شمال · <a href="https://github.com/tarahanshomal/ava-Digits-plugin" target="_blank" rel="noopener noreferrer">راهنما و نسخه‌ها</a></p>
        </div>
        <?php
    }
}
