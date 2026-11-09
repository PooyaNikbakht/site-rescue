<?php
/**
 * Harbourview child theme, as handed over by the previous developer.
 *
 * Every block below is a pattern we often find on small business sites.
 * They are here on purpose so the BEFORE site behaves like the real thing.
 */

add_action( 'wp_enqueue_scripts', function () {
	// Parent stylesheet enqueued again (OceanWP already loads it).
	wp_enqueue_style( 'oceanwp-parent', get_template_directory_uri() . '/style.css' );

	// Four Google font families with every weight, no font-display.
	wp_enqueue_style(
		'hv-google-fonts',
		'https://fonts.googleapis.com/css?family=Playfair+Display:400,400i,500,500i,600,700,800,900|Lato:100,100i,300,300i,400,400i,700,700i,900|Dancing+Script:400,500,600,700|Open+Sans:300,400,600,700,800',
		array(),
		null
	);

	wp_enqueue_style( 'hv-child', get_stylesheet_uri(), array( 'oceanwp-style' ), '1.0' );

	// Site script loaded in the <head>, depends on jQuery, blocks rendering.
	wp_enqueue_script( 'hv-site', get_stylesheet_directory_uri() . '/js/site.js', array( 'jquery' ), '1.0', false );
}, 20 );

// "Keep our photos sharp": turns off WordPress' automatic downscaling of huge uploads.
add_filter( 'big_image_size_threshold', '__return_false' );

// "Lazy loading broke the slider": turns lazy loading off everywhere.
add_filter( 'wp_lazy_loading_enabled', '__return_false' );

// "SEO": the same keyword title on every single page.
add_filter( 'pre_get_document_title', function () {
	return 'Harbourview Bakery | Best Bakery in Toronto | Cakes, Bread, Pastries';
} );

// Demo copy: the business is fictional, so robots.txt keeps every crawler out.
add_filter( 'robots_txt', function () {
	return "User-agent: *\nDisallow: /\n";
} );
