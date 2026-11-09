<?php
/**
 * Plugin Name: Harbourview mail delivery
 * Description: Sends WordPress mail through an SMTP server instead of PHP mail(), which this host does not support.
 *
 * Locally SMTP_HOST points at Mailpit (a mail catcher). On a real job this
 * would be the client's transactional mail service with SPF and DKIM set up
 * for their domain, and credentials stored outside the repo.
 */

add_action( 'phpmailer_init', function ( $mailer ) {
	$host = getenv( 'SMTP_HOST' );
	if ( ! $host ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host        = $host;
	$mailer->Port        = (int) ( getenv( 'SMTP_PORT' ) ?: 25 );
	$mailer->SMTPAuth    = (bool) getenv( 'SMTP_USER' );
	$mailer->Username    = (string) getenv( 'SMTP_USER' );
	$mailer->Password    = (string) getenv( 'SMTP_PASS' );
	$mailer->SMTPAutoTLS = (bool) getenv( 'SMTP_TLS' );
} );

// Send as an address on the bakery's own domain, never as gmail.com.
add_filter( 'wp_mail_from', function () {
	return 'website@harbourview-bakery.example';
} );
add_filter( 'wp_mail_from_name', function () {
	return 'Harbourview Bakery website';
} );
