<?php
/**
 * Product Swatches product-edit tab (Free-owned container).
 *
 * @package Rtwpvs\Controllers
 */

namespace Rtwpvs\Controllers;

/**
 * Owns the "Product Swatches" product-data tab and its panel container.
 *
 * Contract (Free <-> Pro):
 * - Free registers the tab (`rtwpvs-tab`, target `rtwpvs_tab_options`,
 *   `show_if_variable`) in every mode.
 * - With a supported Pro ('api' mode) Free fires
 *   `rtwpvs_product_swatches_panel_content` ( WC_Product|null $product ) inside
 *   `<div id="rtwpvs_tab_options" class="panel wc-metaboxes-wrapper rtwpvs-tab-options hidden">`.
 *   Pro prints only the inner form (fields plus the `.rtwpvs-save-attributes` /
 *   `.rtwpvs-reset-attributes` toolbar), keeping the existing admin script
 *   serialization and save/reset AJAX contract unchanged.
 * - Without content, Free prints a notice panel instead (no form fields):
 *   upgrade (no Pro), update Pro (outdated Pro without the contract), update
 *   Free (incompatible API), or reinstall (Pro files missing).
 * - Pro's obsolete self-registered tab/panel callbacks
 *   (Rtwpvsp\Controllers\Admin\AdminFilter, Pro < MIN_PRO_VERSION) are removed
 *   so only Free's tab and panel exist. Saved `_rtwpvs` data and storefront
 *   rendering are not affected.
 */
class ProductSwatchesTab {

	/**
	 * Tab key (shared with older Pro versions).
	 *
	 * @var string
	 */
	const TAB_KEY = 'rtwpvs-tab';

	/**
	 * Panel id the tab targets.
	 *
	 * @var string
	 */
	const PANEL_ID = 'rtwpvs_tab_options';

	/**
	 * Action through which a supported Pro prints the panel form.
	 *
	 * @var string
	 */
	const CONTENT_HOOK = 'rtwpvs_product_swatches_panel_content';

	/**
	 * First Pro release implementing the content contract (notice text only).
	 *
	 * @var string
	 */
	const MIN_PRO_VERSION = '2.3.2';

	/**
	 * Obsolete Pro callbacks (class, method, priority) per hook, unchanged in
	 * every Pro release up to 2.3.1.
	 *
	 * @var array
	 */
	const OBSOLETE_PRO_CALLBACKS = [
		'woocommerce_product_data_tabs'   => [ 'Rtwpvsp\Controllers\Admin\AdminFilter', 'product_swatches_tab', 99 ],
		'woocommerce_product_data_panels' => [ 'Rtwpvsp\Controllers\Admin\AdminFilter', 'product_swatches_tab_content', 10 ],
	];

