<?php
/**
 * Builds the Harbourview Bakery site content (the state the client handed over).
 *
 * Runs inside the wp-cli container: wp eval-file /work/seed/build-content.php
 * Both BEFORE and AFTER start from exactly this content. The AFTER site then
 * gets the rescue scripts in after/ applied on top.
 *
 * Harbourview Bakery is a fictional business. Names, address and phone
 * number are made up (555 numbers, .example domains).
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Media: import the full-size "camera" photos exactly as an owner would.
// No alt text, original files, no compression. Two of them were re-saved as
// PNG before upload (see "format" in seed/photos.json).
// Photos: free stock photos listed in seed/photos.json, downloaded and
// checksum-verified into build/photos/ by `make photos`.
// ---------------------------------------------------------------------------
$photos = json_decode( file_get_contents( '/work/seed/photos.json' ), true );
if ( empty( $photos['photos'] ) ) {
	WP_CLI::error( 'seed/photos.json is missing or empty.' );
}
$tmp = sys_get_temp_dir() . '/hv-photos';
if ( ! is_dir( $tmp ) ) {
	mkdir( $tmp, 0700, true );
}

$media = array();
foreach ( $photos['photos'] as $photo ) {
	$slug = $photo['slug'];
	$src  = "/images/{$photo['id']}.jpg";
	if ( ! is_readable( $src ) || hash_file( 'sha256', $src ) !== $photo['sha256'] ) {
		WP_CLI::error( "Missing or changed photo for {$slug} ({$src}). Run `make photos` first." );
	}
	// The file name the owner uploaded: the slug, as JPEG or re-saved as PNG.
	$file = "{$tmp}/{$slug}.{$photo['format']}";
	if ( 'png' === $photo['format'] ) {
		wp_raise_memory_limit( 'image' );
		$im = imagecreatefromjpeg( $src );
		imagepng( $im, $file, 6 );
		imagedestroy( $im );
	} else {
		copy( $src, $file );
	}
	$id = (int) WP_CLI::runcommand(
		sprintf( 'media import %s --title=%s --porcelain', escapeshellarg( $file ), escapeshellarg( $photo['title'] ) ),
		array( 'return' => true )
	);
	unlink( $file );
	$media[ $slug ] = array(
		'id'  => $id,
		'url' => wp_get_attachment_url( $id ),
	);
	WP_CLI::log( sprintf( 'Imported %s as #%d (%.1f MB)', $slug, $id, filesize( get_attached_file( $id ) ) / 1e6 ) );
}
update_option( 'hv_seed_media', $media, false );

// ---------------------------------------------------------------------------
// Tiny Elementor helpers. Uses the classic section / column structure that
// most existing Elementor sites still have.
// ---------------------------------------------------------------------------
function hv_eid() {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
}

function hv_section( array $columns, array $settings = array() ) {
	return array(
		'id'       => hv_eid(),
		'elType'   => 'section',
		'settings' => $settings,
		'elements' => $columns,
	);
}

function hv_col( array $widgets, $size = 100, array $settings = array() ) {
	return array(
		'id'       => hv_eid(),
		'elType'   => 'column',
		'settings' => array_merge( array( '_column_size' => $size ), $settings ),
		'elements' => $widgets,
	);
}

function hv_w( $type, array $settings ) {
	return array(
		'id'         => hv_eid(),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
	);
}

function hv_heading( $text, $tag = 'h2', array $extra = array() ) {
	return hv_w( 'heading', array_merge( array( 'title' => $text, 'header_size' => $tag ), $extra ) );
}

function hv_text( $html ) {
	return hv_w( 'text-editor', array( 'editor' => $html ) );
}

function hv_image( $m, $size = 'full', array $extra = array() ) {
	return hv_w(
		'image',
		array_merge(
			array(
				'image'      => array( 'id' => $m['id'], 'url' => $m['url'], 'alt' => '' ),
				'image_size' => $size,
			),
			$extra
		)
	);
}

function hv_button( $text, $url ) {
	return hv_w( 'button', array( 'text' => $text, 'link' => array( 'url' => $url ) ) );
}

// Script font used for "fancy" headings. Elementor loads it from Google Fonts.
$script_font = array(
	'typography_typography'  => 'custom',
	'typography_font_family' => 'Dancing Script',
	'typography_font_size'   => array( 'unit' => 'px', 'size' => 46 ),
);
$montserrat = array(
	'typography_typography'  => 'custom',
	'typography_font_family' => 'Montserrat',
	'typography_font_weight' => '600',
);

function hv_page( $title, $slug, array $data, $template = 'elementor_header_footer' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_content' => '',
		)
	);
	update_post_meta( $id, '_wp_page_template', $template );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	return $id;
}

// ---------------------------------------------------------------------------
// Contact form (Contact Form 7). Placeholders instead of labels, and the
// mail settings point at a mailbox that is never delivered (see seed.sh).
// ---------------------------------------------------------------------------
$form_body = <<<'CF7'
[text* your-name placeholder "Your Name"]

[email* your-email placeholder "Email"]

[tel your-phone placeholder "Phone"]

[date event-date placeholder "Date of event"]

[select cake-size "Choose a size" "6 inch round (serves 8 to 10)" "8 inch round (serves 12 to 16)" "Two tier (serves 30 to 40)" "Something bigger"]

[textarea your-message placeholder "Tell us about your dream cake!!"]

[submit "SEND"]

Demo: nothing is ordered or sent.
CF7;

$form_id = wp_insert_post(
	array(
		'post_type'   => 'wpcf7_contact_form',
		'post_title'  => 'Cake order form',
		'post_status' => 'publish',
	)
);
update_post_meta( $form_id, '_form', $form_body );
update_post_meta(
	$form_id,
	'_mail',
	array(
		'active'             => true,
		'subject'            => 'New cake order from [your-name]',
		// Spoofed From address: the server is not allowed to send as gmail.com.
		'sender'             => 'Harbourview Bakery <harbourviewbakery.orders@gmail.com>',
		'recipient'          => 'orders@harbourview-bakery.example',
		'body'               => "From: [your-name] <[your-email]>\nPhone: [your-phone]\nEvent date: [event-date]\nSize: [cake-size]\n\n[your-message]",
		'additional_headers' => '',
		'attachments'        => '',
		'use_html'           => false,
		'exclude_blank'      => false,
	)
);
update_post_meta( $form_id, '_locale', 'en_US' );
update_option( 'hv_cake_form_id', $form_id );

// ---------------------------------------------------------------------------
// Pages
// ---------------------------------------------------------------------------
$m = $media;

$home = hv_page(
	'Home',
	'home',
	array(
		// Full width auto-playing slider with three 5200px photos.
		hv_section(
			array(
				hv_col(
					array(
						hv_w(
							'image-carousel',
							array(
								'carousel'       => array(
									array( 'id' => $m['hero-sourdough']['id'], 'url' => $m['hero-sourdough']['url'] ),
									array( 'id' => $m['hero-croissants']['id'], 'url' => $m['hero-croissants']['url'] ),
									array( 'id' => $m['hero-counter']['id'], 'url' => $m['hero-counter']['url'] ),
								),
								'thumbnail_size' => 'full',
								'slides_to_show' => '1',
								'navigation'     => 'both',
								'autoplay'       => 'yes',
								'autoplay_speed' => 3000,
								'infinite'       => 'yes',
								'effect'         => 'fade',
								'image_stretch'  => 'yes',
							)
						),
					)
				),
			),
			array( 'layout' => 'full_width', 'gap' => 'no' )
		),
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Welcome to Harbourview Bakery!!', 'h2', $script_font + array( 'align' => 'center' ) ),
						hv_text( '<p style="text-align:center">Since 2014 we have been baking naturally leavened bread, flaky croissants and celebration cakes a short walk from the water in Toronto. Everything is made by hand in small batches, starting at 4 in the morning, with Ontario flour and butter from a family dairy near Guelph.</p>' ),
					)
				),
			),
			array( 'padding' => array( 'unit' => 'px', 'top' => '60', 'bottom' => '20', 'left' => '0', 'right' => '0', 'isLinked' => false ) )
		),
		hv_section(
			array(
				hv_col(
					array(
						hv_image( $m['menu-sourdough'] ),
						hv_heading( 'Country Sourdough', 'h4', $montserrat ),
						hv_text( '<p>Our 36 hour loaf. Crackly crust, open crumb, a gentle tang.</p>' ),
						hv_button( 'Click here', '/menu/' ),
					),
					33
				),
				hv_col(
					array(
						hv_image( $m['menu-croissant'] ),
						hv_heading( 'Butter Croissant', 'h4', $montserrat ),
						hv_text( '<p>Laminated over three days. Out of the oven at 7 every morning.</p>' ),
						hv_button( 'Click here', '/menu/' ),
					),
					33
				),
				hv_col(
					array(
						hv_image( $m['menu-cinnamon-bun'] ),
						hv_heading( 'Cardamom Cinnamon Bun', 'h4', $montserrat ),
						hv_text( '<p>Swedish style knots with brown butter and cardamom sugar.</p>' ),
						hv_button( 'Click here', '/menu/' ),
					),
					33
				),
			)
		),
		// CTA band with a 6720px PNG as a CSS background image.
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Custom Cakes for Every Occasion', 'h3', array( 'align' => 'center', 'title_color' => '#ffffff' ) ),
						hv_text( '<p style="text-align:center;color:#f3e9da">Birthdays, weddings, baby showers and office parties. Tell us the date and the number of guests and we will design something together.</p>' ),
						hv_button( 'ORDER NOW', '/custom-cakes/' ),
					)
				),
			),
			array(
				'background_background' => 'classic',
				'background_image'      => array( 'id' => $m['band-cake-decorating']['id'], 'url' => $m['band-cake-decorating']['url'] ),
				'background_size'       => 'cover',
				'background_position'   => 'center center',
				'padding'               => array( 'unit' => 'px', 'top' => '140', 'bottom' => '140', 'left' => '0', 'right' => '0', 'isLinked' => false ),
			)
		),
		hv_section(
			array(
				hv_col(
					array(
						hv_w(
							'testimonial',
							array(
								'testimonial_content' => 'The best croissant I have had outside of Paris. We ordered our wedding cake here and every guest asked where it came from.',
								'testimonial_name'    => 'Priya S.',
								'testimonial_job'     => 'Liberty Village',
							)
						),
					),
					50
				),
				hv_col(
					array(
						hv_w(
							'testimonial',
							array(
								'testimonial_content' => 'Friendly people, real bread, and they remember my order. Saturday mornings are not the same without a cardamom bun.',
								'testimonial_name'    => 'Daniel M.',
								'testimonial_job'     => 'Fort York',
							)
						),
					),
					50
				),
			),
			array( 'padding' => array( 'unit' => 'px', 'top' => '60', 'bottom' => '60', 'left' => '0', 'right' => '0', 'isLinked' => false ) )
		),
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'From the blog', 'h5', $script_font ),
						hv_text( '<ul><li><a href="/how-to-feed-a-sourdough-starter/">How to feed a sourdough starter</a></li><li><a href="/planning-your-wedding-cake/">Planning your wedding cake: a timeline</a></li><li><a href="/holiday-order-guide/">Holiday order guide</a></li></ul><p>For more posts <a href="/blog/">click here</a>.</p>' ),
					)
				),
			)
		),
	)
);

$menu_page = hv_page(
	'Menu',
	'menu',
	array(
		hv_section( array( hv_col( array( hv_heading( 'Our Menu', 'h2', $script_font + array( 'align' => 'center' ) ), hv_text( '<p style="text-align:center">Baked fresh every morning. Prices include tax. Quantities are limited on weekends, so come early or call ahead to reserve.</p>' ) ) ) ) ),
		hv_section(
			array(
				hv_col( array( hv_image( $m['menu-sourdough'] ) ), 50 ),
				hv_col(
					array(
						hv_heading( 'Bread', 'h4', $montserrat ),
						hv_text( '<table><tr><td>Country sourdough</td><td>$9.50</td></tr><tr><td>Seeded rye</td><td>$10.00</td></tr><tr><td>Olive and rosemary</td><td>$11.00</td></tr><tr><td>Whole wheat sandwich loaf</td><td>$8.00</td></tr><tr><td>Focaccia slab</td><td>$7.50</td></tr></table>' ),
					),
					50
				),
			)
		),
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Viennoiserie', 'h4', $montserrat ),
						hv_text( '<table><tr><td>Butter croissant</td><td>$4.25</td></tr><tr><td>Pain au chocolat</td><td>$4.75</td></tr><tr><td>Almond croissant</td><td>$5.50</td></tr><tr><td>Ham and gruyere croissant</td><td>$6.75</td></tr><tr><td>Seasonal fruit danish</td><td>$5.25</td></tr></table>' ),
					),
					50
				),
				hv_col( array( hv_image( $m['menu-croissant'] ) ), 50 ),
			)
		),
		hv_section(
			array(
				hv_col( array( hv_image( $m['menu-cinnamon-bun'] ) ), 50 ),
				hv_col(
					array(
						hv_heading( 'Sweet Things', 'h4', $montserrat ),
						hv_text( '<table><tr><td>Cardamom cinnamon bun</td><td>$4.50</td></tr><tr><td>Brown butter chocolate chip cookie</td><td>$3.25</td></tr><tr><td>Lemon tart</td><td>$6.00</td></tr><tr><td>Maple pecan blondie</td><td>$4.00</td></tr></table>' ),
					),
					50
				),
			)
		),
		hv_section(
			array(
				hv_col( array( hv_image( $m['menu-cookies'] ) ), 50 ),
				hv_col( array( hv_image( $m['menu-baguette'] ) ), 50 ),
			)
		),
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Celebration Cakes', 'h4', $montserrat ),
						hv_text( '<table><tr><td>6 inch round (serves 8 to 10)</td><td>from $55</td></tr><tr><td>8 inch round (serves 12 to 16)</td><td>from $80</td></tr><tr><td>Two tier (serves 30 to 40)</td><td>from $240</td></tr></table><p>Flavours: vanilla bean, dark chocolate, lemon raspberry, carrot with brown butter cream cheese. Please give us at least 5 days notice. <a href="/custom-cakes/">Click here</a> to order.</p>' ),
					),
					50
				),
				hv_col( array( hv_image( $m['menu-celebration-cake'] ) ), 50 ),
			)
		),
	)
);

$about = hv_page(
	'About',
	'about',
	array(
		hv_section( array( hv_col( array( hv_image( $m['about-shop'] ) ) ) ), array( 'layout' => 'full_width', 'gap' => 'no' ) ),
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Our Story', 'h2', $script_font ),
						hv_text( '<p>Harbourview Bakery started in 2014 as a two person stall at a weekend market. Maya Lindqvist baked the bread and her brother Tomas handled the cash box and the coffee. The sourdough sold out by ten every Saturday, so a year later they signed the lease on a small shop near the harbour.</p><p>Today we are a team of eleven bakers, pastry cooks and counter staff. We still mix every dough by hand, we still use the same starter from that first market season, and we still close when the bread runs out.</p><p>We buy our flour from an Ontario mill, our butter from a family dairy, and our fruit from farms within a few hours of the city. Day old bread goes to a local food bank every evening.</p>' ),
					),
					60
				),
				hv_col( array( hv_image( $m['about-bakers'] ) ), 40 ),
			)
		),
	)
);

$cakes = hv_page(
	'Custom Cakes',
	'custom-cakes',
	array(
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Custom Cake Orders', 'h2', $script_font ),
						hv_text( '<p>Every custom cake is designed with you. Fill in the form with your date, the number of guests and any ideas you have, and our cake team will reply within two business days with sketches and a quote.</p><p>We need at least 5 days notice for celebration cakes and 4 weeks for wedding cakes. Tastings are available on Tuesdays.</p>' ),
						hv_w( 'shortcode', array( 'shortcode' => '[contact-form-7 id="' . $form_id . '" title="Cake order form"]' ) ),
					),
					60
				),
				hv_col( array( hv_image( $m['menu-celebration-cake'] ) ), 40 ),
			)
		),
		// Gallery of recent cakes, straight from the owner's phone.
		hv_section( array( hv_col( array( hv_heading( 'Recent Cakes', 'h3', $script_font ) ) ) ) ),
		hv_section(
			array(
				hv_col( array( hv_image( $m['cakes-layer'] ) ), 33 ),
				hv_col( array( hv_image( $m['blog-wedding-cake'] ) ), 33 ),
				hv_col( array( hv_image( $m['cakes-cupcakes'] ) ), 33 ),
			)
		),
	)
);

$visit = hv_page(
	'Visit Us',
	'visit',
	array(
		hv_section(
			array(
				hv_col(
					array(
						hv_heading( 'Location & Hours', 'h2', $script_font ),
						hv_image( $m['visit-coffee'] ),
						hv_text( '<p><strong>Harbourview Bakery</strong><br>48 Harbour Lane, Toronto, ON<br>Phone: 416-555-0142<br>Email: hello@harbourview-bakery.example</p><table class="hv-hours"><tr><td>Monday</td><td>Closed</td></tr><tr><td>Tuesday to Friday</td><td>7:00 am to 6:00 pm</td></tr><tr><td>Saturday</td><td>7:00 am to 4:00 pm</td></tr><tr><td>Sunday</td><td>8:00 am to 2:00 pm</td></tr></table><p>Street parking on Harbour Lane. The streetcar stop is a two minute walk.</p>' ),
					),
					40
				),
				hv_col(
					array(
						hv_w(
							'google_maps',
							array(
								'address' => 'Harbourfront, Toronto, ON',
								'zoom'    => array( 'unit' => 'px', 'size' => 15 ),
								'height'  => array( 'unit' => 'px', 'size' => 420 ),
							)
						),
					),
					60
				),
			)
		),
	)
);

// Blog index uses the theme's default template.
$blog = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Blog', 'post_name' => 'blog', 'post_status' => 'publish' ) );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );
update_option( 'page_for_posts', $blog );

// ---------------------------------------------------------------------------
// Blog posts. Images are pasted as plain <img> tags pointing at the full
// size file, with no width, height or alt: what you get when content is
// copied in from another editor.
// ---------------------------------------------------------------------------
$cat = wp_create_category( 'Baking Tips' );
$news = wp_create_category( 'News' );

$posts = array(
	array(
		'title' => 'How to feed a sourdough starter',
		'slug'  => 'how-to-feed-a-sourdough-starter',
		'img'   => 'blog-starter',
		'cat'   => $cat,
		'date'  => '2026-09-18 09:00:00',
		'body'  => array(
			'People ask us about our starter every week, so here is exactly what we do at the bakery, scaled down for a home kitchen.',
			'<h3>What you need</h3><ul><li>A clean glass jar with a loose lid</li><li>Unbleached all purpose flour and a little whole wheat or rye</li><li>Water at room temperature</li><li>A kitchen scale (cups are not accurate enough)</li></ul>',
			'<h3>The daily feed</h3>Discard all but 50 g of starter. Add 50 g of flour and 50 g of water and stir until no dry flour is left. Mark the level on the jar with a rubber band. A healthy starter doubles in 4 to 8 hours at around 24 degrees.',
			'IMG',
			'<h3>Reading the signs</h3>Lots of small bubbles, a domed top and a pleasant yogurt smell mean it is ready to bake with. A layer of grey liquid on top (hooch) means it is hungry. Pour it off and feed it. If it smells like nail polish remover, feed it twice a day for a couple of days.',
			'<h3>Keeping it in the fridge</h3>If you bake once a week, keep the starter in the fridge and feed it once a week. Take it out the night before you bake and give it two feeds at room temperature before you mix your dough.',
			'Still stuck? Bring your jar into the shop on a weekday afternoon and one of our bakers will take a look.',
		),
	),
	array(
		'title' => 'Planning your wedding cake: a timeline',
		'slug'  => 'planning-your-wedding-cake',
		'img'   => 'blog-wedding-cake',
		'cat'   => $news,
		'date'  => '2026-08-30 10:30:00',
		'body'  => array(
			'A wedding cake is one of the last things couples think about and one of the first things guests photograph. Here is the timeline we suggest.',
			'<h3>Six months out</h3>Book a tasting. Our tastings run on Tuesdays and include four flavours and two fillings. Bring photos, colour swatches and your venue details.',
			'IMG',
			'<h3>Three months out</h3>Confirm the design and the number of guests. We will send a final sketch and a quote. A 30 percent deposit holds your date.',
			'<h3>Four weeks out</h3>Confirm final numbers and the delivery window with your venue. We deliver anywhere in the city and set up the cake on site.',
			'<h3>The day</h3>Relax. Our team arrives two hours before the reception to assemble and finish the cake, and we leave serving instructions with your venue coordinator.',
		),
	),
	array(
		'title' => 'Holiday order guide',
		'slug'  => 'holiday-order-guide',
		'img'   => 'blog-holiday',
		'cat'   => $news,
		'date'  => '2026-10-20 08:00:00',
		'body'  => array(
			'Holiday pre-orders open on November 15. Here is everything on the list this year and how to get your order in.',
			'IMG',
			'<h3>What is on the menu</h3><ul><li>Stollen with marzipan</li><li>Panettone (orange and dark chocolate)</li><li>Gingerbread cookie tins</li><li>Bûche de Noël, serves 8</li><li>Country sourdough and seeded rye, reserved for pick-up</li></ul>',
			'<h3>Pick-up days</h3>December 21 to 24 from 8 am to 3 pm. We are closed December 25 and 26.',
			'<h3>How to order</h3>Call the shop or use the order form on our Custom Cakes page and mention "holiday order" in the message. Payment is taken at pick-up.',
		),
	),
	array(
		'title' => 'Why our croissants take three days',
		'slug'  => 'why-our-croissants-take-three-days',
		'img'   => 'blog-croissant-lamination',
		'cat'   => $cat,
		'date'  => '2026-07-12 07:45:00',
		'body'  => array(
			'Day one is the dough. Day two is lamination. Day three is shaping, proofing and baking. Each step needs cold butter and patience.',
			'IMG',
			'<h3>Day one: the détrempe</h3>We mix flour, milk, sugar, salt, yeast and a little butter into a stiff dough, then rest it overnight in the fridge so the gluten relaxes.',
			'<h3>Day two: lamination</h3>A slab of cold butter is locked into the dough and folded three times. That gives us 27 layers of butter. Between each fold the dough goes back into the fridge.',
			'<h3>Day three: shape and bake</h3>We roll, cut and shape each croissant by hand at 4 am, proof them for about two hours, egg wash them and bake them at 190 degrees until deep golden.',
		),
	),
);

foreach ( $posts as $p ) {
	$img_html = sprintf( '<img src="%s" />', esc_url( $media[ $p['img'] ]['url'] ) );
	$parts    = array();
	foreach ( $p['body'] as $b ) {
		$parts[] = ( 'IMG' === $b ) ? $img_html : $b;
	}
	$pid = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_title'    => $p['title'],
			'post_name'     => $p['slug'],
			'post_status'   => 'publish',
			'post_date'     => $p['date'],
			'post_content'  => implode( "\n\n", $parts ),
			'post_category' => array( $p['cat'] ),
		)
	);
	set_post_thumbnail( $pid, $media[ $p['img'] ]['id'] );
}

// ---------------------------------------------------------------------------
// Photo credits: one page, linked from the footer.
// ---------------------------------------------------------------------------
$credit_items = array();
foreach ( $photos['photos'] as $photo ) {
	$credit_items[] = sprintf(
		'<li>%s: photo by <a href="%s">%s</a> on <a href="%s">%s</a> (%s)</li>',
		esc_html( ucfirst( str_replace( '-', ' ', $photo['slug'] ) ) ),
		esc_url( $photo['photographer_url'] ),
		esc_html( $photo['photographer'] ),
		esc_url( $photo['page'] ),
		esc_html( $photo['source'] ),
		esc_html( $photo['license'] )
	);
}
wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_title'   => 'Photo credits',
		'post_name'    => 'photo-credits',
		'post_status'  => 'publish',
		'post_content' => '<p>Harbourview Bakery is a fictional business made for a portfolio demo. The photos on this site are free stock photos used under the <a href="https://unsplash.com/license">Unsplash License</a>. Thank you to the photographers.</p><ul>' . implode( '', $credit_items ) . '</ul>',
	)
);
// Demo disclosure, one small line. DEMO_CREDIT_URL overrides the link.
$hv_credit_url = getenv( 'DEMO_CREDIT_URL' ) ?: 'https://pooya.website';
set_theme_mod( 'ocean_footer_copyright_text', 'Copyright 2026 Harbourview Bakery. <a href="/photo-credits/">Photo credits</a><br><a href="' . esc_url( $hv_credit_url ) . '">Fictional business. Demo by Pooya Nikbakht</a>' );

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------
$menu_id = wp_create_nav_menu( 'Main menu' );
foreach ( array( $home => 'Home', $menu_page => 'Menu', $cakes => 'Custom Cakes', $about => 'About', $visit => 'Visit Us', $blog => 'Blog' ) as $pid => $label ) {
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'     => $label,
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $pid,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		)
	);
}
set_theme_mod( 'nav_menu_locations', array( 'main_menu' => $menu_id ) );

// ---------------------------------------------------------------------------
// Footer widgets: hours text and a social icons widget from a plugin.
// ---------------------------------------------------------------------------
update_option(
	'widget_text',
	array(
		2              => array(
			'title'  => 'Visit',
			'text'   => "48 Harbour Lane, Toronto\nTue to Fri 7 to 6, Sat 7 to 4, Sun 8 to 2\n416-555-0142",
			'filter' => true,
			'visual' => true,
		),
		'_multiwidget' => 1,
	)
);
update_option(
	'widget_zoom-social-icons-widget',
	array(
		2              => array( 'title' => 'Follow us' ),
		'_multiwidget' => 1,
	)
);
$sidebars                = get_option( 'sidebars_widgets', array() );
$sidebars['footer-one']  = array( 'text-2' );
$sidebars['footer-two']  = array( 'zoom-social-icons-widget-2' );
update_option( 'sidebars_widgets', $sidebars );

// ---------------------------------------------------------------------------
// Newsletter popup on every page (Popup Maker), opens after 3 seconds.
// ---------------------------------------------------------------------------
if ( function_exists( 'pum_get_default_theme_id' ) ) {
	$popup_id = wp_insert_post(
		array(
			'post_type'    => 'popup',
			'post_title'   => 'Newsletter',
			'post_status'  => 'publish',
			'post_content' => '<h3>Get 10% off your first order!</h3><p>Join our newsletter for weekly specials.</p><p><input type="email" placeholder="Your email"> <button>Subscribe</button></p><p><small>Demo: nothing is sent.</small></p>',
		)
	);
	update_post_meta( $popup_id, 'popup_title', 'Get 10% off' );
	update_post_meta( $popup_id, 'enabled', 1 );
	update_post_meta(
		$popup_id,
		'popup_settings',
		array(
			'triggers'               => array( array( 'type' => 'auto_open', 'settings' => array( 'cookie_name' => array( "pum-$popup_id" ), 'delay' => 3000 ) ) ),
			'cookies'                => array( array( 'event' => 'on_popup_close', 'settings' => array( 'name' => "pum-$popup_id", 'key' => '', 'session' => false, 'time' => '1 day', 'path' => '1' ) ) ),
			'conditions'             => array(),
			'theme_id'               => pum_get_default_theme_id(),
			'size'                   => 'medium',
			'responsive_min_width'   => '0%',
			'responsive_max_width'   => '100%',
			'animation_type'         => 'fade',
			'animation_speed'        => '350',
			'animation_origin'       => 'center top',
			'location'               => 'center',
			'position_top'           => '100',
			'zindex'                 => '1999999999',
			'close_on_overlay_click' => true,
		)
	);
}

WP_CLI::success( "Seeded content. Home #{$home}, menu #{$menu_page}, cakes #{$cakes}, form #{$form_id}." );
