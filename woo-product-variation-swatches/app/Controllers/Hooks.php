<?php

namespace Rtwpvs\Controllers;

use Rtwpvs\Helpers\Functions;
use Rtwpvs\Helpers\Options;

class Hooks {

	static function init() {
		add_filter( 'product_attributes_type_selector', [ __CLASS__, 'product_attributes_types' ] );
		add_action( 'admin_init', [ __CLASS__, 'add_product_taxonomy_meta' ] );
		add_action( 'woocommerce_product_option_terms', [ __CLASS__, 'product_option_terms' ], 20, 2 );
		add_action( 'dokan_product_option_terms', [ __CLASS__, 'product_option_terms' ], 20, 2 );
		add_action( 'after_rtwpvs_product_option_terms_button', [ __CLASS__, 'product_option_group_fields' ], 10, 3 );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [
			__CLASS__,
			'variation_attribute_options_html'
		], 200, 2 );

		if ( ! is_admin() ) {
			add_filter( 'woocommerce_ajax_variation_threshold', [ __CLASS__, 'ajax_variation_threshold' ], 99 );
		}

		// Optionally replace WooCommerce's variations <table> with a <div> layout.
		// Registered unconditionally; the setting is checked inside the callback so we
		// don't call get_option() this early (that would force the settings API to
		// build during its own construction and recurse).
		add_filter( 'woocommerce_locate_template', [ __CLASS__, 'override_variable_template' ], 20, 4 );
		add_action( 'admin_init', [ __CLASS__, 'after_plugin_active' ] );

		add_filter( 'wp_get_attachment_image_attributes', [
			__CLASS__,
			'add_wp_class_attachment_image_attributes'
		], 9 );

		add_filter( 'woocommerce_available_variation', [ __CLASS__, 'available_variation' ], 100, 3 );
		add_filter( 'post_class', [ __CLASS__, 'product_loop_post_class' ], 25, 3 );
		add_filter( 'woocommerce_loop_add_to_cart_args', [ __CLASS__, 'loop_add_to_cart_args' ], 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', [ __CLASS__, 'simple_product_cart_url' ], 10, 2 );
		add_filter( 'woocommerce_get_script_data', [ __CLASS__, 'wc_get_script_data' ], 10, 2 );

		add_action( 'wp_ajax_nopriv_rtwpvs_add_variation_to_cart', [ __CLASS__, 'add_to_cart' ] );
		add_action( 'wp_ajax_rtwpvs_add_variation_to_cart', [ __CLASS__, 'add_to_cart' ] );
		add_filter( 'script_loader_tag', [ __CLASS__, 'script_loader_add_defer_tag' ], 10, 3 );
		add_action( 'rtwpvs_save_product_attributes', [
			__CLASS__,
			'delete_transient_at_rtwpvs_save_or_reset_product_attributes'
		] );
		add_action( 'rtwpvs_reset_product_attributes', [
			__CLASS__,
			'delete_transient_at_rtwpvs_save_or_reset_product_attributes'
		] );
		add_action( 'woocommerce_save_product_variation', [
			__CLASS__,
			'delete_transient_at_save_or_update_product_variation'
		] );
		add_action( 'woocommerce_update_product_variation', [
			__CLASS__,
			'delete_transient_at_save_or_update_product_variation'
		] );
		add_action( 'woocommerce_delete_product_transients', [
			__CLASS__,
			'delete_transient_at_delete_product_transients'
		] );
		add_action( 'woocommerce_attribute_updated', [ __CLASS__, 'delete_transient_at_attribute_updated' ], 20, 3 );
		add_action( 'woocommerce_attribute_deleted', [ __CLASS__, 'delete_transient_at_attribute_deleted' ], 20, 3 );
		add_action( 'woocommerce_attribute_added', [ __CLASS__, 'delete_transient_at_attribute_added' ], 20, 2 );

		add_filter( 'pre_update_option_rtwpvs', [ __CLASS__, 'delete_transient_at_update_option' ], 10, 2 );
		add_filter( 'pre_update_option_rtwpvs', [ __CLASS__, 'maybe_clear_template_cache' ], 10, 2 );
		add_action( 'rtwpvs_after_term_meta_saved', [ __CLASS__, 'delete_transient_at_term_meta_saved' ], 20, 2 );
		add_action( 'init', [ __CLASS__, 'delete_transient_at_force' ] );
	}

