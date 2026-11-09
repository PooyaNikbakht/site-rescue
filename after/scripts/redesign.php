<?php
/**
 * Design refresh for the AFTER site: Home, Menu, Custom Cakes and About.
 *
 * Same theme, same page builder, same photos: each page is still Elementor sections
 * the owner edits as before. The layouts are built from text-editor widgets with
 * plain HTML (styled in the child theme), so there are no new plugins, fonts or
 * scripts. Card images go through wp_get_attachment_image(), so they keep srcset
 * and lazy loading; the home hero stays an image widget so hv-performance.php can
 * load it first.
 *
 * wp eval-file /work/after/scripts/redesign.php
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

$media = get_option( 'hv_seed_media' );
if ( ! $media ) {
	WP_CLI::error( 'Seed media map missing. Was the seed run?' );
}
$form_id = (int) get_option( 'hv_cake_form_id' );
// wp eval-file runs this file inside a function, so share the media map explicitly.
$GLOBALS['rd_media'] = $media;

// ---------------------------------------------------------------------------
// Elementor helpers (classic sections and columns, like the rest of the site)
// ---------------------------------------------------------------------------
function rd_eid() {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
}

function rd_section( array $columns, $class = '', array $settings = array() ) {
	return array(
		'id'       => rd_eid(),
		'elType'   => 'section',
		'settings' => array_merge( array( 'css_classes' => $class, 'gap' => 'no' ), $settings ),
		'elements' => $columns,
	);
}

function rd_col( array $widgets, $size = 100, $class = '' ) {
	return array(
		'id'       => rd_eid(),
		'elType'   => 'column',
		'settings' => array( '_column_size' => $size, 'css_classes' => $class ),
		'elements' => $widgets,
	);
}

function rd_html( $html, $class = '' ) {
	return array(
		'id'         => rd_eid(),
		'elType'     => 'widget',
		'widgetType' => 'text-editor',
		'settings'   => array( 'editor' => $html, '_css_classes' => $class ),
	);
}

/** One block of HTML in a full-width row: the building block for every section below. */
function rd_band( $html, $class ) {
	return rd_section( array( rd_col( array( rd_html( $html ) ) ) ), $class, array( 'layout' => 'full_width' ) );
}

/** A right-sized, lazy-loaded image with srcset. */
function rd_img( $slug, $size = 'medium_large', $sizes = '(min-width: 1100px) 360px, (min-width: 700px) 45vw, 100vw' ) {
	return wp_get_attachment_image(
		$GLOBALS['rd_media'][ $slug ]['id'],
		$size,
		false,
		array(
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => $sizes,
			// A class per photo, so a crop can be tuned in CSS (the cake sits right of centre).
			'class'    => "attachment-{$size} size-{$size} hv-photo-{$slug}",
		)
	);
}

function rd_btn( $text, $url, $style = 'primary' ) {
	return sprintf( '<a class="hv-btn hv-btn--%s" href="%s">%s</a>', esc_attr( $style ), esc_url( $url ), esc_html( $text ) );
}

function rd_heading( $eyebrow, $title, $intro = '', $tag = 'h2' ) {
	$out = '<div class="hv-head">';
	if ( $eyebrow ) {
		$out .= '<p class="hv-eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	$out .= "<{$tag}>" . esc_html( $title ) . "</{$tag}>";
	if ( $intro ) {
		$out .= '<p class="hv-lede">' . esc_html( $intro ) . '</p>';
	}
	return $out . '</div>';
}

function rd_save( $slug, array $data ) {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		WP_CLI::error( "Page not found: {$slug}" );
	}
	update_post_meta( $page->ID, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $page->ID, '_elementor_edit_mode', 'builder' );
	WP_CLI::log( "Redesigned {$slug} (#{$page->ID})" );
}

