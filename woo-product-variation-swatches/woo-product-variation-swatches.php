<?php

/**
 * Plugin Name:             Variation Swatches for WooCommerce
 * Plugin URI:              https://radiustheme.com
 * Description:             Variation Swatches for WooCommerce change beautiful colors, images and buttons variation swatches for WooCommerce product attributes.
 * Version:                 2.5.0
 * Author:                  RadiusTheme
 * Author URI:              https://radiustheme.com
 * Requires at least:       6.0
 * WC requires at least:    3.2
 * WC tested up to:         11.1
 * Domain Path:             /languages
 * Text Domain:             woo-product-variation-swatches
 */

use Rtwpvs\WooProductVariationSwatches;
use Rtwpvs\Controllers\Install;
use Rtwpvs\Controllers\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define RTWPVS_PLUGIN_FILE.
if (!defined('RTWPVS_PLUGIN_FILE')) {
	define('RTWPVS_PLUGIN_FILE', __FILE__);
}

// Define RTCL_PLUGIN_FILE.
if (!defined('RTWPVS_VERSION')) {
	define('RTWPVS_VERSION', '2.5.0');
}

define( 'RTWPVS_PLUGIN_PATH', plugin_dir_path(__FILE__) );

define( 'RTWPVS_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
define( 'RTWPVS_PLUGIN_DIRNAME', dirname( plugin_basename( __FILE__ ) ) ); // plugin-slug
define( 'RTWPVS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // plugin-slug/plugin-slug.php


require_once RTWPVS_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Return the main plugin instance.
 *
 * @return WooProductVariationSwatches
 */
function rtwpvs() {
	return WooProductVariationSwatches::get_instance();
}

register_activation_hook( RTWPVS_PLUGIN_FILE, [ Install::class, 'activate' ] );
register_activation_hook( RTWPVS_PLUGIN_FILE, [ Review::class, 'rtvs_activation_time' ] );
register_deactivation_hook( RTWPVS_PLUGIN_FILE, [ Install::class, 'deactivate' ] );

add_action( 'plugins_loaded', 'rtwpvs' );

// HPOS
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );