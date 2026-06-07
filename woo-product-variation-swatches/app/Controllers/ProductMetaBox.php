<?php


namespace Rtwpvs\Controllers;


class ProductMetaBox
{

    function __construct() {
        add_action('woocommerce_process_product_meta', array(
            $this,
            'process_product_switches_meta'
        ), 10, 2);
        add_action('wp_ajax_rtwpvs_save_product_attributes', array($this, 'rtwpvs_save_product_attributes'));
        add_action('wp_ajax_rtwpvs_reset_product_attributes', array($this, 'rtwpvs_reset_product_attributes'));
    }

    public function rtwpvs_reset_product_attributes() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rtwpvs_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Wrong Nonce', 'woo-product-variation-swatches' ) );
        }

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( esc_html__( 'Permission denied', 'woo-product-variation-swatches' ) );
        }

        $product_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product', 'woo-product-variation-swatches' ) );
        }

        delete_post_meta( $product_id, '_rtwpvs' );
        do_action( 'rtwpvs_reset_product_attributes', $product_id );
        wp_send_json_success();
    }

    public function rtwpvs_save_product_attributes() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rtwpvs_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Wrong Nonce', 'woo-product-variation-swatches' ) );
        }

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( esc_html__( 'Permission denied', 'woo-product-variation-swatches' ) );
        }

        $product_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product', 'woo-product-variation-swatches' ) );
        }

        $raw_data = isset( $_REQUEST['data'] ) ? wp_unslash( $_REQUEST['data'] ) : '';
        parse_str( $raw_data, $data );
        if ( ! empty( $data['rtwpvs'] ) ) {
            $data = $data['rtwpvs'];
            update_post_meta( $product_id, '_rtwpvs', $data );
        }
        do_action( 'rtwpvs_save_product_attributes', $product_id, $data );
        wp_send_json_success();
    }

    public function process_product_switches_meta( $post_id ) {
        if ( isset( $_REQUEST['rtwpvs'] ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested array stored as post meta.
            update_post_meta( $post_id, '_rtwpvs', wp_unslash( $_REQUEST['rtwpvs'] ) );
        }
    }

}
