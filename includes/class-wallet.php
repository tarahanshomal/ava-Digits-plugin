<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

/** Read-only account balance. Never reuse the paid-send request path. */
final class Wallet {
    const CACHE_SECONDS = 60;

    public static function get( $force = false ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'ava_wallet_forbidden', 'برای مشاهدهٔ مانده، دسترسی مدیر لازم است.' );
        }
        $s = Settings::get();
        $url = Settings::valid_url( $s['api_url'] ?? null );
        if ( ! $url || ! Settings::valid_key( $s['api_key'] ?? null ) ) {
            return new \WP_Error( 'ava_wallet_configuration', 'برای نمایش مانده، نشانی پنل و کلید API معتبر را ذخیره کنید.' );
        }
        // Scope snapshots to both credentials and origin, including wp-config keys.
        // The transient name and value contain no key or upstream response body.
        $cache_key = 'ava_digits_wallet_' . hash_hmac( 'sha256', $url . "\n" . $s['api_key'], wp_salt( 'auth' ) );
        if ( $force ) {
            delete_transient( $cache_key );
        } else {
            $cached = get_transient( $cache_key );
            if ( self::valid( $cached ) ) {
                return $cached;
            }
        }
        $response = wp_safe_remote_request( $url . '/api/v1/wallet', array(
            'method' => 'GET', 'timeout' => 5, 'redirection' => 0, 'sslverify' => true,
            'reject_unsafe_urls' => true, 'limit_response_size' => 16384,
            'headers' => array( 'Authorization' => 'Bearer ' . $s['api_key'], 'Accept' => 'application/json' ),
            'user-agent' => 'AvaDigits/' . AVA_DIGITS_VERSION . '; WordPress',
        ) );
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'ava_wallet_network', 'مانده دریافت نشد؛ اتصال به پنل آوا را بررسی و دوباره تازه‌سازی کنید.' );
        }
        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            $messages = array(
                401 => 'مانده دریافت نشد؛ کلید API نامعتبر یا منقضی است، یا به این پنل تعلق ندارد.',
                403 => 'مانده دریافت نشد؛ وضعیت حساب و مجوز کلید را بررسی کنید. کلید ارسال یا مجوز wallet:read لازم است.',
                404 => 'نمایش مانده در این پنل در دسترس نیست؛ نسخهٔ سرویس پنل را بررسی کنید.',
                429 => 'دریافت مانده موقتاً محدود شده است؛ کمی بعد دوباره تازه‌سازی کنید.',
            );
            return new \WP_Error( 'ava_wallet_http', $messages[ $status ] ?? 'مانده دریافت نشد؛ سرویس پنل موقتاً در دسترس نیست. دوباره تلاش کنید.' );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! self::valid( $body ) ) {
            return new \WP_Error( 'ava_wallet_response', 'پاسخ ماندهٔ پنل معتبر نیست؛ مقدار موجودی قابل نمایش نیست. دوباره تازه‌سازی کنید.' );
        }
        $snapshot = array_intersect_key( $body, array_flip( array( 'balance_irr', 'held_irr', 'available_irr', 'currency' ) ) );
        set_transient( $cache_key, $snapshot, self::CACHE_SECONDS );
        return $snapshot;
    }

    private static function valid( $value ) {
        if ( ! is_array( $value ) || 'IRR' !== ( $value['currency'] ?? null ) ||
            ! isset( $value['balance_irr'], $value['held_irr'], $value['available_irr'] ) ||
            ! is_int( $value['balance_irr'] ) || ! is_int( $value['held_irr'] ) || ! is_int( $value['available_irr'] ) ||
            $value['held_irr'] < 0 ) {
            return false;
        }
        // Integer overflow becomes a float in PHP; reject it rather than rounding.
        $available = $value['balance_irr'] - $value['held_irr'];
        return is_int( $available ) && $available === $value['available_irr'];
    }
}