// ---------------------------------------------------------------------------
// The menu, in one place: the home page and the menu page both read it.
// ---------------------------------------------------------------------------
$menu = array(
	'bread'    => array(
		'title' => 'Bread',
		'note'  => 'Naturally leavened and baked every morning. Gone by early afternoon on Saturdays.',
		'img'   => 'menu-sourdough',
		'items' => array(
			array( 'Country sourdough', '9.50', 'Our 36 hour loaf. Crackly crust, open crumb, a gentle tang.' ),
			array( 'Seeded rye', '10.00', 'Dense and nutty, packed with seeds.' ),
			array( 'Olive and rosemary', '11.00', 'Sourdough with olives and fresh rosemary.' ),
			array( 'Whole wheat sandwich loaf', '8.00', 'Soft, sliceable, made for lunches.' ),
			array( 'Focaccia slab', '7.50', 'Olive oil, flaky salt and a deep golden crust.' ),
		),
	),
	'pastries' => array(
		'title' => 'Croissants and pastries',
		'note'  => 'Laminated over three days. Out of the oven at 7 every morning.',
		'img'   => 'menu-croissant',
		'items' => array(
			array( 'Butter croissant', '4.25', 'Flaky, buttery, the one we are known for.' ),
			array( 'Pain au chocolat', '4.75', 'Two bars of dark chocolate in croissant dough.' ),
			array( 'Almond croissant', '5.50', 'Filled with almond cream and topped with toasted almonds.' ),
			array( 'Ham and gruyere croissant', '6.75', 'A savoury lunch favourite.' ),
			array( 'Seasonal fruit danish', '5.25', 'Fruit from farms a few hours from the city.' ),
		),
	),
	'sweets'   => array(
		'title' => 'Sweets',
		'note'  => 'For the afternoon coffee, or the walk home.',
		'img'   => 'menu-cinnamon-bun',
		'items' => array(
			array( 'Cardamom cinnamon bun', '4.50', 'Swedish style knots with brown butter and cardamom sugar.' ),
			array( 'Brown butter chocolate chip cookie', '3.25', 'Crisp edges, soft middle.' ),
			array( 'Lemon tart', '6.00', 'Sharp lemon curd in a buttery shell.' ),
			array( 'Maple pecan blondie', '4.00', 'Ontario maple and toasted pecans.' ),
		),
	),
);

$cake_sizes = array(
	array( '6 inch round', 'Serves 8 to 10', '55' ),
	array( '8 inch round', 'Serves 12 to 16', '80' ),
	array( 'Two tier', 'Serves 30 to 40', '240' ),
);
$flavours = array( 'Vanilla bean', 'Dark chocolate', 'Lemon raspberry', 'Carrot with brown butter cream cheese' );

$hours_html = '<table class="hv-hours-table"><tbody>'
	. '<tr><th scope="row">Monday</th><td>Closed</td></tr>'
	. '<tr><th scope="row">Tuesday to Friday</th><td>7:00 am to 6:00 pm</td></tr>'
	. '<tr><th scope="row">Saturday</th><td>7:00 am to 4:00 pm</td></tr>'
	. '<tr><th scope="row">Sunday</th><td>8:00 am to 2:00 pm</td></tr>'
	. '</tbody></table>';

function rd_product_card( $slug, $name, $price, $desc, $url ) {
	return '<article class="hv-card hv-product">'
		. '<a class="hv-card-media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . rd_img( $slug ) . '</a>'
		. '<div class="hv-card-body"><h3><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></h3>'
		. '<p class="hv-price">$' . esc_html( $price ) . '</p>'
		. '<p>' . esc_html( $desc ) . '</p></div></article>';
}

// ---------------------------------------------------------------------------
// Home
// ---------------------------------------------------------------------------
$hero_id = (int) get_option( 'hv_hero_attachment_id' );
if ( ! $hero_id ) {
	$hero_id = $media['hero-sourdough']['id'];
}

