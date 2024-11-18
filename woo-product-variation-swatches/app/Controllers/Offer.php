<?php

namespace Rtwpvs\Controllers;

class Offer {
	public function __construct() {
		add_action(
			'admin_init',
			function () {
				$installed_pro = $this->check_plugin_validity();
				$current       = time();
				$start         = strtotime( '18 November 2024' );
				$end           = strtotime( '10 January 2025' );
				// Black Friday Notice.
				if ( ! $installed_pro && $start <= $current && $current <= $end ) {
					if ( get_option( 'woobundle_black_friday_offer_2024' ) != '1' ) {
						if ( ! isset( $GLOBALS['woobundle_notice'] ) ) {
							$GLOBALS['woobundle_notice'] = 'woobundle_notice';
							self::black_friday_notice();
						}
					}
				} elseif ( get_option( 'woobundle-release-notice' ) != '1' ) {
					if ( ! isset( $GLOBALS['woobundle_release_notice'] ) ) {
						$GLOBALS['woobundle_release_notice'] = 'woobundle-release-notice';
						self::wc_release_notice();
					}
				}

				/*
				 * New Year Notice
				if ( ! $installed_pro && $start <= $current && $current <= $end ) {
					if ( get_option( 'rtwpvs_offer_july_2022' ) != '1' ) {
						if ( ! isset( $GLOBALS['rtwpvs_offer_july_2022_notice'] ) ) {
							$GLOBALS['rtwpvs_offer_july_2022_notice'] = 'rtwpvs_offer_july_2022';
							self::new_year_notice();
						}
					}
				}
				*/
			},
            14
		);
	}
	/**
	 * Check if plugin is validate.
	 *
	 * @return bool
	 */
	public function check_plugin_validity(): bool {
		$license_status = rtwpvs()->get_option( 'license_status' );
		$status         = ( ! empty( $license_status ) && 'valid' === $license_status ) ? true : false;
		return $status;
	}

