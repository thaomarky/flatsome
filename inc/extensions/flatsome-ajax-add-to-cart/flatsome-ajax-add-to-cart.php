<?php
/**
 * Flatsome Ajax add to cart extension.
 *
 * @package    Flatsome/Extensions
 * @since      3.17.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * To be enqueued script.
 */
function flatsome_ajax_add_to_cart_script() {
	wp_enqueue_script(
		'flatsome-ajax-add-to-cart-frontend',
		get_template_directory_uri() . '/assets/js/extensions/flatsome-ajax-add-to-cart-frontend.js',
		array( 'jquery', 'wc-add-to-cart' ),
		flatsome()->version(),
		true
	);
}

add_action( 'wp_enqueue_scripts', 'flatsome_ajax_add_to_cart_script' );

/**
 * Product ajax add to cart.
 *
 * @see WC_AJAX::add_to_cart() function (slightly modified).
 */
function flatsome_ajax_add_to_cart() {
	ob_start();

	if ( ! isset( $_POST['product_id'] ) ) { // phpcs:disable WordPress.Security.NonceVerification
		wp_send_json_error( array(
			'message' => 'Invalid request',
		) );
	}

	$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
	$product           = wc_get_product( $product_id );
	$quantity          = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_POST['quantity'] ) );
	$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
	$product_status    = get_post_status( $product_id );
	$variation_id      = 0;
	$variation         = array();

	if ( $product && 'variation' === $product->get_type() ) {
		$variation_id = $product_id;
		$product_id   = $product->get_parent_id();
		$variation    = $product->get_variation_attributes();

		if ( ! empty( $_POST['variation'] ) ) {
			foreach ( $_POST['variation'] as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$variation[ sanitize_title( wp_unslash( $key ) ) ] = wp_unslash( $value );
			}

			$variation = array_unique( array_filter( $variation ) );
		}
	}

	if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) && 'publish' === $product_status ) {
		do_action( 'woocommerce_ajax_added_to_cart', $product_id );

		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			wc_add_to_cart_message( array( $product_id => $quantity ), true );
		}

		WC_AJAX::get_refreshed_fragments();
	} else {

		// If there was an error adding to the cart, redirect to the product page to show any errors.
		$data = array(
			'error'       => true,
			'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
		);

		wp_send_json( $data );
	}
}

add_action( 'wp_ajax_flatsome_ajax_add_to_cart', 'flatsome_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_flatsome_ajax_add_to_cart', 'flatsome_ajax_add_to_cart' );