$home = array(
	// Hero: the photo stays an image widget (loaded first by hv-performance.php),
	// with the headline laid over it.
	rd_section(
		array(
			rd_col(
				array(
					array(
						'id'         => rd_eid(),
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array(
							'image'        => array( 'id' => $hero_id, 'url' => wp_get_attachment_url( $hero_id ), 'alt' => get_post_meta( $hero_id, '_wp_attachment_image_alt', true ) ),
							'image_size'   => '1536x1536',
							'_css_classes' => 'hv-hero',
						),
					),
					rd_html(
						'<p class="hv-eyebrow">Since 2014 &middot; Harbourfront, Toronto</p>'
						. '<h1>Bread worth getting up early for</h1>'
						. '<p class="hv-lede">Naturally leavened sourdough, flaky croissants and celebration cakes, made by hand every morning a short walk from the water.</p>'
						. '<p class="hv-actions">' . rd_btn( 'See today&#8217;s menu', '/menu/' ) . rd_btn( 'Order a custom cake', '/custom-cakes/', 'light' ) . '</p>'
						. '<p class="hv-hero-hours">Open Tuesday to Sunday &middot; Closed Mondays</p>',
						'hv-hero-copy'
					),
				),
				100,
				'hv-hero-col'
			),
		),
		'hv-hero-wrap',
		array( 'layout' => 'full_width' )
	),

	rd_band(
		'<ul class="hv-promises">'
		. '<li><strong>By hand, from 4 am</strong><span>Every dough mixed and shaped in small batches</span></li>'
		. '<li><strong>36 hour sourdough</strong><span>The same starter since our first market season</span></li>'
		. '<li><strong>Local ingredients</strong><span>Ontario flour, family dairy butter, nearby farms</span></li>'
		. '<li><strong>Nothing wasted</strong><span>Day old bread goes to a food bank every evening</span></li>'
		. '</ul>',
		'hv-band hv-band--promises'
	),

	rd_band(
		rd_heading( 'Fresh from the oven', 'Our favourites', 'A few things regulars come back for. The full menu has everything we bake.' )
		. '<div class="hv-grid hv-grid--4">'
		. rd_product_card( 'menu-sourdough', 'Country sourdough', '9.50', 'Our 36 hour loaf. Crackly crust, open crumb, a gentle tang.', '/menu/#bread' )
		. rd_product_card( 'menu-croissant', 'Butter croissant', '4.25', 'Laminated over three days, out of the oven at 7.', '/menu/#pastries' )
		. rd_product_card( 'menu-cinnamon-bun', 'Cardamom cinnamon bun', '4.50', 'Swedish style knots with brown butter and cardamom sugar.', '/menu/#sweets' )
		. rd_product_card( 'menu-cookies', 'Chocolate chip cookie', '3.25', 'Brown butter, crisp edges, soft middle.', '/menu/#sweets' )
		. '</div><p class="hv-center">' . rd_btn( 'See the full menu', '/menu/', 'outline' ) . '</p>',
		'hv-band'
	),

	rd_band(
		'<div class="hv-split">'
		. '<div class="hv-split-media">' . rd_img( 'cakes-layer', 'large', '(min-width: 900px) 540px, 100vw' ) . '</div>'
		. '<div class="hv-split-copy">'
		. rd_heading( 'Celebration cakes', 'A cake for every occasion', 'Birthdays, weddings, baby showers and office parties. Tell us the date and the number of guests, and we will design something together.' )
		. '<ul class="hv-pricelist">'
		. '<li><span>6 inch round, serves 8 to 10</span><strong>from $55</strong></li>'
		. '<li><span>8 inch round, serves 12 to 16</span><strong>from $80</strong></li>'
		. '<li><span>Two tier, serves 30 to 40</span><strong>from $240</strong></li>'
		. '</ul>'
		. '<p class="hv-small">At least 5 days&#8217; notice, 4 weeks for wedding cakes. Tastings on Tuesdays.</p>'
		. '<p class="hv-actions">' . rd_btn( 'Start a cake order', '/custom-cakes/' ) . '</p>'
		. '</div></div>',
		'hv-band hv-band--tint'
	),

	rd_band(
		'<div class="hv-holiday">'
		. '<div><p class="hv-eyebrow">Holiday pre-orders</p><h2>Stollen, panettone and B&ucirc;che de No&euml;l</h2>'
		. '<p>Pre-orders open November 15. Pick up December 21 to 24, from 8 am to 3 pm.</p></div>'
		. '<p class="hv-actions">' . rd_btn( 'Read the holiday order guide', '/holiday-order-guide/', 'light' ) . '</p>'
		. '</div>',
		'hv-band hv-band--dark'
	),

	rd_band(
		'<div class="hv-split hv-split--reverse">'
		. '<div class="hv-split-media">' . rd_img( 'about-bakers', 'large', '(min-width: 900px) 540px, 100vw' ) . '</div>'
		. '<div class="hv-split-copy">'
		. rd_heading( 'Our story', 'From a market stall to the harbour', 'We started in 2014 at a weekend market. The sourdough sold out by ten every Saturday, so a year later we opened a small shop near the water. Today we are a team of eleven, and we still close when the bread runs out.' )
		. '<p class="hv-actions">' . rd_btn( 'Read our story', '/about/', 'outline' ) . '</p>'
		. '</div></div>',
		'hv-band'
	),

	rd_band(
		rd_heading( 'Kind words', 'What our regulars say' )
		. '<div class="hv-grid hv-grid--2">'
		. '<figure class="hv-card hv-quote"><blockquote><p>The best croissant I have had outside of Paris. We ordered our wedding cake here and every guest asked where it came from.</p></blockquote><figcaption><strong>Priya S.</strong> Liberty Village</figcaption></figure>'
		. '<figure class="hv-card hv-quote"><blockquote><p>Friendly people, real bread, and they remember my order. Saturday mornings are not the same without a cardamom bun.</p></blockquote><figcaption><strong>Daniel M.</strong> Fort York</figcaption></figure>'
		. '</div>',
		'hv-band hv-band--tint'
	),

	rd_band(
		'<div class="hv-visit">'
		. '<div class="hv-visit-card">'
		. rd_heading( 'Visit us', '48 Harbour Lane, Toronto' )
		. '<p>Street parking on Harbour Lane. The streetcar stop is a two minute walk.</p>'
		. $hours_html
		. '<p class="hv-actions">' . rd_btn( 'Get directions', '/visit/' ) . rd_btn( 'Call 416-555-0142', 'tel:+14165550142', 'outline' ) . '</p>'
		. '</div>'
		. '<div class="hv-booking hv-order-ahead" id="book"><h2>Order ahead</h2><p>Online ordering for pick-up is coming soon.</p>'
		. '<p>Until then, call <a href="tel:+14165550142">416-555-0142</a> or <a href="/custom-cakes/">send a cake order request</a>.</p></div>'
		. '</div>',
		'hv-band'
	),

	rd_band(
		rd_heading( 'From the blog', 'Notes from the bakery' )
		. '<div class="hv-grid hv-grid--3">'
		. '<article class="hv-card hv-post"><a class="hv-card-media" href="/how-to-feed-a-sourdough-starter/" tabindex="-1" aria-hidden="true">' . rd_img( 'blog-starter' ) . '</a><div class="hv-card-body"><h3><a href="/how-to-feed-a-sourdough-starter/">How to feed a sourdough starter</a></h3></div></article>'
		. '<article class="hv-card hv-post"><a class="hv-card-media" href="/planning-your-wedding-cake/" tabindex="-1" aria-hidden="true">' . rd_img( 'blog-wedding-cake' ) . '</a><div class="hv-card-body"><h3><a href="/planning-your-wedding-cake/">Planning your wedding cake: a timeline</a></h3></div></article>'
		. '<article class="hv-card hv-post"><a class="hv-card-media" href="/holiday-order-guide/" tabindex="-1" aria-hidden="true">' . rd_img( 'blog-holiday' ) . '</a><div class="hv-card-body"><h3><a href="/holiday-order-guide/">Holiday order guide</a></h3></div></article>'
		. '</div><p class="hv-center"><a href="/blog/">Read all posts on the blog</a></p>',
		'hv-band'
	),
);
rd_save( 'home', $home );

