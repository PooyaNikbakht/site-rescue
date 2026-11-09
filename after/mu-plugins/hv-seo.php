<?php
/**
 * Plugin Name: Harbourview SEO basics
 * Description: Unique titles, meta descriptions, Open Graph tags and Bakery structured data.
 *
 * A small, readable alternative to a full SEO plugin. On a site where the
 * owner edits SEO text often, a lightweight SEO plugin would be the better
 * choice so they can change it in the admin.
 */

if ( is_admin() ) {
	return;
}

const HV_BUSINESS = array(
	'name'      => 'Harbourview Bakery',
	'phone'     => '+1-416-555-0142',
	'email'     => 'hello@harbourview-bakery.example',
	'street'    => '48 Harbour Lane',
	'city'      => 'Toronto',
	'region'    => 'ON',
	'country'   => 'CA',
	'latitude'  => 43.6389,
	'longitude' => -79.3817,
);

// "Menu | Harbourview Bakery" instead of one keyword title everywhere.
add_filter( 'document_title_separator', function () {
	return '|';
} );
add_filter( 'document_title_parts', function ( $parts ) {
	if ( is_front_page() ) {
		$parts['title']   = 'Harbourview Bakery';
		$parts['tagline'] = 'Sourdough, pastries and custom cakes in Toronto';
	}
	return $parts;
} );

function hv_meta_description() {
	$pages = array(
		'home'         => 'Neighbourhood bakery by the Toronto harbour. Naturally leavened bread, croissants and custom celebration cakes, baked by hand every morning.',
		'menu'         => 'Our daily menu: sourdough and rye, butter croissants, cardamom buns, cookies and celebration cakes, with prices.',
		'custom-cakes' => 'Order a custom cake for a birthday, wedding or party. Tell us the date and guest count and we reply within two business days.',
		'about'        => 'Harbourview Bakery started as a weekend market stall in 2014. Meet the team and learn where our flour, butter and fruit come from.',
		'visit'        => 'Find Harbourview Bakery at 48 Harbour Lane, Toronto. Open Tuesday to Sunday. Hours, parking and transit directions.',
		'blog'         => 'Baking tips, holiday order dates and news from the Harbourview Bakery kitchen.',
	);
	if ( is_front_page() ) {
		return $pages['home'];
	}
	if ( is_home() ) {
		return $pages['blog'];
	}
	if ( is_page() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		return $pages[ $slug ] ?? '';
	}
	if ( is_singular( 'post' ) ) {
		return wp_strip_all_tags( get_the_excerpt( get_queried_object_id() ) );
	}
	if ( is_category() ) {
		return wp_strip_all_tags( term_description() ) ?: $pages['blog'];
	}
	return '';
}

add_action( 'wp_head', function () {
	$desc  = hv_meta_description();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$title = wp_get_document_title();
	$image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( null, 'large' );
	} elseif ( get_option( 'hv_hero_attachment_id' ) ) {
		$image = wp_get_attachment_image_url( (int) get_option( 'hv_hero_attachment_id' ), 'large' );
	}

	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( HV_BUSINESS['name'] ) );
	echo '<meta property="og:locale" content="en_CA">' . "\n";
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	echo '<script type="application/ld+json">' . wp_json_encode( hv_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 5 );

function hv_schema() {
	$b      = HV_BUSINESS;
	$bakery = array(
		'@type'                     => 'Bakery',
		'@id'                       => home_url( '/#bakery' ),
		'name'                      => $b['name'],
		'url'                       => home_url( '/' ),
		'telephone'                 => $b['phone'],
		'email'                     => $b['email'],
		'priceRange'                => '$$',
		'servesCuisine'             => array( 'Bakery', 'French pastry', 'Cakes' ),
		'hasMenu'                   => home_url( '/menu/' ),
		'acceptsReservations'       => false,
		'address'                   => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $b['street'],
			'addressLocality' => $b['city'],
			'addressRegion'   => $b['region'],
			'addressCountry'  => $b['country'],
		),
		'geo'                       => array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $b['latitude'],
			'longitude' => $b['longitude'],
		),
		'openingHoursSpecification' => array(
			array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => array( 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ), 'opens' => '07:00', 'closes' => '18:00' ),
			array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Saturday', 'opens' => '07:00', 'closes' => '16:00' ),
			array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Sunday', 'opens' => '08:00', 'closes' => '14:00' ),
		),
	);
	if ( get_option( 'hv_hero_attachment_id' ) ) {
		$bakery['image'] = wp_get_attachment_image_url( (int) get_option( 'hv_hero_attachment_id' ), 'large' );
	}

	$graph = array( $bakery );

	if ( is_singular( 'post' ) ) {
		$post    = get_queried_object();
		$graph[] = array(
			'@type'            => 'BlogPosting',
			'headline'         => get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'description'      => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'image'            => get_the_post_thumbnail_url( $post, 'large' ),
			'mainEntityOfPage' => get_permalink( $post ),
			'author'           => array( '@type' => 'Organization', 'name' => $b['name'] ),
			'publisher'        => array( '@id' => home_url( '/#bakery' ) ),
		);
	}

	return array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);
}
