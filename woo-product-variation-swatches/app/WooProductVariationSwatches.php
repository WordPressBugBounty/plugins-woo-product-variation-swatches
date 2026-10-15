<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Rtwpvs
 */

namespace Rtwpvs;

use Rtwpvs\Controllers\BlackFridayV2;
use Rtwpvs\Controllers\Hooks;
use Rtwpvs\Controllers\Offer;
use Rtwpvs\Controllers\Review;
use Rtwpvs\Controllers\Install;
use Rtwpvs\Controllers\InitHooks;
use Rtwpvs\Controllers\AppliedHook;
use Rtwpvs\Controllers\SettingsAPI;
use Rtwpvs\Controllers\ScriptLoader;
use Rtwpvs\Controllers\ThemeSupport;
use Rtwpvs\Controllers\Notifications;
use Rtwpvs\Controllers\ProductMetaBox;
use Rtwpvs\Controllers\ProCompatibility;
use Rtwpvs\Controllers\ProductSwatchesTab;
use Rtwpvs\Controllers\DeactivationFeedback;

/**
 * Class WooProductVariationSwatches
 */
final class WooProductVariationSwatches {
	protected static $_instance = null;

	private $_settings_api;

	protected $plugin_id = 'rtwpvs';

	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		$this->hooks();
		do_action( 'rtwpvs_loaded', $this );
	}

	public function hooks() {
		$this->load_plugin_textdomain(); // Load text domain
		Notifications::init();
		// new Offer();
		new BlackFridayV2();
		Review::init();
		// Registered before the WooCommerce gate so the deactivation feedback
		// modal is still available when WooCommerce is inactive.
		if ( is_admin() ) {
			DeactivationFeedback::init();
		}
		if ( $this->is_valid_php_version() && $this->is_wc_active() ) {
			// Must precede the defaults sync: seeding `default_dropdown_to` first
			// would make the legacy migration skip itself and drop the old value.
			add_action( 'init', [ Install::class, 'maybe_migrate_dropdown_setting' ], 3 );
			add_action( 'init', [ Install::class, 'maybe_sync_defaults' ], 4 );
			add_action( 'init', [ $this, 'settings_api' ], 5 );
			new ScriptLoader();
			new ProductMetaBox();
			AppliedHook::init();
			Hooks::init();
			InitHooks::init();
			new ThemeSupport();
			// Product-data tab hooks only fire on the product edit screen.
			ProductSwatchesTab::init( $this );
			if ( is_admin() ) {
				ProCompatibility::init();
			}
		}
	}

	public function settings_api() {
		if ( ! $this->_settings_api ) {
			$this->_settings_api = new SettingsAPI();
		}

		return $this->_settings_api;
	}

	public function get_transient_name( $id, $type ) {
		$transient_name = false;
		if ( $type === 'attribute-html' ) {
			$transient_name = sprintf( '%s_attribute_html_%s', $this->plugin_id, $id );
		} elseif ( $type === 'attribute-taxonomy' ) {
			$transient_name = sprintf( '%s_attribute_taxonomy_%s', $this->plugin_id, $id );
		}

		return apply_filters( 'rtwpvs_transient_name', $transient_name, $type, $id );
	}

	/**
	 * @return bool
	 */
	public function is_valid_php_version() {
		return version_compare( PHP_VERSION, '5.6.0', '>=' );
	}

	/**
	 * @return bool
	 */
	public function is_wc_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * @return bool
	 */
	public function is_valid_wc_version() {
		return version_compare( WC_VERSION, '3.2', '>' );
	}

	/**
	 * Load Localisation files.
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 * Locales found in:
	 *      - WP_LANG_DIR/woo-product-variation-swatches/woo-product-variation-swatches-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/woo-product-variation-swatches-LOCALE.mo.
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'woo-product-variation-swatches' );
		unload_textdomain( 'woo-product-variation-swatches' );
		load_textdomain( 'woo-product-variation-swatches', WP_LANG_DIR . '/woo-product-variation-swatches/woo-product-variation-swatches-' . $locale . '.mo' );
		load_plugin_textdomain( 'woo-product-variation-swatches', false, trailingslashit( RTWPVS_PLUGIN_DIRNAME ) . 'languages' );
	}

	/**
	 * @param      $id
	 * @param null $givenDefault
	 *
	 * @return string
	 */
	public function get_option( $id, $givenDefault = null ) {
		if ( ! $this->_settings_api ) {
			$this->settings_api();
		}

		return $this->_settings_api->get_option( $id, $givenDefault );
	}

	public function update_option( $id, $value ) {
		if ( ! $this->_settings_api ) {
			$this->settings_api();
		}

		return $this->_settings_api->update_option( $id, $value );
	}

	public function get_assets_uri( $file ) {
		$file = ltrim( $file, '/' );

		return trailingslashit( RTWPVS_PLUGIN_URI . 'assets' ) . $file;
	}

	public function get_images_uri( $file ) {
		$file = ltrim( $file, '/' );

		return trailingslashit( RTWPVS_PLUGIN_URI . 'assets/images' ) . $file;
	}

	/**
	 * Pro compatibility API versions this Free release explicitly supports.
	 *
	 * Policy: only listed versions are 'api'. A Pro without a valid RTWPVSP_API
	 * (missing, malformed, < 1) is 'legacy' (Pro 2.3.x and older keep working);
	 * a valid but unlisted, e.g. newer, version is 'incompatible' until a Free
	 * release adds it here.
	 *
	 * @var int[]
	 */
	const SUPPORTED_PRO_API = [ 1 ];

	/**
	 * Directory holding the Pro plugin's templates, for inclusion.
	 *
	 * Returns the real Pro directory when it resolves (see
	 * get_pro_template_dir()); otherwise Free's placeholder directory, whose
	 * empty templates make legacy Pro's wc_get_template() calls harmless instead
	 * of including a root-level path.
	 *
	 * @return string Absolute directory path without trailing slash.
	 */
	public function get_template_path() {
		$dir = $this->get_pro_template_dir();

		return '' !== $dir ? $dir : $this->get_placeholder_template_dir();
	}

	/**
	 * Real Pro templates directory, or '' when it cannot be resolved.
	 *
	 * API-aware Pro supplies its own directory through `rtwpvs_template_path`.
	 * For legacy Pro the historical `<free-dir>-pro/templates` sibling is used,
	 * but only when it exists. A filtered path that is not a directory, or that
	 * is Free's own placeholder directory, counts as unresolved.
	 *
	 * @return string
	 */
	public function get_pro_template_dir() {
		$dir = untrailingslashit( (string) apply_filters( 'rtwpvs_template_path', $this->get_legacy_pro_dir( 'templates' ) ) );

		if ( '' === $dir || ! is_dir( $dir ) || $dir === $this->get_placeholder_template_dir() ) {
			return '';
		}

		return $dir;
	}

	/**
	 * Free-owned directory with empty stand-ins for Pro's templates.
	 *
	 * @return string
	 */
	public function get_placeholder_template_dir() {
		return RTWPVS_PLUGIN_PATH . 'app/Compat/templates';
	}

	public function get_template_file_path( $file_name ) {
		$file_name = ltrim( $file_name, '/' );

		return trailingslashit( $this->get_template_path() ) . $file_name . '.php';
	}

	/**
	 * Directory holding the Pro plugin's admin views.
	 *
	 * Resolved like get_template_path(), through the `rtwpvs_views_path` filter.
	 *
	 * @return string Absolute directory path without trailing slash, or ''.
	 */
	public function get_views_path() {
		return (string) apply_filters( 'rtwpvs_views_path', $this->get_legacy_pro_dir( 'views' ) );
	}

	public function get_views_file_path( $file ) {
		$file = ltrim( $file, '/' );
		$dir  = $this->get_views_path();

		return '' === $dir ? '' : trailingslashit( $dir ) . $file . '.php';
	}

	/**
	 * Legacy Pro sibling directory (`<free-dir>-pro/<sub>`) when it exists.
	 *
	 * Kept for Pro 2.3.x and older, which do not declare their own paths.
	 *
	 * @param string $sub Sub directory: 'templates' or 'views'.
	 *
	 * @return string Absolute directory path, or '' when it does not exist.
	 */
	private function get_legacy_pro_dir( $sub ) {
		$dir = untrailingslashit( RTWPVS_PLUGIN_PATH ) . '-pro/' . $sub;

		return is_dir( $dir ) ? $dir : '';
	}

	/**
	 * Detected Pro compatibility mode.
	 *
	 * - 'none'         No Pro plugin loaded.
	 * - 'legacy'       Pro loaded without a valid `RTWPVSP_API` (Pro 2.3.x and older).
	 * - 'api'          Pro declares a version listed in SUPPORTED_PRO_API.
	 * - 'incompatible' Pro declares a valid but unsupported (e.g. newer) version.
	 *
	 * Evaluated on every call (cheap) so plugin load order never matters; all
	 * plugin files are loaded before `plugins_loaded`, when this class boots.
	 *
	 * @return string
	 */
	public function pro_mode() {
		return self::resolve_pro_mode( function_exists( 'rtwpvsp' ), defined( 'RTWPVSP_API' ) ? constant( 'RTWPVSP_API' ) : null );
	}

	/**
	 * Pro API version when Pro runs in 'api' mode, otherwise 0.
	 *
	 * @return int
	 */
	public function pro_api_version() {
		return 'api' === $this->pro_mode() ? self::normalize_api_version( constant( 'RTWPVSP_API' ) ) : 0;
	}

	/**
	 * Pure mode resolver (separated for testing).
	 *
	 * @param bool  $pro_present Whether the Pro plugin is loaded.
	 * @param mixed $api         Value of RTWPVSP_API, or null when undefined.
	 *
	 * @return string 'none', 'legacy', 'api' or 'incompatible'.
	 */
	public static function resolve_pro_mode( $pro_present, $api ) {
		if ( ! $pro_present ) {
			return 'none';
		}

		$version = self::normalize_api_version( $api );
		if ( $version < 1 ) {
			return 'legacy';
		}

		return in_array( $version, self::SUPPORTED_PRO_API, true ) ? 'api' : 'incompatible';
	}

	/**
	 * Normalize a declared API version to a positive integer (0 when invalid).
	 *
	 * @param mixed $api Declared API version.
	 *
	 * @return int
	 */
	private static function normalize_api_version( $api ) {
		if ( is_int( $api ) ) {
			return max( 0, $api );
		}
		if ( is_string( $api ) && ctype_digit( $api ) ) {
			return (int) $api;
		}

		return 0;
	}

	public function locate_template( $name ) {
		// Look within passed path within the theme - this is priority.
		$template = [
			"woo-product-variation-swatches-pro/$name.php",
		];

		if ( ! $template_file = locate_template( $template ) ) {
			$template_file = $this->get_template_file_path( $name );
		}

		return apply_filters( 'rtwpvs_locate_template', $template_file, $name );
	}

	public function locate_views( $name ) {
		$template_file = apply_filters( 'rtwpvs_locate_views', $this->get_views_file_path( $name ), $name );

		// Pro includes this path directly: never hand back a missing file, which
		// would be a fatal error. An admin notice reports the unresolved view.
		if ( '' === $template_file || ! is_readable( $template_file ) ) {
			return RTWPVS_PLUGIN_PATH . 'app/Compat/missing-pro-view.php';
		}

		return $template_file;
	}
}
