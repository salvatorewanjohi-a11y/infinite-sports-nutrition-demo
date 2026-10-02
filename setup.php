<?php
/**
 * Builds the Infinite Sports Nutrition demo store inside WordPress Playground.
 * Run by blueprint.json after WooCommerce and the theme are installed.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized, so each one is copied straight into uploads and registered with its
// size. (media_handle_sideload opens every image, which made the demo slow to build.)
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

// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
// (Real hosting can regenerate thumbnails later.)
if ( defined( 'ISN_FAST' ) && ISN_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'Infinite Sports Nutrition',
	'blogdescription'                        => '100% authentic sports nutrition, Nairobi',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'The Bazaar Building, 3rd Floor, Shop C6, Moi Avenue',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '3',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Delivery.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => '30-Min Express Dispatch', 'cost' => '400', 'tax_status' => 'none' ] );
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Standard Delivery (same day)', 'cost' => '250', 'tax_status' => 'none' ] );
$id = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$id}_settings", [ 'title' => 'Free Nairobi Express Delivery', 'requires' => 'min_amount', 'min_amount' => '15000' ] );

$rest = new WC_Shipping_Zone();
$rest->set_zone_name( 'Rest of Kenya' );
$rest->add_location( 'KE', 'country' );
$rest->save();
$id = $rest->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'County Courier (1–3 days)', 'cost' => '600', 'tax_status' => 'none' ] );

update_option( 'woocommerce_pickup_location_settings', [ 'enabled' => 'yes', 'title' => 'In-Store Pickup', 'tax_status' => 'none', 'cost' => '' ] );
update_option( 'pickup_location_pickup_locations', [ [
	'name'    => 'Infinite Sports Nutrition – The Bazaar',
	'address' => [ 'address_1' => 'The Bazaar Building, 3rd Floor, Shop C6, Moi Avenue', 'city' => 'Nairobi CBD', 'state' => 'KE30', 'postcode' => '', 'country' => 'KE' ],
	'details' => 'Mon–Sat 8:00 AM – 7:00 PM. We will call you when your order is packed.',
	'enabled' => true,
] ] );

// Payment: pay on delivery until M-Pesa is connected.
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay on delivery (M-Pesa or cash)',
	'description'        => 'Pay by M-Pesa or cash when your order arrives or at pickup.',
	'instructions'       => 'Our rider will confirm your order by phone.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );

// Newsletter welcome coupon.
$c = new WC_Coupon();
$c->set_code( 'WELCOME500' );
$c->set_discount_type( 'fixed_cart' );
$c->set_amount( 500 );
$c->set_minimum_amount( 3000 );
$c->set_individual_use( true );
$c->set_usage_limit_per_user( 1 );
$c->set_description( 'Newsletter welcome offer' );
$c->save();

// Products: the shop's WhatsApp catalogue plus Instagram-only items (isn-products.json, see catalogue/build.mjs).
require '/wordpress/isn-import-products.php';

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
