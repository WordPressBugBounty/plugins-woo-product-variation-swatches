<?php
/**
 * Per-product swatch meta (`_rtwpvs`) persistence.
 *
 * @package Rtwpvs\Controllers
 */

namespace Rtwpvs\Controllers;

use Rtwpvs\Helpers\Options;

/**
 * Saves and resets the per-product swatch settings stored in `_rtwpvs`.
 *
 * Every write is capability-checked against the specific product and the
 * submitted data is reduced to a known schema before it is stored.
 */
class ProductMetaBox {

	/**
	 * Post meta key holding the per-product swatch settings.
	 *
	 * @var string
	 */
	const META_KEY = '_rtwpvs';

	/**
	 * Allowed per-product "Reveal Swatches on Hover" override values.
	 *
	 * @var string[]
	 */
	const REVEAL_HOVER_VALUES = [ '', 'hover', 'no' ];

	/**
	 * Allowed per-term tooltip modes.
	 *
	 * @var string[]
	 */
	const TOOLTIP_VALUES = [ '', 'text', 'image', 'no' ];

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_switches_meta' ], 10, 2 );
		add_action( 'wp_ajax_rtwpvs_save_product_attributes', [ $this, 'rtwpvs_save_product_attributes' ] );
		add_action( 'wp_ajax_rtwpvs_reset_product_attributes', [ $this, 'rtwpvs_reset_product_attributes' ] );
	}

	/**
	 * AJAX: delete the per-product swatch settings.
	 *
	 * @return void
	 */
	public function rtwpvs_reset_product_attributes() {
		$product_id = $this->verify_ajax_request();

		delete_post_meta( $product_id, self::META_KEY );
		do_action( 'rtwpvs_reset_product_attributes', $product_id );
		wp_send_json_success();
	}

	/**
	 * AJAX: save the per-product swatch settings submitted from the product tab.
	 *
	 * @return void
	 */
	public function rtwpvs_save_product_attributes() {
		$product_id = $this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended -- Nonce verified above; serialized form data is sanitized by sanitize_product_meta().
		$raw_data = isset( $_REQUEST['data'] ) && is_string( $_REQUEST['data'] ) ? wp_unslash( $_REQUEST['data'] ) : '';
		$data     = [];
		parse_str( $raw_data, $data );

		if ( ! empty( $data['rtwpvs'] ) ) {
			$data = self::sanitize_product_meta( $data['rtwpvs'] );
			update_post_meta( $product_id, self::META_KEY, wp_slash( $data ) );
		} else {
			$data = [];
		}

		do_action( 'rtwpvs_save_product_attributes', $product_id, $data );
		wp_send_json_success();
	}

	/**
	 * Save the per-product swatch settings when the product edit form is submitted.
	 *
	 * WooCommerce verifies its meta box nonce before firing this hook; the
	 * capability is re-checked here for the specific product.
	 *
	 * @param int $post_id Product ID.
	 *
	 * @return void
	 */
	public function process_product_switches_meta( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified by WooCommerce before woocommerce_process_product_meta.
		if ( ! isset( $_REQUEST['rtwpvs'] ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended -- Sanitized by sanitize_product_meta().
		$data = self::sanitize_product_meta( wp_unslash( $_REQUEST['rtwpvs'] ) );
		update_post_meta( $post_id, self::META_KEY, wp_slash( $data ) );
	}

	/**
	 * Reduce raw per-product swatch settings to the known schema.
	 *
	 * Schema:
	 * - archive_reveal_hover     '', 'hover' or 'no'.
	 * - archive_single_attribute Attribute name (text).
	 * - {attribute}[type]        '', 'custom' or an available attribute type.
	 * - {attribute}[data][{term}] type, group_key, image, color, is_dual_color,
	 *                             secondary_color, tooltip, tooltip_text, tooltip_image.
	 *
	 * Attribute and term keys are kept verbatim because they are matched
	 * against product attribute names/options at render time; unknown fields
	 * and non-array entries are dropped.
	 *
	 * @param mixed $raw Raw submitted settings.
	 *
	 * @return array
	 */
	public static function sanitize_product_meta( $raw ) {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$types      = array_map( 'strval', array_keys( Options::get_available_attributes_types() ) );
		$attr_types = array_merge( [ '', 'custom' ], $types );
		$term_types = array_merge( [ '' ], $types );
		$clean      = [];

		foreach ( $raw as $key => $value ) {
			$key = (string) $key;

			if ( 'archive_reveal_hover' === $key ) {
				$clean[ $key ] = self::sanitize_choice( $value, self::REVEAL_HOVER_VALUES );
				continue;
			}

			if ( 'archive_single_attribute' === $key ) {
				$clean[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			$attribute = [];
			if ( isset( $value['type'] ) ) {
				$attribute['type'] = self::sanitize_choice( $value['type'], $attr_types );
			}

			if ( isset( $value['data'] ) && is_array( $value['data'] ) ) {
				$attribute['data'] = [];
				foreach ( $value['data'] as $term => $term_value ) {
					if ( is_array( $term_value ) ) {
						$attribute['data'][ (string) $term ] = self::sanitize_term_meta( $term_value, $term_types );
					}
				}
			}

			$clean[ $key ] = $attribute;
		}

		/**
		 * Filter the sanitized per-product swatch settings before they are saved.
		 *
		 * @param array $clean Sanitized settings.
		 * @param array $raw   Raw submitted settings.
		 */
		return (array) apply_filters( 'rtwpvs_sanitize_product_meta', $clean, $raw );
	}

	/**
	 * Sanitize the settings of a single attribute term.
	 *
	 * @param array    $raw        Raw term settings.
	 * @param string[] $term_types Allowed term swatch types.
	 *
	 * @return array
	 */
	private static function sanitize_term_meta( array $raw, array $term_types ) {
		$clean = [];

		if ( isset( $raw['type'] ) ) {
			$clean['type'] = self::sanitize_choice( $raw['type'], $term_types );
		}
		if ( isset( $raw['group_key'] ) ) {
			$clean['group_key'] = is_scalar( $raw['group_key'] ) ? sanitize_key( (string) $raw['group_key'] ) : '';
		}
		foreach ( [ 'image', 'tooltip_image' ] as $field ) {
			if ( isset( $raw[ $field ] ) ) {
				$id              = is_scalar( $raw[ $field ] ) ? absint( $raw[ $field ] ) : 0;
				$clean[ $field ] = $id ? (string) $id : '';
			}
		}
		foreach ( [ 'color', 'secondary_color' ] as $field ) {
			if ( isset( $raw[ $field ] ) ) {
				$clean[ $field ] = self::sanitize_color( $raw[ $field ] );
			}
		}
		if ( isset( $raw['is_dual_color'] ) ) {
			$clean['is_dual_color'] = 'yes' === $raw['is_dual_color'] ? 'yes' : '';
		}
		if ( isset( $raw['tooltip'] ) ) {
			$clean['tooltip'] = self::sanitize_choice( $raw['tooltip'], self::TOOLTIP_VALUES );
		}
		if ( isset( $raw['tooltip_text'] ) ) {
			$clean['tooltip_text'] = is_scalar( $raw['tooltip_text'] ) ? sanitize_text_field( (string) $raw['tooltip_text'] ) : '';
		}

		return $clean;
	}

	/**
	 * Return the value when it is one of the allowed choices, otherwise ''.
	 *
	 * @param mixed    $value   Raw value.
	 * @param string[] $allowed Allowed values.
	 *
	 * @return string
	 */
	private static function sanitize_choice( $value, array $allowed ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = (string) $value;

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Accept a hex color or an rgb()/rgba() color from the alpha color picker.
	 *
	 * @param mixed $value Raw color.
	 *
	 * @return string
	 */
	private static function sanitize_color( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		$hex   = sanitize_hex_color( $value );
		if ( $hex ) {
			return $hex;
		}
		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Verify nonce and per-product capability for the AJAX handlers.
	 *
	 * Sends a JSON error (and exits) on failure.
	 *
	 * @return int Verified product ID.
	 */
	private function verify_ajax_request() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rtwpvs_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Wrong Nonce', 'woo-product-variation-swatches' ) );
		}

		$product_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( esc_html__( 'Invalid product', 'woo-product-variation-swatches' ) );
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( esc_html__( 'Permission denied', 'woo-product-variation-swatches' ) );
		}

		return $product_id;
	}
}
