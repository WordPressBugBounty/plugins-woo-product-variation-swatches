<?php

namespace Rtwpvs\Controllers;

use Rtwpvs\Helpers\Options;

class Install {

	/**
	 * Option holding the signature of the last defaults sync.
	 *
	 * @var string
	 */
	const DEFAULTS_SIGNATURE = 'rtwpvs_defaults_signature';

	/**
	 * Field types that hold no persistable value.
	 *
	 * @var array
	 */
	private static $layout_types = [ 'title', 'feature', 'card' ];


	public static function deactivate( $network_deactivating ) {
		delete_option( 'rtwpvs_pro_activate' );

		global $wpdb;

		$blog_ids         = [ 1 ];
		$original_blog_id = 1;
		$network          = false;
		if ( defined( 'RTSB_VERSION' ) ) {
			return;
		}
		if ( is_multisite() && $network_deactivating ) {
			$blog_ids         = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
			$original_blog_id = get_current_blog_id();
			$network          = true;
		}

		foreach ( $blog_ids as $blog_id ) {
			if ( $network ) {
				switch_to_blog( $blog_id );
			}

			// Backup attribute types
			$attributes        = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : [];
			$default_types     = [ 'text', 'select' ];
			$rtwpvs_attributes = [];

			if ( ! empty( $attributes ) ) {
				foreach ( $attributes as $attribute ) {
					if ( ! in_array( $attribute->attribute_type, $default_types ) ) {
						$rtwpvs_attributes[ $attribute->attribute_id ] = $attribute;
					}
				}
			}

			// Reset attributes
			if ( ! empty( $rtwpvs_attributes ) ) {
				foreach ( $rtwpvs_attributes as $id => $attribute ) {
					$wpdb->update(
						$wpdb->prefix . 'woocommerce_attribute_taxonomies',
						[ 'attribute_type' => 'select' ],
						[ 'attribute_id' => $id ],
						[ '%s' ],
						[ '%d' ]
					);
				}
				update_option( 'rtwpvs_backup_attribute_types', $rtwpvs_attributes );
			}
		}

		if ( $network ) {
			switch_to_blog( $original_blog_id );
		}

		delete_transient( 'wc_attribute_taxonomies' );
	}


	public static function activate( $network_wide ) {

		if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'rtwpvs_pro_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'rtwpvs_pro_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		self::create_options();

		// Seed defaults immediately when WooCommerce is already loaded; otherwise
		// the `init` sync picks it up on the next request.
		if ( function_exists( 'WC' ) ) {
			self::maybe_migrate_dropdown_setting();
			self::maybe_sync_defaults();
		}

		self::update_rtwpvs_version();
		self::update_attribute_types( $network_wide );
		self::remove_unused_transient();

		delete_transient( 'rtwpvs_pro_installing' );

		do_action( 'rtwpvs_flush_rewrite_rules' );
		do_action( 'rtwpvs_pro_installed' );
	}

	private static function update_rtwpvs_version() {
		delete_option( 'rtwpvs_pro_version' );
		add_option( 'rtwpvs_pro_version', RTWPVS_VERSION );
	}

	private static function create_options() {
		update_option( 'rtwpvs_pro_activate', 'yes' );
	}

	private static function update_attribute_types( $network_wide ) {

		global $wpdb;

		$blog_ids         = [ 1 ];
		$original_blog_id = 1;
		$network          = false;

		if ( is_multisite() && $network_wide ) {
			$blog_ids         = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
			$original_blog_id = get_current_blog_id();
			$network          = true;
		}

		foreach ( $blog_ids as $blog_id ) {
			if ( $network ) {
				switch_to_blog( $blog_id );
			}
			$rtwpvs_attributes = get_option( 'rtwpvs_backup_attribute_types', [] );
			// Reset attributes
			if ( ! empty( $rtwpvs_attributes ) ) {
				foreach ( $rtwpvs_attributes as $id => $attribute ) {
					$wpdb->update(
						$wpdb->prefix . 'woocommerce_attribute_taxonomies',
						[ 'attribute_type' => $attribute->attribute_type ],
						[ 'attribute_id' => $id ],
						[ '%s' ],
						[ '%d' ]
					);
				}
			}
		}

		if ( $network ) {
			switch_to_blog( $original_blog_id );
		}
		delete_transient( 'wc_attribute_taxonomies' );
	}

