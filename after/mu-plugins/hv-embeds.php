<?php
/**
 * Plugin Name: Harbourview embeds
 * Description: New features added without a rebuild: a support chat widget and a reserved spot for online ordering.
 */

/*
 * Support chat widget (from the docs-chat project).
 *
 * Locally this embeds the existing "northwind" demo bot, which answers from
 * a different sample company's documents. On a real job the bakery would get
 * its own bot config built from its menu, hours, cake order FAQ and policies,
 * and only the data-bot value below would change.
 *
 * Set HV_SUPPORT_BOT_SRC to an empty string in wp-config.php to switch it off.
 */
add_action( 'wp_footer', function () {
	$src = defined( 'HV_SUPPORT_BOT_SRC' ) ? HV_SUPPORT_BOT_SRC : 'http://localhost:3010/embed/widget.js';
	$bot = defined( 'HV_SUPPORT_BOT_ID' ) ? HV_SUPPORT_BOT_ID : 'northwind';
	$api = defined( 'HV_SUPPORT_BOT_API' ) ? HV_SUPPORT_BOT_API : 'http://localhost:8010';
	if ( ! $src ) {
		return;
	}
	printf(
		'<script src="%s" data-bot="%s" data-api="%s" defer></script>' . "\n",
		esc_url( $src ),
		esc_attr( $bot ),
		esc_url( $api )
	);
}, 50 );

/*
 * [hv_booking] : placeholder for the online ordering / "Book now" widget.
 *
 * To go live, replace the fallback markup below with the ordering widget's
 * <script> tag and keep the wrapper id so existing "#book" links keep working.
 */
add_shortcode( 'hv_booking', function () {
	ob_start();
	?>
	<section id="book" class="hv-booking" aria-labelledby="hv-booking-title" data-hv-booking-slot="online-ordering">
		<!-- ONLINE ORDERING WIDGET GOES HERE (embed script). -->
		<h2 id="hv-booking-title">Order ahead</h2>
		<p>Online ordering for pick-up is coming soon.</p>
		<p>Until then, call <a href="tel:+14165550142">416-555-0142</a> or <a href="/custom-cakes/#hv-cake-form">send a cake order request</a>.</p>
	</section>
	<?php
	return ob_get_clean();
} );
