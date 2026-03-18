<?php

namespace Rtwpvs\Controllers;

class Notifications {


	static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'php_requirement_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'wc_requirement_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'wc_version_requirement_notice' ] );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'plugin_row_meta' ], 10, 2 );
	}

	public static function php_requirement_notice() {
		if ( ! rtwpvs()->is_valid_php_version() ) {
			$class   = 'notice notice-error';
			$text    = esc_html__( 'Please check PHP version requirement.', 'woo-product-variation-swatches' );
			$link    = esc_url( 'https://docs.woocommerce.com/document/server-requirements/' );
			$message = wp_kses( __( "It's required to use latest version of PHP to use <strong>Variation Swatches for WooCommerce</strong>.", 'woo-product-variation-swatches' ), [ 'strong' => [] ] );

			printf( '<div class="%1$s"><p>%2$s <a target="_blank" href="%3$s">%4$s</a></p></div>', $class, $message, $link, $text );
		}
	}

	public static function wc_requirement_notice() {
		if ( ! rtwpvs()->is_wc_active() ) {

			$class = 'notice notice-error';

			$text    = esc_html__( 'WooCommerce', 'woo-product-variation-swatches' );
			$link    = esc_url(
				add_query_arg(
					[
						'tab'       => 'plugin-information',
						'plugin'    => 'woocommerce',
						'TB_iframe' => 'true',
						'width'     => '640',
						'height'    => '500',
					],
					admin_url( 'plugin-install.php' )
				)
			);
			$message = wp_kses( __( '<strong>Variation Swatches for WooCommerce</strong> is an add-on of ', 'woo-product-variation-swatches' ), [ 'strong' => [] ] );

			printf( '<div class="%1$s"><p>%2$s <a class="thickbox open-plugin-details-modal" href="%3$s"><strong>%4$s</strong></a></p></div>', $class, $message, $link, $text );
		}
	}

	public static function wc_version_requirement_notice() {
		$class   = 'notice notice-error';
		$message = '';
		if ( rtwpvs()->is_wc_active() && ! rtwpvs()->is_valid_wc_version() ) {
			$message = sprintf( esc_html__( "Currently, you are using older version of WooCommerce. It's recommended to use latest version of WooCommerce to work with %s.", 'woo-product-variation-swatches' ), esc_html__( 'Variation Swatches for WooCommerce', 'woo-product-variation-swatches' ) );
		} elseif ( defined( 'RTWPVSP_VERSION' ) && version_compare( RTWPVSP_VERSION, '2.2.0', '<' ) ) {
			$plugin_pro_name = esc_html( 'Variation Swatches for WooCommerce' );
			$plugin_pro_link = '<a href="https://www.radiustheme.com/downloads/woocommerce-variation-swatches/" target="_blank" > ' . $plugin_pro_name . ' </a>';
			$message         = sprintf( esc_html__( "Currently, you are using older version of %s. It's recommended minimum version 2.2.0.", 'woo-product-variation-swatches' ), $plugin_pro_link );
		}
		if ( ! empty( $message ) ) {
			printf( '<div class="%1$s"><p><strong>%2$s</strong></p></div>', $class, $message );
		}
	}

	public static function plugin_row_meta( $links, $file ) {
		if ( $file == rtwpvs()->basename() ) {

			$report_url         = 'https://www.radiustheme.com/contact/';
			$row_meta['issues'] = sprintf( '%2$s <a target="_blank" href="%1$s">%3$s</a>', esc_url( $report_url ), esc_html__( 'Facing issue?', 'woo-product-variation-swatches' ), '<span style="color: red">' . esc_html__( 'Please open a support ticket.', 'woo-product-variation-swatches' ) . '</span>' );

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}
}