	/**
	 * Point WooCommerce at the plugin's div-based variable add-to-cart template.
	 *
	 * Only the variations add-to-cart template is redirected, and only when
	 * WooCommerce resolved its own core template. A theme (or another plugin)
	 * that ships `single-product/add-to-cart/variable.php` always wins: this
	 * filter runs after WooCommerce has already looked in the theme, so
	 * replacing a non-core path would silently discard that override.
	 *
	 * @param string $template      Located template path.
	 * @param string $template_name Template name being located.
	 * @param string $template_path Base template path.
	 * @param string $default_path  WooCommerce core template directory. Passed
	 *                              since WooCommerce 9.5; resolved from WC() on
	 *                              older versions.
	 * @return string
	 */
	static function override_variable_template( $template, $template_name, $template_path, $default_path = '' ) {
		if ( 'single-product/add-to-cart/variable.php' !== $template_name ) {
			return $template;
		}

		if ( ! rtwpvs()->get_option( 'remove_variations_table' ) ) {
			return $template;
		}

		if ( ! self::is_core_template( $template, $template_name, $default_path ) ) {
			return $template;
		}

		$override = RTWPVS_PLUGIN_PATH . 'templates/' . $template_name;

		return file_exists( $override ) ? $override : $template;
	}

	/**
	 * Whether the located template is WooCommerce's own bundled template.
	 *
	 * @param string $template      Located template path.
	 * @param string $template_name Template name being located.
	 * @param string $default_path  WooCommerce core template directory, if known.
	 * @return bool
	 */
	private static function is_core_template( $template, $template_name, $default_path = '' ) {
		if ( ! $default_path && function_exists( 'WC' ) ) {
			$default_path = WC()->plugin_path() . '/templates/';
		}

		if ( ! $default_path ) {
			// Without a reliable core path, leave the resolved template alone.
			return false;
		}

		$core     = wp_normalize_path( trailingslashit( $default_path ) . $template_name );
		$resolved = wp_normalize_path( (string) $template );

		return $core === $resolved;
	}

	/**
	 * Flush WooCommerce's template-path cache when the layout setting changes.
	 *
	 * `wc_get_template()` caches the located path in the `woocommerce` cache
	 * group under a key that does not include plugin settings, so on sites with
	 * a persistent object cache the previous template would keep being served
	 * after the setting is toggled (or reset by the migration). Only
	 * WooCommerce's own template keys are cleared — never the whole cache.
	 *
	 * @param mixed $value     Settings about to be stored.
	 * @param mixed $old_value Settings currently stored.
	 * @return mixed Unmodified $value.
	 */
	static function maybe_clear_template_cache( $value, $old_value ) {
		if ( ! function_exists( 'wc_clear_template_cache' ) ) {
			return $value;
		}

		$key = 'remove_variations_table';
		$new = is_array( $value ) && isset( $value[ $key ] ) ? (string) $value[ $key ] : '';
		$old = is_array( $old_value ) && isset( $old_value[ $key ] ) ? (string) $old_value[ $key ] : '';

		if ( $new !== $old ) {
			wc_clear_template_cache();
		}

		return $value;
	}

	static function delete_transient_at_force() {
		if ( isset( $_GET['rtwpvs_clear_all_transient'] ) && isset( $_REQUEST['_wpnonce'] ) && is_admin() && wp_verify_nonce( $_REQUEST['_wpnonce'], 'rtwpvs_clear_all_cache' ) ) {
			$archive_transient_name = "_transient_" . rtwpvs()->get_transient_name( "archive_%", 'attribute-html' );
			$product_transient_name = "_transient_" . rtwpvs()->get_transient_name( "%", 'attribute-html' );
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE `option_name` LIKE (%s) OR `option_name` LIKE (%s) ", $archive_transient_name, $product_transient_name ) );
			do_action( 'rtwpvs_clear_all_transient' );
		}
	}

