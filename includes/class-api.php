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
        $template = $s['template_id'] ?? '';
        if ( ( '' !== $template && ! Settings::valid_template( $template ) ) || ! Settings::valid_brand( $s['brand'] ?? '' ) ) {
            return new \WP_Error( 'ava_configuration', 'شناسهٔ قالب تأییدشده یا برند آوا تنظیم نیست.' );
        }
        $payload = array( 'phone' => $phone, 'code' => $code );
        // A blank template selects Ava's fixed immediate OTP text. Do not send
        // custom branding/parameters for this built-in system template.
        if ( '' !== $template ) {
            $payload['template_id'] = $template;
            if ( '' !== ( $s['brand'] ?? '' ) ) {
                $payload['params'] = array( 'brand' => $s['brand'] );
            }
        } else {
            $metadata = self::site_metadata();
            if ( is_wp_error( $metadata ) ) {
                return $metadata;
            }
            $payload = array_merge( $payload, $metadata );
        }
        return self::request( '/api/v1/sms-otp', $payload, $key, $s );
    }

    /** Public business name only; Ava owns the fixed standard OTP message. */
    private static function site_metadata() {
        $name = get_bloginfo( 'name' );
        if ( ! is_string( $name ) ) {
            return self::invalid_site_metadata();
        }
        $name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        // Reject links before removing markup or shortening the title. Never
        // disguise a domain as a business name or collect the site's home URL.
        if ( 1 === preg_match( '/(?:[A-Za-z][A-Za-z0-9+.-]*:\/\/|www\.)/i', $name ) ||
            1 === preg_match( '/(?<![\p{L}\p{N}_-])(?:[\p{L}\p{M}\p{N}_-]+[.。．｡])+(?:[\p{L}][\p{L}\p{M}\p{N}_-]{1,62})(?![\p{L}\p{N}_-])/u', $name ) ) {
            return self::invalid_site_metadata();
        }
        $name = preg_replace( '/[\p{Z}\s]+/u', ' ', wp_strip_all_tags( $name ) );
        if ( ! is_string( $name ) || '' === trim( $name ) || ! preg_match( '/^.{1,60}/us', trim( $name ), $match ) ) {
            return self::invalid_site_metadata();
        }
        // The API also validates the name. Only its public label is shared.
        return array( 'site_name' => $match[0] );
    }

    private static function invalid_site_metadata() {
        return new \WP_Error( 'ava_site_metadata', 'نام کسب‌وکار معتبر نیست؛ در تنظیمات عمومی وردپرس عنوانی بدون دامنه یا لینک وارد کنید.' );
    }

    public static function text( $value ) {
        if ( ! is_string( $value ) || 1 !== preg_match( '//u', $value ) || preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value ) ) {
            return new \WP_Error( 'ava_text', 'متن پیامک معتبر نیست.' );
        }
        $value = trim( $value );
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : preg_match_all( '/./us', $value );
        return $length >= 1 && $length <= 1000 ? $value : new \WP_Error( 'ava_text', 'متن پیامک باید بین ۱ تا ۱۰۰۰ نویسه باشد.' );
    }

    public static function send_voice( $phone, $code, $key, $settings = null ) {
        $phone = Phone::normalize( $phone );
        $code = Phone::code( $code );
        if ( is_wp_error( $phone ) || is_wp_error( $code ) ) {
            return is_wp_error( $phone ) ? $phone : $code;
        }
        $s = null === $settings ? Settings::get() : $settings;
        // Standard voice OTP accepts only phone and code; it has no template,
        // sender, brand, or arbitrary audio fields.
        return self::request( '/api/v1/voice-otp', array( 'phone' => $phone, 'code' => $code ), $key, $s );
    }

    public static function send_text( $phone, $message, $key, $settings = null ) {
        $phone = Phone::normalize( $phone );
        $message = self::text( $message );
        if ( is_wp_error( $phone ) || is_wp_error( $message ) ) {
            return is_wp_error( $phone ) ? $phone : $message;
        }
        $s = null === $settings ? Settings::get() : $settings;
        if ( ! Settings::valid_sender( $s['sender'] ?? '' ) ) {
            return new \WP_Error( 'ava_configuration', 'خط فرستندهٔ آوا معتبر نیست.' );
        }
        $payload = array( 'phone' => $phone, 'text' => $message );
        if ( '' !== ( $s['sender'] ?? '' ) ) {
            $payload['sender'] = $s['sender'];
        }
        return self::request( '/api/v1/sms', $payload, $key, $s );
    }

    private static function request( $path, $payload, $key, $s ) {
        $url = Settings::valid_url( $s['api_url'] ?? null );
        if ( 'yes' !== ( $s['enabled'] ?? '' ) ) {
            return new \WP_Error( 'ava_disabled', 'ارسال آوا برای دیجیتس خاموش است.' );
        }
        if ( ! $url || ! Settings::valid_key( $s['api_key'] ?? null ) ) {
            return new \WP_Error( 'ava_configuration', 'نشانی یا کلید API آوا تنظیم نیست.' );
        }
        if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9_-]{16,100}$/D', $key ) ) {
            return new \WP_Error( 'ava_idempotency', 'شناسهٔ یکتای درخواست معتبر نیست.' );
        }
        $response = wp_safe_remote_request( $url . $path, array(
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
            $scope = '/api/v1/sms' === $path ? 'sms:send' : ( '/api/v1/voice-otp' === $path ? 'otp:send' : 'sms_otp:send' );
            $coded_error = self::coded_error( $response, $status, $scope );
            if ( null !== $coded_error ) {
                return $coded_error;
            }
            $messages = array(
                400 => 'شماره، کد، متن یا برند درخواست معتبر نیست.',
                401 => 'کلید API نامعتبر یا منقضی است.',
                403 => 'حساب یا کلید API مجوز ' . $scope . ' ندارد.',
                404 => 'قالب تأییدشده یا مسیر API پیدا نشد.',
                409 => 'سرویس، خط یا قالب آماده نیست یا درخواست تعارض دارد؛ پنل آوا را بررسی کنید.',
                429 => 'سقف درخواست سرویس پر شده است.',
            );
            if ( ! isset( $messages[ $status ] ) ) {
                return self::uncertain();
            }
            return new \WP_Error( 'ava_http_' . $status, $messages[ $status ], array( 'uncertain' => false ) );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $state_field = '/api/v1/voice-otp' === $path ? 'state' : 'status';
        if ( ! is_array( $body ) || ! isset( $body['id'], $body[ $state_field ] ) ||
            ! is_string( $body['id'] ) || ! preg_match( '/^[A-Za-z0-9_-]{1,100}$/D', $body['id'] ) ||
            ! is_string( $body[ $state_field ] ) ) {
            return self::uncertain();
        }
        $accepted_states = '/api/v1/voice-otp' === $path ? array( 'pending', 'claimed', 'played' ) : array( 'pending', 'sending', 'accepted', 'sent', 'delivered' );
        if ( ! in_array( $body[ $state_field ], $accepted_states, true ) ) {
            return new \WP_Error( 'ava_not_accepted', 'وضعیت درخواست ارسال قابل تأیید نیست؛ پنل آوا را بررسی کنید.', array( 'uncertain' => true ) );
        }
        return array( 'id' => $body['id'], 'status' => $body[ $state_field ] );
    }

    /** Never display upstream text, even when it resembles a known error. */
    private static function coded_error( $response, $status, $scope ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || ! isset( $body['code'] ) || ! is_string( $body['code'] ) ) {
            return null;
        }
        $messages = array(
            'api_key_invalid' => array( array( 401, 403 ), 'کلید API نامعتبر یا منقضی است، یا به این دامنهٔ پنل تعلق ندارد.' ),
            'scope_missing' => array( array( 403 ), 'کلید API مجوز ' . $scope . ' ندارد؛ مجوز کلید را در پنل آوا بررسی کنید.' ),
            'forbidden' => array( array( 403 ), 'حساب یا کلید API اجازهٔ این درخواست را ندارد؛ دسترسی سرویس را در پنل آوا بررسی کنید.' ),
            'account_inactive' => array( array( 403 ), 'حساب آوا فعال نیست؛ وضعیت حساب را در پنل بررسی کنید.' ),
            'account_eligibility_required' => array( array( 403 ), 'حساب آوا مجوز استفاده از این سرویس را ندارد؛ وضعیت دسترسی سرویس را در پنل بررسی کنید.' ),
            'standard_otp_disabled' => array( array( 403 ), 'ارسال رمز استاندارد برای این حساب فعال نیست؛ تنظیمات سرویس را در پنل آوا بررسی کنید.' ),
            'service_disabled' => array( array( 409 ), 'سرویس ارسال انتخاب‌شده برای حساب آوا غیرفعال است.' ),
            'service_unavailable' => array( array( 409 ), 'سرویس ارسال آوا در حال حاضر آماده نیست؛ وضعیت سرویس را در پنل بررسی کنید.' ),
            'insufficient_credit' => array( array( 400 ), 'اعتبار حساب آوا کافی نیست؛ کیف پول را شارژ کنید.' ),
            'rate_limited' => array( array( 429 ), 'سقف درخواست سرویس پر شده است.' ),
            'validation_error' => array( array( 400 ), 'شماره، کد یا تنظیمات درخواست معتبر نیست.' ),
            'site_metadata_invalid' => array( array( 400 ), 'نام کسب‌وکار معتبر نیست؛ در تنظیمات عمومی وردپرس عنوانی بدون دامنه یا لینک وارد کنید.' ),
            'request_conflict' => array( array( 409 ), 'شناسهٔ درخواست با ارسال قبلی تعارض دارد؛ پیش از ارسال دوباره، گزارش پنل آوا را بررسی کنید.' ),
            'template_unavailable' => array( array( 404, 409 ), 'قالب اختصاصی تأییدشده آماده یا در دسترس نیست؛ قالب را در پنل آوا بررسی کنید.' ),
            'recipient_blocked' => array( array( 403, 409 ), 'ارسال به این گیرنده مجاز نیست.' ),
        );
        $code = $body['code'];
        if ( ! isset( $messages[ $code ] ) || ! in_array( $status, $messages[ $code ][0], true ) ) {
            return null;
        }
        return new \WP_Error( 'ava_' . $code, $messages[ $code ][1], array( 'uncertain' => false, 'http_status' => $status, 'backend_code' => $code ) );
    }

    private static function uncertain() {
        return new \WP_Error( 'ava_uncertain', 'پاسخ قطعی آوا دریافت نشد؛ پیش از ارسال دوباره، گزارش پنل آوا را بررسی کنید.', array( 'uncertain' => true ) );
    }
}
