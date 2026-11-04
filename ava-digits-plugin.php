<?php
/**
 * Plugin Name: Ava Digits SMS OTP
 * Plugin URI: https://github.com/tarahanshomal/ava-Digits-plugin
 * Description: اتصال دیجیتس به سرویس رمز یک‌بارمصرف پیامکی آوا با قالب تأییدشده.
 * Version: 1.0.0
 * Author: طراحان شمال
 * Author URI: https://github.com/tarahanshomal
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ava-digits-plugin
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'AVA_DIGITS_VERSION', '1.0.0' );
define( 'AVA_DIGITS_FILE', __FILE__ );

foreach ( array( 'phone', 'settings', 'api', 'gateway', 'admin' ) as $ava_digits_class ) {
    require_once __DIR__ . '/includes/class-' . $ava_digits_class . '.php';
}
unset( $ava_digits_class );

// Register at plugin load: Digits can build its gateway list before plugins_loaded.
\AvaDigits\Gateway::init();
if ( is_admin() ) {
    \AvaDigits\Admin::init();
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), static function ( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ava-digits' ) ) . '">' . esc_html__( 'تنظیمات', 'ava-digits-plugin' ) . '</a>' );
    return $links;
} );