	static function delete_transient_at_update_option( $new_value, $old_value ) {
		$new_single_attribute = isset( $new_value['archive_swatches_single_attribute'] ) ? $new_value['archive_swatches_single_attribute'] : '';
		$old_single_attribute = isset( $old_value['archive_swatches_single_attribute'] ) ? $old_value['archive_swatches_single_attribute'] : '';
		$new_display_limit    = isset( $new_value['archive_swatches_display_limit'] ) ? absint( $new_value['archive_swatches_display_limit'] ) : '';
		$old_display_limit    = isset( $old_value['archive_swatches_display_limit'] ) ? absint( $old_value['archive_swatches_display_limit'] ) : '';
		// Renaming/adding/removing groups changes the rendered grouping headings.
		$groups_changed       = wp_json_encode( isset( $new_value['groups'] ) ? $new_value['groups'] : [] ) !== wp_json_encode( isset( $old_value['groups'] ) ? $old_value['groups'] : [] );
		// Toggling the color-swatch label (single or archive) changes the markup.
		$label_changed        = ( ( isset( $new_value['color_swatch_label'] ) ? $new_value['color_swatch_label'] : '' ) !== ( isset( $old_value['color_swatch_label'] ) ? $old_value['color_swatch_label'] : '' ) )
			|| ( ( isset( $new_value['archive_color_swatch_label'] ) ? $new_value['archive_color_swatch_label'] : '' ) !== ( isset( $old_value['archive_color_swatch_label'] ) ? $old_value['archive_color_swatch_label'] : '' ) );

		if ( ( $new_single_attribute !== $old_single_attribute ) || ( $new_display_limit !== $old_display_limit ) || $groups_changed || $label_changed ) {
			self::clear_all_attribute_html_transients();
			do_action( 'rtwpvs_clear_all_transient_at_update_option' );
		}

		return $new_value;
	}

	/**
	 * Invalidate cached swatch HTML when a term's meta (e.g. its group, color,
	 * tooltip) is saved, so products that were not re-saved reflect the change.
	 *
	 * @param int    $term_id  The edited term id.
	 * @param string $taxonomy The attribute taxonomy.
	 *
	 * @return void
	 */
	static function delete_transient_at_term_meta_saved( $term_id, $taxonomy ) {
		self::clear_all_attribute_html_transients();
	}

