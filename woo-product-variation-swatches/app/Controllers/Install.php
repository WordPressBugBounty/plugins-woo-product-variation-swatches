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

	/**
	 * Settings that must never be auto-seeded into an existing installation.
	 *
	 * Seeding materializes a default into the stored options, which turns a
	 * schema default into live behaviour for sites that never chose it. That is
	 * harmless for presentational values but not for settings that change the
	 * rendered markup, so those are seeded on fresh installs only. On an existing
	 * install the key simply stays absent and resolves to the schema default.
	 *
	 * Values already stored are never touched here or anywhere else: a saved `1`
	 * may be a deliberate choice and is indistinguishable from one the 2.5.0
	 * defaults sync wrote, so it is always preserved.
	 *
	 * @var array
	 */
	private static $never_seed_on_existing = [ 'remove_variations_table' ];

	/**
	 * Every checkbox field id that existed in 2.4.0 (frozen; never derive at runtime).
	 *
	 * 2.4.0 and older saved settings through WooCommerce's settings form, where an
	 * unchecked checkbox is omitted from the POST and therefore stored as a missing
	 * key, which `SettingsAPI::get_option()` reads as "off". The 2.5.x defaults
	 * sync treats a missing key as "never set" and seeds its default, which turned
	 * those saved choices back on. Fields introduced in 2.5+ are deliberately not
	 * listed: for them a missing key really is unset and must receive its default.
	 *
	 * @var string[]
	 */
	const LEGACY_CHECKBOXES = [
		'tooltip',
		'shape_style_checkmark',
		'attribute_on_click_behavior',
		'archive_swatches',
		'enable_archive_variation_url',
		'enable_ajax_archive_variation',
		'archive_swatches_label',
		'archive_swatches_tooltip',
		'show_clear_on_archive',
		'archive_swatches_enable_single_attribute',
		'clear_on_reselect',
		'disable_out_of_stock',
		'enable_variation_url',
		'remove_all_data',
		'load_scripts',
		'defer_load_js',
		'use_cache',
	];

	/**
	 * Per-site one-time marker of the legacy checkbox migration (completed or skipped).
	 *
	 * @var string
	 */
	const LEGACY_MIGRATION_MARKER = 'rtwpvs_legacy_checkbox_migration';

	/**
	 * Non-autoloaded copy of the settings array as it was before the migration.
	 *
	 * @var string
	 */
	const LEGACY_SETTINGS_BACKUP = 'rtwpvs_settings_pre25_backup';

	/**
	 * Key written by every 2.5.x defaults sync and never by 2.4.0 or Pro.
	 *
	 * @var string
	 */
	const SYNCED_FINGERPRINT_KEY = 'clear_link_font_size';

	/**
	 * Option row used as a database-level lock around the defaults sync.
	 *
	 * @var string
	 */
	const SYNC_LOCK = 'rtwpvs_sync_lock';

	/**
	 * Seconds after which a lock left by a crashed request is considered stale.
	 *
	 * @var int
	 */
	const SYNC_LOCK_TIMEOUT = 60;

	/**
	 * Value of the lock row owned by the current request, if any.
	 *
	 * @var string
	 */
	private static $lock_token = '';


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
	 * The work runs under a database lock with freshly read options, and the
	 * one-time legacy checkbox migration always runs before seeding. When that
	 * migration fails, nothing is seeded and the signature is left untouched so
	 * a later request retries.
	 *
	 * Every write re-checks lock ownership, and settings are only replaced when
	 * the stored row still matches the snapshot they were computed from (see
	 * compare_and_swap_settings()). A request that stalled past the lock timeout,
	 * or raced a concurrent settings save, therefore aborts instead of writing.
	 *
	 * @return void
	 */
	public static function maybe_sync_defaults() {
		$signature = self::get_defaults_signature( rtwpvs()->pro_mode(), rtwpvs()->pro_api_version() );

		if ( get_option( self::DEFAULTS_SIGNATURE ) === $signature ) {
			return;
		}

		if ( ! self::acquire_sync_lock() ) {
			return;
		}

		try {
			// Another request may have finished the sync after this one loaded its
			// option cache: re-read from the database before deciding anything.
			self::refresh_option_cache();

			if ( get_option( self::DEFAULTS_SIGNATURE ) === $signature ) {
				return;
			}

			if ( ! self::maybe_migrate_legacy_checkboxes() ) {
				return;
			}

			if ( ! self::seed_default_options() ) {
				return;
			}

			// Markup/CSS changes between versions: drop swatch HTML and inline CSS
			// cached by the previous version so it is regenerated with the new classes.
			Hooks::clear_all_attribute_html_transients();
			do_action( 'rtwpvs_clear_all_transient' );

			if ( self::owns_sync_lock() ) {
				update_option( self::DEFAULTS_SIGNATURE, $signature );
			}
		} finally {
			self::release_sync_lock();
		}
	}

	/**
	 * One-time migration of checkbox choices saved by 2.4.0 and older.
	 *
	 * Eligible: a non-empty `rtwpvs` array, no defaults signature and no
	 * `clear_link_font_size` key, i.e. settings that no 2.5.x sync has touched.
	 * For those, every LEGACY_CHECKBOXES id missing from the array is stored as
	 * `'0'`, which is exactly how 2.4.0 ran it. Existing values are never changed.
	 *
	 * Skipped (and marked, so never re-evaluated): fresh installs (no or empty
	 * array) and 2.5.x installs (signature or fingerprint present).
	 *
	 * Detection limits: the signature only exists once a 2.5.x sync has run, and
	 * the fingerprint only once a 2.5.x sync or save wrote it. A 2.5.x site that
	 * never synced (WooCommerce inactive the whole time) still holds its 2.4.0
	 * array and is migrated, which is correct. A site downgraded from 2.5.x to
	 * 2.4.0 keeps its signature and is not migrated. 2.3.x and 2.4.0 store
	 * checkboxes identically, so both are treated as pre-2.5. Sites already
	 * synced by 2.5.0–2.5.2 cannot be recovered: a seeded `'1'` is identical to a
	 * deliberate one.
	 *
	 * @return bool False when an eligible migration could not be stored (retry later).
	 */
	private static function maybe_migrate_legacy_checkboxes() {
		if ( false !== get_option( self::LEGACY_MIGRATION_MARKER ) ) {
			return true;
		}

		$raw     = self::read_settings_raw();
		$options = null === $raw ? false : maybe_unserialize( $raw );

		if ( ! is_array( $options ) || empty( $options ) ) {
			return self::record_legacy_migration( 'skipped', 'fresh' );
		}

		if ( false !== get_option( self::DEFAULTS_SIGNATURE ) || array_key_exists( self::SYNCED_FINGERPRINT_KEY, $options ) ) {
			return self::record_legacy_migration( 'skipped', 'synced' );
		}

		// First original copy wins (INSERT IGNORE): a retry or a stale request never replaces it.
		if ( ! self::store_legacy_backup( $raw ) ) {
			return false;
		}

		$migrated = [];
		foreach ( self::LEGACY_CHECKBOXES as $id ) {
			if ( ! array_key_exists( $id, $options ) ) {
				$options[ $id ] = '0';
				$migrated[]     = $id;
			}
		}

		if ( $migrated && ! self::compare_and_swap_settings( $raw, apply_filters( 'rtwpvs_update_option', $options ) ) ) {
			return false;
		}

		return self::record_legacy_migration( 'migrated', 'pre-2.5', $migrated );
	}

	/**
	 * Store the one-time legacy migration marker and confirm it reached the database.
	 *
	 * @param string   $status 'migrated' or 'skipped'.
	 * @param string   $reason 'pre-2.5', 'fresh' or 'synced'.
	 * @param string[] $keys   Checkbox ids set to '0'.
	 *
	 * @return bool
	 */
	private static function record_legacy_migration( $status, $reason, array $keys = [] ) {
		$marker = [
			'status'  => $status,
			'reason'  => $reason,
			'keys'    => $keys,
			'version' => RTWPVS_VERSION,
			'time'    => time(),
		];

		if ( ! self::owns_sync_lock() ) {
			return false;
		}

		update_option( self::LEGACY_MIGRATION_MARKER, $marker, false );
		self::refresh_option_cache();

		return get_option( self::LEGACY_MIGRATION_MARKER ) === $marker;
	}

	/**
	 * Take the per-site sync lock; recover it when its holder is older than the timeout.
	 *
	 * `add_option()` cannot be used: it runs `INSERT … ON DUPLICATE KEY UPDATE`
	 * and trusts the in-request cache, so two requests could both "win". An
	 * `INSERT IGNORE` affects exactly one row only for the request that created
	 * it (same approach as core's `WP_Upgrader::create_lock()`).
	 *
	 * @return bool
	 */
	private static function acquire_sync_lock() {
		global $wpdb;

		$now   = time();
		$token = $now . ':' . wp_generate_password( 12, false );

		$created = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
				self::SYNC_LOCK,
				$token
			)
		);

		if ( 1 === (int) $created ) {
			self::$lock_token = $token;
			return true;
		}

		$held = $wpdb->get_var( $wpdb->prepare( "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s", self::SYNC_LOCK ) );

		if ( null === $held || (int) $held > $now - self::SYNC_LOCK_TIMEOUT ) {
			return false;
		}

		// Stale: take it over only if nobody else replaced it in the meantime.
		$taken = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s",
				$token,
				self::SYNC_LOCK,
				$held
			)
		);

		if ( 1 !== (int) $taken ) {
			return false;
		}

		self::$lock_token = $token;

		return true;
	}

	/**
	 * Release the sync lock if this request still owns it.
	 *
	 * @return void
	 */
	private static function release_sync_lock() {
		global $wpdb;

		if ( '' === self::$lock_token ) {
			return;
		}

		$wpdb->delete(
			$wpdb->options,
			[
				'option_name'  => self::SYNC_LOCK,
				'option_value' => self::$lock_token,
			]
		);
		self::$lock_token = '';
	}

	/**
	 * Whether the lock row still holds this request's token (read from the database).
	 *
	 * A request that stalled past SYNC_LOCK_TIMEOUT may have been taken over;
	 * it must then stop writing. This check alone is not atomic with the write
	 * that follows it, which is why settings also go through
	 * compare_and_swap_settings().
	 *
	 * @return bool
	 */
	private static function owns_sync_lock() {
		global $wpdb;

		if ( '' === self::$lock_token ) {
			return false;
		}

		$held = $wpdb->get_var( $wpdb->prepare( "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s", self::SYNC_LOCK ) );

		return self::$lock_token === $held;
	}

	/**
	 * Stored `rtwpvs` row exactly as saved in the database, or null when absent.
	 *
	 * @return string|null
	 */
	private static function read_settings_raw() {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s", 'rtwpvs' ) );
	}

	/**
	 * Store the pre-migration settings once; an existing backup is never replaced.
	 *
	 * @param string $raw Serialized settings row as read from the database.
	 *
	 * @return bool True when a backup exists afterwards.
	 */
	private static function store_legacy_backup( $raw ) {
		global $wpdb;

		if ( ! self::owns_sync_lock() ) {
			return false;
		}

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
				self::LEGACY_SETTINGS_BACKUP,
				$raw
			)
		);
		self::refresh_option_cache();

		return false !== get_option( self::LEGACY_SETTINGS_BACKUP );
	}

	/**
	 * Replace the `rtwpvs` row only if it still equals the snapshot the new value was built from.
	 *
	 * The condition is evaluated by the database in the same statement as the
	 * write (`MD5()` keeps the comparison byte-exact; the column collation is
	 * case-insensitive), so a settings save that lands between our read and our
	 * write — from a user, or from another sync request — makes this write fail
	 * instead of being overwritten. When the row was absent it is only created if
	 * still absent (INSERT IGNORE). The option filters and actions that
	 * update_option()/add_option() would run are applied, so cache-clearing
	 * listeners keep working.
	 *
	 * @param string|null $expected_raw Row read before computing $value, null when absent.
	 * @param mixed       $value        New settings value.
	 *
	 * @return bool True when $value is stored (or already equal to the stored value).
	 */
	private static function compare_and_swap_settings( $expected_raw, $value ) {
		global $wpdb;

		if ( ! self::owns_sync_lock() ) {
			return false;
		}

		$old_value = null === $expected_raw ? false : maybe_unserialize( $expected_raw );
		$value     = sanitize_option( 'rtwpvs', $value );

		if ( null !== $expected_raw ) {
			$value = apply_filters( 'pre_update_option_rtwpvs', $value, $old_value, 'rtwpvs' );
			$value = apply_filters( 'pre_update_option', $value, 'rtwpvs', $old_value );
		}

		$serialized = maybe_serialize( $value );
		if ( null !== $expected_raw && $serialized === $expected_raw ) {
			return true;
		}

		if ( null === $expected_raw ) {
			do_action( 'add_option', 'rtwpvs', $value );
			$written = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'yes')",
					'rtwpvs',
					$serialized
				)
			);
		} else {
			do_action( 'update_option', 'rtwpvs', $old_value, $value );
			$written = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND MD5( `option_value` ) = %s",
					$serialized,
					'rtwpvs',
					md5( $expected_raw )
				)
			);
		}

		self::refresh_option_cache();

		if ( 1 !== (int) $written ) {
			return false;
		}

		if ( null === $expected_raw ) {
			do_action( 'add_option_rtwpvs', 'rtwpvs', $value );
			do_action( 'added_option', 'rtwpvs', $value );
		} else {
			do_action( 'update_option_rtwpvs', $old_value, $value, 'rtwpvs' );
			do_action( 'updated_option', 'rtwpvs', $old_value, $value );
		}

		return true;
	}

	/**
	 * Drop cached copies of the options the sync reads, so the next get_option() hits the database.
	 *
	 * @return void
	 */
	private static function refresh_option_cache() {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		foreach ( [ 'rtwpvs', self::DEFAULTS_SIGNATURE, self::LEGACY_MIGRATION_MARKER, self::LEGACY_SETTINGS_BACKUP ] as $option ) {
			wp_cache_delete( $option, 'options' );
		}
	}

	/**
	 * Build the defaults signature: `RTWPVS_VERSION|pro_mode|RTWPVSP_API`.
	 *
	 * Any change (Free update, Pro installed/removed/upgraded/downgraded, Pro
	 * API change) re-runs the non-destructive default sync and clears cached
	 * swatch markup.
	 *
	 * @param string $pro_mode    'none', 'legacy', 'api' or 'incompatible'.
	 * @param int    $pro_api     Pro API version (0 unless in 'api' mode).
	 *
	 * @return string
	 */
	public static function get_defaults_signature( $pro_mode, $pro_api ) {
		return implode( '|', [ RTWPVS_VERSION, (string) $pro_mode, (string) absint( $pro_api ) ] );
	}

	/**
	 * Add any default that is not yet present to the stored settings array.
	 *
	 * Values are normalized the same way `SettingsAPI::ajax_save_settings()`
	 * stores them (scalars cast to string, so a `true` checkbox default becomes
	 * `'1'`), keeping seeded and user-saved values in one format.
	 *
	 * Built from the stored row and written with compare_and_swap_settings(), so
	 * a concurrent settings save is never overwritten by stale defaults.
	 *
	 * @return bool False when the write lost a race (the sync is retried later).
	 */
	private static function seed_default_options() {
		$is_pro   = function_exists( 'rtwpvsp' );
		$raw      = self::read_settings_raw();
		$options  = null === $raw ? false : maybe_unserialize( $raw );
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
				// Markup-changing settings are seeded on fresh installs only, so a
				// default can never switch the feature on for an existing shop.
				if ( ! empty( $original ) && in_array( $field['id'], self::$never_seed_on_existing, true ) ) {
					continue;
				}

				$options[ $field['id'] ] = is_array( $field['default'] ) ? $field['default'] : (string) $field['default'];
			}
		}

		if ( $options === $original ) {
			return true;
		}

		return self::compare_and_swap_settings( $raw, apply_filters( 'rtwpvs_update_option', $options ) );
	}

	private static function remove_unused_transient() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE `option_name` LIKE ('_transient_rtwpvs_get_wc_attribute_taxonomy_%')" );
	}
}
