<?php
/**
 * Thin wrapper around the Resend API: transactional sends, audience contacts, broadcasts.
 */

defined( 'ABSPATH' ) || exit;

class CE_Mail_Resend {

	const API = 'https://api.resend.com';

	private static function request( $method, $path, $body = null ) {
		$key = CE_Mail_Settings::get( 'resend_key' );
		if ( ! $key ) {
			return new WP_Error( 'ce_mail_resend', 'Resend is not set up yet. Add the API key under Mail → Settings.' );
		}
		$args = array(
			'method'  => $method,
			'timeout' => 25,
			'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( self::API . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'Resend returned an error (' . $code . ').' );
			return new WP_Error( 'ce_mail_resend', $msg, array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	public static function send_email( $from, $to, $subject, $html, $text = '', $reply_to = '' ) {
		$body = array( 'from' => $from, 'to' => (array) $to, 'subject' => $subject, 'html' => $html );
		if ( $text ) {
			$body['text'] = $text;
		}
		if ( $reply_to ) {
			$body['reply_to'] = $reply_to;
		}
		return self::request( 'POST', '/emails', $body );
	}

	public static function audiences() {
		return self::request( 'GET', '/audiences' );
	}

	public static function create_audience( $name ) {
		return self::request( 'POST', '/audiences', array( 'name' => $name ) );
	}

	public static function add_contact( $audience, $email, $first = '', $last = '' ) {
		$body = array( 'email' => $email, 'unsubscribed' => false );
		if ( $first ) {
			$body['first_name'] = $first;
		}
		if ( $last ) {
			$body['last_name'] = $last;
		}
		return self::request( 'POST', '/audiences/' . rawurlencode( $audience ) . '/contacts', $body );
	}

	public static function remove_contact( $audience, $id_or_email ) {
		return self::request( 'DELETE', '/audiences/' . rawurlencode( $audience ) . '/contacts/' . rawurlencode( $id_or_email ) );
	}

	public static function create_broadcast( $audience, $from, $subject, $html, $text, $name, $reply_to = '' ) {
		$body = array( 'audience_id' => $audience, 'from' => $from, 'subject' => $subject, 'html' => $html, 'name' => $name );
		if ( $text ) {
			$body['text'] = $text;
		}
		if ( $reply_to ) {
			$body['reply_to'] = (array) $reply_to;
		}
		return self::request( 'POST', '/broadcasts', $body );
	}

	public static function send_broadcast( $id, $scheduled_at = '' ) {
		return self::request( 'POST', '/broadcasts/' . rawurlencode( $id ) . '/send', $scheduled_at ? array( 'scheduled_at' => $scheduled_at ) : array() );
	}

	public static function get_broadcast( $id ) {
		return self::request( 'GET', '/broadcasts/' . rawurlencode( $id ) );
	}

	public static function domains() {
		return self::request( 'GET', '/domains' );
	}

	/**
	 * Wrap campaign content in a simple, mobile-friendly email layout with the
	 * required unsubscribe link (Resend fills the placeholder per recipient).
	 */
	public static function wrap_html( $subject, $inner_html ) {
		$s    = CE_Mail_Settings::all();
		$org  = esc_html( $s['org_name'] );
		$addr = esc_html( $s['org_address'] );
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $subject ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f4f0e6;font-family:Arial,Helvetica,sans-serif;color:#1b2822">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f0e6"><tr><td align="center" style="padding:28px 12px">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden">'
			. '<tr><td style="background:#182820;color:#fff;padding:22px 28px;font-size:20px;font-weight:bold">' . $org . '</td></tr>'
			. '<tr><td style="padding:28px;font-size:16px;line-height:1.6">' . $inner_html . '</td></tr>'
			. '<tr><td style="padding:18px 28px;background:#fbfaf5;color:#647169;font-size:12px;line-height:1.6;border-top:1px solid #e6e2d6">'
			. $org . ' · ' . $addr . '<br>You are receiving this because you are part of our church family. <a href="{{{RESEND_UNSUBSCRIBE_URL}}}" style="color:#647169">Unsubscribe</a>.</td></tr>'
			. '</table></td></tr></table></body></html>';
	}
}
