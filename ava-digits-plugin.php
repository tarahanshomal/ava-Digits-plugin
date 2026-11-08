<?php
/**
 * Plugin Name: Ava Digits SMS & Voice OTP
 * Plugin URI: https://github.com/tarahanshomal/ava-Digits-plugin
 * Description: رمز پیامکی و صوتی آوا برای دیجیتس با تنظیمات داخلی و ارسال متنی.
 * Version: 1.3.0
 * Author: طراحان شمال
 * Author URI: https://github.com/tarahanshomal
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ava-digits-plugin
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

// A full Digits bundle embeds this same adapter; load it once when both are present.
if ( defined( 'AVA_DIGITS_VERSION' ) ) {
    return;
}

define( 'AVA_DIGITS_VERSION', '1.3.0' );
define( 'AVA_DIGITS_FILE', __FILE__ );

foreach ( array( 'phone', 'settings', 'api', 'wallet', 'gateway', 'admin' ) as $ava_digits_class ) {
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
