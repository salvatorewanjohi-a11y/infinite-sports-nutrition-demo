<?php
/**
 * Imports the catalogue from isn-products.json (built by catalogue/build.mjs) with photos from isn-images/.
 * Used by setup.php in the Playground demo, and on the local store via a one-off runner.
 * Removes existing products first, so it can be re-run.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$isn_json   = defined( 'ISN_PRODUCTS_JSON' ) ? ISN_PRODUCTS_JSON : '/wordpress/isn-products.json';
$isn_images = defined( 'ISN_IMAGES_DIR' ) ? ISN_IMAGES_DIR : '/wordpress/isn-images';

// The photos are already web-sized, so each one is copied straight into uploads and registered with its
// size. (media_handle_sideload opens every image, which made the demo slow to build.)
if ( ! function_exists( 'isn_sideload' ) ) {
	function isn_sideload( $tmp, $name, $parent, $title ) {
		$up   = wp_upload_dir();
		$file = wp_unique_filename( $up['path'], $name );
		$dest = trailingslashit( $up['path'] ) . $file;
		if ( ! @rename( $tmp, $dest ) && ! copy( $tmp, $dest ) ) {
			return new WP_Error( 'isn_copy', 'Could not copy ' . $name );
		}
		$type = wp_check_filetype( $file );
		$size = @getimagesize( $dest ) ?: [ 0, 0 ];
		$id   = wp_insert_attachment( [ 'post_mime_type' => $type['type'], 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $file ], $dest, $parent, true, false );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		wp_update_attachment_metadata( $id, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
		return $id;
	}
}

$isn_products = json_decode( file_get_contents( $isn_json ), true );

// Remove the previous catalogue (products, their photos, and categories/brands no longer used).
foreach ( wc_get_products( [ 'limit' => -1, 'status' => 'any', 'return' => 'ids' ] ) as $old ) {
	foreach ( get_attached_media( 'image', $old ) as $att ) {
		wp_delete_attachment( $att->ID, true );
	}
	wp_delete_post( $old, true );
}
$isn_cats   = array_unique( array_column( $isn_products, 'category' ) );
$isn_brands = array_filter( array_unique( array_column( $isn_products, 'brand' ) ) );
foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
	if ( ! in_array( $t->name, $isn_cats, true ) && $t->slug !== 'uncategorized' ) wp_delete_term( $t->term_id, 'product_cat' );
}
foreach ( get_terms( [ 'taxonomy' => 'product_brand', 'hide_empty' => false ] ) as $t ) {
	if ( ! in_array( $t->name, $isn_brands, true ) ) wp_delete_term( $t->term_id, 'product_brand' );
}

// Categories in menu order, then goals and brands.
$isn_cat_ids = [];
foreach ( [ 'Protein', 'Creatine', 'Mass Gainers', 'Pre-Workout', 'Amino Acids', 'Fat Burners', 'Wellness', 'Performance', 'Gym Accessories' ] as $order => $c ) {
	if ( ! in_array( $c, $isn_cats, true ) ) continue;
	$t = term_exists( $c, 'product_cat' ) ?: wp_insert_term( $c, 'product_cat' );
	$isn_cat_ids[ $c ] = (int) $t['term_id'];
	update_term_meta( $isn_cat_ids[ $c ], 'order', $order );
}
foreach ( [ 'Muscle Building', 'Weight Loss', 'Energy & Focus', 'Recovery' ] as $g ) {
	if ( ! term_exists( $g, 'product_tag' ) ) wp_insert_term( $g, 'product_tag' );
}
foreach ( $isn_brands as $b ) {
	if ( ! term_exists( $b, 'product_brand' ) ) wp_insert_term( $b, 'product_brand' );
}

foreach ( $isn_products as $row ) {
	$p = new WC_Product_Simple();
	$p->set_name( $row['name'] );
	$p->set_slug( $row['slug'] );
	$p->set_status( 'publish' );
	$p->set_description( $row['description'] );
	$p->set_short_description( $row['description'] );
	if ( $row['regular_price'] !== null ) {
		$p->set_regular_price( $row['regular_price'] );
	}
	if ( $row['sale_price'] !== null ) {
		$p->set_sale_price( $row['sale_price'] );
	}
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 10 ); // placeholder until the shop gives real counts
	$p->set_category_ids( [ $isn_cat_ids[ $row['category'] ] ] );
	$tags = [];
	foreach ( $row['goals'] as $g ) {
		$tags[] = (int) term_exists( $g, 'product_tag' )['term_id'];
	}
	$p->set_tag_ids( $tags );
	$p->set_featured( $row['featured'] );
	$pid = $p->save();

	if ( $row['brand'] ) wp_set_object_terms( $pid, [ $row['brand'] ], 'product_brand' );
	update_post_meta( $pid, '_isn_spec_badge', $row['spec'] );

	$gallery = [];
	foreach ( $row['images'] as $k => $img ) {
		$src = "$isn_images/$img";
		if ( ! file_exists( $src ) ) continue;
		$tmp = wp_tempnam( $img );
		copy( $src, $tmp );
		$att = isn_sideload( $tmp, $img, $pid, $row['name'] );
		if ( is_wp_error( $att ) ) continue;
		if ( $k === 0 ) {
			set_post_thumbnail( $pid, $att );
		} else {
			$gallery[] = $att;
		}
	}
	if ( $gallery ) {
		update_post_meta( $pid, '_product_image_gallery', implode( ',', $gallery ) );
	}
}
wc_delete_product_transients();
