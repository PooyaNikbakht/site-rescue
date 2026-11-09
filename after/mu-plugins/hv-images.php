<?php
/**
 * Plugin Name: Harbourview image settings
 * Description: New uploads are scaled down and saved as WebP with every size WordPress needs for srcset.
 */

// JPEG and PNG uploads produce WebP files (WordPress 6.1+ built-in support).
add_filter( 'image_editor_output_format', function ( $formats ) {
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
} );

// WebP at quality 78 looks the same as the camera JPEG at a fraction of the size.
add_filter( 'wp_editor_set_quality', function ( $quality, $mime ) {
	return 'image/webp' === $mime ? 78 : $quality;
}, 10, 2 );

// Keep WordPress' default: anything wider than 2560px is scaled on upload.
// (The old child theme had switched this off.)
