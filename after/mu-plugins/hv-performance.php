<?php
/**
 * Plugin Name: Harbourview performance fixes
 * Description: Loads only what each page needs. Part of the site rescue, see CASE-STUDY.md.
 */

if ( is_admin() ) {
	return;
}

// 1. Emoji detection script and styles: not needed, every browser has emoji.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
add_filter( 'emoji_svg_url', '__return_false' );

// 2. Contact Form 7 assets only on the page that has the form.
add_filter( 'wpcf7_load_js', 'hv_is_form_page' );
add_filter( 'wpcf7_load_css', 'hv_is_form_page' );
function hv_is_form_page() {
	return is_page( 'custom-cakes' );
}

add_action( 'wp_enqueue_scripts', function () {
	// 3. Block editor styles: this site's content is Elementor and classic
	//    posts, so the block library CSS is dead weight on every page.
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );

	// 4. OceanWP's gallery slider (Flickity) is loaded everywhere, and every
	//    later theme script lists it as a dependency. The site has no
	//    galleries, so drop it from the dependency lists and dequeue it.
	foreach ( wp_scripts()->registered as $script ) {
		$script->deps = array_values( array_diff( $script->deps, array( 'ow-flickity', 'oceanwp-slider' ) ) );
	}
	wp_dequeue_script( 'oceanwp-slider' );
	wp_dequeue_script( 'ow-flickity' );
	wp_dequeue_style( 'e-swiper' );
}, 100 );

// 5. jQuery Migrate is only for very old plugins. None are left.
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
	}
} );

// 6. Move jQuery out of the <head>. Nothing in the head needs it any more,
//    so it no longer blocks the first paint. Theme and Elementor scripts
//    already load at the end of the page.
add_action( 'wp_enqueue_scripts', function () {
	wp_scripts()->add_data( 'jquery', 'group', 1 );
	wp_scripts()->add_data( 'jquery-core', 'group', 1 );
}, 1 );

// 7. The hero image is the Largest Contentful Paint element on the home page.
//    Load it first: no lazy loading and high fetch priority. The <img> is in
//    the initial HTML, so a separate preload hint is not needed (we tried one:
//    with srcset it made some phones download two sizes).
function hv_hero_id() {
	return (int) get_option( 'hv_hero_attachment_id' );
}

add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	if ( is_front_page() && (int) $attachment->ID === hv_hero_id() ) {
		$attr['loading']       = 'eager';
		$attr['fetchpriority'] = 'high';
		$attr['decoding']      = 'async';
		$attr['sizes']         = '100vw';
	}
	return $attr;
}, 20, 2 );
