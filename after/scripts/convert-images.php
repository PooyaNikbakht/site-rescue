<?php
/**
 * Re-processes every uploaded image the way WordPress should have done it:
 * crop it to the shape it is shown in, scale it down to 2560px, save it as
 * WebP, and generate all the smaller WebP sizes used in srcset. Originals are kept on disk (WordPress
 * records them as "original_image") so nothing is lost.
 *
 * wp eval-file /work/after/scripts/convert-images.php
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
require_once ABSPATH . 'wp-admin/includes/image.php';

// Crop per photo: aspect ratio (width, height) and where the subject sits
// vertically (0 = top, 1 = bottom). Product and gallery photos share one
// 4:3 shape so rows line up; the hero matches the hero frame in the theme.
$crops   = array(
	'hero-sourdough'            => array( 26, 15, 0.5 ),
	'menu-sourdough'            => array( 4, 3, 0.5 ),
	'menu-croissant'            => array( 4, 3, 0.55 ),
	'menu-cinnamon-bun'         => array( 4, 3, 0.5 ),
	'menu-cookies'              => array( 4, 3, 0.5 ),
	'menu-celebration-cake'     => array( 4, 3, 0.45 ),
	'menu-baguette'             => array( 4, 3, 0.5 ),
	'about-shop'                => array( 16, 9, 0.42 ),
	'about-bakers'              => array( 4, 3, 0.65 ),
	'blog-starter'              => array( 4, 3, 0.45 ),
	'blog-wedding-cake'         => array( 4, 3, 0.56 ),
	'blog-holiday'              => array( 4, 3, 0.5 ),
	'blog-croissant-lamination' => array( 4, 3, 0.5 ),
	'cakes-layer'               => array( 4, 3, 0.5 ),
	'cakes-cupcakes'            => array( 4, 3, 0.5 ),
	'visit-coffee'              => array( 4, 3, 0.5 ),
);
$slug_by_id = array();
foreach ( (array) get_option( 'hv_seed_media', array() ) as $slug => $m ) {
	$slug_by_id[ (int) $m['id'] ] = $slug;
}

$ids = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$before = 0;
$after  = 0;
foreach ( $ids as $id ) {
	$original = get_attached_file( $id );
	if ( ! $original || ! file_exists( $original ) || str_ends_with( $original, '.webp' ) ) {
		continue;
	}
	$before += filesize( $original );

	$editor = wp_get_image_editor( $original );
	if ( is_wp_error( $editor ) ) {
		WP_CLI::warning( "#{$id}: " . $editor->get_error_message() );
		continue;
	}
	$size = $editor->get_size();
	$slug = $slug_by_id[ $id ] ?? '';
	if ( isset( $crops[ $slug ] ) ) {
		list( $rw, $rh, $focus ) = $crops[ $slug ];
		$w = $size['width'];
		$h = $size['height'];
		if ( $w / $h > $rw / $rh ) {
			$cw = (int) round( $h * $rw / $rh );
			$ch = $h;
			$cx = (int) round( ( $w - $cw ) / 2 );
			$cy = 0;
		} else {
			$cw = $w;
			$ch = (int) round( $w * $rh / $rw );
			$cx = 0;
			$cy = (int) round( max( 0, min( $h - $ch, $h * $focus - $ch / 2 ) ) );
		}
		$editor->crop( $cx, $cy, $cw, $ch );
		$size = $editor->get_size();
	}
	if ( $size['width'] > 2560 || $size['height'] > 2560 ) {
		$editor->resize( 2560, 2560, false );
	}
	$editor->set_quality( 78 );
	$info   = pathinfo( $original );
	$target = $info['dirname'] . '/' . $info['filename'] . '-scaled.webp';
	$saved  = $editor->save( $target, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		WP_CLI::warning( "#{$id}: " . $saved->get_error_message() );
		continue;
	}

	update_attached_file( $id, $saved['path'] );
	wp_update_post( array( 'ID' => $id, 'post_mime_type' => 'image/webp' ) );
	$meta                   = wp_generate_attachment_metadata( $id, $saved['path'] );
	$meta['original_image'] = basename( $original );
	wp_update_attachment_metadata( $id, $meta );

	$after += filesize( $saved['path'] );
	WP_CLI::log( sprintf( '#%d %s: %.1f MB -> %d KB (%dx%d WebP)', $id, basename( $original ), filesize( $original ) / 1e6, filesize( $saved['path'] ) / 1024, $saved['width'], $saved['height'] ) );
}

WP_CLI::success( sprintf( 'Full-size images: %.1f MB -> %.1f MB', $before / 1e6, $after / 1e6 ) );
