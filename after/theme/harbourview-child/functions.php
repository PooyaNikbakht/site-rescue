<?php
/**
 * Harbourview child theme after the rescue.
 *
 * Compare with before/theme/harbourview-child/functions.php to see what was
 * removed. Site-wide behaviour (SEO, performance, mail, embeds) lives in
 * must-use plugins under after/mu-plugins so it survives a theme change.
 */

add_action( 'wp_enqueue_scripts', function () {
	// Only the child stylesheet. The parent style.css was a duplicate, the
	// Google Fonts link and @import are replaced by three self-hosted files.
	wp_enqueue_style( 'hv-child', get_stylesheet_uri(), array( 'oceanwp-style' ), (string) filemtime( get_stylesheet_directory() . '/style.css' ) );
}, 20 );

// Preload the two fonts used above the fold so text renders in its final face quickly.
add_action( 'wp_head', function () {
	$dir = get_stylesheet_directory_uri() . '/fonts/';
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $dir . 'playfair-display-latin-700-normal.woff2' ) );
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $dir . 'lato-latin-400-normal.woff2' ) );
}, 2 );

// The holiday announcement, now part of the HTML instead of injected by jQuery
// 1.2 seconds after load. Same message, no layout shift.
add_action( 'wp_body_open', function () {
	echo '<div class="hv-announcement" role="region" aria-label="Announcement">Holiday pre-orders open November 15. <a href="/custom-cakes/">Place a holiday order</a></div>';
} );

// Removed on purpose:
// - big_image_size_threshold disabled  -> WordPress scales huge uploads again.
// - wp_lazy_loading_enabled disabled   -> native lazy loading is back.
// - one keyword title for every page   -> real titles, see mu-plugins/hv-seo.php.
// - js/site.js (jQuery, in the head)   -> announcement is server rendered,
//                                         smooth scroll is CSS (scroll-behavior).
add_action( 'wp_head', function () {
	echo "<style>html{scroll-behavior:smooth}@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}</style>\n";
}, 3 );

// OceanWP's grey title bar repeated the page name above the Elementor
// content ("Home"). Each page now has its own H1 in the content instead.
add_filter( 'ocean_display_page_header', function ( $show ) {
	if ( is_page() && 'builder' === get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true ) ) {
		return false;
	}
	return $show;
} );

// Footer widget titles were H4 straight after the page's H2s. Same look, right level.
add_filter( 'dynamic_sidebar_params', function ( $params ) {
	if ( ! is_admin() && isset( $params[0]['before_title'] ) ) {
		$params[0]['before_title'] = str_replace( '<h4', '<h2', $params[0]['before_title'] );
		$params[0]['after_title']  = str_replace( '</h4>', '</h2>', $params[0]['after_title'] );
	}
	return $params;
} );

// Demo copy: the business is fictional, so robots.txt keeps every crawler out.
add_filter( 'robots_txt', function () {
	return "User-agent: *\nDisallow: /\n";
} );
