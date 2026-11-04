<?php
namespace AvaDigits;

defined( 'ABSPATH' ) || exit;

final class Phone {
    public static function digits( $value ) {
        return strtr( $value, array_combine(
            preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
            str_split( '01234567890123456789' )
        ) );
    }

    public static function normalize( $phone, $country = '' ) {
        if ( ! is_scalar( $phone ) || ! is_scalar( $country ) ) {
            return new \WP_Error( 'ava_phone', 'شمارهٔ همراه معتبر نیست.' );
        }
        $country = trim( self::digits( (string) $country ) );
        if ( '' !== $country && ! in_array( $country, array( '98', '+98', '0098' ), true ) ) {
            return new \WP_Error( 'ava_country', 'درگاه آوا فقط شمارهٔ همراه ایران را می‌پذیرد.' );
        }
        $phone = self::digits( (string) $phone );
        $phone = preg_replace( '/[\s()\-\x{200c}\x{200e}\x{200f}]+/u', '', $phone );
        if ( ! is_string( $phone ) ) {
            return new \WP_Error( 'ava_phone', 'شمارهٔ همراه معتبر نیست.' );
        }
        if ( 0 === strpos( $phone, '+98' ) ) {
            $phone = '0' . substr( $phone, 3 );
        } elseif ( 0 === strpos( $phone, '0098' ) ) {
            $phone = '0' . substr( $phone, 4 );
        } elseif ( 12 === strlen( $phone ) && 0 === strpos( $phone, '98' ) ) {
            $phone = '0' . substr( $phone, 2 );
        } elseif ( 10 === strlen( $phone ) && '9' === substr( $phone, 0, 1 ) ) {
            $phone = '0' . $phone;
        }
        return preg_match( '/^09[0-9]{9}$/D', $phone ) ? $phone : new \WP_Error( 'ava_phone', 'شماره باید همراه ایران باشد؛ مانند ۰۹۱۲۳۴۵۶۷۸۹.' );
    }

    public static function code( $value ) {
        // Integer input would lose a leading zero; require the original string.
        if ( ! is_string( $value ) ) {
            return new \WP_Error( 'ava_code', 'قالب پیام دیجیتس باید فقط {OTP} و کد آن ۴ تا ۸ رقم باشد.' );
        }
        $code = self::digits( trim( $value ) );
        return preg_match( '/^[0-9]{4,8}$/D', $code ) ? $code : new \WP_Error( 'ava_code', 'قالب پیام دیجیتس باید فقط {OTP} و کد آن ۴ تا ۸ رقم باشد.' );
    }
}
