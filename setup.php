<?php
/**
 * Builds the Infinite Sports Nutrition demo store inside WordPress Playground.
 * Run by blueprint.json after WooCommerce and the theme are installed.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

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

// Categories, goals and brands.
$cat = [];
foreach ( [ 'Protein', 'Mass Gainers', 'Pre-Workout', 'Performance', 'Gym Accessories' ] as $c ) {
	$t = term_exists( $c, 'product_cat' ) ?: wp_insert_term( $c, 'product_cat' );
	$cat[ $c ] = (int) $t['term_id'];
}
foreach ( [ 'Muscle Building', 'Weight Loss', 'Energy & Focus', 'Recovery' ] as $g ) {
	if ( ! term_exists( $g, 'product_tag' ) ) wp_insert_term( $g, 'product_tag' );
}
foreach ( [ 'Nutrex', 'Rule 1', 'MHP', 'MuscleTech', 'BSN', 'MuscleMeds' ] as $b ) {
	if ( ! term_exists( $b, 'product_brand' ) ) wp_insert_term( $b, 'product_brand' );
}

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

// Products, from the shop's Instagram posts.
$products = [
	[ 'anabol-hardcore', 'Nutrex Anabol Hardcore – 60 Liquid Capsules', 'Nutrex', 'Performance', [ 'Muscle Building', 'Recovery' ], 3500, '60 Liquid Caps',
	  'A non-steroidal, non-hormonal anabolic activator made to back up serious weight training and a high-protein diet. Supports muscle protein synthesis, lean muscle and recovery between hard sessions. Fast-acting liquid capsules, vegetarian-friendly.' ],
	[ 'rule1-mass-gainer', 'Rule 1 Mass Gainer – Cookies & Crème', 'Rule 1', 'Mass Gainers', [ 'Muscle Building' ], 6500, '40g Protein',
	  'A high-calorie gainer built on an all-whey protein blend. Per full serving (2 heaping scoops): 1,230 calories, 40g protein, 252g carbs, about 9g BCAAs and 1g creatine. Cookies & Crème flavour.' ],
	[ 'mhp-up-your-mass', 'MHP Up Your Mass XXXL 1350', 'MHP', 'Mass Gainers', [ 'Muscle Building' ], 6500, '50g Protein',
	  'Serious bulking fuel for hard gainers: 1,350 calories, 50g multi-phase protein, 250g carbs, 11g BCAAs and 23g EAAs per full serving (6 scoops).' ],
	[ 'euphoriq', 'MuscleTech EuphoriQ Pre-Workout – Boogieman Punch', 'MuscleTech', 'Pre-Workout', [ 'Energy & Focus' ], 3500, '300mg Paraxanthine',
	  'Clean, strong energy from 300mg paraxanthine (enfinity®), plus 3.2g beta-alanine, 2.5g betaine, taurine, L-tyrosine, AlphaSize® A-GPC, NeuroFactor® and Huperzine A for focus. 20 servings, Boogieman Punch flavour.' ],
	[ 'rule1-citrulline', 'Rule 1 Citrulline – Unflavoured', 'Rule 1', 'Pre-Workout', [ 'Energy & Focus' ], 3500, '3g L-Citrulline',
	  'A clean single-ingredient L-Citrulline (3,000mg per serving) for bigger pumps and better endurance. Stimulant-free and unflavoured, so it stacks easily with your pre-workout, BCAAs or protein shake.' ],
	[ 'carnivor-shred', 'MuscleMeds Carnivor Shred – Chocolate, 4.35 lb', 'MuscleMeds', 'Protein', [ 'Weight Loss', 'Muscle Building' ], 9500, '23g Protein',
	  'Hydrolyzed beef protein isolate with a thermogenic complex. 23g protein and 175mg caffeine per serving, with 0g sugar and 0g fat. Lactose, dairy and gluten free. 56 servings, chocolate flavour. Contains caffeine: not for use close to bedtime.' ],
	[ 'nox-legendary', 'BSN N.O.-XPLODE Legendary Pre-Workout – 30 Servings', 'BSN', 'Pre-Workout', [ 'Energy & Focus' ], 4200, '275mg Caffeine',
	  'Explosive energy, intense focus and powerful pumps for high-intensity training. 275mg caffeine per serving, 30 servings. High caffeine dose: not recommended if you are sensitive to caffeine.' ],
	[ 'water-bottle', 'Large Capacity Sports Water Bottle (~2L)', '', 'Gym Accessories', [], 1800, '2 Litres',
	  'Keep hydration within reach at the gym, outdoors or at your desk. Around 2 litres, with a built-in straw for easy drinking mid-workout.' ],
	[ 'yoga-mat', 'Eco-Friendly EVA Yoga & Exercise Mat – Green', '', 'Gym Accessories', [], 2000, 'Non-Slip Grip',
	  'A comfortable, grippy EVA mat for yoga, stretching, home workouts and floor exercises. Vibrant green.' ],
	[ 'barbell-pad', 'Barbell Pad – Red', '', 'Gym Accessories', [], 1500, 'Squats & Hip Thrusts',
	  'A durable padded sleeve that makes squats, hip thrusts and other barbell lifts more comfortable. Red.' ],
];

$i = 0;
foreach ( $products as [ $slug, $name, $brand, $category, $goals, $price, $spec, $desc ] ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_slug( $slug );
	$p->set_status( 'publish' );
	$p->set_description( $desc );
	$p->set_short_description( $desc );
	$p->set_regular_price( $price );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 10 );
	$p->set_category_ids( [ $cat[ $category ] ] );
	$tags = [];
	foreach ( $goals as $g ) {
		$t      = term_exists( $g, 'product_tag' );
		$tags[] = (int) $t['term_id'];
	}
	$p->set_tag_ids( $tags );
	$p->set_featured( $i++ < 8 );
	$pid = $p->save();

	if ( $brand ) wp_set_object_terms( $pid, [ $brand ], 'product_brand' );
	update_post_meta( $pid, '_isn_spec_badge', $spec );

	$file = "/wordpress/isn-images/$slug.webp";
	if ( file_exists( $file ) ) {
		$tmp = wp_tempnam( "$slug.webp" );
		copy( $file, $tmp );
		$att = media_handle_sideload( [ 'name' => "$slug.webp", 'tmp_name' => $tmp ], $pid, $name );
		if ( ! is_wp_error( $att ) ) set_post_thumbnail( $pid, $att );
	}
}

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
