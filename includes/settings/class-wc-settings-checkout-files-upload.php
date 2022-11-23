<?php
/**
 * Checkout Files Upload - Settings
 *
 * @version 1.4.4
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Settings_Checkout_Files_Upload' ) ) :

class Alg_WC_Settings_Checkout_Files_Upload extends WC_Settings_Page {

	/**
	 * Constructor.
	 *
	 * @version 1.4.4
	 * @since   1.0.0
	 */
	function __construct() {
		$this->id    = 'alg_wc_checkout_files_upload';
		$this->label = __( 'Checkout Files Upload', 'checkout-files-upload-woocommerce' );
		parent::__construct();
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_unsanitize_option' ), PHP_INT_MAX, 3 );
		add_action( 'admin_notices',                              array( $this, 'settings_saved_admin_notice' ) );
	}

	/**
	 * settings_saved_admin_notice.
	 *
	 * @since   1.4.4
	 * @version 1.4.4
	 */
	function settings_saved_admin_notice() {
		if ( ! empty( $_GET['alg_wc_checkout_files_upload_settings_saved'] ) ) {
			WC_Admin_Settings::add_message( __( 'Your settings have been saved.', 'woocommerce' ) );
		}
	}

	/**
	 * maybe_unsanitize_option.
	 *
	 * @since   1.4.4
	 * @version 1.4.4
	 */
	function maybe_unsanitize_option( $value, $option, $raw_value ) {
		return ( ! empty( $option['alg_wc_cfu_raw'] ) ? $raw_value : $value );
	}

	/**
	 * get_settings.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function get_settings() {
		global $current_section;
		return array_merge( apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() ), array(
			array(
				'title'     => __( 'Reset Settings', 'checkout-files-upload-woocommerce' ),
				'type'      => 'title',
				'id'        => $this->id . '_' . $current_section . '_reset_options',
			),
			array(
				'title'     => __( 'Reset section settings', 'checkout-files-upload-woocommerce' ),
				'desc'      => '<strong>' . __( 'Reset', 'checkout-files-upload-woocommerce' ) . '</strong>',
				'id'        => $this->id . '_' . $current_section . '_reset',
				'default'   => 'no',
				'type'      => 'checkbox',
			),
			array(
				'type'      => 'sectionend',
				'id'        => $this->id . '_' . $current_section . '_reset_options',
			),
		) );
	}

	/**
	 * maybe_reset_settings.
	 *
	 * @version 1.4.4
	 * @since   1.3.0
	 */
	function maybe_reset_settings() {
		global $current_section;
		if ( 'yes' === get_option( $this->id . '_' . $current_section . '_reset', 'no' ) ) {
			foreach ( $this->get_settings() as $value ) {
				if ( isset( $value['id'] ) ) {
					$id = explode( '[', $value['id'] );
					$id = $id[0];
					delete_option( $id );
				}
			}
		}
	}

	/**
	 * save.
	 *
	 * @version 1.4.4
	 * @since   1.3.0
	 */
	function save() {
		parent::save();
		$this->maybe_reset_settings();
		wp_safe_redirect( add_query_arg( 'alg_wc_checkout_files_upload_settings_saved', true ) );
		exit;
	}

}

endif;

return new Alg_WC_Settings_Checkout_Files_Upload();