// ---------------------------------------------------------------------------
// Menu
// ---------------------------------------------------------------------------
$menu_page = array(
	rd_band(
		rd_heading( 'Baked every morning', 'Our menu', 'Bread, pastries and sweets made by hand from 4 am, plus celebration cakes to order. Prices include tax.', 'h1' )
		. '<nav class="hv-chips" aria-label="Menu sections"><a href="#bread">Bread</a><a href="#pastries">Croissants and pastries</a><a href="#sweets">Sweets</a><a href="#cakes">Celebration cakes</a></nav>',
		'hv-band hv-band--intro'
	),
);
foreach ( $menu as $key => $cat ) {
	$items = '';
	foreach ( $cat['items'] as $item ) {
		$items .= '<li><div><h3>' . esc_html( $item[0] ) . '</h3><p>' . esc_html( $item[2] ) . '</p></div><strong>$' . esc_html( $item[1] ) . '</strong></li>';
	}
	$menu_page[] = rd_band(
		'<section class="hv-menu-cat" id="' . esc_attr( $key ) . '">'
		. '<div class="hv-menu-media">' . rd_img( $cat['img'], 'large', '(min-width: 900px) 420px, 100vw' ) . '</div>'
		. '<div class="hv-menu-list"><h2>' . esc_html( $cat['title'] ) . '</h2><p class="hv-small">' . esc_html( $cat['note'] ) . '</p>'
		. '<ul class="hv-dishes">' . $items . '</ul></div></section>',
		'hv-band' . ( 'pastries' === $key ? ' hv-band--tint' : '' )
	);
}
$sizes_html = '';
foreach ( $cake_sizes as $s ) {
	$sizes_html .= '<li><div><h3>' . esc_html( $s[0] ) . '</h3><p>' . esc_html( $s[1] ) . '</p></div><strong>from $' . esc_html( $s[2] ) . '</strong></li>';
}
$menu_page[] = rd_band(
	'<section class="hv-menu-cat" id="cakes">'
	. '<div class="hv-menu-media">' . rd_img( 'menu-celebration-cake', 'large', '(min-width: 900px) 420px, 100vw' ) . '</div>'
	. '<div class="hv-menu-list"><h2>Celebration cakes</h2><p class="hv-small">Flavours: ' . esc_html( implode( ', ', $flavours ) ) . '. At least 5 days&#8217; notice.</p>'
	. '<ul class="hv-dishes">' . $sizes_html . '</ul>'
	. '<p class="hv-actions">' . rd_btn( 'Order a custom cake', '/custom-cakes/' ) . '</p></div></section>',
	'hv-band hv-band--tint'
);
$menu_page[] = rd_band(
	'<div class="hv-cta-box"><h2>Want something set aside?</h2><p>Call us the day before and we will keep it for you at the counter.</p>'
	. '<p class="hv-actions">' . rd_btn( 'Call 416-555-0142', 'tel:+14165550142', 'light' ) . '</p></div>',
	'hv-band hv-band--dark'
);
rd_save( 'menu', $menu_page );

