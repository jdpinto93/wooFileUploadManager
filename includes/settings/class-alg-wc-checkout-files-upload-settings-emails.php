<?php
/**
 * Checkout Files Upload - Emails Section Settings
 *
 * @version 2.0.3
 * @since   1.1.0
 * @author  Algoritmika Ltd.
 * @author  WP Wham
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Settings_Emails' ) ) :

class Alg_WC_Checkout_Files_Upload_Settings_Emails extends Alg_WC_Checkout_Files_Upload_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function __construct() {
		$this->id   = 'emails';
		$this->desc = __( 'Emails', 'checkout-files-upload-woocommerce' );
		parent::__construct();
	}

	/**
	 * get_settings.
	 *
	 * @version 2.0.3
	 * @since   1.1.0
	 */
	public static function get_settings() {
		$settings = array(
			array(
				'title'    => __( 'General Emails Options', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_checkout_files_upload_emails_options',
			),
			array(
				'title'    => __( 'Attach files to admin\'s new order emails', 'checkout-files-upload-woocommerce' ),
				'desc'     => __( 'Attach', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_attach_to_admin_new_order',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Attach files to customer\'s processing order emails', 'checkout-files-upload-woocommerce' ),
				'desc'     => __( 'Attach', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_attach_to_customer_processing_order',
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_checkout_files_upload_emails_options',
			),
			array(
				'title'    => __( 'Additional Emails Options', 'checkout-files-upload-woocommerce' ),
				'desc'     => sprintf( __( 'Available placeholders in %s options are: %s.', 'checkout-files-upload-woocommerce' ),
					'<strong>' . implode( '</strong>, <strong>', array(
						__( 'Subject', 'checkout-files-upload-woocommerce' ),
						__( 'Email heading', 'checkout-files-upload-woocommerce' ),
						__( 'Email content', 'checkout-files-upload-woocommerce' ) ) ) . '</strong>',
					'<code>' . implode( '</code>, <code>', array(
						'{action}',
						'{site_title}',
						'{order_date}',
						'{order_number}',
						'{file_name}',
						'{file_num}' ) ) . '</code>'
					),
				'type'     => 'title',
				'id'       => 'alg_checkout_files_upload_additional_emails_options',
			),
			array(
				'title'    => __( 'Send additional email to admin on user actions', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'Leave blank to disable additional emails.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_emails_actions',
				'default'  => array(),
				'type'     => 'multiselect',
				'class'    => 'chosen_select',
				'options'  => array(
					'remove_file' => __( 'File removed on "Thank You" or "My Account" page', 'checkout-files-upload-woocommerce' ),
					'upload_file' => __( 'File uploaded on "Thank You" or "My Account" page', 'checkout-files-upload-woocommerce' ),
				),
			),
			array(
				'title'    => __( 'Attach file on upload action', 'checkout-files-upload-woocommerce' ),
				'desc'     => __( 'Attach', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_emails_do_attach_on_upload',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Recipient(s)', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => sprintf( __( 'Enter recipients (comma separated). Defaults to %s.', 'checkout-files-upload-woocommerce' ),
					'<em>' . esc_attr( get_option( 'admin_email' ) ) . '</em>' ),
				'id'       => 'alg_checkout_files_upload_emails_address',
				'default'  => get_option( 'admin_email' ),
				'type'     => 'text',
			),
			array(
				'title'    => __( 'Subject', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_emails_subject',
				'default'  => __( '[{site_title}] Checkout files upload action in customer order ({order_number}) - {order_date}', 'checkout-files-upload-woocommerce' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'title'    => __( 'Email heading', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_emails_heading',
				'default'  => __( '{action}: File #{file_num}', 'checkout-files-upload-woocommerce' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'title'    => __( 'Email content', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_emails_content',
				'default'  => __( 'File name: {file_name}', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'height:200px;width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Action: File removed', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => sprintf( __( 'Replaces %s placeholder.', 'checkout-files-upload-woocommerce' ), '<em>{action}</em>' ),
				'id'       => 'alg_checkout_files_upload_emails_action_removed',
				'default'  => __( 'File removed', 'checkout-files-upload-woocommerce' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'title'    => __( 'Action: File uploaded', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => sprintf( __( 'Replaces %s placeholder.', 'checkout-files-upload-woocommerce' ), '<em>{action}</em>' ),
				'id'       => 'alg_checkout_files_upload_emails_action_uploaded',
				'default'  => __( 'File uploaded', 'checkout-files-upload-woocommerce' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_checkout_files_upload_additional_emails_options',
			),
		);
		return $settings;
	}

}

endif;

return new Alg_WC_Checkout_Files_Upload_Settings_Emails();
