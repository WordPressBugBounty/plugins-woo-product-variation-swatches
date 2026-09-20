<?php
/**
 * Deactivation feedback modal.
 *
 * Renders a "Quick Feedback" dialog on the WordPress plugins screen when the
 * user clicks the plugin's Deactivate link, collects an optional reason and
 * message, and forwards it to the RadiusTheme plugin survey endpoint. The
 * survey request is strictly best-effort: deactivation always proceeds.
 *
 * @package Rtwpvs
 */

namespace Rtwpvs\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DeactivationFeedback
 */
class DeactivationFeedback {

	/**
	 * Base id/class prefix used for every element this class prints.
	 *
	 * Every selector is scoped with this prefix so the dialog cannot clash
	 * with other RadiusTheme plugins that print a similar modal.
	 *
	 * @var string
	 */
	const PREFIX = 'rtwpvs-deactivation';

	/**
	 * Plugin identifier expected by the survey endpoint.
	 *
	 * @var string
	 */
	const PLUGIN_KEY = 'VariationSwatch';

	/**
	 * Survey endpoint receiving the feedback.
	 *
	 * @var string
	 */
	const SURVEY_ENDPOINT = 'https://shopbuilderwp.com/wp-json/RadiusTheme/pluginSurvey/v1/Survey/appendToSheet';

