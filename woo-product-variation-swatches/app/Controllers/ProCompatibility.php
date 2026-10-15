<?php
/**
 * Free <-> Pro compatibility notices.
 *
 * @package Rtwpvs\Controllers
 */

namespace Rtwpvs\Controllers;

/**
 * Shows one compatibility notice when the installed Pro needs attention,
 * following the SchemaEngine AI ProCompatibilityNotice pattern:
 * an admin notice on every admin screen for users who can update plugins, an
 * update row under the affected plugin on the Plugins screen (anchored, so
 * "Go to Plugins" scrolls to it), and the same notice printed directly on the
 * plugin's settings page, where ordinary admin notices are suppressed.
 *
 * States (capability-based, never from version strings):
 * - outdated:     Pro loaded without RTWPVSP_API ('legacy', Pro 2.3.1 and older).
 * - incompatible: Pro declares an API this Free does not support (update Free).
 * - missing:      Pro loaded but its templates/views cannot be resolved.
 * Nothing is shown when Pro is not installed or is supported.
 */
class ProCompatibility {

	/**
	 * HTML id of the notice row on the Plugins screen (link anchor).
	 *
	 * @var string
	 */
	const OUTDATED_ROW_ID = 'rtwpvs-pro-compat';

	/**
	 * Register hooks (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'render' ] );
		add_action( 'after_plugin_row', [ __CLASS__, 'render_plugin_row' ], 20 );
	}

	/**
	 * Current compatibility problem, or '' when there is none.
	 *
	 * @return string outdated|incompatible|missing|''
	 */
	public static function get_state() {
		$mode = rtwpvs()->pro_mode();
		if ( 'legacy' === $mode ) {
			return 'outdated';
		}
		if ( 'incompatible' === $mode ) {
			return 'incompatible';
		}
		if ( 'api' === $mode && self::get_missing_pro_dirs() ) {
			return 'missing';
		}

		return '';
	}

	/**
	 * Whether a loaded Pro predates the compatibility contract.
	 *
	 * @return bool
	 */
	public static function is_pro_outdated() {
		return 'outdated' === self::get_state();
	}

	/**
	 * Pro directories that are expected but could not be resolved.
	 *
	 * @return string[] Subset of [ 'templates', 'views' ].
	 */
	public static function get_missing_pro_dirs() {
		if ( 'none' === rtwpvs()->pro_mode() ) {
			return [];
		}

		$missing = [];
		$paths   = [
			// The real Pro directory, never Free's placeholder stand-in.
			'templates' => rtwpvs()->get_pro_template_dir(),
			'views'     => rtwpvs()->get_views_path(),
		];
		foreach ( $paths as $type => $path ) {
			if ( '' === $path || ! is_dir( $path ) ) {
				$missing[] = $type;
			}
		}

		return $missing;
	}