	/**
	 * Register hooks.
	 *
	 * Called from the main plugin constructor, so the instance is passed in:
	 * calling rtwpvs() here would re-enter the constructor (infinite recursion).
	 *
	 * @param \Rtwpvs\WooProductVariationSwatches $plugin Main plugin instance.
	 *
	 * @return void
	 */
	public static function init( $plugin ) {
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'register_tab' ], 99 );
		add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'render_panel' ] );

		if ( 'none' !== $plugin->pro_mode() ) {
			// Pro registers its callbacks later (on `rtwpvs_loaded`), so remove
			// them when the hooks start running, before they can print anything.
			add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'suppress_on_tabs' ], 1 );
			add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'suppress_obsolete_pro_callbacks' ], 1 );
		}
	}

	/**
	 * Add the Product Swatches tab after the core tabs (same position as before).
	 *
	 * @param array $tabs Product data tabs.
	 *
	 * @return array
	 */
	public static function register_tab( $tabs ) {
		if ( ! is_array( $tabs ) || isset( $tabs[ self::TAB_KEY ] ) ) {
			return $tabs;
		}

		$tab = [
			self::TAB_KEY => [
				'label'  => __( 'Product Swatches', 'woo-product-variation-swatches' ),
				'target' => self::PANEL_ID,
				'class'  => [ 'show_if_variable' ],
			],
		];

		return array_slice( $tabs, 0, 6, true ) + $tab + array_slice( $tabs, 6, null, true );
	}

	/**
	 * Tabs-filter wrapper around suppress_obsolete_pro_callbacks().
	 *
	 * @param array $tabs Product data tabs.
	 *
	 * @return array
	 */
	public static function suppress_on_tabs( $tabs ) {
		self::suppress_obsolete_pro_callbacks();

		return $tabs;
	}

	/**
	 * Remove exactly the obsolete Pro tab/panel callbacks, matched by class,
	 * method and priority. Nothing else on these hooks is touched.
	 *
	 * @return int Number of callbacks removed.
	 */
	public static function suppress_obsolete_pro_callbacks() {
		global $wp_filter;

		$removed = 0;
		foreach ( self::OBSOLETE_PRO_CALLBACKS as $hook => $target ) {
			list( $class, $method, $priority ) = $target;
			if ( empty( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $callback ) {
				$function = $callback['function'];
				if ( is_array( $function ) && is_object( $function[0] ) && get_class( $function[0] ) === $class && $function[1] === $method ) {
					$removed += remove_filter( $hook, $function, $priority ) ? 1 : 0;
				}
			}
		}

		return $removed;
	}

	/**
	 * Print the panel for the current Pro state.
	 *
	 * @return void
	 */
	public static function render_panel() {
		global $product_object;

		$product = ( isset( $product_object ) && $product_object instanceof \WC_Product ) ? $product_object : null;
		$mode    = rtwpvs()->pro_mode();

		if ( 'none' === $mode ) {
			self::render_notice_panel( 'upgrade' );
			return;
		}
		if ( 'legacy' === $mode ) {
			self::render_notice_panel( 'update-pro' );
			return;
		}
		if ( 'incompatible' === $mode ) {
			self::render_notice_panel( 'update-free' );
			return;
		}

		// Supported Pro: delegate the form, unless Pro's views are missing.
		if ( in_array( 'views', ProCompatibility::get_missing_pro_dirs(), true ) ) {
			self::render_notice_panel( 'reinstall' );
			return;
		}

		ob_start();
		do_action( self::CONTENT_HOOK, $product );
		$content = trim( (string) ob_get_clean() );

		if ( '' !== $content ) {
			printf(
				'<div id="%1$s" class="panel wc-metaboxes-wrapper rtwpvs-tab-options hidden">%2$s</div>',
				esc_attr( self::PANEL_ID ),
				$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro-rendered form markup, escaped by Pro's views.
			);
			return;
		}

		self::render_notice_panel( ( $product && ! $product->is_type( 'variable' ) ) ? 'not-variable' : 'reinstall' );
	}

	/**
	 * Print a Free notice panel (never contains form fields).
	 *
	 * @param string $state upgrade|update-pro|update-free|reinstall|not-variable.
	 *
	 * @return void
	 */
	private static function render_notice_panel( $state ) {
		$pro_url   = 'https://www.radiustheme.com/downloads/woocommerce-variation-swatches/';
		$demo_url  = 'https://radiustheme.com/demo/wordpress/woopluginspro/product/woocoommerce-variation-swatches/';
		$plugins   = admin_url( 'plugins.php' );
		$features  = [];
		$buttons   = [];
		$preserved = __( 'Your saved Product Swatches settings are kept and keep working on your store.', 'woo-product-variation-swatches' );

		switch ( $state ) {
			case 'upgrade':
				$badge    = __( 'PRO', 'woo-product-variation-swatches' );
				$title    = __( 'Product Swatches is a Pro feature.', 'woo-product-variation-swatches' );
				$text     = __( 'Customize swatches for this product only, without changing your global attribute settings.', 'woo-product-variation-swatches' );
				$features = [
					__( 'Choose a swatch type for each attribute of this product', 'woo-product-variation-swatches' ),
					__( 'Set custom colors, dual colors and images per term', 'woo-product-variation-swatches' ),
					__( 'Add text or image tooltips to individual swatches', 'woo-product-variation-swatches' ),
					__( 'Group terms and control how swatches show in the shop', 'woo-product-variation-swatches' ),
				];
				$buttons  = [
					[ __( 'Upgrade to Pro', 'woo-product-variation-swatches' ), $pro_url, true, true ],
					[ __( 'View Demo', 'woo-product-variation-swatches' ), $demo_url, false, true ],
				];
				break;
			case 'update-pro':
				$badge   = __( 'UPDATE', 'woo-product-variation-swatches' );
				$title   = __( 'Variation Swatches Pro needs an update.', 'woo-product-variation-swatches' );
				$text    = sprintf(
					/* translators: %s: minimum Pro version. */
					__( 'Update Variation Swatches Pro to version %s or later to edit Product Swatches here.', 'woo-product-variation-swatches' ),
					self::MIN_PRO_VERSION
				) . ' ' . $preserved;
				$buttons = [ [ __( 'Go to Plugins', 'woo-product-variation-swatches' ), $plugins, true, false ] ];
				break;
			case 'update-free':
				$badge   = __( 'UPDATE', 'woo-product-variation-swatches' );
				$title   = __( 'Variation Swatches for WooCommerce needs an update.', 'woo-product-variation-swatches' );
				$text    = __( 'The installed Pro version needs a newer version of the free plugin. Update Variation Swatches for WooCommerce to edit Product Swatches here.', 'woo-product-variation-swatches' ) . ' ' . $preserved;
				$buttons = [ [ __( 'Go to Plugins', 'woo-product-variation-swatches' ), $plugins, true, false ] ];
				break;
			case 'not-variable':
				$badge = __( 'INFO', 'woo-product-variation-swatches' );
				$title = __( 'Product Swatches is available for variable products.', 'woo-product-variation-swatches' );
				$text  = __( 'Save this product as a variable product to edit its Product Swatches.', 'woo-product-variation-swatches' );
				break;
			default: // reinstall.
				$state   = 'reinstall';
				$badge   = __( 'ERROR', 'woo-product-variation-swatches' );
				$title   = __( 'Product Swatches could not be loaded.', 'woo-product-variation-swatches' );
				$text    = __( 'Some Variation Swatches Pro files are missing. Reinstall or update Variation Swatches Pro.', 'woo-product-variation-swatches' ) . ' ' . $preserved;
				$buttons = [ [ __( 'Go to Plugins', 'woo-product-variation-swatches' ), $plugins, true, false ] ];
				break;
		}

		$classes = 'panel woocommerce_options_panel hidden rtwpvs-free-panel rtwpvs-free-panel--' . $state;
		if ( 'upgrade' === $state ) {
			$classes .= ' rtwpvs-free-upgrade-panel';
		}
		?>
		<div id="<?php echo esc_attr( self::PANEL_ID ); ?>" class="<?php echo esc_attr( $classes ); ?>">
			<style>
				.rtwpvs-free-panel .rtwpvs-upsell{margin:20px;padding:24px 28px;border:1px solid #dcdcde;border-radius:8px;background:linear-gradient(135deg,#f6f2ff 0%,#fff 60%);max-width:640px;box-sizing:border-box}
				.rtwpvs-free-panel .rtwpvs-upsell__head{display:flex;align-items:center;gap:10px;margin:0 0 8px}
				.rtwpvs-free-panel .rtwpvs-upsell__badge{display:inline-block;padding:2px 8px;border-radius:4px;background:#8c54ff;color:#fff;font-size:11px;font-weight:600;line-height:18px;letter-spacing:.5px}
				.rtwpvs-free-panel--update-pro .rtwpvs-upsell__badge,.rtwpvs-free-panel--update-free .rtwpvs-upsell__badge{background:#dba617}
				.rtwpvs-free-panel--reinstall .rtwpvs-upsell__badge{background:#d63638}
				.rtwpvs-free-panel--not-variable .rtwpvs-upsell__badge{background:#72aee6}
				.rtwpvs-free-panel .rtwpvs-upsell__title{margin:0;padding:0;font-size:16px;font-weight:600;line-height:1.4;color:#1d2327;background:none}
				.rtwpvs-free-panel .rtwpvs-upsell__text{margin:0 0 14px;padding:0;font-size:13px;line-height:1.6;color:#50575e}
				.rtwpvs-free-panel .rtwpvs-upsell__list{margin:0 0 18px;padding:0;list-style:none}
				.rtwpvs-free-panel .rtwpvs-upsell__list li{position:relative;margin:0 0 8px;padding:0 0 0 26px;font-size:13px;line-height:20px;color:#1d2327}
				.rtwpvs-free-panel .rtwpvs-upsell__list li .dashicons{position:absolute;left:0;top:0;color:#8c54ff}
				.rtwpvs-free-panel .rtwpvs-upsell__actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
				.rtwpvs-free-panel .rtwpvs-upsell__actions .button-primary{background:#8c54ff;border-color:#8c54ff}
				.rtwpvs-free-panel .rtwpvs-upsell__actions .button-primary:hover,.rtwpvs-free-panel .rtwpvs-upsell__actions .button-primary:focus{background:#7a40f2;border-color:#7a40f2}
				.rtwpvs-free-panel .rtwpvs-upsell__actions .button:not(.button-primary){color:#8c54ff;border-color:#8c54ff;background:#fff}
				.rtwpvs-free-panel .rtwpvs-upsell__actions .button:not(.button-primary):hover,.rtwpvs-free-panel .rtwpvs-upsell__actions .button:not(.button-primary):focus{color:#7a40f2;border-color:#7a40f2;background:#f6f2ff}
			</style>
			<div class="rtwpvs-upsell">
				<div class="rtwpvs-upsell__head">
					<span class="rtwpvs-upsell__badge"><?php echo esc_html( $badge ); ?></span>
					<h3 class="rtwpvs-upsell__title"><?php echo esc_html( $title ); ?></h3>
				</div>
				<p class="rtwpvs-upsell__text"><?php echo esc_html( $text ); ?></p>
				<?php if ( $features ) : ?>
					<ul class="rtwpvs-upsell__list">
						<?php foreach ( $features as $feature ) : ?>
							<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php echo esc_html( $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $buttons ) : ?>
					<div class="rtwpvs-upsell__actions">
						<?php foreach ( $buttons as $button ) : ?>
							<a class="button button-large<?php echo $button[2] ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $button[1] ); ?>"<?php echo $button[3] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $button[0] ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
