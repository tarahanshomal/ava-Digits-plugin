<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

final class Api {
    public static function send( $phone, $code, $key, $settings = null ) {
        $phone = Phone::normalize( $phone );
        $code = Phone::code( $code );
        if ( is_wp_error( $phone ) || is_wp_error( $code ) ) {
            return is_wp_error( $phone ) ? $phone : $code;
        }
        $s = null === $settings ? Settings::get() : $settings;
        $url = Settings::valid_url( $s['api_url'] ?? null );
        if ( 'yes' !== ( $s['enabled'] ?? '' ) ) {
            return new \WP_Error( 'ava_disabled', 'ارسال آوا برای دیجیتس خاموش است.' );
        }
        if ( ! $url || ! Settings::valid_key( $s['api_key'] ?? null ) ||
            ! Settings::valid_template( $s['template_id'] ?? null ) || ! Settings::valid_brand( $s['brand'] ?? '' ) ) {
            return new \WP_Error( 'ava_configuration', 'نشانی، کلید API، شناسهٔ قالب تأییدشده یا برند آوا تنظیم نیست.' );
        }
        if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $key ) ) {
            return new \WP_Error( 'ava_idempotency', 'شناسهٔ یکتای درخواست معتبر نیست.' );
        }
        $payload = array( 'phone' => $phone, 'code' => $code, 'template_id' => $s['template_id'] );
        if ( '' !== ( $s['brand'] ?? '' ) ) {
            $payload['params'] = array( 'brand' => $s['brand'] );
        }
        $response = wp_safe_remote_request( $url . '/api/v1/sms-otp', array(
            'method' => 'POST', 'timeout' => 15, 'redirection' => 0, 'sslverify' => true,
            'reject_unsafe_urls' => true, 'limit_response_size' => 65536,
            'headers' => array( 'Authorization' => 'Bearer ' . $s['api_key'], 'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8', 'Idempotency-Key' => $key ),
            'user-agent' => 'AvaDigits/' . AVA_DIGITS_VERSION . '; WordPress',
            'body' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ), 'data_format' => 'body',
        ) );
        if ( is_wp_error( $response ) ) {
            return self::uncertain();
        }
        $status = wp_remote_retrieve_response_code( $response );
        if ( ! in_array( $status, array( 200, 202 ), true ) ) {
            $messages = array(
                400 => 'شماره، کد یا برند درخواست معتبر نیست.',
                401 => 'کلید API نامعتبر یا منقضی است.',
                403 => 'حساب یا کلید API مجوز sms_otp:send ندارد.',
                404 => 'قالب تأییدشده یا مسیر API پیدا نشد.',
                409 => 'سرویس یا قالب آماده نیست یا درخواست تعارض دارد؛ پنل آوا را بررسی کنید.',
                429 => 'سقف درخواست سرویس پر شده است.',
            );
            if ( ! isset( $messages[ $status ] ) ) {
                return self::uncertain();
            }
            return new \WP_Error( 'ava_http_' . $status, $messages[ $status ], array( 'uncertain' => false ) );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || ! isset( $body['id'], $body['status'] ) ||
            ! is_string( $body['id'] ) || ! preg_match( '/^[A-Za-z0-9_-]{1,100}$/D', $body['id'] ) ||
            ! is_string( $body['status'] ) ) {
            return self::uncertain();
        }
        if ( ! in_array( $body['status'], array( 'pending', 'sending', 'accepted', 'sent', 'delivered' ), true ) ) {
            return new \WP_Error( 'ava_not_accepted', 'وضعیت درخواست ارسال قابل تأیید نیست؛ پنل آوا را بررسی کنید.', array( 'uncertain' => true ) );
        }
        return array( 'id' => $body['id'], 'status' => $body['status'] );
    }

    private static function uncertain() {
        // Never expose upstream messages, response bodies, credentials or codes.
        return new \WP_Error( 'ava_uncertain', 'پاسخ قطعی آوا دریافت نشد؛ پیش از ارسال دوباره، گزارش پنل آوا را بررسی کنید.', array( 'uncertain' => true ) );
    }
}