	/**
	 * Undocumented function.
	 *
	 * @return void
	 */
	public static function black_friday_notice() {
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'jquery' );
			}
		);

		add_action(
			'admin_notices',
			function () {
				$plugin_name   = 'Variation Swatches for WooCommerce Pro';
				$download_link = 'https://www.radiustheme.com/downloads/woocommerce-bundle/';
				?>
				<div class="notice notice-info is-dismissible" data-woobundlebfdismissable="woobundle_black_friday_offer_2024"
					 style="display:grid;grid-template-columns: 100px auto;padding-top: 20px; padding-bottom: 22px;">
					<img alt="<?php echo esc_attr( $plugin_name ); ?>"
						 src="<?php echo rtwpvs()->get_assets_uri( 'images/icon-128x128.png' ); ?>" width="74px"
						 height="74px" style="grid-row: 1 / 4; align-self: center;justify-self: center"/>
					<h3 style="margin:0; position: relative;display: flex; align-items: center;">WooCommerce Bundle [Black Friday <img style="width: 40px;position: relative; " src="<?php echo rtwpvs()->get_assets_uri( 'images/deal.gif' ); ?>">]</h3>

					<p style="margin:0 0 2px; padding: 5px 0; max-width: 100%; font-size: 14px;">
						Enjoy savings of up to 50% with our <b>ShopBuilder Elementor Addon</b>, <b>Variation Swatches</b>, <b>Variation Gallery</b>, and <b>Themes</b>!
					</p>

					<p style="margin:0;">
						<a class="button button-primary" href="<?php echo esc_url( $download_link ); ?>" target="_blank">Buy Now</a>
						<a class="button button-dismiss" href="#">Dismiss</a>
					</p>
				</div>
				<?php
			}
		);

		add_action(
			'admin_footer',
			function () {
				?>
				<script type="text/javascript">
					(function ($) {
						$(function () {
							setTimeout(function () {
								$('div[data-woobundlebfdismissable] .notice-dismiss, div[data-woobundlebfdismissable] .button-dismiss')
									.on('click', function (e) {
										e.preventDefault();
										$.post(ajaxurl, {
											'action': 'rtwpvs_dismiss_admin_black_friday_notice',
											'nonce': <?php echo json_encode( wp_create_nonce( 'rtwpvs-black-friday-offer-2024' ) ); ?>
										});
										$(e.target).closest('.is-dismissible').remove();
									});
							}, 1000);
						});
					})(jQuery);
				</script>
				<?php
			}
		);

		add_action(
			'wp_ajax_rtwpvs_dismiss_admin_black_friday_notice',
			function () {
				check_ajax_referer( 'rtwpvs-black-friday-offer-2024', 'nonce' );

				update_option( 'woobundle_black_friday_offer_2024', '1' );
				wp_die();
			}
		);
	}

	/**
	 * Undocumented function.
	 *
	 * @return void
	 */
	public static function new_year_notice() {
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'jquery' );
			}
		);

		add_action(
			'admin_notices',
			function () {
				$plugin_name   = 'Variation Swatches for WooCommerce Pro';
				$download_link = 'https://www.radiustheme.com/downloads/woocommerce-variation-swatches/';
				?>
				<div class="notice notice-info is-dismissible" data-rtwpvsdismissable="rtwpvs_offer_july_2022"
					style="display:grid;grid-template-columns: 100px auto;padding-top: 25px; padding-bottom: 22px;">
					<img alt="<?php echo esc_attr( $plugin_name ); ?>"
						src="<?php echo rtwpvs()->get_assets_uri( 'images/icon-128x128.png' ); ?>" width="74px"
						height="74px" style="grid-row: 1 / 4; align-self: center;justify-self: center"/>
					<h3 style="margin:0;"><?php echo sprintf( '%s Lifetime Deal!!', $plugin_name ); ?></h3>

					<p style="margin:0 0 2px;">
						<?php echo esc_html__( "Don't miss out on our biggest sale of the year! Get your ", 'woo-product-variation-swatches' ); ?>
						<b><?php echo $plugin_name; ?> plan</b> with <b>UP TO 80% OFF</b>!
					</p>

					<p style="margin:0;">
						<a class="button button-primary" href="<?php echo esc_url( $download_link ); ?>" target="_blank">Buy Now</a>
						<a class="button button-dismiss" href="#">Dismiss</a>
					</p>
				</div>
					<?php
			}
		);

		add_action(
			'admin_footer',
			function () {
				?>
				<script type="text/javascript">
					(function ($) {
						$(function () {
							setTimeout(function () {
								$('div[data-rtwpvsdismissable] .notice-dismiss, div[data-rtwpvsdismissable] .button-dismiss')
									.on('click', function (e) {
										e.preventDefault();
										$.post(ajaxurl, {
											'action': 'rtwpvs_dismiss_admin_notice',
											'nonce': <?php echo json_encode( wp_create_nonce( 'rtwpvs-dismissible-notice-july-2022' ) ); ?>
										});
										$(e.target).closest('.is-dismissible').remove();
									});
							}, 1000);
						});
					})(jQuery);
				</script>
					<?php
			}
		);

		add_action(
			'wp_ajax_rtwpvs_dismiss_admin_notice',
			function () {
				check_ajax_referer( 'rtwpvs-dismissible-notice-july-2022', 'nonce' );

				update_option( 'rtwpvs_offer_july_2022', '1' );
				wp_die();
			}
		);
	}

	/**
	 * Undocumented function.
	 *
	 * @return void
	 */
	public static function wc_release_notice() {
		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'jquery' );
			}
		);

		add_action(
			'admin_notices',
			function () {
				?>
				<style>
					.woobundle-release-notice {
						--e-button-context-color: #5d3dfd;
						--e-button-context-color-dark: #0047FF;
						--e-button-context-tint: rgb(75 47 157/4%);
						--e-focus-color: rgb(75 47 157/40%);
					}

					.woobundle-release-notice .button-primary,
					.woobundle-release-notice .button-dismiss {
						display: inline-block;
						border: 0;
						border-radius: 3px;
						background: var(--e-button-context-color-dark);
						color: #fff;
						vertical-align: middle;
						text-align: center;
						text-decoration: none;
						white-space: nowrap;
						margin-right: 5px;
					}
					.woobundle-release-notice .button-dismiss {
						border: 1px solid;
						background: 0 0;
						color: var(--e-button-context-color);
						background: #fff;
					}
					.wp-core-ui .woobundle-release-notice .button-dismiss:hover,
					.woobundle-release-notice .button-dismiss {
						background: #fff;
					}
					.woobundle-release-notice .button-primary:hover{
						background: var(--e-button-context-color-dark);
					}
				</style>
				<?php
				$download_link = 'https://www.radiustheme.com/downloads/woocommerce-bundle/';
				?>
				<div class="woobundle-release-notice notice notice-info is-dismissible" data-woobundle="woobundle_release_notice"
					 style="display:grid;grid-template-columns: 100px auto;padding-top: 25px; padding-bottom: 22px;column-gap: 15px;">
					<img alt="WooCommerce Bundle"
						 src="<?php echo rtwpvs()->get_assets_uri( 'images/shop-100-100.svg' ); ?>" width="100px"
						 height="100px" style="grid-row: 1 / 4; justify-self: center"/>
					<h3 style="margin:0;"><?php echo sprintf( '%s !!', 'ShopBuilder - Elementor Addon Pro is now available!' ); ?></h3>

					<p style="margin:0 0 2px; padding: 5px 0; max-width: 100%; font-size: 14px;">
						Acquire our WooCommerce bundled <b>ShopBuilder Elementor addon</b>, <b>Variation Swatches</b>, and <b>Variation Gallery </b> plugin to enjoy <b>savings of up to 30%!</b>
					</p>

					<p style="margin:0;">
						<a class="button button-primary" href="<?php echo esc_url( $download_link ); ?>" target="_blank">Buy Now</a>
						<a class="button button-dismiss" href="#">Dismiss</a>
					</p>
				</div>
				<?php
			}
		);

		add_action(
			'admin_footer',
			function () {
				?>
				<script type="text/javascript">
					(function ($) {
						$(function () {
							setTimeout(function () {
								$('div[data-woobundle] .notice-dismiss, div[data-woobundlebfdismissable] .button-dismiss')
									.on('click', function (e) {
										e.preventDefault();
										$.post(ajaxurl, {
											'action': 'woobundle_dismiss_admin_notice',
											'nonce': <?php echo json_encode( wp_create_nonce( 'woobundle-dismissible-notice' ) ); ?>
										});
										$(e.target).closest('.is-dismissible').remove();
									});
							}, 1000);
						});
					})(jQuery);
				</script>
				<?php
			}
		);

		add_action(
			'wp_ajax_woobundle_dismiss_admin_notice',
			function () {
				check_ajax_referer( 'woobundle-dismissible-notice', 'nonce' );

				update_option( 'woobundle-release-notice', '1' );
				wp_die();
			}
		);
	}
}
