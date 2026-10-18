<?php
/**
 * Plugin settings (one option) with defaults.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Settings {

	const OPTION = 'ce_mail_settings';

	public static function defaults() {
		return array(
			'imap_host'         => 'imap.purelymail.com',
			'imap_port'         => 993,
			'smtp_host'         => 'smtp.purelymail.com',
			'smtp_port'         => 465,
			'smtp_secure'       => 'ssl',
			'webmail_url'       => 'https://app.purelymail.com/',
			'resend_key'        => '',
			'resend_audience'   => '',
			'marketing_from'    => 'info@calvaryellicott.org',
			'marketing_name'    => 'Calvary Ellicott',
			'marketing_reply'   => '',
			'roles'             => array( 'editor' ),
			'demo'              => 1,
			'marketing_enabled' => 0,
			'page_size'         => 25,
			'org_name'          => 'Calvary Ellicott',
			'org_address'       => '2150 N. Ellicott Hwy, Calhan, CO 80808',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all, false );
		return $all;
	}

	/**
	 * Marketing is "configured" when Resend has a key, an audience, and a from address.
	 */
	public static function marketing_enabled() {
		return (bool) self::get( 'marketing_enabled' );
	}

	public static function marketing_ready() {
		$s = self::all();
		return ! empty( $s['resend_key'] ) && ! empty( $s['resend_audience'] ) && is_email( $s['marketing_from'] );
	}
}