	/**
	 * Admin notice on every admin screen (users who can update plugins).
	 *
	 * The settings page removes all admin_notices callbacks; it prints the
	 * same notice through render_settings_notices() instead.
	 *
	 * @return void
	 */
	public static function render() {
		$state = self::get_state();
		if ( '' === $state || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning rtwpvs-pro-compat-notice"><p>%s</p></div>',
			self::get_message_html( $state ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_message_html().
		);
	}

	/**
	 * Print the notice on the plugin's own settings page, before the React
	 * mount point (third-party notices stay suppressed there).
	 *
	 * @return void
	 */
	public static function render_settings_notices() {
		self::render();
	}

	/**
	 * Update row under the affected plugin on the Plugins screen.
	 *
	 * Outdated Pro / missing Pro files: under the Pro row. Incompatible API
	 * (the free plugin needs updating): under the free plugin's row.
	 *
	 * @param string $plugin_file Plugin basename of the current row.
	 *
	 * @return void
	 */
	public static function render_plugin_row( $plugin_file ) {
		$state = self::get_state();
		if ( '' === $state || is_network_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$target = 'incompatible' === $state ? RTWPVS_PLUGIN_BASENAME : ( defined( 'RTWPVSP_PLUGIN_FILE' ) ? plugin_basename( RTWPVSP_PLUGIN_FILE ) : '' );
		if ( '' === $target || $target !== $plugin_file ) {
			return;
		}

		global $wp_list_table;
		$colspan = ( $wp_list_table && method_exists( $wp_list_table, 'get_column_count' ) ) ? (int) $wp_list_table->get_column_count() : 3;
		$parts   = self::get_message_parts( $state );

		printf(
			'<tr id="%1$s" class="plugin-update-tr active rtwpvs-pro-compat-row" style="scroll-margin-top:60px"><td colspan="%2$d" class="plugin-update colspanchange"><div class="update-message notice inline notice-warning notice-alt"><p><strong>%3$s</strong> %4$s</p></div></td></tr>',
			esc_attr( self::OUTDATED_ROW_ID ),
			absint( $colspan ),
			esc_html( $parts['title'] ),
			esc_html( $parts['message'] )
		);
	}

	/**
	 * Back-compat alias for the earlier outdated-only row callback.
	 *
	 * @param string $plugin_file Plugin basename of the current row.
	 *
	 * @return void
	 */
	public static function outdated_pro_plugin_row( $plugin_file ) {
		self::render_plugin_row( $plugin_file );
	}

	/**
	 * Back-compat alias: outdated-Pro notice.
	 *
	 * @return void
	 */
	public static function outdated_pro_notice() {
		if ( self::is_pro_outdated() ) {
			self::render();
		}
	}

	/**
	 * Back-compat alias: incompatible-API notice.
	 *
	 * @return void
	 */
	public static function incompatible_pro_notice() {
		if ( 'incompatible' === self::get_state() ) {
			self::render();
		}
	}

	/**
	 * Back-compat alias: missing-Pro-files notice.
	 *
	 * @return void
	 */
	public static function missing_pro_files_notice() {
		if ( 'missing' === self::get_state() ) {
			self::render();
		}
	}

	/**
	 * Unescaped title and message for a state.
	 *
	 * @param string $state outdated|incompatible|missing.
	 *
	 * @return array{title: string, message: string}
	 */
	private static function get_message_parts( $state ) {
		if ( 'incompatible' === $state ) {
			return [
				'title'   => __( 'Variation Swatches for WooCommerce needs an update.', 'woo-product-variation-swatches' ),
				'message' => __( 'The installed Pro version uses a newer compatibility API than this version of the free plugin supports. Update Variation Swatches for WooCommerce to the latest version; until then Product Swatches editing shows an update notice.', 'woo-product-variation-swatches' ),
			];
		}
		if ( 'missing' === $state ) {
			return [
				'title'   => __( 'Variation Swatches Pro needs to be reinstalled.', 'woo-product-variation-swatches' ),
				'message' => sprintf(
					/* translators: %s: comma-separated list of missing folders (templates, views). */
					__( 'some Pro files could not be located (%s). Shop swatches and the product "Product Swatches" tab may be unavailable. Reinstall Pro or update it to the latest version.', 'woo-product-variation-swatches' ),
					implode( ', ', self::get_missing_pro_dirs() )
				),
			];
		}

		return [
			'title'   => __( 'Variation Swatches Pro needs an update.', 'woo-product-variation-swatches' ),
			'message' => sprintf(
				/* translators: 1: installed Pro version, 2: required Pro version. */
				__( 'Version %1$s is installed, but editing Product Swatches requires Variation Swatches Pro %2$s or later. Until Pro is updated that tab shows an update notice; saved settings and your storefront are not affected.', 'woo-product-variation-swatches' ),
				defined( 'RTWPVSP_VERSION' ) ? (string) RTWPVSP_VERSION : '',
				ProductSwatchesTab::MIN_PRO_VERSION
			),
		];
	}

	/**
	 * Escaped notice HTML linking to the plugin row on the Plugins screen.
	 *
	 * @param string $state outdated|incompatible|missing.
	 *
	 * @return string
	 */
	private static function get_message_html( $state ) {
		$parts = self::get_message_parts( $state );

		return sprintf(
			'<strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a>',
			esc_html( $parts['title'] ),
			esc_html( $parts['message'] ),
			esc_url( admin_url( 'plugins.php#' . self::OUTDATED_ROW_ID ) ),
			esc_html__( 'Go to Plugins', 'woo-product-variation-swatches' )
		);
	}
}