// ---------------------------------------------------------------------------
// Custom cakes
// ---------------------------------------------------------------------------
$size_cards = '';
foreach ( $cake_sizes as $s ) {
	$size_cards .= '<div class="hv-card hv-size"><h3>' . esc_html( $s[0] ) . '</h3><p>' . esc_html( $s[1] ) . '</p><p class="hv-price">from $' . esc_html( $s[2] ) . '</p></div>';
}
$flavour_chips = '<ul class="hv-tags">';
foreach ( $flavours as $f ) {
	$flavour_chips .= '<li>' . esc_html( $f ) . '</li>';
}
$flavour_chips .= '</ul>';

$cakes = array(
	rd_band(
		'<div class="hv-split">'
		. '<div class="hv-split-copy">'
		. rd_heading( 'Designed with you', 'Custom cakes', 'Birthdays, weddings, baby showers and office parties. Tell us the date, the number of guests and any ideas you have, and our cake team will reply within two business days with sketches and a quote.', 'h1' )
		. '<p class="hv-actions">' . rd_btn( 'Start your order', '#order' ) . '</p>'
		. '</div>'
		. '<div class="hv-split-media">' . rd_img( 'cakes-layer', 'large', '(min-width: 900px) 540px, 100vw' ) . '</div>'
		. '</div>',
		'hv-band hv-band--intro'
	),
	rd_band(
		rd_heading( 'Sizes and prices', 'Pick a size' ) . '<div class="hv-grid hv-grid--3">' . $size_cards . '</div>'
		. '<h3 class="hv-subhead">Flavours</h3>' . $flavour_chips,
		'hv-band hv-band--tint'
	),
	rd_band(
		rd_heading( 'How it works', 'From idea to cake in three steps' )
		. '<ol class="hv-steps">'
		. '<li><strong>Tell us about it</strong><span>Send the form below with your date, guests and ideas.</span></li>'
		. '<li><strong>Get sketches and a quote</strong><span>We reply within two business days. Tastings are on Tuesdays.</span></li>'
		. '<li><strong>Pick up or delivery</strong><span>Wedding cakes are delivered and set up at your venue. A 30 percent deposit holds your date.</span></li>'
		. '</ol>'
		. '<p class="hv-note">We need at least <strong>5 days&#8217; notice</strong> for celebration cakes and <strong>4 weeks</strong> for wedding cakes.</p>',
		'hv-band'
	),
	rd_band(
		rd_heading( 'Recent cakes', 'Made for our customers' )
		. '<div class="hv-gallery">' . rd_img( 'cakes-layer' ) . rd_img( 'blog-wedding-cake' ) . rd_img( 'cakes-cupcakes' ) . rd_img( 'menu-celebration-cake' ) . '</div>',
		'hv-band hv-band--tint'
	),
	rd_band(
		'<div class="hv-form-wrap" id="order">'
		. rd_heading( 'Order request', 'Tell us about your cake', 'Fields marked with an asterisk are required.' )
		. '[contact-form-7 id="' . $form_id . '" title="Cake order form"]'
		. '</div>',
		'hv-band'
	),
);
rd_save( 'custom-cakes', $cakes );

