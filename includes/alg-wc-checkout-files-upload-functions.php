<?php
/**
 * Checkout Files Upload Functions
 *
 * @version 1.2.0
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*
 * alg_current_filter_priority.
 *
 * @version 1.1.1
 * @since   1.0.0
 */
if ( ! function_exists( 'alg_current_filter_priority' ) ) {
	function alg_current_filter_priority() {
		global $wp_filter;
		$current_filter_data = $wp_filter[ current_filter() ];
		if ( class_exists( 'WP_Hook' ) ) {
			// since WordPress v4.7
			return $current_filter_data->current_priority();
		} else {
			// before WordPress v4.7
			return key( $current_filter_data );
		}
	}
}

/**
 * alg_get_alg_uploads_dir.
 *
 * @version 1.0.0
 * @since   1.0.0
 */
if ( ! function_exists( 'alg_get_alg_uploads_dir' ) ) {
	function alg_get_alg_uploads_dir( $subdir = '' ) {
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'];
		$upload_dir = $upload_dir . '/woocommerce_uploads/alg_uploads';
		if ( '' != $subdir ) {
			$upload_dir = $upload_dir . '/' . $subdir;
		}
		return $upload_dir;
	}
}

/**
 * alg_is_user_role.
 *
 * @version 1.2.0
 * @since   1.0.0
 * @return  bool
 */
if ( ! function_exists( 'alg_is_user_role' ) ) {
	function alg_is_user_role( $user_role, $user_id = 0 ) {
		$the_user = ( 0 == $user_id ) ? wp_get_current_user() : get_user_by( 'id', $user_id );
		if ( ! isset( $the_user->roles ) || empty( $the_user->roles ) ) {
			$the_user->roles = array( 'guest' );
		}
		return ( isset( $the_user->roles ) && is_array( $the_user->roles ) && in_array( $user_role, $the_user->roles ) );
	}
}