	/**
	 * Migrate legacy dropdown-conversion checkboxes to the single radio option.
	 *
	 * Legacy keys: `default_to_button` (free), `default_to_image` (pro).
	 * New key    : `default_dropdown_to` with values none|button|image.
	 *
	 * Runs once for existing installs (in-place updates skip the activation
	 * hook); guarded by the `rtwpvs_dropdown_migrated` option flag.
	 *
	 * @return void
	 */
	public static function maybe_migrate_dropdown_setting() {
		if ( 'yes' === get_option( 'rtwpvs_dropdown_migrated' ) ) {
			return;
		}

		$options = get_option( 'rtwpvs' );

		if ( is_array( $options )
			&& ! isset( $options['default_dropdown_to'] )
			&& ( array_key_exists( 'default_to_button', $options ) || array_key_exists( 'default_to_image', $options ) )
		) {
			if ( ! empty( $options['default_to_image'] ) ) {
				$options['default_dropdown_to'] = 'image';
			} elseif ( ! empty( $options['default_to_button'] ) ) {
				$options['default_dropdown_to'] = 'button';
			} else {
				$options['default_dropdown_to'] = 'none';
			}

			unset( $options['default_to_button'], $options['default_to_image'] );
			update_option( 'rtwpvs', $options );
		}

		update_option( 'rtwpvs_dropdown_migrated', 'yes' );
	}

	/**
	 * Write every missing default into the `rtwpvs` option, once per signature.
	 *
	 * The settings UI and the frontend both read through
	 * `SettingsAPI::get_option()`, which can only fall back to a default when it
	 * knows the field — checkbox fields absent from the stored array are treated
	 * as "unchecked", so a newly shipped toggle whose default is `true` would
	 * render off after an update. Persisting the defaults removes that ambiguity:
	 * after a sync every known key exists in the DB with its intended value.
	 *
	 * Runs on fresh installs, after a plugin update (in-place updates never fire
	 * the activation hook) and when Pro is activated/deactivated — each of those
	 * changes the signature. Existing values are never overwritten.
	 *
	 * @return void
	 */
	public static function maybe_sync_defaults() {
		$signature = RTWPVS_VERSION . '|' . ( function_exists( 'rtwpvsp' ) ? 'pro' : 'free' );

		if ( get_option( self::DEFAULTS_SIGNATURE ) === $signature ) {
			return;
		}

		self::seed_default_options();

		update_option( self::DEFAULTS_SIGNATURE, $signature );
	}

	/**
	 * Add any default that is not yet present to the stored settings array.
	 *
	 * Values are normalized the same way `SettingsAPI::ajax_save_settings()`
	 * stores them (scalars cast to string, so a `true` checkbox default becomes
	 * `'1'`), keeping seeded and user-saved values in one format.
	 *
	 * @return void
	 */
	private static function seed_default_options() {
		$is_pro   = function_exists( 'rtwpvsp' );
		$options  = get_option( 'rtwpvs' );
		$options  = is_array( $options ) ? $options : [];
		$original = $options;

		foreach ( Options::get_settings_sections() as $section ) {
			if ( empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
				continue;
			}

			foreach ( $section['fields'] as $field ) {
				if ( empty( $field['id'] ) || ! array_key_exists( 'default', $field ) ) {
					continue;
				}
				if ( isset( $field['type'] ) && in_array( $field['type'], self::$layout_types, true ) ) {
					continue;
				}
				// Pro-only defaults are seeded once Pro is active, not before.
				if ( ! empty( $field['is_pro'] ) && ! $is_pro ) {
					continue;
				}
				if ( array_key_exists( $field['id'], $options ) ) {
					continue;
				}

				$options[ $field['id'] ] = is_array( $field['default'] ) ? $field['default'] : (string) $field['default'];
			}
		}

		if ( $options !== $original ) {
			update_option( 'rtwpvs', apply_filters( 'rtwpvs_update_option', $options ) );
		}
	}

	private static function remove_unused_transient() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE `option_name` LIKE ('_transient_rtwpvs_get_wc_attribute_taxonomy_%')" );
	}
}