// ---------------------------------------------------------------------------
// About
// ---------------------------------------------------------------------------
$about = array(
	rd_band(
		'<div class="hv-split">'
		. '<div class="hv-split-copy">'
		. rd_heading( 'Since 2014', 'Our story', 'Harbourview Bakery started as a two person stall at a weekend market. Maya Lindqvist baked the bread and her brother Tomas handled the cash box and the coffee.', 'h1' )
		. '</div>'
		. '<div class="hv-split-media">' . rd_img( 'about-shop', 'large', '(min-width: 900px) 540px, 100vw' ) . '</div>'
		. '</div>',
		'hv-band hv-band--intro'
	),
	rd_band(
		'<ol class="hv-timeline">'
		. '<li><strong>2014</strong><span>A weekend market stall. The sourdough sells out by ten every Saturday.</span></li>'
		. '<li><strong>2015</strong><span>We sign the lease on a small shop near the harbour.</span></li>'
		. '<li><strong>Today</strong><span>A team of eleven bakers, pastry cooks and counter staff, still using the starter from that first season.</span></li>'
		. '</ol>',
		'hv-band'
	),
	rd_band(
		'<div class="hv-split hv-split--reverse">'
		. '<div class="hv-split-media">' . rd_img( 'about-bakers', 'large', '(min-width: 900px) 540px, 100vw' ) . '</div>'
		. '<div class="hv-split-copy">'
		. rd_heading( 'How we bake', 'Slow dough, early mornings', 'We still mix every dough by hand, and we still close when the bread runs out. Croissants are laminated over three days and shaped by hand at 4 am.' )
		. '</div></div>',
		'hv-band hv-band--tint'
	),
	rd_band(
		rd_heading( 'What we care about', 'Good bread, done right' )
		. '<div class="hv-grid hv-grid--3">'
		. '<div class="hv-card hv-value"><h3>Made by hand</h3><p>Small batches, mixed and shaped by people, not machines.</p></div>'
		. '<div class="hv-card hv-value"><h3>Close to home</h3><p>Flour from an Ontario mill, butter from a family dairy, fruit from farms a few hours away.</p></div>'
		. '<div class="hv-card hv-value"><h3>Nothing wasted</h3><p>Day old bread goes to a local food bank every evening.</p></div>'
		. '</div>',
		'hv-band'
	),
	rd_band(
		'<div class="hv-cta-box"><h2>Come and say hello</h2><p>48 Harbour Lane, a short walk from the water. Open Tuesday to Sunday.</p>'
		. '<p class="hv-actions">' . rd_btn( 'Plan your visit', '/visit/', 'light' ) . rd_btn( 'See the menu', '/menu/', 'ghost' ) . '</p></div>',
		'hv-band hv-band--dark'
	),
);
rd_save( 'about', $about );

// Regenerate Elementor's CSS and drop cached pages so the new layouts show at once.
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
if ( class_exists( 'Cache_Enabler' ) ) {
	Cache_Enabler::clear_complete_cache();
}
WP_CLI::success( 'Redesign applied.' );
