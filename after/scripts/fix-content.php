<?php
/**
 * Content fixes for the AFTER site. Edits the existing Elementor pages and
 * posts in place (no rebuild): the client keeps editing in Elementor as before.
 *
 * - hero slider (3 photos, autoplay) -> one fast, preloaded hero image
 * - "full" image sizes -> right-sized images with srcset
 * - alt text on every image
 * - one H1 per page, headings in order (H1 then H2)
 * - descriptive link and button text instead of "click here"
 * - fonts consolidated: per-widget Google Fonts removed
 * - contact form: real labels, working mail settings
 * - "Order ahead" placeholder for the future online ordering widget
 *
 * wp eval-file /work/after/scripts/fix-content.php
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

$media = get_option( 'hv_seed_media' );
if ( ! $media ) {
	WP_CLI::error( 'Seed media map missing. Was the seed run?' );
}

// ---------------------------------------------------------------------------
// Alt text: describe what is in the picture, in the bakery's voice.
// ---------------------------------------------------------------------------
$alt = array(
	'hero-sourdough'            => 'Loaves of country sourdough cooling on a rack, with dark, crackly crusts',
	'hero-croissants'           => 'Rows of freshly baked butter croissants on a baking sheet',
	'hero-counter'              => 'The bakery counter with croissants, scones and loaves on the shelves behind',
	'menu-sourdough'            => 'A round country sourdough loaf with a scored, golden crust',
	'menu-croissant'            => 'Two butter croissants dusted with flour on a dark slate',
	'menu-cinnamon-bun'         => 'Cinnamon buns, one topped with white icing',
	'menu-cookies'              => 'Brown butter chocolate chip cookies on a cooling rack',
	'menu-celebration-cake'     => 'A layered chocolate cake with whipped cream and dark cherries',
	'menu-baguette'             => 'Two long crusty loaves dusted with flour on a wooden board',
	'about-shop'                => 'Inside the shop: pastries in the glass case and loaves on the shelves',
	'about-bakers'              => 'A baker holding a piping bag of cream over a tray of choux pastry shells',
	'band-cake-decorating'      => 'A cake decorator piping pink and white buttercream onto a layer cake',
	'blog-starter'              => 'A jar of bubbly sourdough starter ready to bake with',
	'blog-wedding-cake'         => 'A three tier white wedding cake with pink peonies',
	'blog-holiday'              => 'Gingerbread cookies decorated with white icing',
	'blog-croissant-lamination' => 'Rolling out dough with a wooden rolling pin',
	'cakes-layer'               => 'A mint green celebration cake with pink piped rosettes and sprinkles',
	'cakes-cupcakes'            => 'Chocolate cupcakes with swirls of vanilla frosting',
	'visit-coffee'              => 'A latte with a croissant and pain au chocolat',
);
$alt_by_id = array();
foreach ( $alt as $slug => $text ) {
	update_post_meta( $media[ $slug ]['id'], '_wp_attachment_image_alt', $text );
	$alt_by_id[ $media[ $slug ]['id'] ] = $text;
}

// ---------------------------------------------------------------------------
// Elementor helpers
// ---------------------------------------------------------------------------
function hv_walk( array &$elements, callable $fn ) {
	foreach ( $elements as $i => &$el ) {
		$fn( $el, $elements, $i );
		if ( ! empty( $el['elements'] ) ) {
			hv_walk( $el['elements'], $fn );
		}
	}
}

function hv_get_data( $id ) {
	return json_decode( get_post_meta( $id, '_elementor_data', true ), true );
}

function hv_save_data( $id, array $data ) {
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
}

function hv_page_id( $slug ) {
	$p = get_page_by_path( $slug );
	return $p ? $p->ID : 0;
}

function hv_new_widget( $type, array $settings ) {
	return array(
		'id'         => substr( md5( uniqid( '', true ) ), 0, 7 ),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
	);
}

// Pages are flat: one H1, then H2s. (The old pages jumped from H2 to H4/H5.)
$heading_levels = array( 'h3' => 'h2', 'h4' => 'h2', 'h5' => 'h2' );
$link_text      = array(
	'Country Sourdough'     => 'See our breads',
	'Butter Croissant'      => 'See our pastries',
	'Cardamom Cinnamon Bun' => 'See our sweets',
);

// Generic fixes applied to every Elementor page.
$common = function ( array &$el ) use ( $alt_by_id, $heading_levels ) {
	if ( 'widget' !== ( $el['elType'] ?? '' ) ) {
		return;
	}
	$s = &$el['settings'];

	// One font system: drop per-widget Google Fonts (Dancing Script, Montserrat).
	unset( $s['typography_typography'], $s['typography_font_family'], $s['typography_font_weight'] );

	if ( 'image' === $el['widgetType'] ) {
		// Full-size originals -> "large" (1024px) with srcset for smaller screens.
		$s['image_size'] = 'large';
		$id              = (int) ( $s['image']['id'] ?? 0 );
		if ( isset( $alt_by_id[ $id ] ) ) {
			$s['image']['alt'] = $alt_by_id[ $id ];
			$s['image']['url'] = wp_get_attachment_url( $id );
		}
	}
	if ( 'heading' === $el['widgetType'] && isset( $heading_levels[ $s['header_size'] ?? '' ] ) ) {
		$s['header_size'] = $heading_levels[ $s['header_size'] ];
	}
	if ( 'text-editor' === $el['widgetType'] ) {
		$s['editor'] = str_replace(
			array( 'For more posts <a href="/blog/">click here</a>.', '<a href="/custom-cakes/">Click here</a> to order.' ),
			array( '<a href="/blog/">Read all posts on the blog</a>', '<a href="/custom-cakes/">Order a custom cake</a>.' ),
			$s['editor']
		);
		$s['editor'] = str_replace( '<table>', '<table class="hv-prices">', $s['editor'] );
	}
};

// ---------------------------------------------------------------------------
// Home page
// ---------------------------------------------------------------------------
$home = hv_page_id( 'home' );
$data = hv_get_data( $home );
$hero = $media['hero-sourdough']['id'];
update_option( 'hv_hero_attachment_id', $hero );

$last_heading = '';
hv_walk(
	$data,
	function ( array &$el ) use ( $common, $hero, $alt, $media, &$last_heading, $link_text ) {
		$common( $el );
		if ( 'section' === ( $el['elType'] ?? '' ) && ! empty( $el['settings']['background_image']['id'] ) ) {
			// Photo band: right-sized WebP backgrounds per device, plus a solid
			// panel behind the text for contrast.
			$bg = (int) $el['settings']['background_image']['id'];
			$el['settings']['background_image']        = array( 'id' => $bg, 'url' => wp_get_attachment_image_url( $bg, '1536x1536' ), 'size' => '1536x1536' );
			$el['settings']['background_image_tablet'] = array( 'id' => $bg, 'url' => wp_get_attachment_image_url( $bg, 'large' ), 'size' => 'large' );
			$el['settings']['background_image_mobile'] = array( 'id' => $bg, 'url' => wp_get_attachment_image_url( $bg, 'medium_large' ), 'size' => 'medium_large' );
			$el['settings']['css_classes']             = 'hv-cta';
		}
		if ( 'widget' !== ( $el['elType'] ?? '' ) ) {
			return;
		}
		$s = &$el['settings'];
		if ( 'image-carousel' === $el['widgetType'] ) {
			// Autoplay slider -> one static hero image. Same first photo the
			// slider showed, now preloaded and right-sized.
			$el = hv_new_widget(
				'image',
				array(
					'image'       => array( 'id' => $hero, 'url' => wp_get_attachment_url( $hero ), 'alt' => $alt['hero-sourdough'] ),
					'image_size'  => '1536x1536',
					'_css_classes' => 'hv-hero',
				)
			);
			return;
		}
		if ( 'testimonial' === $el['widgetType'] ) {
			// No customer photos: stop Elementor printing its grey placeholder avatar.
			$s['testimonial_image'] = array( 'url' => '', 'id' => '' );
		}
		if ( 'heading' === $el['widgetType'] ) {
			$last_heading = $s['title'];
			if ( 'Welcome to Harbourview Bakery!!' === $s['title'] ) {
				$s['title']       = 'Welcome to Harbourview Bakery';
				$s['header_size'] = 'h1';
			}
		}
		if ( 'button' === $el['widgetType'] ) {
			if ( 'Click here' === $s['text'] && isset( $link_text[ $last_heading ] ) ) {
				$s['text'] = $link_text[ $last_heading ];
			}
			if ( 'ORDER NOW' === $s['text'] ) {
				$s['text'] = 'Start a cake order';
			}
		}
	}
);

// Add the "Order ahead" placeholder after the favourites row.
array_splice(
	$data,
	3,
	0,
	array(
		array(
			'id'       => substr( md5( 'booking-home' ), 0, 7 ),
			'elType'   => 'section',
			'settings' => array(),
			'elements' => array(
				array(
					'id'       => substr( md5( 'booking-home-col' ), 0, 7 ),
					'elType'   => 'column',
					'settings' => array( '_column_size' => 100 ),
					'elements' => array( hv_new_widget( 'shortcode', array( 'shortcode' => '[hv_booking]' ) ) ),
				),
			),
		),
	)
);
hv_save_data( $home, $data );

// ---------------------------------------------------------------------------
// Other pages: generic fixes and an H1 for the first heading.
// ---------------------------------------------------------------------------
foreach ( array( 'menu', 'about', 'custom-cakes', 'visit' ) as $slug ) {
	$id    = hv_page_id( $slug );
	$data  = hv_get_data( $id );
	$first = true;
	hv_walk(
		$data,
		function ( array &$el ) use ( $common, &$first, $slug ) {
			$common( $el );
			if ( 'widget' !== ( $el['elType'] ?? '' ) ) {
				return;
			}
			$s = &$el['settings'];
			if ( 'heading' === $el['widgetType'] ) {
				if ( $first ) {
					$s['header_size'] = 'h1';
					$first            = false;
				}
				if ( 'menu' === $slug ) {
					// Anchors for the home page buttons.
					$anchors = array( 'Bread' => 'bread', 'Viennoiserie' => 'pastries', 'Sweet Things' => 'sweets', 'Celebration Cakes' => 'cakes' );
					if ( isset( $anchors[ $s['title'] ] ) ) {
						$s['_element_id'] = $anchors[ $s['title'] ];
					}
				}
			}
			if ( 'shortcode' === $el['widgetType'] && str_contains( $s['shortcode'], 'contact-form-7' ) ) {
				$s['shortcode'] = '<div id="hv-cake-form">' . $s['shortcode'] . '</div>';
			}
		}
	);
	if ( 'custom-cakes' === $slug ) {
		$data[] = array(
			'id'       => substr( md5( 'booking-cakes' ), 0, 7 ),
			'elType'   => 'section',
			'settings' => array(),
			'elements' => array(
				array(
					'id'       => substr( md5( 'booking-cakes-col' ), 0, 7 ),
					'elType'   => 'column',
					'settings' => array( '_column_size' => 100 ),
					'elements' => array( hv_new_widget( 'shortcode', array( 'shortcode' => '[hv_booking]' ) ) ),
				),
			),
		);
	}
	hv_save_data( $id, $data );
}

// Home buttons point at the matching menu section.
$data = hv_get_data( $home );
hv_walk(
	$data,
	function ( array &$el ) {
		if ( 'button' !== ( $el['widgetType'] ?? '' ) ) {
			return;
		}
		$map = array( 'See our breads' => '/menu/#bread', 'See our pastries' => '/menu/#pastries', 'See our sweets' => '/menu/#sweets' );
		if ( isset( $map[ $el['settings']['text'] ] ) ) {
			$el['settings']['link']['url'] = $map[ $el['settings']['text'] ];
		}
	}
);
hv_save_data( $home, $data );

// ---------------------------------------------------------------------------
// Blog posts: pasted full-size <img> tags -> WordPress images with srcset,
// width/height (no layout shift), lazy loading and alt text. Plus a real
// excerpt, used as the meta description.
// ---------------------------------------------------------------------------
$by_url = array();
foreach ( $media as $slug => $m ) {
	$by_url[ $m['url'] ] = $m['id'];
}
foreach ( get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1 ) ) as $post ) {
	// Pasted content used H3 straight after the post title (H1).
	$content = str_replace( array( '<h3>', '</h3>' ), array( '<h2>', '</h2>' ), $post->post_content );
	$content = preg_replace_callback(
		'#<img src="([^"]+)" />#',
		function ( $m ) use ( $by_url ) {
			$id = $by_url[ $m[1] ] ?? 0;
			if ( ! $id ) {
				return $m[0];
			}
			return wp_get_attachment_image( $id, 'large', false, array( 'class' => "wp-image-{$id} size-large" ) );
		},
		$content
	);
	// First paragraph, trimmed, as a real excerpt.
	$first   = trim( strtok( wp_strip_all_tags( $post->post_content ), "\n" ) );
	$excerpt = wp_trim_words( $first, 28, '...' );
	wp_update_post( array( 'ID' => $post->ID, 'post_content' => $content, 'post_excerpt' => $excerpt ) );
}

// ---------------------------------------------------------------------------
// Contact form: labels, hints, autocomplete, a From address on the bakery's
// own domain and a Reply-To so staff can answer the customer directly.
// ---------------------------------------------------------------------------
$form_id = (int) get_option( 'hv_cake_form_id' );
$form    = <<<'CF7'
<label for="hv-name">Your name (required)</label>
[text* your-name id:hv-name autocomplete:name]

<label for="hv-email">Email (required)</label>
[email* your-email id:hv-email autocomplete:email]

<label for="hv-phone">Phone</label>
[tel your-phone id:hv-phone autocomplete:tel]

<label for="hv-date">Date of your event</label>
[date event-date id:hv-date]

<label for="hv-size">Cake size</label>
[select cake-size id:hv-size "6 inch round (serves 8 to 10)" "8 inch round (serves 12 to 16)" "Two tier (serves 30 to 40)" "Something bigger"]

<label for="hv-message">Tell us about your cake (required)</label>
<span class="hv-hint" id="hv-message-hint">Flavours, colours, theme, allergies, and how many guests.</span>
[textarea* your-message id:hv-message]

[submit "Send cake request"]

<p class="hv-hint">Demo: nothing is ordered or sent.</p>
CF7;
update_post_meta( $form_id, '_form', $form );
$mail                       = get_post_meta( $form_id, '_mail', true );
$mail['sender']             = 'Harbourview Bakery website <website@harbourview-bakery.example>';
$mail['additional_headers'] = 'Reply-To: [your-name] <[your-email]>';
$mail['recipient']          = 'orders@harbourview-bakery.example';
update_post_meta( $form_id, '_mail', $mail );
update_post_meta(
	$form_id,
	'_messages',
	array_merge(
		(array) get_post_meta( $form_id, '_messages', true ),
		array(
			'mail_sent_ok' => 'Thank you. Your cake request was sent, and we will reply within two business days.',
			'mail_sent_ng' => 'Sorry, your request could not be sent. Please call us at 416-555-0142.',
		)
	)
);

// ---------------------------------------------------------------------------
// Footer: the social icons plugin is replaced by plain text links.
// ---------------------------------------------------------------------------
$text      = get_option( 'widget_text', array() );
$text[3]   = array(
	'title'  => 'Follow us',
	'text'   => '<p class="hv-social"><a href="https://www.instagram.com/" rel="noopener">Instagram</a> <a href="https://www.facebook.com/" rel="noopener">Facebook</a></p>',
	'filter' => false,
	'visual' => false,
);
update_option( 'widget_text', $text );
$sidebars               = get_option( 'sidebars_widgets', array() );
$sidebars['footer-two'] = array( 'text-3' );
update_option( 'sidebars_widgets', $sidebars );

// Menu: add an "Order ahead" link to the placeholder.
$menu = wp_get_nav_menu_object( 'Main menu' );
if ( $menu ) {
	wp_update_nav_menu_item(
		$menu->term_id,
		0,
		array(
			'menu-item-title'  => 'Order ahead',
			'menu-item-url'    => home_url( '/#book' ),
			'menu-item-type'   => 'custom',
			'menu-item-status' => 'publish',
		)
	);
}

WP_CLI::success( 'Content fixed.' );
