<?php
/**
 * Encrypts mailbox passwords at rest. Key is derived from the site's salts, so it
 * never leaves wp-config.php. Prefers libsodium, falls back to OpenSSL AES-256-GCM.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Crypto {

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|ce-mail|' . wp_salt( 'secure_auth' ), true );
	}

	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
		}
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return 'o1:' . base64_encode( $iv . $tag . $ct );
	}

	public static function decrypt( $stored ) {
		if ( ! is_string( $stored ) || strlen( $stored ) < 4 ) {
			return '';
		}
		$scheme = substr( $stored, 0, 3 );
		$raw    = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw ) {
			return '';
		}
		if ( 's1:' === $scheme && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ct    = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$out   = sodium_crypto_secretbox_open( $ct, $nonce, self::key() );
			return false === $out ? '' : $out;
		}
		if ( 'o1:' === $scheme ) {
			$iv  = substr( $raw, 0, 12 );
			$tag = substr( $raw, 12, 16 );
			$ct  = substr( $raw, 28 );
			$out = openssl_decrypt( $ct, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
			return false === $out ? '' : $out;
		}
		return '';
	}
}
