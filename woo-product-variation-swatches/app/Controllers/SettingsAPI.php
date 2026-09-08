<?php

namespace Rtwpvs\Controllers;

use Rtwpvs\Helpers\Options;
use Rtwpvsp\Controllers\Licensing;

class SettingsAPI {


	private $setting_id = 'rtwpvs';
	private $defaults   = [];
	private $sections   = [];

	public function __construct() {
		$this->sections = Options::get_settings_sections();
		add_action( 'init', [ $this, 'set_defaults' ], 8 );
		add_filter(
			'plugin_action_links_' . RTWPVS_PLUGIN_BASENAME,
			[
				$this,
				'plugin_action_links',
			]
		);
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_' . $this->setting_id, [ $this, 'settings_tab' ] );
		add_action( 'woocommerce_update_options_' . $this->setting_id, [ $this, 'update_settings' ] );
		add_action( 'woocommerce_admin_field_' . $this->setting_id, [ $this, 'global_settings' ] );
		add_action( 'wp_ajax_rtwpvs_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_rtwpvs_clear_cache', [ $this, 'ajax_clear_cache' ] );
		add_action( 'admin_menu', [ $this, 'register_submenu' ], 60 );
		add_action( 'admin_init', [ $this, 'maybe_redirect_legacy_tab' ] );
		add_action( 'in_admin_header', [ $this, 'suppress_admin_notices' ], PHP_INT_MAX );
		if ( apply_filters( 'rtwpvs_settings_link_on_admin_bar', false ) ) {
			add_action( 'wp_before_admin_bar_render', [ $this, 'add_admin_bar' ], 999 );
		}
	}

	public function add_admin_bar() {

		global $wp_admin_bar;
		$wp_admin_bar->add_menu(
			[
				'id'    => $this->setting_id,
				'title' => esc_html__( 'Product Swatches', 'woo-product-variation-swatches' ),
				'href'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'meta'  => [
					'class' => sprintf( '%s-admin-toolbar', $this->setting_id ),
				],
			]
		);

		if ( ! is_admin() && rtwpvs()->is_wc_active() && ( is_singular( 'product' ) || is_shop() ) ) {
			$wp_admin_bar->add_menu(
				[
					'id'     => 'rtwpvs-clear-transient',
					'title'  => esc_html__( 'Clear Cache', 'woo-product-variation-swatches' ),
					'href'   => esc_url(
						remove_query_arg(
							[
								'variation_id',
								'remove_item',
								'add-to-cart',
								'added-to-cart',
							],
							add_query_arg( 'rtwpvs_clear_transient', '' )
						)
					),
					'parent' => $this->setting_id,
					'meta'   => [
						'class' => sprintf( '%s-admin-toolbar-cache', $this->setting_id ),
					],
				]
			);
		}

		do_action( 'rtwpvs_admin_bar_menu', $wp_admin_bar, $this->setting_id );
	}