	/**
	 * Delete every cached attribute-html transient (single + archive).
	 *
	 * @return void
	 */
	private static function clear_all_attribute_html_transients() {
		global $wpdb;
		$archive_transient_name = '_transient_' . rtwpvs()->get_transient_name( 'archive_%', 'attribute-html' );
		$product_transient_name = '_transient_' . rtwpvs()->get_transient_name( '%', 'attribute-html' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE `option_name` LIKE (%s) OR `option_name` LIKE (%s)", $archive_transient_name, $product_transient_name ) );
	}

	static function delete_transient_at_attribute_added( $attribute_id, $attribute ) {
		$transient_name = rtwpvs()->get_transient_name( wc_attribute_taxonomy_name( $attribute['attribute_name'] ), 'attribute-taxonomy' );
		delete_transient( $transient_name );
	}

	static function delete_transient_at_attribute_deleted( $attribute_id, $attribute_name, $taxonomy ) {
		$transient_name = rtwpvs()->get_transient_name( $taxonomy, 'attribute-taxonomy' );
		delete_transient( $transient_name );
	}

	static function delete_transient_at_attribute_updated( $attribute_id, $attribute, $old_attribute_name ) {
		$transient_name = rtwpvs()->get_transient_name( wc_attribute_taxonomy_name( $attribute['attribute_name'] ), 'attribute-taxonomy' );
		$old_transient  = sprintf( 'rtwpvs_get_wc_attribute_taxonomy_%s', wc_attribute_taxonomy_name( $old_attribute_name ) );
		delete_transient( $transient_name );
		delete_transient( $old_transient );
	}

	static function delete_transient_at_delete_product_transients( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( $product && $product->is_type( 'variable' ) ) {
			$attribute_keys = array_keys( $product->get_variation_attributes() );

			foreach ( $attribute_keys as $attribute_id ) {
				$transient_id           = $product_id . "_" . wc_variation_attribute_name( $attribute_id );
				$archive_transient_name = rtwpvs()->get_transient_name( "archive_" . $transient_id, 'attribute-html' );
				$product_transient_name = rtwpvs()->get_transient_name( $transient_id, 'attribute-html' );
				delete_transient( $archive_transient_name );
				delete_transient( $product_transient_name );
			}
		}
	}

	static function delete_transient_at_save_or_update_product_variation( $variation_id ) {
		$product = wc_get_product( $variation_id );
		if ( ! $product ) {
			return;
		}
		$product_id     = $product->get_parent_id();
		$attribute_keys = array_keys( $product->get_variation_attributes() );
		foreach ( $attribute_keys as $attribute_id ) {
			$transient_id           = $product_id . "_" . wc_variation_attribute_name( $attribute_id );
			$archive_transient_name = rtwpvs()->get_transient_name( "archive_" . $transient_id, 'attribute-html' );
			$product_transient_name = rtwpvs()->get_transient_name( $transient_id, 'attribute-html' );
			delete_transient( $archive_transient_name );
			delete_transient( $product_transient_name );
		}
	}

	static function delete_transient_at_rtwpvs_save_or_reset_product_attributes( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$attribute_keys = array_keys( $product->get_variation_attributes() );
		foreach ( $attribute_keys as $attribute_id ) {
			$transient_id           = $product_id . "_" . wc_variation_attribute_name( $attribute_id );
			$archive_transient_name = rtwpvs()->get_transient_name( "archive_" . $transient_id, 'attribute-html' );
			$product_transient_name = rtwpvs()->get_transient_name( $transient_id, 'attribute-html' );
			delete_transient( $archive_transient_name );
			delete_transient( $product_transient_name );
		}
	}

	static function script_loader_add_defer_tag( $tag, $handle, $src ) {

		$defer_load_js = (bool) rtwpvs()->get_option( 'defer_load_js' );

		if ( $defer_load_js ) {
			$handles = [ 'rtwpvs' ];

			if ( ! wp_is_mobile() && in_array( $handle, $handles ) && ( strpos( $tag, 'plugins' . DIRECTORY_SEPARATOR . 'woo-product-variation-swatches' ) !== false ) ) {
				return str_ireplace( ' src=', ' defer src=', $tag );
			}
		}

		return $tag;

	}

	static function add_to_cart() {

		ob_start();

		$data = wp_parse_args( $_POST, [
			'product_id'   => 0,
			'quantity'     => 0,
			'variation_id' => 0,
			'variation'    => [],
		] );

		$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $data['product_id'] ) );
		$product           = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json( [
				'error'       => true,
				'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
			] );
		}

		$quantity          = empty( $data['quantity'] ) ? 1 : wc_stock_amount( $data['quantity'] );
		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
		$product_status    = get_post_status( $product_id );
		$variation_id      = absint( $data['variation_id'] );
		$variation         = $data['variation'];

		// If Not a variation
		if (
			( 'variable' != $product->get_type() || empty( $variation_id ) ) ||
			! $passed_validation ||
			false === WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) ||
			'publish' !== $product_status
		) {
			// If there was an error adding to the cart, redirect to the product page to show any errors
			$response = [
				'error'       => true,
				'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
			];

			wp_send_json( $response );
		}

		do_action( 'woocommerce_ajax_added_to_cart', $product_id );
		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			wc_add_to_cart_message( [ $product_id => $quantity ], true );
		}

		// Return fragments
		\WC_AJAX::get_refreshed_fragments();

	}

	static function simple_product_cart_url( $url, $product ) {

		if ( ! $product instanceof \WC_Product ) {
			return $url;
		}

		if ( 'simple' === $product->get_type() ) {
			$url = $product->is_purchasable() && $product->is_in_stock() ? remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $product->get_id(), Functions::get_current_url() ) ) : get_permalink( $product->get_id() );
		}

		return $url;
	}

	static function loop_add_to_cart_args( $args, $product ) {

		if ( ! $product instanceof \WC_Product ) {
			return $args;
		}

		if ( $product->is_type( 'variable' ) ) {

			if ( ! rtwpvs()->get_option( 'archive_swatches' ) ) {
				return $args;
			}

			$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

			$enable_archive_single_attribute = (bool) rtwpvs()->get_option( 'archive_swatches_enable_single_attribute' );

			if ( ! $enable_archive_single_attribute ) {
				$args['class'] .= ' rtwpvs_add_to_cart';
			}

			// Based On WooCommerce Settings
			if ( 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) && ! $enable_archive_single_attribute ) {
				$args['class'] .= ' rtwpvs_ajax_add_to_cart';
			} else {
				$args['attributes']['data-product_permalink'] = $product->add_to_cart_url();
				$args['attributes']['data-add_to_cart_url']   = $product->is_purchasable() && $product->is_in_stock() ? Functions::get_current_url() : get_permalink( $product->get_id() );
			}

			// variation_id
			$args['attributes']['data-variation_id'] = "";
			$args['attributes']['data-variation']    = "";

			$args['variations'] = [
				'available_variations' => $get_variations ? array_values( $product->get_available_variations() ) : false,
				'attributes'           => $product->get_variation_attributes(),
				'selected_attributes'  => $product->get_default_attributes(),
			];
		}

		return $args;
	}

	public static function product_loop_post_class( $classes, $class, $product_id ) {
        global $product;
		if ( ! $product instanceof \WC_Product ) {
			return $classes;
		}

		if ( $product->is_type( 'variable' ) ) {
			$classes[] = 'rtwpvs-product';
            $beside_label = function_exists( 'rtwpvsp' ) && rtwpvs()->get_option( 'attribute_on_click_behavior' );
            if( $beside_label ){
                $classes[] = 'rtwpvs-selected-term-beside-label';
            }
        }

		return $classes;
	}

	/**
	 * @param $variation
	 * @param $product      \WC_Product
	 * @param $variationObj \WC_Product_Variable
	 *
	 * @return bool
	 */
	static function available_variation( $variation, $product, $variationObj ) {
		if ( isset( $variation['image']['thumb_src'] ) && ! empty( $variation['image']['thumb_src'] ) ) {
			$attachment_id                      = $variationObj->get_image_id();
			$thumbnail_size                     = apply_filters( 'woocommerce_thumbnail_size', 'woocommerce_thumbnail' );
			$thumb_srcset                       = function_exists( 'wp_get_attachment_image_srcset' ) ? wp_get_attachment_image_srcset( $attachment_id, $thumbnail_size ) : false;
			$thumb_sizes                        = function_exists( 'wp_get_attachment_image_sizes' ) ? wp_get_attachment_image_sizes( $attachment_id, $thumbnail_size ) : false;
			$variation['image']['thumb_srcset'] = apply_filters( 'rtwpvs_thumb_srcset', $thumb_srcset, $variation, $product, $variationObj );
			$variation['image']['thumb_sizes']  = apply_filters( 'rtwpvs_thumb_sizes', $thumb_sizes, $variation, $product, $variationObj );
		}

		if ( rtwpvs()->get_option( 'disable_out_of_stock' ) && ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ! is_admin() ) ) {
			return $variationObj->is_in_stock() ? $variation : false;
		}

		return $variation;
	}


	static function add_wp_class_attachment_image_attributes( $attr ) {

		$classes = (array) explode( ' ', $attr['class'] );

		array_push( $classes, 'wp-post-image' );

		$attr['class'] = implode( ' ', array_unique( $classes ) );

		return $attr;
	}

	static function ajax_variation_threshold( $threshold ) {
		return absint( rtwpvs()->get_option( 'threshold', $threshold ) );
	}


	static function product_attributes_types( $selector ) {
		$types = Options::get_available_attributes_types();
		if ( ! empty( $types ) ) {
			foreach ( $types as $key => $type ) {
				$selector[ $key ] = $type;
			}
		}

		return $selector;
	}


	static function add_product_taxonomy_meta() {

		$fields         = Options::get_taxonomy_meta_fields();
		$meta_added_for = apply_filters( 'rtwpvs_product_taxonomy_meta_for', array_keys( $fields ) );

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ):

			$attribute_taxonomies = wc_get_attribute_taxonomies();
			if ( $attribute_taxonomies ) :
				foreach ( $attribute_taxonomies as $tax ) :
					$product_attr      = wc_attribute_taxonomy_name( $tax->attribute_name );
					$product_attr_type = $tax->attribute_type;
					if ( in_array( $product_attr_type, $meta_added_for ) ) :
						new TermMeta( $product_attr, $fields[ $product_attr_type ] );
						do_action( 'rtwpvs_wc_attribute_taxonomy_meta_added', $product_attr, $product_attr_type );
					endif;
				endforeach;
			endif;
		endif;

	}

	static function product_option_terms( $attribute_taxonomy, $i ) {
		global $thepostid;
        if ( 'select' === $attribute_taxonomy->attribute_type ){
            return;
        }
		if ( in_array( $attribute_taxonomy->attribute_type, array_keys( Options::get_available_attributes_types() ) ) ) {

			$taxonomy = wc_attribute_taxonomy_name( $attribute_taxonomy->attribute_name );

			$product_id = $thepostid;

			if ( is_null( $thepostid ) && isset( $_POST['post_id'] ) ) {
				$product_id = absint( $_POST['post_id'] );
			}

			$args = [
				'orderby'    => 'name',
				'hide_empty' => 0,
			];
			?>
            <select multiple="multiple"
                    data-placeholder="<?php esc_attr_e( 'Select terms', 'woo-product-variation-swatches' ); ?>"
                    class="multiselect attribute_values wc-enhanced-select"
                    name="attribute_values[<?php echo esc_attr( $i ); ?>][]">
				<?php
				$all_terms = get_terms( $taxonomy, apply_filters( 'woocommerce_product_attribute_terms', $args ) );
				if ( $all_terms ) :
					foreach ( $all_terms as $term ) :
						echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( has_term( absint( $term->term_id ), $taxonomy, $product_id ), true, false ) . '>' . esc_attr( apply_filters( 'woocommerce_product_attribute_term_name', $term->name, $term ) ) . '</option>';
					endforeach;
				endif;
				?>
            </select>
			<?php do_action( 'before_rtwpvs_product_option_terms_button', $attribute_taxonomy, $taxonomy ); ?>
            <button class="button plus select_all_attributes"><?php esc_html_e( 'Select all', 'woo-product-variation-swatches' ); ?></button>
            <button class="button minus select_no_attributes"><?php esc_html_e( 'Select none', 'woo-product-variation-swatches' ); ?></button>

			<?php
			$fields = Options::get_available_attributes_types( $attribute_taxonomy->attribute_type );

			if ( ! empty( $fields ) ): ?>
                <!--<button class="button fr plus rtwpvs_add_new_attribute"-->
                <!--        data-dialog_title="--><?php //printf( esc_html__( 'Add new %s', 'woo-product-variation-swatches' ), esc_attr( $attribute_taxonomy->attribute_label ) ) ?><!--">--><?php //esc_html_e( 'Add new', 'woo-product-variation-swatches' ); ?><!--</button>-->
                <button class="button fr plus add_new_attribute"
                        data-dialog_title="<?php printf( esc_html__( 'Add new %s', 'woo-product-variation-swatches' ), esc_attr( $attribute_taxonomy->attribute_label ) ) ?>"><?php esc_html_e( 'Add new', 'woo-product-variation-swatches' ); ?></button>
			<?php else: ?>
                <button class="button fr plus add_new_attribute"><?php esc_html_e( 'Add new', 'woo-product-variation-swatches' ); ?></button>
			<?php endif; ?>
			<?php
			do_action( 'after_rtwpvs_product_option_terms_button', $attribute_taxonomy, $taxonomy, $product_id );
		}
	}

	/**
	 * Render a per-term "Group" override selector under each attribute in the
	 * product Attributes panel.
	 *
	 * The value overrides the term's global group for this product only and is
	 * stored inside `_rtwpvs[<taxonomy>][data][<slug>][group_key]`, which is
	 * persisted by ProductMetaBox::process_product_switches_meta() on product
	 * update and consumed by Functions::get_variable_items_contents().
	 *
	 * @param object $attribute_taxonomy The WooCommerce attribute taxonomy object.
	 * @param string $taxonomy           Full taxonomy name, e.g. `pa_color`.
	 * @param int    $product_id         Current product id (0 for a new product).
	 *
	 * @return void
	 */
	static function product_option_group_fields( $attribute_taxonomy, $taxonomy, $product_id = 0 ) {
		// Pro provides a richer per-term override (with a Group select) in its
		// "Product Swatches" tab, so skip this fallback UI when Pro is active.
		if ( function_exists( 'rtwpvsp' ) ) {
			return;
		}

		$groups = Options::get_groups();
		if ( empty( $groups ) ) {
			return;
		}

		$product_id = absint( $product_id );
		$terms      = $product_id ? wc_get_product_terms( $product_id, $taxonomy, [ 'fields' => 'all' ] ) : [];

		echo '<div class="rtwpvs-attribute-groups" style="width:100%;margin-top:8px;clear:both;">';
		printf(
			'<details><summary style="cursor:pointer;font-weight:600;padding:6px 0;">%s</summary>',
			esc_html__( 'Swatch groups (per-product override)', 'woo-product-variation-swatches' )
		);

		if ( empty( $terms ) ) {
			printf(
				'<p class="description" style="margin:6px 0;">%s</p>',
				esc_html__( 'Select terms and update the product to assign per-product groups. Terms use their global group by default.', 'woo-product-variation-swatches' )
			);
			echo '</details></div>';
			return;
		}

		$options   = Options::get_group_options();
		$group_map = Options::get_groups_map();
		$meta      = get_post_meta( $product_id, '_rtwpvs', true );
		$overrides = ( is_array( $meta ) && isset( $meta[ $taxonomy ]['data'] ) && is_array( $meta[ $taxonomy ]['data'] ) ) ? $meta[ $taxonomy ]['data'] : [];

		echo '<table class="widefat striped" style="margin:8px 0;"><tbody>';
		foreach ( $terms as $term ) {
			$override_id  = isset( $overrides[ $term->slug ]['group_key'] ) ? sanitize_key( $overrides[ $term->slug ]['group_key'] ) : '';
			$global_id    = sanitize_key( (string) get_term_meta( $term->term_id, 'rtwpvs_term_group', true ) );
			$global_label = ( '' !== $global_id && isset( $group_map[ $global_id ] ) ) ? $group_map[ $global_id ]['name'] : esc_html__( 'None', 'woo-product-variation-swatches' );

			$field_name = sprintf( 'rtwpvs[%s][data][%s][group_key]', esc_attr( $taxonomy ), esc_attr( $term->slug ) );

			echo '<tr>';
			printf( '<td style="width:40%%;">%s</td>', esc_html( $term->name ) );
			echo '<td>';
			printf( '<select name="%s" class="rtwpvs-term-group-select" style="width:100%%;">', esc_attr( $field_name ) );
			printf(
				'<option value="">%s</option>',
				sprintf(
					/* translators: %s: global group name. */
					esc_html__( 'Use global group (%s)', 'woo-product-variation-swatches' ),
					esc_html( $global_label )
				)
			);
			foreach ( $options as $id => $name ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $id ),
					selected( $override_id, $id, false ),
					esc_html( $name )
				);
			}
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		printf(
			'<p class="description" style="margin:6px 0;">%s</p>',
			esc_html__( 'Overrides apply to this product only and are saved when you update the product.', 'woo-product-variation-swatches' )
		);
		echo '</details></div>';
	}

	static function variation_attribute_options_html( $html, $args ) {

		if ( apply_filters( 'default_rtwpvs_variation_attribute_options_html', false, $args, $html ) ) {
			return $html;
		}

		// WooCommerce Product Bundle Fixing
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'woocommerce_configure_bundle_order_item' ) {
			return $html;
		}

		return Functions::generate_variation_attribute_option_html( apply_filters( 'rtwpvs_variation_attribute_options_args', $args ), $html );
	}

	static function after_plugin_active() {
		if ( get_option( 'rtwpvs_pro_activate' ) === 'yes' ) {
			delete_option( 'rtwpvs_pro_activate' );
			wp_safe_redirect( add_query_arg( [
				'page'    => 'wc-settings',
				'tab'     => 'rtwpvs',
				'section' => 'general',
			], admin_url( 'admin.php' ) ) );
		}
	}

	static function wc_get_script_data( $params, $handle ) {
		if ( 'wc-add-to-cart-variation' == $handle ) {
			$params = array_merge( $params, [
				'ajax_url'                => WC()->ajax_url(),
				'i18n_view_cart'          => apply_filters( 'rtwpvs_view_cart_text', esc_attr__( 'View cart', 'woo-product-variation-swatches' ) ),
				'i18n_add_to_cart'        => apply_filters( 'rtwpvs_add_to_cart_text', esc_attr__( 'Add to cart', 'woo-product-variation-swatches' ) ),
				'i18n_select_options'     => apply_filters( 'rtwpvs_select_options_text', esc_attr__( 'Select options', 'woo-product-variation-swatches' ) ),
				'cart_url'                => apply_filters( 'woocommerce_add_to_cart_redirect', wc_get_cart_url(), null ),
				'is_cart'                 => is_cart(),
				'cart_redirect_after_add' => get_option( 'woocommerce_cart_redirect_after_add' ),
				'enable_ajax_add_to_cart' => get_option( 'woocommerce_enable_ajax_add_to_cart' )
			] );

			if ( function_exists( 'rtwpvsp' ) ) {
				wc_get_template( 'rtwpvs-variation-template.php', [], '', trailingslashit( rtwpvs()->get_template_path() ) );
			}
		}

		return $params;
	}
}