<?php

namespace Rtwpvs\Helpers;

class Options {

	static function get_available_attributes_types( $type = false ) {
		$types = [
			'color'  => esc_html__( 'Color', 'woo-product-variation-swatches' ),
			'image'  => esc_html__( 'Image', 'woo-product-variation-swatches' ),
			'button' => esc_html__( 'Button', 'woo-product-variation-swatches' ),
			'radio'  => esc_html__( 'Radio', 'woo-product-variation-swatches' ),
			'select' => esc_html__( 'Select Field', 'woo-product-variation-swatches' ),
		];

		$types = apply_filters( 'rtwpvs_available_attributes_types', $types );

		if ( $type ) {
			return isset( $types[ $type ] ) ? $types[ $type ] : null;
		}

		return $types;
	}

	/**
	 * Return the user-defined attribute groups as a normalized list.
	 *
	 * Groups are managed on the Settings > Groups tab (each group is just a Name)
	 * and stored in the `rtwpvs` option under `groups`. Each group has a stable
	 * `id` (terms reference this, so the name can be edited without orphaning
	 * assignments), and a `key` slug derived from the name for use as a CSS hook.
	 * The id falls back to a name slug for legacy groups saved without one, and
	 * duplicate ids / keys are de-duplicated (first occurrence wins).
	 *
	 * @return array<int,array{id:string,key:string,name:string}>
	 */
	public static function get_groups() {
		// Attribute groups are a Pro-only feature; free installs expose none, so
		// the term selector, term column, frontend grouping and per-product
		// override all no-op automatically.
		if ( ! function_exists( 'rtwpvsp' ) ) {
			return apply_filters( 'rtwpvs_groups', [] );
		}

		$raw      = rtwpvs()->get_option( 'groups' );
		$out      = [];
		$seen_id  = [];
		$seen_key = [];

		if ( is_array( $raw ) ) {
			foreach ( $raw as $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				$name = isset( $group['name'] ) ? trim( (string) $group['name'] ) : '';
				if ( '' === $name ) {
					continue;
				}
				$id = isset( $group['id'] ) ? sanitize_key( $group['id'] ) : '';
				if ( '' === $id ) {
					// Legacy fallback: reuse a stored key, else a slug of the name.
					$id = isset( $group['key'] ) ? sanitize_key( $group['key'] ) : sanitize_title( $name );
				}
				if ( isset( $seen_id[ $id ] ) ) {
					continue;
				}

				// Derive the CSS slug from the name and keep it unique.
				$base = sanitize_title( $name );
				$base = '' !== $base ? $base : $id;
				$key  = $base;
				$n    = 2;
				while ( isset( $seen_key[ $key ] ) ) {
					$key = $base . '-' . $n;
					$n++;
				}

				$seen_id[ $id ]   = true;
				$seen_key[ $key ] = true;
				$out[]            = [
					'id'   => $id,
					'key'  => $key,
					'name' => $name,
				];
			}
		}

		return apply_filters( 'rtwpvs_groups', $out );
	}

	/**
	 * Group options for a <select>: stable id => group name.
	 *
	 * @return array<string,string>
	 */
	public static function get_group_options() {
		$options = [];
		foreach ( self::get_groups() as $group ) {
			$options[ $group['id'] ] = $group['name'];
		}

		return $options;
	}

	/**
	 * Groups indexed by their stable id for resolving a stored reference to its
	 * current key/name.
	 *
	 * @return array<string,array{key:string,name:string}>
	 */
	public static function get_groups_map() {
		$map = [];
		foreach ( self::get_groups() as $group ) {
			$map[ $group['id'] ] = [
				'key'  => $group['key'],
				'name' => $group['name'],
			];
		}

		return $map;
	}