	/**
	 * Support ticket URL shown inside the dialog.
	 *
	 * @var string
	 */
	const SUPPORT_URL = 'https://www.radiustheme.com/contact/';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_footer', [ __CLASS__, 'render' ], 99 );
	}

	/**
	 * Deactivation reasons offered in the dialog.
	 *
	 * @return array List of reason key => label pairs.
	 */
	public static function get_reasons() {
		return [
			'bug_issue_detected'             => esc_html__( 'Bug or issue detected.', 'woo-product-variation-swatches' ),
			'no_longer_needed'               => esc_html__( 'I no longer need the plugin', 'woo-product-variation-swatches' ),
			'found_a_better_plugin'          => esc_html__( 'I found a better plugin', 'woo-product-variation-swatches' ),
			'couldnt_get_the_plugin_to_work' => esc_html__( "I couldn't get the plugin to work", 'woo-product-variation-swatches' ),
			'temporary_deactivation'         => esc_html__( "It's a temporary deactivation", 'woo-product-variation-swatches' ),
		];
	}

	/**
	 * Print the dialog markup, styles and scripts on the plugins screen only.
	 *
	 * @return void
	 */
	public static function render() {
		global $pagenow;

		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		self::render_styles();
		self::render_markup();
		self::render_scripts();
	}

	/**
	 * Print the dialog markup.
	 *
	 * @return void
	 */
	public static function render_markup() {
		$prefix = self::PREFIX;
		?>
		<div id="<?php echo esc_attr( $prefix ); ?>-dialog" title="<?php esc_attr_e( 'Quick Feedback', 'woo-product-variation-swatches' ); ?>">
			<div class="<?php echo esc_attr( $prefix ); ?>-content">
				<div id="<?php echo esc_attr( $prefix ); ?>-form-body">
					<p class="<?php echo esc_attr( $prefix ); ?>-intro">
						<?php esc_html_e( 'Having trouble?', 'woo-product-variation-swatches' ); ?><br/>
						<?php
						printf(
							/* translators: %s: Support ticket link. */
							esc_html__( 'For faster and more accurate support, please %s — our support agent will personally review and resolve your issue.', 'woo-product-variation-swatches' ),
							sprintf(
								'<a target="_blank" href="%1$s" class="%2$s-support-link">%3$s</a>',
								esc_url( self::SUPPORT_URL ),
								esc_attr( $prefix ),
								esc_html__( 'open a support ticket', 'woo-product-variation-swatches' )
							)
						);
						?>
					</p>

					<div class="<?php echo esc_attr( $prefix ); ?>-input-header">
						<?php esc_html_e( 'If you’d prefer not to open a support ticket, please take a moment to share why you’re deactivating Variation Swatches for WooCommerce:', 'woo-product-variation-swatches' ); ?>
					</div>

					<?php foreach ( self::get_reasons() as $reason_key => $reason_label ) : ?>
						<div class="<?php echo esc_attr( $prefix ); ?>-input-wrapper<?php echo 'found_a_better_plugin' === $reason_key ? ' conditional' : ''; ?>">
							<input id="<?php echo esc_attr( $prefix . '-' . $reason_key ); ?>"
								   class="<?php echo esc_attr( $prefix ); ?>-input"
								   type="radio"
								   name="rtwpvs_reason_key"
								   value="<?php echo esc_attr( $reason_key ); ?>">
							<label for="<?php echo esc_attr( $prefix . '-' . $reason_key ); ?>" class="<?php echo esc_attr( $prefix ); ?>-label">
								<?php echo esc_html( $reason_label ); ?>
							</label>
							<?php if ( 'found_a_better_plugin' === $reason_key ) : ?>
								<input class="<?php echo esc_attr( $prefix ); ?>-better-plugin"
									   type="text"
									   name="rtwpvs_reason_found_a_better_plugin"
									   placeholder="<?php esc_attr_e( 'Please share the plugin name', 'woo-product-variation-swatches' ); ?>">
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<span class="<?php echo esc_attr( $prefix ); ?>-error"></span>
				</div>

				<div class="<?php echo esc_attr( $prefix ); ?>-extra" style="display:none;">
					<p>
						<?php esc_html_e( 'Please let us know about any issues you are facing with the plugin. How can we improve the plugin?', 'woo-product-variation-swatches' ); ?>
					</p>
					<div class="<?php echo esc_attr( $prefix ); ?>-text-wrapper">
						<textarea id="<?php echo esc_attr( $prefix ); ?>-feedback" rows="2" cols="40"
								  placeholder="<?php esc_attr_e( 'Write something here. How can we improve the plugin?', 'woo-product-variation-swatches' ); ?>"></textarea>
						<span class="<?php echo esc_attr( $prefix ); ?>-error"></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Print the dialog styles.
	 *
	 * Every rule is scoped to the plugin specific dialog id, to the widget
	 * class added on open, or to the body class toggled while the dialog is
	 * open, so no jQuery UI defaults leak into other plugins' dialogs.
	 *
	 * @return void
	 */
	public static function render_styles() {
		$prefix = self::PREFIX;
		?>
		<style id="<?php echo esc_attr( $prefix ); ?>-style">
			#<?php echo esc_attr( $prefix ); ?>-dialog {
				display: none;
				padding: 30px !important;
				text-align: left;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget {
				background-color: #fefefe;
				box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
				z-index: 9999;
				position: fixed !important;
				top: 50% !important;
				left: 50% !important;
				transform: translate(-50%, -50%) !important;
				max-height: 90vh;
				overflow-y: auto;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-titlebar {
				padding: 18px 15px;
				box-shadow: 0 0 3px rgba(0, 0, 0, 0.1);
				text-align: left;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-titlebar-close {
				display: none;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-title {
				text-transform: uppercase;
				font-weight: 700;
				font-size: 16px;
				padding-left: 15px;
				padding-right: 15px;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonpane,
			.<?php echo esc_attr( $prefix ); ?>-widget .ui-widget-content {
				border: 0;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-resizable-handle {
				display: none !important;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonset {
				background-color: #fefefe;
				box-shadow: none;
				z-index: 99;
				padding: 0 30px 25px;
				display: flex;
				justify-content: space-between;
				gap: 10px;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonset .ui-button {
				min-width: 110px;
				text-align: center;
				border: none;
				outline: none;
				padding: 8px 16px;
				border-radius: 5px;
				height: 40px;
				font-size: 12px;
				font-weight: 500;
				line-height: 1.2;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				transition: 0.3s all;
				margin: 0;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonset .ui-button:first-child {
				background: #4360ef;
				color: #fff;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonset .ui-button:first-child:hover {
				background: #1f3edc;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonset .ui-button:last-child {
				background: none;
			}

			.<?php echo esc_attr( $prefix ); ?>-widget .ui-dialog-buttonset .ui-button:last-child:hover {
				background: #d80e0e;
				color: #fff;
			}

			body.<?php echo esc_attr( $prefix ); ?>-open .ui-widget-overlay.ui-front {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				z-index: 999;
				background-color: rgba(0, 0, 0, 0.5);
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-content {
				position: relative;
				margin: auto;
				padding: 0;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-content > * {
				width: 100%;
				overflow: hidden;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog p {
				font-size: 14px;
				color: #4b5563;
				line-height: 1.55;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-intro {
				margin: 0 0 15px 0;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-support-link {
				display: inline-block;
				padding: 2px 8px;
				margin: 0 2px;
				background: rgba(216, 14, 14, 0.08);
				color: #d80e0e;
				font-weight: 600;
				border-radius: 4px;
				text-decoration: none;
				border: 1px solid rgba(216, 14, 14, 0.25);
				transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-support-link:hover,
			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-support-link:focus {
				background: #d80e0e;
				color: #fff;
				border-color: #d80e0e;
				text-decoration: none;
				outline: none;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-input-header {
				font-weight: 600;
				font-size: 14px;
				line-height: 1.5;
				margin-bottom: 16px;
				color: #1f2937;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-input-wrapper {
				margin-bottom: 6px;
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 10px 12px;
				font-size: 14px;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				background: #fff;
				cursor: pointer;
				transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-input-wrapper:hover {
				border-color: #4360ef;
				background: rgba(67, 96, 239, 0.04);
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-input-wrapper:has(input[type="radio"]:checked) {
				border-color: #4360ef;
				background: rgba(67, 96, 239, 0.06);
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-label {
				flex: 1;
				cursor: pointer;
				user-select: none;
				color: #1f2937;
				font-weight: 500;
				line-height: 1.4;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"] {
				appearance: none;
				-webkit-appearance: none;
				width: 18px;
				height: 18px;
				border: 1.5px solid #cbd5e1;
				border-radius: 50%;
				background: #fff;
				cursor: pointer;
				position: relative;
				flex: 0 0 auto;
				box-sizing: border-box;
				outline: none;
				box-shadow: none;
				transition: border-color 0.15s ease;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:hover,
			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:focus,
			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:focus-visible,
			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:active {
				outline: none;
				box-shadow: none;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:hover {
				border-color: #94a3b8;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:checked {
				border-color: #4360ef;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog input[type="radio"]:checked::after {
				content: '';
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				width: 8px;
				height: 8px;
				border-radius: 50%;
				background: #4360ef;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-input-wrapper.conditional {
				flex-wrap: wrap;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-better-plugin {
				flex: 0 0 calc(100% - 28px);
				margin: 8px 0 0 28px;
				min-width: 250px;
				min-height: 38px;
				border: 1px solid #cbd5e1;
				border-radius: 6px;
				padding: 0 12px;
				font-size: 13px;
				transition: border-color 0.15s ease, box-shadow 0.15s ease;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-better-plugin:focus {
				border-color: #4360ef;
				outline: none;
				box-shadow: 0 0 0 3px rgba(67, 96, 239, 0.15);
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-error {
				display: block;
				color: red;
				font-size: 13px;
				margin-top: 5px;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-extra {
				margin: 6px 0 10px;
				padding: 12px 14px;
				background: #f8fafc;
				border: 1px dashed #cbd5e1;
				border-radius: 8px;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog .<?php echo esc_attr( $prefix ); ?>-extra p {
				margin: 0 0 8px !important;
				font-size: 13px !important;
				font-weight: 500;
				color: #4b5563;
				line-height: 1.4;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog textarea {
				border: 1px solid #cbd5e1;
				border-radius: 6px;
				padding: 8px 10px;
				width: 100%;
				min-height: 56px;
				max-height: 120px;
				font-size: 13px;
				line-height: 1.45;
				resize: vertical;
				transition: border-color 0.15s ease, box-shadow 0.15s ease;
			}

			#<?php echo esc_attr( $prefix ); ?>-dialog textarea:focus {
				border-color: #4360ef;
				outline: none;
				box-shadow: 0 0 0 3px rgba(67, 96, 239, 0.15);
			}

			.<?php echo esc_attr( $prefix ); ?>-loader {
				position: absolute;
				inset: 0;
				display: flex;
				align-items: center;
				justify-content: center;
				background: rgba(255, 255, 255, 0.72);
				z-index: 100;
				border-radius: inherit;
			}

			.<?php echo esc_attr( $prefix ); ?>-loader .<?php echo esc_attr( $prefix ); ?>-spinner {
				width: 40px;
				height: 40px;
				border: 3px solid rgba(67, 96, 239, 0.2);
				border-top-color: #4360ef;
				border-radius: 50%;
				animation: <?php echo esc_attr( $prefix ); ?>-spin 0.8s linear infinite;
			}

			@keyframes <?php echo esc_attr( $prefix ); ?>-spin {
				to {
					transform: rotate(360deg);
				}
			}
		</style>
		<?php
	}

	/**
	 * Encode a value as a quoted JavaScript string literal.
	 *
	 * `esc_js()` is not used here because it HTML-encodes characters such as
	 * `&`, which jQuery UI would then print verbatim inside button labels.
	 *
	 * @param string $value Value to encode.
	 *
	 * @return string Quoted, script-safe JavaScript string literal.
	 */
	protected static function js_string( $value ) {
		return wp_json_encode( (string) $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	}

	/**
	 * Print the dialog behaviour script.
	 *
	 * The deactivate link is matched through the plugin row's `data-plugin`
	 * attribute rather than the generated `deactivate-{slug}` id, because
	 * WordPress appends a collision suffix to that id when two plugins share
	 * a slug.
	 *
	 * @return void
	 */
	public static function render_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );

		$prefix = self::PREFIX;
		?>
		<script id="<?php echo esc_attr( $prefix ); ?>-script">
			jQuery( function ( $ ) {
				var prefix       = <?php echo self::js_string( $prefix ); ?>;
				var pluginFile   = <?php echo self::js_string( RTWPVS_PLUGIN_BASENAME ); ?>;
				var linkSelector = 'tr[data-plugin="' + pluginFile + '"] .deactivate a';
				var $dialog      = $( '#' + prefix + '-dialog' );
				var deactivateUrl = '';

				if ( ! $dialog.length ) {
					return;
				}

				/**
				 * Show the loading overlay and lock the footer buttons.
				 */
				function showLoader() {
					if ( ! $dialog.hasClass( 'ui-dialog-content' ) ) {
						return;
					}
					var $widget = $dialog.dialog( 'widget' );
					if ( ! $widget.find( '.' + prefix + '-loader' ).length ) {
						$widget.append( '<div class="' + prefix + '-loader"><span class="' + prefix + '-spinner"></span></div>' );
					}
					$widget.find( '.ui-dialog-buttonpane button' ).prop( 'disabled', true );
				}

				/**
				 * Leave the dialog and let WordPress deactivate the plugin.
				 */
				function deactivate() {
					showLoader();
					window.location.href = deactivateUrl;
				}

				/**
				 * Clear every validation message.
				 */
				function clearErrors() {
					$dialog.find( '.' + prefix + '-error' ).text( '' );
				}

				/**
				 * Validate the form and submit the feedback, then deactivate.
				 */
				function submitFeedback() {
					var reasons      = $dialog.find( 'input[type="radio"]:checked' ).val();
					var feedback     = $( '#' + prefix + '-feedback' ).val();
					var betterPlugin = $dialog.find( '.' + prefix + '-better-plugin' ).val();
					var websiteUrl   = <?php echo self::js_string( home_url() ); ?>;

					clearErrors();

					if ( ! reasons ) {
						$( '#' + prefix + '-form-body' ).children( '.' + prefix + '-error' )
							.text( <?php echo self::js_string( __( 'Choose the reason', 'woo-product-variation-swatches' ) ); ?> );
						return;
					}

					if ( 'bug_issue_detected' === reasons && ! feedback ) {
						$dialog.find( '.' + prefix + '-text-wrapper .' + prefix + '-error' )
							.text( <?php echo self::js_string( __( 'Please provide more details regarding the issue so we can address it in future updates.', 'woo-product-variation-swatches' ) ); ?> );
						return;
					}

					// Naming an alternative is a complete answer on its own, but the
					// survey endpoint drops rows whose feedback is empty, so send a
					// placeholder message to keep the plugin name.
					if ( 'found_a_better_plugin' === reasons && ! feedback && betterPlugin ) {
						feedback = '-';
					}

					// Nothing worth sending: deactivate straight away.
					if ( 'temporary_deactivation' === reasons || ! feedback || ! websiteUrl ) {
						deactivate();
						return;
					}

					showLoader();

					// Feedback is best-effort: if the endpoint is slow or
					// unreachable, fail fast so the user is not stuck on the
					// loader. `complete` runs on success, error and timeout,
					// so deactivation always proceeds.
					$.ajax( {
						url: <?php echo self::js_string( self::SURVEY_ENDPOINT ); ?>,
						method: 'GET',
						dataType: 'json',
						timeout: 5000,
						data: {
							website: websiteUrl,
							reasons: reasons ? reasons : '',
							better_plugin: betterPlugin,
							feedback: feedback,
							wpplugin: <?php echo self::js_string( self::PLUGIN_KEY ); ?>,
							version: <?php echo self::js_string( defined( 'RTWPVS_VERSION' ) ? RTWPVS_VERSION : '' ); ?>,
							date: <?php echo self::js_string( gmdate( 'M j, Y' ) ); ?>
						},
						complete: function () {
							$dialog.dialog( 'close' );
							window.location.href = deactivateUrl;
						}
					} );
				}

				// Open the dialog instead of following the Deactivate link.
				$( document ).on( 'click', linkSelector, function ( e ) {
					e.preventDefault();

					deactivateUrl = $( this ).attr( 'href' );

					clearErrors();
					$dialog.find( '.' + prefix + '-better-plugin' ).hide();

					$dialog.dialog( {
						modal: true,
						width: 550,
						position: { my: 'center', at: 'center', of: window },
						show: { effect: 'fadeIn', duration: 400 },
						hide: { effect: 'fadeOut', duration: 100 },
						open: function () {
							$( this ).dialog( 'widget' ).addClass( prefix + '-widget' );
							$( 'body' ).addClass( prefix + '-open' );
						},
						close: function () {
							$( 'body' ).removeClass( prefix + '-open' );
						},
						buttons: [
							{
								text: <?php echo self::js_string( __( 'Submit & Deactivate', 'woo-product-variation-swatches' ) ); ?>,
								click: submitFeedback
							},
							{
								text: <?php echo self::js_string( __( 'Skip & Deactivate', 'woo-product-variation-swatches' ) ); ?>,
								click: deactivate
							}
						]
					} );

					// Keep the dialog centered on window resize.
					$( window ).off( 'resize.' + prefix ).on( 'resize.' + prefix, function () {
						if ( $dialog.dialog( 'instance' ) ) {
							$dialog.dialog( 'option', 'position', { my: 'center', at: 'center', of: window } );
						}
					} );
				} );

				// Make the whole reason card clickable.
				$dialog.on( 'click', '.' + prefix + '-input-wrapper', function ( e ) {
					if ( $( e.target ).is( 'input, label' ) ) {
						return;
					}
					var $radio = $( this ).find( 'input[type="radio"]' );
					if ( $radio.length && ! $radio.prop( 'checked' ) ) {
						$radio.prop( 'checked', true ).trigger( 'change' );
					}
				} );

				// Reveal the message box under the selected reason.
				$dialog.on( 'change', 'input[type="radio"]', function () {
					var $extra   = $dialog.find( '.' + prefix + '-extra' );
					var $row     = $( this ).closest( '.' + prefix + '-input-wrapper' );
					var reasons  = $dialog.find( 'input[type="radio"]:checked' ).val();

					// A temporary deactivation needs no message.
					if ( 'temporary_deactivation' === reasons ) {
						$extra.stop( true, true ).slideUp( 200 );
					} else if ( $row.length ) {
						$extra.hide().insertAfter( $row ).slideDown( 200 );
					} else {
						$extra.slideDown( 200 );
					}

					if ( 'found_a_better_plugin' === reasons ) {
						$dialog.find( '.' + prefix + '-better-plugin' ).show();
					} else {
						$dialog.find( '.' + prefix + '-better-plugin' ).hide();
					}
				} );

				// Close the dialog when the overlay is clicked.
				$( document ).on( 'click', '.ui-widget-overlay.ui-front', function ( e ) {
					if ( ! $dialog.hasClass( 'ui-dialog-content' ) || ! $( 'body' ).hasClass( prefix + '-open' ) ) {
						return;
					}
					if ( 0 === $( e.target ).closest( $dialog.parent() ).length ) {
						$dialog.dialog( 'close' );
					}
				} );
			} );
		</script>
		<?php
	}
}