	public function set_defaults() {
		foreach ( $this->sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				$field['default'] = isset( $field['default'] ) ? $field['default'] : null;
				$this->set_default( $field['id'], $field['type'], $field['default'] );
			}
		}
	}

	private function set_default( $key, $type, $value ) {
		$this->defaults[ $key ] = [
			'id'    => $key,
			'type'  => $type,
			'value' => $value,
		];
	}

	private function get_default( $key ) {
		return isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null;
	}

	public function get_defaults() {
		return $this->defaults;
	}

	public function plugin_action_links( $links ) {
		$new_links = [
			'<a href="' . admin_url( 'admin.php?page=' . self::PAGE_SLUG ) . '">' . __( 'Settings', 'woo-product-variation-swatches' ) . '</a>',
			'<a target="_blank" href="' . esc_url( 'https://radiustheme.com/demo/wordpress/woopluginspro/product/woocoommerce-variation-swatches/' ) . '">' . esc_html__( 'Demo', 'woo-product-variation-swatches' ) . '</a>',
			'<a target="_blank" href="' . esc_url( 'https://www.radiustheme.com/docs/variation-swatches/' ) . '">' . esc_html__( 'Documentation', 'woo-product-variation-swatches' ) . '</a>',
		];

		if ( ! function_exists( 'rtwpvsp' ) ) {
			$new_links[] = '<a style="color: #39b54a;font-weight: 700;" target="_blank" href="' . esc_url( 'https://www.radiustheme.com/downloads/woocommerce-variation-swatches/?utm_source=WordPress&utm_medium=swatches&utm_campaign=pro_click' ) . '">' . esc_html__( 'Get Pro', 'woo-product-variation-swatches' ) . '</a>';
		}

		return array_merge( $links, $new_links );
	}

	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs[ $this->setting_id ] = __( 'Product Swatches', 'woo-product-variation-swatches' );

		return $settings_tabs;
	}

	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	public function update_settings() {
		woocommerce_update_options( $this->get_settings() );
		$this->update_licencing_status();
	}

	private function update_licencing_status() {

		$license_key    = trim( $this->get_option( 'license_key' ) );
		$license_status = $this->get_option( 'license_status' );
		$status         = ( ! empty( $license_status ) && $license_status === 'valid' ) ? true : false;
		if ( $license_key && ! $status ) {
			if ( ! class_exists( Licensing::class ) ) {
				return;
			}
			$api_params = [
				'edd_action' => 'activate_license',
				'license'    => $license_key,
				'item_id'    => Licensing::$product_id,
				'url'        => home_url(),
			];
			$response   = wp_remote_post(
				Licensing::$store_url,
				[
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				]
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				if ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) {
					$message = $response->get_error_message();
				} elseif ( isset( $response['response'] ) && is_array( $response['response'] ) && isset( $response['response']['message'] ) ) {
					$message = 'sss' . $response['response']['message'];
				} else {
					$message = esc_html__( 'An error occurred, please try again.', 'woo-product-variation-swatches' );
				}
			} else {
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				if ( false === $license_data->success ) {
					switch ( $license_data->error ) {
						case 'expired':
							$message = sprintf(
								__( 'Your license key expired on %s.', 'woo-product-variation-swatches' ),
								date_i18n(
									get_option( 'date_format' ),
									strtotime( $license_data->expires, current_time( 'timestamp' ) )
								)
							);
							break;
						case 'revoked':
							$message = esc_html__( 'Your license key has been disabled.', 'woo-product-variation-swatches' );
							break;
						case 'missing':
							$message = esc_html__( 'Invalid license.', 'woo-product-variation-swatches' );
							break;
						case 'invalid':
						case 'site_inactive':
							$message = esc_html__( 'Your license is not active for this URL.', 'woo-product-variation-swatches' );
							break;
						case 'item_name_mismatch':
							$message = esc_html__( 'This appears to be an invalid license key for Variation Swatch Pro.', 'woo-product-variation-swatches' );
							break;
						case 'no_activations_left':
							$message = esc_html__( 'Your license key has reached its activation limit.', 'woo-product-variation-swatches' );
							break;
						default:
							$message = esc_html__( 'An error occurred, please try again.', 'woo-product-variation-swatches' );
							break;
					}
				}
				// Check if anything passed on a message constituting a failure
				if ( empty( $message ) && $license_data->license === 'valid' ) {
					$this->update_option( 'license_status', $license_data->license );
				} else {
					$this->update_option( 'license_status', '' );
				}
			}
		} elseif ( ! $license_key && ! $status ) {
			$this->update_option( 'license_status', '' );
		}
	}

	public function get_settings() {
		$settings = [
			[
				'name' => __( 'Variation Swatches for WooCommerce Settings', 'woo-product-variation-swatches' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'wc_rtwpvs_settings_section',
			],
			[
				'type' => $this->setting_id,
				'id'   => $this->setting_id,
			],
			'section_end' => [
				'type' => 'sectionend',
				'id'   => 'wc_rtwpvs_settings_section',
			],
		];

		return apply_filters( 'wc_rtwpvs_settings', $settings );
	}

	public function options_tabs() {
		?>
		<nav class="nav-tab-wrapper wp-clearfix">
			<?php
			foreach ( $this->sections as $tabs ) :
				$active_class = $this->get_options_tab_css_classes( $tabs );
				if ( $this->get_last_active_tab() == 'license' && ! function_exists( 'rtwpvsp' ) && $tabs['id'] == 'general' ) {
					$active_class = 'nav-tab-active';
				}
				?>
				<a data-target="<?php echo esc_attr( $tabs['id'] ); ?>"
				   class="rtwpvs-setting-nav-tab nav-tab <?php echo esc_attr( $active_class ); ?> "
				   href="#<?php echo esc_attr( $tabs['id'] ); ?>"><?php echo esc_html( $tabs['title'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Slug of the dedicated settings page under the WooCommerce menu.
	 */
	const PAGE_SLUG = 'rtwpvs-settings';

	/**
	 * Register the "Product Swatches" submenu under the WooCommerce menu.
	 */
	public function register_submenu() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Product Swatches', 'woo-product-variation-swatches' ),
			esc_html__( 'Product Swatches', 'woo-product-variation-swatches' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Redirect the legacy WooCommerce settings tab (wc-settings&tab=rtwpvs) to
	 * the dedicated submenu page so both entry points land in the same place.
	 */
	public function maybe_redirect_legacy_tab() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'wc-settings' === $page && $this->setting_id === $tab ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
			exit;
		}
	}

	/**
	 * Keep the dedicated settings screen clean by removing third-party admin
	 * notices (theme license, TGM "required plugin" prompts, etc.) that
	 * WordPress otherwise injects below our React header.
	 */
	public function suppress_admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/**
	 * Render the dedicated submenu settings page.
	 */
	public function render_settings_page() {
		echo '<div class="rtwpvs-settings-page">';
		$this->render_settings_app();
		echo '</div>';
	}

	/**
	 * WooCommerce settings-tab field callback (kept as a fallback in case the
	 * redirect is short-circuited). Hides the WooCommerce save button and
	 * renders the same React app.
	 */
	function global_settings() {
		$GLOBALS['hide_save_button'] = true;
		$this->render_settings_app();
	}

	/**
	 * Print the React mount point, its data and the module bundle.
	 *
	 * The old server-rendered fields were replaced by a React 19 UI that reads
	 * its schema/values from `rtwpvsSettings` and saves via the
	 * `rtwpvs_save_settings` AJAX action. The bundle is printed directly (not
	 * enqueued) so optimization/security plugins can't strip it, and loaded as
	 * a module because it relies on `import.meta`. JSON_HEX_TAG keeps any HTML
	 * in the data from breaking out of the inline <script>.
	 */
	private function render_settings_app() {
		$data    = $this->get_react_data();
		$version = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : RTWPVS_VERSION;
		$src     = rtwpvs()->get_assets_uri( 'js/settings.js' ) . '?ver=' . rawurlencode( (string) $version );
		?>
		<div class="rtwpvs-settings">
			<div id="rtwpvs-settings-root"></div>
		</div>
		<script type="text/javascript">
			window.rtwpvsSettings = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
		</script>
		<script type="module" id="rtwpvs-settings-js" src="<?php echo esc_url( $src ); ?>"></script>
		<?php
	}

	/**
	 * Build the data object consumed by the React settings app.
	 *
	 * @return array
	 */
	public function get_react_data() {
		$is_pro = function_exists( 'rtwpvsp' );

		$values = [];
		foreach ( $this->get_saveable_fields() as $field ) {
			$values[ $field['id'] ] = $this->get_option( $field['id'] );
		}

		return [
			'sections'   => array_values( $this->sections ),
			'values'     => $values,
			'isPro'      => $is_pro,
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'rtwpvs_nonce' ),
			'saveAction' => 'rtwpvs_save_settings',
			'licenseStatus' => (string) $this->get_option( 'license_status' ),
			'licenseNonce'  => wp_create_nonce( 'rtwpvs_manage_licensing' ),
			'licenseAction' => 'rtwpvs_manage_licensing',
			'logoUrl'    => rtwpvs()->get_images_uri( 'icon-256x256.png' ),
			'proUrl'     => 'https://www.radiustheme.com/downloads/woocommerce-variation-swatches/',
			'docUrl'     => 'https://www.radiustheme.com/docs/variation-swatches/',
			'supportUrl' => 'https://www.radiustheme.com/contact/',
			'reviewUrl'  => 'https://wordpress.org/support/plugin/woo-product-variation-swatches/reviews/#new-post',
			'version'    => RTWPVS_VERSION,
			'strings'    => [
				'pageTitle' => esc_html__( 'Variation Swatches', 'woo-product-variation-swatches' ),
				'save'      => esc_html__( 'Save Changes', 'woo-product-variation-swatches' ),
				'saving'    => esc_html__( 'Saving…', 'woo-product-variation-swatches' ),
				'saved'     => esc_html__( 'Changes Saved', 'woo-product-variation-swatches' ),
				'requiredField' => esc_html__( '%s is required.', 'woo-product-variation-swatches' ),
				'requiredError' => esc_html__( 'Please fill the required fields before saving.', 'woo-product-variation-swatches' ),
				'upgrade'   => esc_html__( 'Upgrade to Pro', 'woo-product-variation-swatches' ),
				'licenseActive'  => esc_html__( 'License Active', 'woo-product-variation-swatches' ),
				'licenseInvalid' => esc_html__( 'Invalid Licence', 'woo-product-variation-swatches' ),
				'pro'       => esc_html__( 'Pro', 'woo-product-variation-swatches' ),
				'proField'  => esc_html__( 'This is a premium field. Upgrade to Pro to use it.', 'woo-product-variation-swatches' ),
				'cancel'          => esc_html__( 'Cancel', 'woo-product-variation-swatches' ),
				'proAlertTitle'   => esc_html__( 'Pro field alert!', 'woo-product-variation-swatches' ),
				'proAlertMessage' => esc_html__( 'Sorry! this is a pro field. To use this field, you need to use pro plugin.', 'woo-product-variation-swatches' ),
			],
		];
	}

	/**
	 * Flat list of persistable fields (excludes layout-only field types).
	 *
	 * @return array
	 */
	private function get_saveable_fields() {
		$skip   = [ 'title', 'feature', 'card' ];
		$fields = [];
		foreach ( $this->sections as $section ) {
			foreach ( $section['fields'] as $field ) {
				if ( empty( $field['id'] ) || in_array( $field['type'], $skip, true ) ) {
					continue;
				}
				$fields[ $field['id'] ] = $field;
			}
		}

		return $fields;
	}

	/**
	 * AJAX handler: persist settings sent by the React app to the `rtwpvs`
	 * option. Unknown keys are ignored and existing keys not present in the
	 * payload are preserved (no data migration).
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'woo-product-variation-swatches' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rtwpvs_nonce' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid or expired request. Please reload the page.', 'woo-product-variation-swatches' ) ], 403 );
		}

		$payload = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null;
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid payload.', 'woo-product-variation-swatches' ) ], 400 );
		}

		$is_pro    = function_exists( 'rtwpvsp' );
		$allowed   = $this->get_saveable_fields();
		$sanitized = [];
		foreach ( $payload as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			// Never let a free user overwrite Pro-only values.
			if ( ! empty( $allowed[ $key ]['is_pro'] ) && ! $is_pro ) {
				continue;
			}
			if ( isset( $allowed[ $key ]['type'] ) && 'repeater' === $allowed[ $key ]['type'] ) {
				$sanitized[ $key ] = $this->sanitize_groups( $value );
				continue;
			}
			if ( isset( $allowed[ $key ]['type'] ) && 'spacing' === $allowed[ $key ]['type'] ) {
				$sanitized[ $key ] = $this->sanitize_spacing( $value );
				continue;
			}
			$sanitized[ $key ] = $this->sanitize_setting_value( $value );
		}

		$existing = get_option( $this->setting_id );
		$existing = is_array( $existing ) ? $existing : [];
		$merged   = array_merge( $existing, $sanitized );

		// Reject the save when a visible required field is left empty.
		$errors = $this->get_required_errors( $merged, $allowed );
		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				[
					'message' => implode( ' ', $errors ),
					'errors'  => $errors,
				],
				422
			);
		}

		update_option( $this->setting_id, apply_filters( 'rtwpvs_update_option', $merged ) );

		// License activation is handled explicitly by the "Activate" button
		// (pro `rtwpvs_manage_licensing`), so we no longer hit the EDD API on
		// every auto-save.

		do_action( 'rtwpvs_settings_saved', $merged, $sanitized );

		wp_send_json_success( [ 'message' => esc_html__( 'Settings saved.', 'woo-product-variation-swatches' ) ] );
	}

	/**
	 * Collect "required" validation errors for the given (merged) settings.
	 *
	 * A field is only enforced when it is currently visible — i.e. it has no
	 * `condition`, or its `condition` field/value matches the merged data.
	 *
	 * @param array $values  Merged settings that would be persisted.
	 * @param array $allowed Flat map of saveable field definitions.
	 *
	 * @return array Map of field id => error message.
	 */
	private function get_required_errors( $values, $allowed ) {
		$errors = [];

		foreach ( $allowed as $id => $field ) {
			if ( empty( $field['required'] ) ) {
				continue;
			}

			// Respect conditional visibility: skip hidden required fields.
			if ( ! empty( $field['condition'] ) ) {
				$dep      = $field['condition']['field'];
				$expected = $field['condition']['value'];
				$actual   = isset( $values[ $dep ] ) ? $values[ $dep ] : '';

				if ( is_bool( $expected ) ) {
					$actual_bool = ! in_array( (string) $actual, [ '', '0', 'false' ], true );
					if ( $actual_bool !== $expected ) {
						continue;
					}
				} elseif ( (string) $actual !== (string) $expected ) {
					continue;
				}
			}

			$value = isset( $values[ $id ] ) ? trim( (string) $values[ $id ] ) : '';
			if ( '' === $value ) {
				$errors[ $id ] = sprintf(
					/* translators: %s: field label. */
					esc_html__( '%s is required.', 'woo-product-variation-swatches' ),
					isset( $field['title'] ) ? $field['title'] : $id
				);
			}
		}

		return $errors;
	}

	/**
	 * AJAX handler: clear all swatch attribute-html transient caches.
	 */
	public function ajax_clear_cache() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'woo-product-variation-swatches' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rtwpvs_nonce' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid or expired request. Please reload the page.', 'woo-product-variation-swatches' ) ], 403 );
		}

		global $wpdb;
		$archive_transient_name = '_transient_' . rtwpvs()->get_transient_name( 'archive_%', 'attribute-html' );
		$product_transient_name = '_transient_' . rtwpvs()->get_transient_name( '%', 'attribute-html' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE `option_name` LIKE (%s) OR `option_name` LIKE (%s)", $archive_transient_name, $product_transient_name ) );

		do_action( 'rtwpvs_clear_all_transient' );

		wp_send_json_success( [ 'message' => esc_html__( 'Cache cleared.', 'woo-product-variation-swatches' ) ] );
	}

	/**
	 * Recursively sanitize a setting value coming from the React app.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private function sanitize_setting_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ $this, 'sanitize_setting_value' ], $value );
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitize the attribute-groups repeater value.
	 *
	 * Each row is normalized to `[ 'id' => <stable id>, 'name' => <text> ]`. The
	 * CSS slug is derived from the name on read (see Options::get_groups()), so
	 * only the id and name are persisted. Rows without a name are dropped and
	 * duplicate ids are removed (first occurrence wins).
	 *
	 * @param mixed $value Raw repeater rows from the React app.
	 *
	 * @return array
	 */
	/**
	 * Sanitize a four-side spacing value to `[ top, right, bottom, left ]` of
	 * numeric px strings (blank sides preserved as '').
	 *
	 * @param mixed $value Raw spacing object from the React app.
	 *
	 * @return array
	 */
	private function sanitize_spacing( $value ) {
		$value = is_array( $value ) ? $value : [];
		$out   = [];
		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$raw          = isset( $value[ $side ] ) ? trim( (string) $value[ $side ] ) : '';
			$out[ $side ] = ( '' !== $raw && is_numeric( $raw ) ) ? (string) absint( $raw ) : '';
		}

		return $out;
	}

	private function sanitize_groups( $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$groups  = [];
		$seen_id = [];
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			// Stable id keeps term assignments intact when the name changes; falls
			// back to a slug of the name for rows saved before ids existed.
			$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
			if ( '' === $id ) {
				$id = sanitize_title( $name );
			}
			if ( '' === $id || isset( $seen_id[ $id ] ) ) {
				continue;
			}
			$seen_id[ $id ] = true;
			$groups[]       = [
				'id'   => $id,
				'name' => $name,
			];
		}

		return $groups;
	}

	private function do_settings_fields( $fields ) {
		foreach ( (array) $fields as $field ) {
			$custom_attributes = $this->array2html_attr( isset( $field['attributes'] ) ? $field['attributes'] : [] );
			$wrapper_id        = ! empty( $field['id'] ) ? esc_attr( $field['id'] ) . '-wrapper' : '';
			// $dependency = !empty($field['require']) ? $this->build_dependency($field['require']) : '';
			$dependency = '';
			$html       = '';
			if ( $field['type'] == 'title' ) {
				$html .= sprintf(
					'<div class="rtwpvs-item-title">%s%s</div>',
					isset( $field['title'] ) && $field['title'] ? "<h3>{$field['title']}</h3>" : '',
					$this->get_field_description( $field )
				);
			} elseif ( $field['type'] == 'feature' ) {
				$html .= sprintf(
					'<div class="rtwpvs-item-feature">%s%s%s</div>',
					isset( $field['title'] ) && $field['title'] ? "<h3>{$field['title']}</h3>" : '',
					$this->get_field_description( $field ),
					$this->field_callback( $field )
				);
			} else {
				$pro_label = ( isset( $field['is_pro'] ) && $field['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ? '<span class="rtvs-pro rtvs-tooltip">' . esc_html__( '[Pro]', 'woo-product-variation-swatches' ) . '<span class="rtvs-tooltiptext">' . esc_html__( 'This is premium field', 'woo-product-variation-swatches' ) . '</span></span>' : '';
				$pro_label = apply_filters( 'rtvs_pro_label', $pro_label );

				$html .= sprintf(
					'<div class="rtwpvs-field-label">%s %s</div>',
					isset( $field['label_for'] ) && ! empty( $field['label_for'] ) ?
						sprintf( '<label for="%s">%s</label>', esc_attr( $field['label_for'] ), $field['title'] ) :
						$field['title'],
					wp_kses(
						$pro_label,
						[
							'div'  => [ 'class' => [] ],
							'span' => [ 'class' => [] ],
						]
					)
				);

				$pro_class       = ( isset( $field['is_pro'] ) && $field['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ? 'pro-field' : '';
				$pro_overlay_div = ( isset( $field['is_pro'] ) && $field['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ? '<div class="pro-field-overlay"></div>' : '';
				$html           .= sprintf( '<div class="rtwpvs-field %s">%s %s</div>', $pro_class, $pro_overlay_div, $this->field_callback( $field ) );
			}
			echo sprintf( '<div id="%s" class="rtwpvs-setting-field" %s %s>%s</div>', $wrapper_id, $custom_attributes, $dependency, $html );
		}
	}

	private function last_tab_input() {
		printf( '<input type="hidden" id="_last_active_tab" name="%s[_last_active_tab]" value="%s">', esc_attr( $this->setting_id ), esc_attr( $this->get_last_active_tab() ) );
	}

	public function field_callback( $field ) {
		// return;
		switch ( $field['type'] ) {
			case 'radio':
				$field_html = $this->radio_field_callback( $field );
				break;

			case 'checkbox':
				$field_html = $this->checkbox_field_callback( $field );
				break;

			case 'select':
				$field_html = $this->select_field_callback( $field );
				break;

			case 'number':
				$field_html = $this->number_field_callback( $field );
				break;

			case 'color':
				$field_html = $this->color_field_callback( $field );
				break;

			case 'post_select':
				$field_html = $this->post_select_field_callback( $field );
				break;

			case 'feature':
				$field_html = $this->feature_field_callback( $field );
				break;

			case 'title':
				// $field_html = $this->title_field_callback($field);
				$field_html = '';
				break;

			default:
				$field_html = $this->text_field_callback( $field );
				break;
		}
		ob_start();
		echo $field_html;
		do_action( 'rtwpvs_settings_field_callback', $field );

		return ob_get_clean();
	}

	public function checkbox_field_callback( $args ) {

		$value = (bool) $this->get_option( $args['id'] );

		$attrs = isset( $args['attrs'] ) ? $this->make_implode_html_attributes( $args['attrs'] ) : '';

		if ( ( isset( $args['is_pro'] ) && $args['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ) {
			$attrs .= 'readonly';
		}

		return sprintf( '<fieldset><label><input %1$s type="checkbox" id="%2$s-field" name="%4$s[%2$s]" value="%3$s" %5$s/> %6$s</label></fieldset>', $attrs, $args['id'], true, $this->setting_id, checked( $value, true, false ), wp_kses_post( $args['desc'] ) );
	}

	public function radio_field_callback( $args ) {
		$options = apply_filters( "rtwpvs_settings_{$args[ 'id' ]}_radio_options", $args['options'] );
		$value   = esc_attr( $this->get_option( $args['id'] ) );

		// Fall back to the field default when the stored value is empty or not a valid option.
		if ( ! array_key_exists( $value, $options ) ) {
			$value = isset( $args['default'] ) ? $args['default'] : '';
		}

		$attrs = isset( $args['attrs'] ) ? $this->make_implode_html_attributes( $args['attrs'] ) : '';

		if ( ( isset( $args['is_pro'] ) && $args['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ) {
			$attrs .= 'readonly';
		}

		$html  = '<fieldset>';
		$html .= implode(
			'<br />',
			array_map(
				function ( $key, $option ) use ( $attrs, $args, $value ) {
					return sprintf( '<label class="rtwpvs-radio-label"><input %1$s type="radio" id="%2$s-field" name="%4$s[%2$s]" value="%3$s" %5$s/> %6$s</label>', $attrs, $args['id'], $key, $this->setting_id, checked( $value, $key, false ), $option );
				},
				array_keys( $options ),
				$options
			)
		);
		$html .= $this->get_field_description( $args );
		$html .= '</fieldset>';

		return $html;
	}

	public function select_field_callback( $args ) {
		$options = apply_filters( "rtwpvs_settings_{$args[ 'id' ]}_select_options", $args['options'] );
		$value   = esc_attr( $this->get_option( $args['id'] ) );
		$options = array_map(
			function ( $key, $option ) use ( $value ) {
				return "<option value='{$key}'" . selected( $key, $value, false ) . ">{$option}</option>";
			},
			array_keys( $options ),
			$options
		);
		$size    = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$attrs = isset( $args['attrs'] ) ? $this->make_implode_html_attributes( $args['attrs'] ) : '';

		if ( ( isset( $args['is_pro'] ) && $args['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ) {
			$attrs .= 'disabled';
		}

		$html  = sprintf( '<select %5$s class="%1$s-text" id="%2$s-field" name="%4$s[%2$s]">%3$s</select>', $size, $args['id'], implode( '', $options ), $this->setting_id, $attrs );
		$html .= $this->get_field_description( $args );

		return $html;
	}

	public function get_field_description( $args ) {
		if ( isset( $args['desc'] ) && ! empty( $args['desc'] ) ) {
			$desc = sprintf(
				'<p class="description">%s%s</p>',
				$args['id'] == 'license_key' ? sprintf(
					'<span class="license-status">%s</span>',
					trim( $this->get_option( $args['id'] ) ) ? sprintf(
						'<span class="rt-licensing-btn button-secondary %s">%s</span>',
						$this->get_option( 'license_status' ) == 'valid' ? 'danger license_deactivate' : 'button-primary license_activate',
						$this->get_option( 'license_status' ) == 'valid' ? esc_html__( 'Deactivate License', 'woo-product-variation-swatches' ) : esc_html__( 'Activate License', 'woo-product-variation-swatches' )
					) : null
				) : null,
				$args['desc']
			);
		} else {
			$desc = '';
		}

		return $desc;
	}

	public function post_select_field_callback( $args ) {

		$options = apply_filters( "rtwpvs_settings_{$args[ 'id' ]}_post_select_options", $args['options'] );

		$value = esc_attr( $this->get_option( $args['id'] ) );

		$options = array_map(
			function ( $option ) use ( $value ) {
				return "<option value='{$option->ID}'" . selected( $option->ID, $value, false ) . ">$option->post_title</option>";
			},
			$options
		);

		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
		$html  = sprintf( '<select class="%1$s-text" id="%2$s-field" name="%4$s[%2$s]">%3$s</select>', $size, $args['id'], implode( '', $options ), $this->setting_id );
		$html .= $this->get_field_description( $args );

		return $html;
	}

	public function text_field_callback( $args ) {
		$value = esc_attr( $this->get_option( $args['id'] ) );
		$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

		$attrs = isset( $args['attrs'] ) ? $this->make_implode_html_attributes( $args['attrs'] ) : '';

		if ( ( isset( $args['is_pro'] ) && $args['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ) {
			$attrs .= 'readonly';
		}

		$html  = sprintf( '<input %5$s type="text" class="%1$s-text" id="%2$s-field" name="%4$s[%2$s]" value="%3$s"/>', $size, $args['id'], $value, $this->setting_id, $attrs );
		$html .= $this->get_field_description( $args );

		return $html;
	}

	public function feature_field_callback( $args ) {

		$is_html = isset( $args['html'] );

		if ( $is_html ) {
			$html = $args['html'];
		} else {
			$image = esc_url( $args['screen_shot'] );
			$link  = esc_url( $args['product_link'] );

			$width = isset( $args['width'] ) ? $args['width'] : '70%';

			$html  = sprintf( '<a target="_blank" href="%s"><img style="width: %s" src="%s" /></a>', $link, $width, $image );
			$html .= $this->get_field_description( $args );
		}

		return $html;
	}

	public function color_field_callback( $args ) {
		$value = esc_attr( $this->get_option( $args['id'] ) );
		$alpha = isset( $args['alpha'] ) && $args['alpha'] === true ? ' data-alpha="true"' : '';
		$html  = sprintf( '<input type="text" %1$s class="rtwpvs-color-picker" id="%2$s-field" name="%4$s[%2$s]" value="%3$s"  data-default-color="%3$s" />', $alpha, $args['id'], $value, $this->setting_id );
		$html .= $this->get_field_description( $args );

		return $html;
	}

	public function number_field_callback( $args ) {
		$value  = esc_attr( $this->get_option( $args['id'] ) );
		$size   = isset( $args['size'] ) && ! is_null( $args['size'] ) ? esc_attr( $args['size'] ) : 'small';
		$min    = isset( $args['min'] ) && ! is_null( $args['min'] ) ? 'min="' . $args['min'] . '"' : '';
		$max    = isset( $args['max'] ) && ! is_null( $args['max'] ) ? 'max="' . $args['max'] . '"' : '';
		$step   = isset( $args['step'] ) && ! is_null( $args['step'] ) ? 'step="' . $args['step'] . '"' : '';
		$suffix = isset( $args['suffix'] ) && ! is_null( $args['suffix'] ) ? ' <span>' . $args['suffix'] . '</span>' : '';
		$attrs  = isset( $args['attrs'] ) ? $this->make_implode_html_attributes( $args['attrs'] ) : '';

		if ( ( isset( $args['is_pro'] ) && $args['is_pro'] ) && ! function_exists( 'rtwpvsp' ) ) {
			$attrs .= 'readonly';
		}

		$html  = sprintf( '<input %9$s type="number" class="%1$s-text" id="%2$s-field" name="%4$s[%2$s]" value="%3$s" %5$s %6$s %7$s /> %8$s', $size, $args['id'], $value, $this->setting_id, $min, $max, $step, $suffix, $attrs );
		$html .= $this->get_field_description( $args );

		return $html;
	}


	/**
	 * @param      $option
	 * @param null $givenDefault
	 *
	 * @return mixed|void
	 */
	public function get_option( $option, $givenDefault = null ) {
		$default = $this->get_default( $option );
		$options = get_option( $this->setting_id );
		$is_new  = ( ! is_array( $options ) && is_bool( $options ) );
		if ( $is_new || ( ! isset( $options[ $option ] ) && ( isset( $default['type'] ) && $default['type'] !== 'checkbox' ) ) ) {
			$value = isset( $default['value'] ) ? $default['value'] : '';
		} else {
			$value = isset( $options[ $option ] ) ? $options[ $option ] : '';
			if ( $givenDefault && ! $value ) {
				$value = $givenDefault;
			}
		}

		return apply_filters( 'rtwpvs_get_option', $value, $default, $option, $options, $is_new );
	}

	private function get_options_tab_css_classes( $tabs ) {
		$classes   = [];
		$classes[] = ( $this->get_last_active_tab() == $tabs['id'] ) ? 'nav-tab-active' : '';

		return implode( ' ', array_unique( apply_filters( 'rtwpvs_get_options_tab_css_classes', $classes ) ) );
	}

	private function get_last_active_tab() {
		$last_tab = trim( $this->get_option( '_last_active_tab' ) );
		if ( isset( $_GET['tab'] ) && ! empty( $_GET['tab'] ) && $this->setting_id == $_GET['tab'] && isset( $_GET['section'] ) && ! empty( $_GET['section'] ) ) {
			$last_tab = trim( $_GET['section'] );
		}
		$default_tab = 'general';
		foreach ( $this->sections as $tabs ) {
			if ( isset( $tabs['active'] ) && $tabs['active'] ) {
				$default_tab = $tabs['id'];
				break;
			}
		}

		return ! empty( $last_tab ) ? $last_tab : $default_tab;
	}

	public function update_option( $key, $value ) {
		$options         = get_option( $this->setting_id );
		$options[ $key ] = $value;
		update_option( $this->setting_id, apply_filters( 'rtwpvs_update_option', $options ) );
	}

	public function sanitize_callback( $options ) {
		foreach ( $this->get_defaults() as $opt ) {
			if ( $opt['type'] === 'checkbox' && ! isset( $options[ $opt['id'] ] ) ) {
				$options[ $opt['id'] ] = 0;
			}
		}

		return $options;
	}

	public function make_implode_html_attributes(
		$raw_attributes,
		$except = [
			'type',
			'id',
			'name',
			'value',
		]
	) {
		$attributes = [];
		foreach ( $raw_attributes as $name => $value ) {
			if ( in_array( $name, $except ) ) {
				continue;
			}
			$attributes[] = esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return implode( ' ', $attributes );
	}

	public function array2html_attr( $attributes, $do_not_add = [] ) {

		$attributes = wp_parse_args( $attributes, [] );
		if ( ! empty( $do_not_add ) and is_array( $do_not_add ) ) {
			foreach ( $do_not_add as $att_name ) {
				unset( $attributes[ $att_name ] );
			}
		}
		$attributes_array = [];
		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $attributes[ $key ] ) and $attributes[ $key ] === true ) {
				return $attributes[ $key ] ? $key : '';
			} elseif ( is_bool( $attributes[ $key ] ) and $attributes[ $key ] === false ) {
				$attributes_array[] = '';
			} else {
				$attributes_array[] = $key . '="' . $value . '"';
			}
		}

		return implode( ' ', $attributes_array );
	}

	function pro_alert_html() {
		if ( function_exists( 'rtwpvsp' ) ) {
			return;
		}
		$html  = '';
		$html .= '<div class="rtvs-document-box rtvs-alert rtvs-pro-alert">
                <div class="rtvs-box-icon"><i class="dashicons dashicons-lock"></i></div>
                <div class="rtvs-box-content">
                    <h3 class="rtvs-box-title">' . esc_html__( 'Pro field alert!', 'woo-product-variation-swatches' ) . '</h3>
                    <p><span></span>' . esc_html__( 'Sorry! this is a pro field. To use this field, you need to use pro plugin.', 'woo-product-variation-swatches' ) . '</p>
                    <a href="https://www.radiustheme.com/downloads/woocommerce-variation-swatches" target="_blank" class="rt-admin-btn">' . esc_html__( 'Upgrade to pro', 'woo-product-variation-swatches' ) . '</a>
                    <a href="#" target="_blank" class="rtvs-alert-close rtvs-pro-alert-close">x</a>
                </div>
            </div>';
		echo $html;
	}
}
