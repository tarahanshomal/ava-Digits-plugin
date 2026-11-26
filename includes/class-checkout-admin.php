<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

/** Optional LearnPress integration and links to Digits' own form controls. */
final class CheckoutAdmin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
    }

    public static function menu() {
        add_options_page(
            'فرم پرداخت آوا و دیجیتس',
            'فرم و پرداخت آوا',
            'manage_options',
            'ava-digits-checkout',
            array( __CLASS__, 'page' )
        );
    }

    public static function register() {
        register_setting( 'ava_digits_checkout', Checkout::OPTION, array(
            'type' => 'array',
            'default' => Checkout::defaults(),
            'sanitize_callback' => array( Checkout::class, 'sanitize' ),
            'show_in_rest' => false,
        ) );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'دسترسی مدیر برای تنظیم فرم پرداخت لازم است.', 'ava-digits-plugin' ), '', array( 'response' => 403 ) );
        }

        $settings = Checkout::get();
        ?>
        <div class="wrap" dir="rtl">
            <h1>فرم پرداخت آوا و دیجیتس</h1>
            <p>در صفحهٔ پرداخت لرن‌پرس، فرم ورود و ثبت‌نام دیجیتس را به خریدار نشان دهید و متن و رنگ دکمهٔ آن را متناسب با سایت خود تنظیم کنید.</p>
            <?php if ( ! Checkout::available() ) : ?>
                <div class="notice notice-warning inline"><p>برای نمایش فرم، LearnPress و Digits نسخهٔ ۸٫۶٫۲ یا جدیدتر باید نصب و فعال باشند و توابع فرم دیجیتس در دسترس باشد. تا آن زمان فرم فعلی لرن‌پرس حفظ می‌شود.</p></div>
            <?php endif; ?>
            <?php settings_errors( Checkout::OPTION ); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( 'ava_digits_checkout' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">ورود در پرداخت لرن‌پرس</th>
                            <td>
                                <input type="hidden" name="ava_digits_checkout[enabled]" value="no">
                                <label for="ava-digits-checkout-enabled"><input id="ava-digits-checkout-enabled" type="checkbox" name="ava_digits_checkout[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?>> نمایش فرم دیجیتس برای خریداران واردنشده</label>
                                <p class="description">این قابلیت به‌صورت پیش‌فرض خاموش است. پس از ورود یا ثبت‌نام موفق، خریدار به صفحهٔ پرداخت برمی‌گردد.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">فقط شماره همراه در پرداخت</th>
                            <td>
                                <input type="hidden" name="ava_digits_checkout[phone_only]" value="no">
                                <label for="ava-digits-checkout-phone-only"><input id="ava-digits-checkout-phone-only" type="checkbox" name="ava_digits_checkout[phone_only]" value="yes" <?php checked( $settings['phone_only'], 'yes' ); ?>> ورود و ثبت‌نام پرداخت با شماره همراه و کد تأیید انجام شود</label>
                                <p class="description">این حالت برای فرم جدید Digits است. پرداخت برای حساب عادی فقط شماره همراه و کد تأیید می‌خواهد. حساب‌های دارای دسترسی ویژه یا مرحله احراز هویت اضافی باید از صفحه ورود سایت وارد شوند و سپس به پرداخت برگردند. تنظیمات ورود سایت، کپچا و فیلدهای سفارشی ضروری حفظ می‌شوند.</p>
                                <p class="description">خریدار فقط شماره و کد را وارد می‌کند؛ پیوند یا فرم جداگانهٔ ثبت‌نام نمایش داده نمی‌شود. پس از تأیید کد، شمارهٔ موجود وارد همان حساب می‌شود و برای شمارهٔ جدید یک حساب عادی ساخته می‌شود. ثبت‌نام دیجیتس باید فعال و نقش آن مشتری یا مشترک باشد. برای اجباری‌بودن ورود پیش از خرید، پرداخت مهمان LearnPress را خاموش کنید. فیلد سفارشی ضروری باید پیش از فعال‌کردن این مسیر توسط مدیر بررسی شود؛ اعتبارسنجی آن حذف نمی‌شود.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">فرم فعلی لرن‌پرس</th>
                            <td>
                                <input type="hidden" name="ava_digits_checkout[hide_native_forms]" value="no">
                                <label for="ava-digits-checkout-hide-native"><input id="ava-digits-checkout-hide-native" type="checkbox" name="ava_digits_checkout[hide_native_forms]" value="yes" <?php checked( $settings['hide_native_forms'], 'yes' ); ?>> هنگام نمایش دیجیتس، فرم ورود و ثبت‌نام لرن‌پرس نمایش داده نشود</label>
                                <p class="description">برای نمایش هر دو روش ورود کنار هم، این گزینه را بردارید. در حالت «فقط شماره همراه»، فرم‌های قبلی همیشه پنهان می‌شوند.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ava-digits-checkout-message">متن راهنمای خریدار</label></th>
                            <td>
                                <textarea class="large-text" rows="3" id="ava-digits-checkout-message" name="ava_digits_checkout[message]" maxlength="400"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
                                <p class="description">حداکثر ۴۰۰ نویسه، به‌صورت متن ساده. برای حذف راهنما، این فیلد را خالی بگذارید.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ava-digits-checkout-button">متن دکمهٔ ورود</label></th>
                            <td>
                                <input class="regular-text" id="ava-digits-checkout-button" type="text" name="ava_digits_checkout[button_label]" value="<?php echo esc_attr( $settings['button_label'] ); ?>" maxlength="80" required>
                                <p class="description">حداکثر ۸۰ نویسه؛ متن دکمهٔ بازکردن فرم دیجیتس.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ava-digits-checkout-color">رنگ دکمه</label></th>
                            <td>
                                <input id="ava-digits-checkout-color" type="color" name="ava_digits_checkout[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>">
                                <p class="description">رنگ دکمهٔ ورود در پرداخت. ظاهر داخل فرم را از تنظیمات ظاهر دیجیتس تغییر دهید.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( 'ذخیرهٔ تنظیمات فرم پرداخت' ); ?>
            </form>
            <div class="card">
                <h2>تنظیم فیلدها و ظاهر فرم دیجیتس</h2>
                <p>با روشن‌بودن «فقط شماره همراه در پرداخت»، فرم و روش ورودِ پرداخت برای همان مسیر تنظیم می‌شوند. سایر صفحه‌ها از تنظیمات اصلی دیجیتس استفاده می‌کنند. برای تغییر ظاهر پنجره، از طراحی فرم دیجیتس استفاده کنید.</p>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=digits_settings&tab=login' ) ); ?>">روش‌های ورود</a> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=digits_settings&tab=signup' ) ); ?>">فیلدهای ثبت‌نام</a> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=digits_settings&tab=native_form_style' ) ); ?>">رنگ، قلم و ظاهر فرم</a></p>
                <p>برای تغییر روش ارسال پیامکی یا صوتی، به <a href="<?php echo esc_url( admin_url( 'admin.php?page=digits_settings&tab=apisettings' ) ); ?>">درگاه پیامک دیجیتس</a> بروید. <a href="https://github.com/tarahanshomal/ava-Digits-plugin/blob/main/docs/SETUP-FA.md" target="_blank" rel="noopener noreferrer">راهنمای فارسی نصب و تنظیمات</a></p>
            </div>
        </div>
        <?php
    }
}