	public static function get_taxonomy_meta_fields( $field_id = false ) {

		$fields = [];

		// Tooltip fields are a Pro feature. Defined here (marked is_pro) so free
		// installs render them as locked [Pro] teasers instead of hiding them; Pro
		// unlocks them via the function_exists() check in TermMeta. Same approach as
		// the "Group" selector below.
		$tooltip_fields = [
			[
				'label'   => esc_html__( 'Show Tooltip', 'woo-product-variation-swatches' ),
				'desc'    => esc_html__( 'Individually show hide tooltip.', 'woo-product-variation-swatches' ),
				'id'      => 'rtwpvs_attribute_tooltip',
				'type'    => 'select',
				'is_pro'  => true,
				'options' => apply_filters(
					'rtwpvs_tooltip_option',
					[
						'text'  => esc_html__( 'Text', 'woo-product-variation-swatches' ),
						'image' => esc_html__( 'Image', 'woo-product-variation-swatches' ),
						'no'    => esc_html__( 'No', 'woo-product-variation-swatches' ),
					]
				),
			],
			[
				'label'  => esc_html__( 'Tooltip Text', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'By default tooltip text will be the term name.', 'woo-product-variation-swatches' ),
				'id'     => 'rtwpvs_attribute_tooltip_text',
				'type'   => 'text',
				'class'  => 'rtwpvs-hidden',
				'is_pro' => true,
			],
			[
				'label'  => esc_html__( 'Tooltip Image', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Choose an image for tooltip.', 'woo-product-variation-swatches' ),
				'id'     => 'rtwpvs_attribute_tooltip_image',
				'type'   => 'image',
				'class'  => 'rtwpvs-hidden',
				'is_pro' => true,
			],
		];
		$common_fields = apply_filters( 'rtwpvs_custom_tooltip', $tooltip_fields );

		// Color field + Dual Color (Pro). Dual Color / Secondary Color are marked
		// is_pro so they stay visible as locked [Pro] teasers on free installs.
		$color_fields = [
			[
				'label' => esc_html__( 'Color', 'woo-product-variation-swatches' ),
				'desc'  => esc_html__( 'Choose a color', 'woo-product-variation-swatches' ),
				'id'    => 'product_attribute_color',
				'type'  => 'color',
			],
			[
				'label'         => esc_html__( 'Dual Color', 'woo-product-variation-swatches' ),
				'trigger_label' => esc_html__( 'Enable', 'woo-product-variation-swatches' ),
				'id'            => 'is_dual_color',
				'type'          => 'checkbox',
				'is_pro'        => true,
			],
			[
				'label'      => esc_html__( 'Secondary Color', 'woo-product-variation-swatches' ),
				'desc'       => esc_html__( 'Add term secondary color', 'woo-product-variation-swatches' ),
				'id'         => 'secondary_color',
				'type'       => 'color',
				'is_pro'     => true,
				'dependency' => [
					[ '#is_dual_color' => [ 'type' => 'equal', 'value' => 'yes' ] ],
				],
			],
		];

		$fields['color'] = array_merge(
			apply_filters( 'rtwpvs_get_taxonomy_meta_color', $color_fields ),
			$common_fields
		);

		$fields['image']  = array_merge(
			[
				[
					'label' => esc_html__( 'Image', 'woo-product-variation-swatches' ), // <label>
					'desc'  => esc_html__( 'Choose an Image', 'woo-product-variation-swatches' ), // description
					'id'    => 'product_attribute_image',
					'type'  => 'image',
				],
			],
			$common_fields
		);
		$fields['button'] = $common_fields;
		$fields['radio']  = $common_fields;

		// Append the "Group" selector to every swatch type so any global
		// attribute term can be assigned to a user-defined group.
		// Attribute groups are a Pro feature. The "Group" selector is shown on all
		// versions (marked is_pro so free installs render it as a locked [Pro]
		// teaser); options are only populated for Pro via get_group_options().
		$groups_url  = admin_url( 'admin.php?page=' . \Rtwpvs\Controllers\SettingsAPI::PAGE_SLUG ) . '#/groups';
		$group_field = [
			'label'   => esc_html__( 'Group', 'woo-product-variation-swatches' ),
			'desc'    => sprintf(
				/* translators: %s: link to the Swatch Groups settings page. */
				esc_html__( 'Assign this term to a group. Groups are managed under %s.', 'woo-product-variation-swatches' ),
				'<a href="' . esc_url( $groups_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Product Swatches → Swatch Groups', 'woo-product-variation-swatches' ) . '</a>'
			),
			'id'      => 'rtwpvs_term_group',
			'type'    => 'select',
			'is_pro'  => true,
			'options' => [ '' => esc_html__( '— No group —', 'woo-product-variation-swatches' ) ] + self::get_group_options(),
		];
		foreach ( $fields as $type => $type_fields ) {
			$fields[ $type ]   = is_array( $type_fields ) ? $type_fields : [];
			$fields[ $type ][] = $group_field;
		}

		$fields = apply_filters( 'rtwpvs_get_product_taxonomy_meta_fields', $fields );

		if ( $field_id ) {
			return isset( $fields[ $field_id ] ) ? $fields[ $field_id ] : [];
		}

		return $fields;
	}

	public static function get_settings_sections() {
		$fields = [
			'general'         => [
				'id'     => 'general',
				'title'  => esc_html__( 'General', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Simple change some visual styles', 'woo-product-variation-swatches' ),
				'active' => apply_filters( 'rtwpvs_general_setting_active', true ),
				'fields' => apply_filters(
					'rtwpvs_general_setting_fields',
					[
						[
							'id'      => 'tooltip',
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Enable Tooltip', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Enable / Disable plugin default tooltip on each product attribute.', 'woo-product-variation-swatches' ),
							'default' => true,
						],
						[
							'id'      => 'style',
							'type'    => 'radio',
							'title'   => esc_html__( 'Shape style', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Shape of Color and Image swatches: Squared (slightly rounded corners) or Rounded (circle). Button and Radio swatches are not affected.', 'woo-product-variation-swatches' ),
							'options' => [
                                'squared' => esc_html__( 'Squared Shape ( Recommended )', 'woo-product-variation-swatches' ),
                                'rounded' => esc_html__( 'Rounded Shape', 'woo-product-variation-swatches' ),
							],
							'default' => 'squared',
						],
						[
							'id'      => 'color_swatch_label',
							'type'    => 'checkbox',
							'is_pro'  => true,
							'title'   => esc_html__( 'Show Color Swatches with Label', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Display the term name beside the color as a pill on the product details page (e.g. a green dot with "Green"). A matching option for shop / archive pages is on the Archive / Shop tab.', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'shape_style_checkmark',
							'type'    => 'checkbox',
							'is_pro'  => true,
							'title'   => esc_html__( 'Shape style with checkmark', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Shape style will add checkmark icon' ),
							'default' => false,
						],
						[
							'id'        => 'checkmark_position',
							'type'      => 'select',
							'is_pro'    => true,
							'title'     => esc_html__( 'Checkmark Position', 'woo-product-variation-swatches' ),
							'desc'      => esc_html__( 'Position of the checkmark icon on the selected swatch.', 'woo-product-variation-swatches' ),
							'options'   => [
								'top-left'      => esc_html__( 'Top Left', 'woo-product-variation-swatches' ),
								'top-right'     => esc_html__( 'Top Right', 'woo-product-variation-swatches' ),
								'center-center' => esc_html__( 'Center Center', 'woo-product-variation-swatches' ),
								'bottom-left'   => esc_html__( 'Bottom Left', 'woo-product-variation-swatches' ),
								'bottom-right'  => esc_html__( 'Bottom Right', 'woo-product-variation-swatches' ),
							],
							'default'   => 'center-center',
							// Only visible when the checkmark shape is enabled.
							'condition' => [ 'field' => 'shape_style_checkmark', 'value' => true ],
						],
						[
							'id'      => 'default_dropdown_to',
							'type'    => 'radio',
							'title'   => esc_html__( 'Auto Convert Dropdown To Swatch', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Automatically convert the default WooCommerce dropdown into the selected swatch type.', 'woo-product-variation-swatches' ),
							'options' => [
								'none'   => esc_html__( 'Default', 'woo-product-variation-swatches' ),
								'button' => esc_html__( 'Button', 'woo-product-variation-swatches' ),
								'image'  => esc_html__( 'Image', 'woo-product-variation-swatches' ) . ( function_exists( 'rtwpvsp' ) ? '' : ' [Pro]' ),
							],
							'default' => 'button',
						],
						[
							'id'      => 'attribute_on_click_behavior',
							'is_pro'  => true,
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Selected variation name beside label', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Show the selected term name next to the attribute label (e.g. "Color: Gray") when a swatch is selected.', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'single_swatches_display_limit',
							'is_pro'  => true,
							'type'    => 'number',
							'size'    => 'tiny',
							'title'   => esc_html__( 'Product Page Attribute display limit', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Catalog mode attribute display limit. Default is 0. Means no limit.', 'woo-product-variation-swatches' ),
							'default' => 0,
						],
						[
							'id'      => 'lazy_load_swatch_images',
							'type'    => 'checkbox',
							'is_pro'  => true,
							'title'   => esc_html__( 'Lazy-load swatch images', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Defer loading of image swatches and tooltip images until needed (native browser lazy-loading). Improves initial page load, especially on shop/archive pages and for hover tooltips.', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'attribute_image_size',
							'type'    => 'select',
							'title'   => esc_html__( 'Attribute image size', 'woo-product-variation-swatches' ),
							'desc'    => has_filter( 'rtwpvs_product_attribute_image_size' ) ? __( '<span style="color: red">Attribute image size changed by <code>rtwpvs_product_attribute_image_size</code> hook. So this option will not apply any effect.</span>', 'woo-product-variation-swatches' ) : __( sprintf( 'Choose attribute image size. <a target="_blank" href="%s">Media Settings</a>', esc_url( admin_url( 'options-media.php' ) ) ), 'woo-product-variation-swatches' ),
							'options' => Functions::get_all_image_sizes(),
							'default' => 'thumbnail',
						],
						[
							'id'    => 'title_color_image_size',
							'type'  => 'title',
							'title' => esc_html__( 'Color & Image Swatch', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Width and height for color and image type swatches', 'woo-product-variation-swatches' ),
						],
						[
							'id'      => 'width',
							'type'    => 'number',
							'title'   => esc_html__( 'Width', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Color & image swatch width', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 10,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'      => 'height',
							'type'    => 'number',
							'title'   => esc_html__( 'Height', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Color & image swatch height', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 10,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'    => 'title_button_size',
							'type'  => 'title',
							'title' => esc_html__( 'Button Swatch', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Min width/height for button/label type swatches on the single product page. Width is flexible based on text content. (Font size is under the Style tab.)', 'woo-product-variation-swatches' ),
						],
						[
							'id'      => 'button_min_width',
							'type'    => 'number',
							'title'   => esc_html__( 'Min Width', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Button swatch minimum width. Expands based on content.', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 20,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'      => 'button_min_height',
							'type'    => 'number',
							'title'   => esc_html__( 'Min Height', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Button swatch minimum height', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 20,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'    => 'tooltip_options_title',
							'type'  => 'title',
							'title' => esc_html__( 'Tooltip Options', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Tooltip settings for single page and catalog mode on shop / archive pages', 'woo-product-variation-swatches' ),
						],
						[
							'id'      => 'tooltip_image_size',
							'type'    => 'select',
							'is_pro'  => true,
							'title'   => esc_html__( 'Tooltip image size', 'woo-product-variation-swatches' ),
							'desc'    => has_filter( 'rtwpvs_tooltip_image_size' ) ? __( '<span style="color: red">Tooltip image size changed by <code>rtwpvs_tooltip_image_size</code> hook. So this option will not apply any effect.</span>', 'woo-product-variation-swatches' ) : __( sprintf( 'Choose tooltip image size. <a target="_blank" href="%s">Media Settings</a> Default (Thumbnail)', esc_url( admin_url( 'options-media.php' ) ) ), 'woo-product-variation-swatches' ),
							'options' => Functions::get_all_image_sizes(),
							'default' => 'thumbnail',
						],
						[
							'id'      => 'tooltip_image_width',
							'type'    => 'number',
							'is_pro'  => true,
							'title'   => esc_html__( 'Tooltip Image Width', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Tooltip Image width', 'woo-product-variation-swatches' ),
							'default' => 150,
							'min'     => 50,
							'max'     => 800,
							'suffix'  => 'px',
						],
					]
				),
			],
			'archive'         => [
				'id'     => 'archive',
				'title'  => esc_html__( 'Archive / Shop', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Advanced settings on shop / archive pages', 'woo-product-variation-swatches' ),
				'fields' => apply_filters(
					'rtwpvs_archive_setting_fields',
					[
						[
							'id'      => 'archive_swatches',
							'is_pro'  => true,
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Enable Swatches', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Show swatches on archive / shop page.', 'woo-product-variation-swatches' ),
							'default' => true,
						],
						[
							'id'      => 'archive_swatches_reveal_hover',
							'is_pro'  => true,
							'type'    => 'radio',
							'title'   => esc_html__( 'Swatches Display Mode', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Default shows the swatches inline. On Hover hides them and slides them up from the bottom of the product when the product is hovered. Modal keeps the "Select options" button and opens the swatches inside a popup when the button is clicked.', 'woo-product-variation-swatches' ),
							'options' => [
								'default' => esc_html__( 'Default', 'woo-product-variation-swatches' ),
                                'modal'   => esc_html__( 'Modal', 'woo-product-variation-swatches' ),
                                'hover'   => esc_html__( 'On Hover', 'woo-product-variation-swatches' ),
							],
							'default' => 'default',
						],
						[
							'id'      => 'enable_archive_variation_url',
							'type'    => 'checkbox',
							'is_pro'  => true,
							'title'   => esc_html__( 'Variation URL', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Generate URL based on selected variation attributes.', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'enable_ajax_archive_variation',
							'type'    => 'checkbox',
							'is_pro'  => true,
							'title'   => esc_html__( 'Variation Ajaxify', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Variation will load by ajax. This option will decrease loading time. Some Of Attribute behavior may not be work', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'archive_swatches_label',
							'is_pro'  => true,
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Show Attribute Label', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Display the attribute name (e.g. "Color", "Size") above its swatches on the shop / archive pages.', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'archive_color_swatch_label',
							'is_pro'  => true,
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Show Color Swatches with Label', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Display the term name beside the color as a pill on the shop / archive pages.', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'archive_product_wrapper_selector',
							'is_pro'  => true,
							'type'    => 'text',
							'title'   => esc_html__( 'Product Wrapper Selector', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Archive product Wrapper Selector (.rtwpvs-product,.tmb-woocommerce)' ),
							'default' => '.rtwpvs-product,.product-item',
						],
						[
							'id'      => 'archive_swatches_image_selector',
							'is_pro'  => true,
							'type'    => 'text',
							'title'   => esc_html__( 'Image Selector', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Archive product image selector to show variation image. You can also use multiple selectors separated by comma (.attachment-woocommerce_thumbnail, .wp-post-image) ', 'woo-product-variation-swatches' ),
							'default' => '.wp-post-image, .attachment-woocommerce_thumbnail',
						],
						[
							'id'      => 'archive_swatches_position',
							'is_pro'  => true,
							'type'    => 'radio',
							'title'   => esc_html__( 'Swatches position', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Show archive swatches position.', 'woo-product-variation-swatches' ) . '<br/>' . __( '<span style="color: red">Some theme remove woocommerce default hook, in that case this may not work with some theme.</span>', 'woo-product-variation-swatches' ),
							'options' => self::get_archive_swatches_positions(),
							'default' => 'after_title_and_price',
						],
						[
							'id'      => 'archive_swatches_align',
							'is_pro'  => true,
							'type'    => 'select',
							'title'   => esc_html__( 'Swatches align', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Swatches align on archive page.', 'woo-product-variation-swatches' ),
							'options' => self::get_archive_swatches_aligns(),
							'default' => 'left',
						],
						[
							'id'      => 'archive_swatches_tooltip',
							'is_pro'  => true,
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Enable Tooltip', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Show tooltip on archive / shop page', 'woo-product-variation-swatches' ),
							'default' => true,
						],
						[
							'id'      => 'show_clear_on_archive',
							'is_pro'  => true,
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Show clear link', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Show clear link on archive / shop page.', 'woo-product-variation-swatches' ),
							'default' => true,
						],
						[
							'id'    => 'archive_color_image_size_title',
							'type'  => 'title',
							'title' => esc_html__( 'Color & Image Swatch Size', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Width and height for color and image type swatches on archive / shop page', 'woo-product-variation-swatches' ),
						],
						[
							'id'      => 'archive_swatches_width',
							'is_pro'  => true,
							'type'    => 'number',
							'title'   => esc_html__( 'Width', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Color & image swatch width on archive / shop page', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 10,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'      => 'archive_swatches_height',
							'is_pro'  => true,
							'type'    => 'number',
							'title'   => esc_html__( 'Height', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Color & image swatch height on archive / shop page', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 10,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'    => 'archive_button_size_title',
							'type'  => 'title',
							'title' => esc_html__( 'Button Swatch Size', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Min width/height for button/label type swatches on archive / shop page. Width is flexible based on text content. (Font size is under the Style tab.)', 'woo-product-variation-swatches' ),
						],
						[
							'id'      => 'archive_button_min_width',
							'is_pro'  => true,
							'type'    => 'number',
							'title'   => esc_html__( 'Min Width', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Button swatch minimum width on archive / shop page. Expands based on content.', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 10,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'      => 'archive_button_min_height',
							'is_pro'  => true,
							'type'    => 'number',
							'title'   => esc_html__( 'Min Height', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Button swatch minimum height on archive / shop page', 'woo-product-variation-swatches' ),
							'default' => 40,
							'min'     => 10,
							'max'     => 200,
							'suffix'  => 'px',
						],
						[
							'id'      => 'archive_swatches_display_event',
							'is_pro'  => true,
							'type'    => 'select',
							'title'   => esc_html__( 'Catalog Mode Display Event', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Show catalog mode image display event.', 'woo-product-variation-swatches' ),
							'options' => [
								'click' => esc_html__( 'on Click', 'woo-product-variation-swatches' ),
								'hover' => esc_html__( 'on Hover', 'woo-product-variation-swatches' ),
							],
							'default' => 'click',
						],
						[
							'id'      => 'archive_swatches_display_limit',
							'is_pro'  => true,
							'type'    => 'number',
							'size'    => 'tiny',
							'min'     => 0,
							'title'   => esc_html__( 'Attribute display limit', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Catalog mode attribute display limit. Default is 0. Means no limit.', 'woo-product-variation-swatches' ),
							'default' => 0,
						],
						[
							'id'    => 'archive_special_attribute_title',
							'type'  => 'title',
							'title' => esc_html__( 'Special Attribute', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Show single attribute as catalog mode on shop / archive pages', 'woo-product-variation-swatches' ),
						],
						[
							'id'     => 'archive_swatches_enable_single_attribute',
							'is_pro' => true,
							'type'   => 'checkbox',
							'title'  => esc_html__( 'Show Single Attribute', 'woo-product-variation-swatches' ),
							'desc'   => esc_html__( 'Show single attribute taxonomies on archive page.', 'woo-product-variation-swatches' ),
						],
						[
							'id'        => 'archive_swatches_single_attribute',
							'is_pro'    => true,
							'type'      => 'select',
							'title'     => esc_html__( 'Chose Attribute', 'woo-product-variation-swatches' ),
							'desc'      => esc_html__( 'Choose an attribute to show on catalog mode', 'woo-product-variation-swatches' ),
							'options'   => Functions::get_wc_attributes( esc_html__( ' - Choose Attribute - ', 'woo-product-variation-swatches' ) ),
							'condition' => [ 'field' => 'archive_swatches_enable_single_attribute', 'value' => true ],
							'required'  => true,
						],
					]
				),
			],
			'groups'          => [
				'id'     => 'groups',
				'title'  => esc_html__( 'Swatch Groups', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Create attribute groups, then assign each attribute term to a group (Products → Attributes → edit a term). Swatches are displayed grouped under their group name.', 'woo-product-variation-swatches' ),
				'active' => apply_filters( 'rtwpvs_groups_setting_active', false ),
				'fields' => apply_filters(
					'rtwpvs_groups_setting_fields',
					[
						[
							'id'      => 'groups',
							'type'    => 'repeater',
							'is_pro'  => true,
							'title'   => esc_html__( 'Attribute Swatch Groups', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Add a group name for each group you want. You can rename a group anytime without losing the terms assigned to it.', 'woo-product-variation-swatches' ),
							'default' => [],
						],
					]
				),
			],
            'advanced'        => [
                'id'     => 'advanced',
                'title'  => esc_html__( 'Advanced', 'woo-product-variation-swatches' ),
                'desc'   => esc_html__( 'Advanced change some visual styles', 'woo-product-variation-swatches' ),
                'active' => apply_filters( 'rtwpvs_advanced_setting_active', false ),
                'fields' => apply_filters(
                    'rtwpvs_advanced_setting_fields',
                    [
                        [
                            'id'      => 'clear_on_reselect',
                            'type'    => 'checkbox',
                            'title'   => esc_html__( 'Clear on Reselect', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Clear selected attribute on select again', 'woo-product-variation-swatches' ),
                            'default' => false,
                        ],
                        [
                            'id'      => 'remove_variations_table',
                            'type'    => 'checkbox',
                            'title'   => esc_html__( 'Div-based variations layout', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Replace WooCommerce\'s variations <table> with <div> markup on the single product page. Some themes style the table.variations markup, so verify your product page after enabling.', 'woo-product-variation-swatches' ),
                            'default' => true,
                        ],
                        [
                            'id'      => 'threshold',
                            'type'    => 'number',
                            'title'   => esc_html__( 'Ajax variation threshold', 'woo-product-variation-swatches' ),
                            'desc'    => __( 'Default value is <code>30</code>, If you want all product variation set it to <code>1</code> then all variation will be load via ajax.<br><span style="color: red">Note: It\'s recommended to keep this number between 30 - 40.</span>', 'woo-product-variation-swatches' ),
                            'default' => 30,
                            'min'     => 1,
                            'max'     => 700,
                        ],
                        [
                            'id'      => 'disable_out_of_stock',
                            'type'    => 'checkbox',
                            'title'   => esc_html__( 'Out of stock for variation', 'woo-product-variation-swatches' ),
                            'desc'    => __( 'Disable out of stock for variation product attribute item<br><span style="color: red">Note: Will not work if you set Ajax variation threshold to 1.</span>', 'woo-product-variation-swatches' ),
                            'default' => true,
                        ],
                        [
                            'id'      => 'enable_variation_url',
                            'type'    => 'checkbox',
                            'is_pro'  => true,
                            'title'   => esc_html__( 'Variation URL', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Generate URL based on selected variation attributes.', 'woo-product-variation-swatches' ),
                            'default' => true,
                        ],
                        [
                            'id'      => 'attribute_behavior',
                            'type'    => 'radio',
                            'title'   => esc_html__( 'Attribute behavior', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Disabled attribute will be hide / blur.', 'woo-product-variation-swatches' ) . ' <span style="color: red">' . __( ' Note: This feature will be operational for variation quantities below the "Ajax variation threshold.', 'woo-product-variation-swatches' ) . '</span>',
                            'options' => [
                                'blur'          => esc_html__( 'Blur with cross', 'woo-product-variation-swatches' ),
                                'blur-no-cross' => esc_html__( 'Blur without cross', 'woo-product-variation-swatches' ),
                                'hide'          => esc_html__( 'Hide', 'woo-product-variation-swatches' ),
                            ],
                            'default' => 'blur',
                        ],
                    ]
                ),
            ],
            'style'           => [
                'id'     => 'style',
                'title'  => esc_html__( 'Style', 'woo-product-variation-swatches' ),
                'desc'   => esc_html__( 'Advanced change some visual styles', 'woo-product-variation-swatches' ),
                'active' => apply_filters( 'rtwpvs_style_setting_active', false ),
                'fields' => apply_filters(
                    'rtwpvs_style_setting_fields',
                    [
                        [
                            'id'    => 'card_common',
                            'type'  => 'card',
                            'title' => esc_html__( 'Global Style Settings', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Styling shared across the single product page and archive / shop pages.', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'tooltip_background',
                            'type'    => 'color',
                            'title'   => esc_html__( 'Tooltip background', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Tooltip background color', 'woo-product-variation-swatches' ),
                            'default' => '',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'tooltip_text_color',
                            'type'    => 'color',
                            'title'   => esc_html__( 'Tooltip text color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Tooltip text color', 'woo-product-variation-swatches' ),
                            'default' => '',
                        ],
                        [
                            'id'    => 'title_item_styling',
                            'type'  => 'title',
                            'title' => esc_html__( 'Attribute item styling', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Change attribute item display style', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'border_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Border color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches item border color. Default is: #eeeeee', 'woo-product-variation-swatches' ),
                            'default' => '#cccccc',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'border_size',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Border size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches attribute item border size. Default is: 1', 'woo-product-variation-swatches' ),
                            'default' => 1,
                            'min'     => 1,
                            'max'     => 5,
                            'suffix'  => esc_html__( 'px', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'item_border_radius',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Border radius', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Corner radius for all swatch items (and the color label pill). Applies when Shape style is "Squared". Leave blank for the default.', 'woo-product-variation-swatches' ),
                            'default' => '',
                            'min'     => 0,
                            'max'     => 50,
                            'suffix'  => esc_html__( 'px', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'item_padding',
                            'is_pro'  => true,
                            'type'    => 'spacing',
                            'title'   => esc_html__( 'Button padding', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Inner padding of button/label swatches (top, right, bottom, left). Color and image swatches have their own padding controls. Leave a side blank for its default.', 'woo-product-variation-swatches' ),
                            'default' => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                            'min'     => 0,
                            'max'     => 40,
                            'suffix'  => esc_html__( 'px', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'text_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Text color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches attribute item text color. Default is: #000000', 'woo-product-variation-swatches' ),
                            'default' => '#000000',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'background_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Background color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches attribute item background color. Default is: #FFFFFF', 'woo-product-variation-swatches' ),
                            'default' => '#FFFFFF',
                            'alpha'   => true,
                        ],
                        [
                            'id'    => 'title_color_swatch_label_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Color Swatch Label Area', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Label text color and color-area size shown beside the label (when "Show Color Swatches with Label" is enabled)', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'color_swatch_label_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Color swatch label text', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Text color of the label shown beside color swatches (when "Show Color Swatches with Label" is enabled). Default is: #6B6B6B', 'woo-product-variation-swatches' ),
                            'default' => '#6B6B6B',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'color_swatch_label_font_size',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Label font size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Font size of the label shown beside color swatches. Default is: 15', 'woo-product-variation-swatches' ),
                            'default' => 15,
                            'min'     => 8,
                            'max'     => 40,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'color_swatch_label_dot_width',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Color area width', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Width of the color area shown beside the label. Default is: 24', 'woo-product-variation-swatches' ),
                            'default' => 24,
                            'min'     => 8,
                            'max'     => 100,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'color_swatch_label_dot_height',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Color area height', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Height of the color area shown beside the label. Default is: 24', 'woo-product-variation-swatches' ),
                            'default' => 24,
                            'min'     => 8,
                            'max'     => 100,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'color_swatch_label_padding',
                            'is_pro'  => true,
                            'type'    => 'spacing',
                            'title'   => esc_html__( 'Padding', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Inner padding of the color-with-label pill (top, right, bottom, left). Leave a side blank for its default.', 'woo-product-variation-swatches' ),
                            'default' => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'    => 'title_radio_swatch_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Radio Swatch', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Label font size and radio input size for radio type swatches', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'radio_label_font_size',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Label font size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Font size of the radio swatch label. Default is: 14', 'woo-product-variation-swatches' ),
                            'default' => 14,
                            'min'     => 8,
                            'max'     => 40,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'radio_input_width',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Radio input width', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Width of the radio input control. Default is: 18', 'woo-product-variation-swatches' ),
                            'default' => 18,
                            'min'     => 8,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'radio_input_height',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Radio input height', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Height of the radio input control. Default is: 18', 'woo-product-variation-swatches' ),
                            'default' => 18,
                            'min'     => 8,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'radio_label_input_gap',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Label & input gap', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Gap between the radio input and its label. Default is: 10', 'woo-product-variation-swatches' ),
                            'default' => 10,
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'radio_group_title_gap',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Group title gap', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Gap between a group title and its radio items (when swatch groups are used). Default is: 8', 'woo-product-variation-swatches' ),
                            'default' => 8,
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'    => 'title_image_swatch_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Image Swatch', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Inner padding for image type swatches (overrides the general item padding).', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'image_swatch_padding',
                            'is_pro'  => true,
                            'type'    => 'spacing',
                            'title'   => esc_html__( 'Padding', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Inner padding of image swatches (top, right, bottom, left). Leave a side blank for its default.', 'woo-product-variation-swatches' ),
                            'default' => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'    => 'title_select_field_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Select Field', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Styling for the Select (dropdown) swatch type.', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'select_field_font_size',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Font size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Text size of the select dropdown. Default is: 14', 'woo-product-variation-swatches' ),
                            'default' => 14,
                            'min'     => 8,
                            'max'     => 40,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'select_field_height',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Height', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Height of the select dropdown. Leave blank for auto (padding controls the height).', 'woo-product-variation-swatches' ),
                            'default' => '',
                            'min'     => 20,
                            'max'     => 120,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'select_field_border_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Border color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Border color of the select dropdown. Default is: #cccccc', 'woo-product-variation-swatches' ),
                            'default' => '#cccccc',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'select_field_border_radius',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Border radius', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Corner radius of the select dropdown. Default is: 0', 'woo-product-variation-swatches' ),
                            'default' => 0,
                            'min'     => 0,
                            'max'     => 50,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'select_field_padding',
                            'is_pro'  => true,
                            'type'    => 'spacing',
                            'title'   => esc_html__( 'Padding', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Inner padding of the select dropdown (top, right, bottom, left). Leave a side blank for its default.', 'woo-product-variation-swatches' ),
                            'default' => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'    => 'title_clear_link_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Clear Link', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Styling for the "Clear" (reset variations) link shown below the attributes.', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'clear_link_font_size',
                            'type'    => 'number',
                            'title'   => esc_html__( 'Font size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Text size of the Clear link. Default is: 14', 'woo-product-variation-swatches' ),
                            'default' => 14,
                            'min'     => 8,
                            'max'     => 40,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'clear_link_padding',
                            'type'    => 'spacing',
                            'title'   => esc_html__( 'Padding', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Inner padding of the Clear link (top, right, bottom, left). Leave a side blank for its default.', 'woo-product-variation-swatches' ),
                            'default' => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'      => 'clear_link_margin',
                            'type'    => 'spacing',
                            'title'   => esc_html__( 'Margin', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Outer margin of the Clear link (top, right, bottom, left). Leave a side blank for its default.', 'woo-product-variation-swatches' ),
                            'default' => [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'    => 'title_attribute_item_hover_styling',
                            'type'  => 'title',
                            'title' => esc_html__( 'Attribute item Hover Styling', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Change attribute item hover display style', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'hover_border_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Hover border color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches attribute item hover border color. Default is: #000000', 'woo-product-variation-swatches' ),
                            'default' => '#000000',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'hover_text_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Hover text color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches attribute item hover text color. Default is: #000000', 'woo-product-variation-swatches' ),
                            'default' => '#000000',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'hover_background_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Hover background color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches attribute item hover background color. Default is: #FFFFFF', 'woo-product-variation-swatches' ),
                            'default' => '#FFFFFF',
                            'alpha'   => true,
                        ],
                        [
                            'id'    => 'title_attribute_item_selected_styling',
                            'type'  => 'title',
                            'title' => esc_html__( 'Attribute item Selected Styling', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Change attribute selected item display style', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'selected_border_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Border color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches selected item border color. Default is: #000000', 'woo-product-variation-swatches' ),
                            'default' => '#000000',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'selected_text_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Text color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches item selected text color. Default is: #000000', 'woo-product-variation-swatches' ),
                            'default' => '#000000',
                            'alpha'   => true,
                        ],
                        [
                            'id'      => 'selected_background_color',
                            'is_pro'  => true,
                            'type'    => 'color',
                            'title'   => esc_html__( 'Background color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Swatches item selected background color. Default is: #FFFFFF', 'woo-product-variation-swatches' ),
                            'default' => '#FFFFFF',
                            'alpha'   => true,
                        ],
                        [
                            'id'    => 'title_attribute_behaviour',
                            'type'  => 'title',
                            'title' => esc_html__( 'Attribute behavior', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'This will work for (blur and blur-no-cross)', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'attribute_behaviour_cross_color',
                            'type'    => 'color',
                            'title'   => esc_html__( 'Cross background color', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Cross background color for disabled item', 'woo-product-variation-swatches' ),
                            'default' => '#ff0000',
                        ],
                        [
                            'id'      => 'attribute_behaviour_blur_opacity',
                            'type'    => 'number',
                            'title'   => esc_html__( 'Blur Opacity', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Blur Opacity for disabled item range[.1 to 1]', 'woo-product-variation-swatches' ),
                            'default' => .3,
                            'step'    => '0.1',
                            'min'     => .1,
                            'max'     => 1,
                        ],
                        [
                            'id'    => 'card_archive',
                            'type'  => 'card',
                            'title' => esc_html__( 'Archive / Shop Page', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Styling that applies to shop, archive and related / up-sell / cross-sell loops.', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'reveal_panel_radius',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Reveal Panel Corner Radius', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Top corner radius of the archive "Reveal Swatches on Hover" panel. Default is: 3', 'woo-product-variation-swatches' ),
                            'default' => 3,
                            'min'     => 0,
                            'max'     => 50,
                            'suffix'  => esc_html__( 'px', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'reveal_panel_padding',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Reveal Panel Inner Padding', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Padding inside the "Reveal Swatches on Hover" panel around the swatches, price and buttons. Default is: 16', 'woo-product-variation-swatches' ),
                            'default' => 16,
                            'min'     => 0,
                            'max'     => 60,
                            'suffix'  => esc_html__( 'px', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'    => 'title_archive_button_swatch_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Button Swatch', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Button/label swatch font size on shop, archive and related/upsell/cross-sell loops. (Min width/height stay under the Archive tab.)', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'archive_swatches_font_size',
                            'is_pro'  => true,
                            'type'    => 'number',
                            'title'   => esc_html__( 'Font Size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Button swatch font size on archive / shop page.', 'woo-product-variation-swatches' ),
                            'default' => 16,
                            'min'     => 8,
                            'max'     => 24,
                            'suffix'  => 'px',
                        ],
                        [
                            'id'    => 'card_product',
                            'type'  => 'card',
                            'title' => esc_html__( 'Product Page', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Styling that applies only to swatches on the single product page.', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'    => 'title_button_swatch_area',
                            'type'  => 'title',
                            'title' => esc_html__( 'Button Swatch', 'woo-product-variation-swatches' ),
                            'desc'  => esc_html__( 'Button/label swatch text on the single product page.', 'woo-product-variation-swatches' ),
                        ],
                        [
                            'id'      => 'single_font_size',
                            'type'    => 'number',
                            'title'   => esc_html__( 'Font Size', 'woo-product-variation-swatches' ),
                            'desc'    => esc_html__( 'Button swatch font size on the single product page.', 'woo-product-variation-swatches' ),
                            'default' => 16,
                            'min'     => 8,
                            'max'     => 24,
                            'suffix'  => 'px',
                        ],
                    ]
                ),
            ],
			'tools'           => [
				'id'     => 'tools',
				'title'  => esc_html__( 'Tools', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Tools define some system tasks', 'woo-product-variation-swatches' ),
				'active' => apply_filters( 'rtwpvs_tools_setting_active', false ),
				'fields' => apply_filters(
					'rtwpvs_tools_setting_fields',
					[
						[
							'id'    => 'remove_all_data',
							'type'  => 'checkbox',
							'title' => esc_html__( 'Enable to delete all data', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Enable / Disable Allow to delete all data for WooCommerce Product variation plugin during delete this plugin', 'woo-product-variation-swatches' ),
						],
						[
							'id'    => 'archive_special_attribute_title',
							'type'  => 'title',
							'title' => esc_html__( 'Performance', 'woo-product-variation-swatches' ),
							'desc'  => __( 'Improve your site performance.', 'woo-product-variation-swatches' ) . sprintf(
								__( 'You can remove all cache from here. <a href="%s">Clear all cache</a>', 'woo-product-variation-swatches' ),
								add_query_arg(
									[
										'_wpnonce' => wp_create_nonce( 'rtwpvs_clear_all_cache' ),
										'rtwpvs_clear_all_transient' => '',
									],
									Functions::get_current_actual_url()
								)
							),
						],
						[
							'id'      => 'load_scripts',
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Load Scripts', 'woo-product-variation-swatches' ),
							'desc'    => __( 'Only <strong>Single product</strong> and <strong>Product archive</strong> pages. [if unchecked then it will load the scripts to all over the site]', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'defer_load_js',
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Defer Load JS', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Defer Load JS for PageSpeed Score', 'woo-product-variation-swatches' ),
							'default' => false,
						],
						[
							'id'      => 'use_cache',
							'type'    => 'checkbox',
							'title'   => esc_html__( 'Use Cache', 'woo-product-variation-swatches' ),
							'desc'    => esc_html__( 'Use Transient Cache for PageSpeed Score', 'woo-product-variation-swatches' ),
							'default' => false,
						],
					]
				),
			],
			'premium_plugins' => [
				'id'     => 'premium_plugins',
				'title'  => __( 'Our Plugins & Themes', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Explore our other WooCommerce plugins and WordPress themes.', 'woo-product-variation-swatches' ),
				'fields' => apply_filters(
					'rtwpvs_premium_plugins_setting_fields',
					[
						[
							'id'     => 'premium_plugins_title',
							'type'   => 'title',
							'title'  => esc_html__( 'Our Plugins', 'woo-product-variation-swatches' ),
							'desc'   => esc_html__( 'Premium WooCommerce plugins from RadiusTheme.', 'woo-product-variation-swatches' ),
							'action' => [
								'label' => esc_html__( 'View All', 'woo-product-variation-swatches' ),
								'url'   => 'https://www.radiustheme.com/wordpress-plugins/',
							],
						],
						[
							'id'         => 'premium_plugins_list',
							'type'       => 'feature',
							'attributes' => [
								'class' => 'rt-feature',
							],
							'html'       => Functions::get_product_list_html(
								[
									'rtsb-pro'   => [
										'price'     => '$41.00 – $209.00',
										'title'     => 'ShopBuilder - WooCommerce Builder For Elementor',
										'image_url' => rtwpvs()->get_images_uri( 'shopbuilde.png' ),
										'url'       => 'https://www.radiustheme.com/downloads/woocommerce-bundle/',
										'demo_url'  => 'https://shopbuilderwp.com/',
										'buy_url'   => 'https://www.radiustheme.com/downloads/woocommerce-bundle/',
									],
									'rtwpvs-pro' => [
										'title'     => 'Variation Swatches for WooCommerce Pro',
										'price'     => '$29.00 – $549.00',
										'image_url' => rtwpvs()->get_images_uri( 'rtwpvs-pro.png' ),
										'url'       => 'https://www.radiustheme.com/downloads/woocommerce-variation-swatches/',
										'demo_url'  => 'https://radiustheme.com/demo/wordpress/woopluginspro/',
										'buy_url'   => 'https://www.radiustheme.com/downloads/woocommerce-variation-swatches/',
										// 'doc_url' => 'https://www.radiustheme.com/setup-configure-woocommerce-product-variation-swatches-pro/'
									],
									'rtwpvg-pro' => [
										'price'     => '$29.00 – $549.00',
										'title'     => 'Variation Images Gallery for WooCommerce Pro',
										'image_url' => rtwpvs()->get_images_uri( 'rtwpvg-pro.png' ),
										'url'       => 'https://www.radiustheme.com/downloads/woocommerce-variation-images-gallery/',
										'demo_url'  => 'https://radiustheme.com/demo/wordpress/woopluginspro/product/woocommerce-variation-images-gallery/',
										'buy_url'   => 'https://www.radiustheme.com/downloads/woocommerce-variation-images-gallery/',
										// 'doc_url'   => 'https://www.radiustheme.com/how-to-use-woocommerce-variation-images-gallery-pro/'
									],
									'review-schema' => [
										'price'     => esc_html__( 'Free', 'woo-product-variation-swatches' ),
										'title'     => 'Review Schema – Rich Snippets & Structured Data',
										'image_url' => 'https://ps.w.org/review-schema/assets/icon-256x256.gif',
										'url'       => 'https://wordpress.org/plugins/review-schema/',
										'demo_url'  => 'https://www.radiustheme.com/demo/wordpress/plugins/review-schema/',
										'buy_url'   => 'https://wordpress.org/plugins/review-schema/',
									],
								]
							),
						],
						[
							'id'     => 'premium_themes_title',
							'type'   => 'title',
							'title'  => esc_html__( 'Our Themes', 'woo-product-variation-swatches' ),
							'desc'   => esc_html__( 'Premium WordPress themes from RadiusTheme.', 'woo-product-variation-swatches' ),
							'action' => [
								'label' => esc_html__( 'View All', 'woo-product-variation-swatches' ),
								'url'   => 'https://www.radiustheme.com/wordpress-themes/',
							],
						],
						[
							'id'         => 'premium_themes_list',
							'type'       => 'feature',
							'attributes' => [
								'class' => 'rt-feature',
							],
							'html'       => Functions::get_product_list_html(
								[
									'metro' => [
										'title'     => 'Metro – Minimal WooCommerce WordPress Theme',
										'image_url' => rtwpvs()->get_images_uri( 'metro.jpg' ),
										'url'       => 'https://www.radiustheme.com/downloads/metro-minimal-woocommerce-wordpress-theme/',
										'demo_url'  => 'https://www.radiustheme.com/demo/wordpress/themes/metro/preview/',
										'buy_url'   => 'https://www.radiustheme.com/downloads/metro-minimal-woocommerce-wordpress-theme/',
										// 'doc_url'   => 'https://www.radiustheme.com/demo/wordpress/themes/metro/docs/'
									],
								]
							),
						],
					]
				),
			],
		];

		$fields = self::maybe_add_license_section( $fields );

		return apply_filters( 'rtwpvs_settings_fields', $fields );
	}

	/**
	 * Append the License section when the Pro plugin is active.
	 *
	 * The license key/status live in the free plugin settings, so the tab is
	 * defined here and only surfaced once Pro is installed and loaded.
	 *
	 * @param array $sections Settings sections keyed by tab id.
	 *
	 * @return array
	 */
	private static function maybe_add_license_section( $sections ) {
		if ( ! function_exists( 'rtwpvsp' ) ) {
			return $sections;
		}

		$license = [
			'license' => [
				'id'     => 'license',
				'title'  => esc_html__( 'License', 'woo-product-variation-swatches' ),
				'desc'   => esc_html__( 'Add your licence code here', 'woo-product-variation-swatches' ),
				'active' => apply_filters( 'rtwpvs_license_setting_active', false ),
				'fields' => apply_filters(
					'rtwpvs_license_setting_fields',
					[
						[
							'id'    => 'license_key',
							'type'  => 'license',
							'title' => esc_html__( 'Licence key', 'woo-product-variation-swatches' ),
							'desc'  => esc_html__( 'Enter your license key and save settings to activate Pro features and updates.', 'woo-product-variation-swatches' ),
						],
					]
				),
			],
		];

		// Place the License tab right after the "Tools" tab.
		$keys     = array_keys( $sections );
		$position = array_search( 'tools', $keys, true );
		$position = ( false === $position ) ? count( $sections ) : $position + 1;

		return array_merge(
			array_slice( $sections, 0, $position, true ),
			$license,
			array_slice( $sections, $position, null, true )
		);
	}

	public static function get_archive_swatches_positions() {
		$positions = [
			'after_title_and_price'      => esc_html__( 'After item title and price', 'woo-product-variation-swatches' ),
			'before_title_and_price'     => esc_html__( 'Before item title and price', 'woo-product-variation-swatches' ),
			'after_select_option_button' => esc_html__( 'After select options button', 'woo-product-variation-swatches' ),

		];

		return apply_filters( 'rtwpvs_archive_swatches_positions', $positions );
	}

	public static function get_archive_swatches_aligns() {
		$aligns = [
			'left'   => esc_html__( 'Left', 'woo-product-variation-swatches' ),
			'right'  => esc_html__( 'Right', 'woo-product-variation-swatches' ),
			'center' => esc_html__( 'Center', 'woo-product-variation-swatches' ),

		];

		return apply_filters( 'get_archive_swatches_aligns', $aligns );
	}

	public static function get_tooltip_options() {
		$options = [
			'no'  => esc_html__( 'No', 'woo-product-variation-swatches' ),
			'yes' => esc_html__( 'Yes', 'woo-product-variation-swatches' ),
		];

		return apply_filters( 'get_tooltip_options', $options );
	}
}
