<?php
/**
 * Checkout Files Upload - Template Section Settings
 *
 * @version 2.0.3
 * @since   1.1.0
 * @author  Algoritmika Ltd.
 * @author  WP Wham
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Settings_Template' ) ) :

class Alg_WC_Checkout_Files_Upload_Settings_Template extends Alg_WC_Checkout_Files_Upload_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function __construct() {
		$this->id   = 'template';
		$this->desc = __( 'Template', 'checkout-files-upload-woocommerce' );
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
				'title'    => __( 'Form Template Options', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_checkout_files_upload_form_template_options',
			),
			array(
				'title'    => __( 'Before', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_form_template_before',
				'default'  => '<table>',
				'type'     => 'textarea',
				'css'      => 'width:100%;',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Label', 'checkout-files-upload-woocommerce' ),
				'desc'     => sprintf( __( 'Replaced values: %s.', 'checkout-files-upload-woocommerce' ), '<code>%field_id%</code>, <code>%field_label%</code>, <code>%required_html%</code>' ),
				'id'       => 'alg_checkout_files_upload_form_template_label',
				'default'  => '<tr><td colspan="2"><label for="%field_id%">%field_label%</label>%required_html%</td></tr>',
				'type'     => 'textarea',
				'css'      => 'width:100%;',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Field Container', 'checkout-files-upload-woocommerce' ),
				'desc'     => sprintf( __( 'Replaced values: %s.', 'checkout-files-upload-woocommerce' ), '<code>%field_html%</code>, <code>%button_html%</code>' ),
				'id'       => 'alg_checkout_files_upload_form_template_field_ajax',
				'default'  => '<tr><td colspan="2">%button_html% %field_html%</td></tr>',
				'type'     => 'textarea',
				'css'      => 'width:100%;',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Uploaded File', 'checkout-files-upload-woocommerce' ),
				'desc'     => sprintf( __( 'Replaced values: %s.', 'checkout-files-upload-woocommerce' ), '<code>%file_name%</code>, <code>%image%</code>, <code>%remove_button%</code>' ),
				'id'       => 'wpw_cfu_form_template_uploaded_file',
				'default'  => '%image% %file_name% %remove_button%',
				'type'     => 'textarea',
				'css'      => 'width:100%;',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'After', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_form_template_after',
				'default'  => '</table>',
				'type'     => 'textarea',
				'css'      => 'width:100%;',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_checkout_files_upload_form_template_options',
			),
			array(
				'title'    => __( 'Styling Options', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_checkout_files_upload_form_styling_options',
			),
			array(
				'title'    => __( '"Delete" button style', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_form_style_ajax_delete',
				'default'  => 'color:red;',
				'type'     => 'text',
			),
			array(
				'title'    => __( 'Image style', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'If using %image% replaced value.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_form_image_style',
				'default'  => 'width:64px;',
				'type'     => 'text',
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_checkout_files_upload_form_styling_options',
			),
		);
		return $settings;
	}

}

endif;

return new Alg_WC_Checkout_Files_Upload_Settings_Template();
