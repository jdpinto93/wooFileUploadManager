<?php
/**
 * Checkout Files Upload - File Section Settings
 *
 * @version 2.1.1
 * @since   1.3.0
 * @author  Algoritmika Ltd.
 * @author  WP Wham
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Settings_File' ) ) :

class Alg_WC_Checkout_Files_Upload_Settings_File extends Alg_WC_Checkout_Files_Upload_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.3.0
	 * @since   1.3.0
	 */
	function __construct( $id ) {
		$this->id   = 'file_' . $id;
		$this->nr   = $id;
		$this->desc = sprintf( __( 'Boton', 'checkout-files-upload-woocommerce' ), $id );
		parent::__construct();
	}

	/**
	 * get_settings.
	 *
	 * @version 2.1.1
	 * @since   1.3.0
	 * @todo    [dev] re-do settings as array, i.e. `alg_checkout_files_upload_enabled[{$i}]` etc.
	 * @todo    [feature] products, cats and tags as comma separated ID list (e.g. from WPML)
	 */
	function get_settings() {

		// Products Tags
		$product_tags_options = array();
		$product_tags = get_terms( 'product_tag', 'orderby=name&hide_empty=0' );
		if ( ! empty( $product_tags ) && ! is_wp_error( $product_tags ) ){
			foreach ( $product_tags as $product_tag ) {
				$product_tags_options[ $product_tag->term_id ] = $product_tag->name;
			}
		}

		// Products Cats
		$product_cats_options = array();
		$product_cats = get_terms( 'product_cat', 'orderby=name&hide_empty=0' );
		if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ){
			foreach ( $product_cats as $product_cat ) {
				$product_cats_options[ $product_cat->term_id ] = $product_cat->name;
			}
		}

		// Products
		$products_options = array();
		$offset     = 0;
		$block_size = 1024;
		while( true ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => $block_size,
				'offset'         => $offset,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			);
			$loop = new WP_Query( $args );
			if ( ! $loop->have_posts() ) {
				break;
			}
			foreach ( $loop->posts as $post_id ) {
				$products_options[ $post_id ] = get_the_title( $post_id );
			}
			$offset += $block_size;
		}

		// Settings
		$i = $this->nr;
		$settings = array(
			array(
				'title'    => __( 'File Uploader', 'checkout-files-upload-woocommerce' ) . ' #' . $i,
				'type'     => 'title',
				'id'       => "alg_checkout_files_upload_general_file_options[$i]",
			),
			array(
				'title'    => __( 'Enable/disable', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_enabled_' . $i,
				'desc'     => '<strong>' . __( 'Enabled', 'checkout-files-upload-woocommerce' ) . '</strong>',
				'type'     => 'checkbox',
				'default'  => 'yes',
			),
			array(
				'title'    => __( 'Required', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_required_' . $i,
				'desc'     => __( 'Yes', 'checkout-files-upload-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array(
				'title'    => __( 'Allow multiple files', 'checkout-files-upload-woocommerce' ),
				'desc'     => __( 'Yes', 'checkout-files-upload-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc'     => '<em>' . sprintf(
					__( '%s adds the ability to upload multiple files.', 'checkout-files-upload-woocommerce' ),
					'<a target="_blank" href="' . esc_url( 'https://wpwham.com/products/checkout-files-upload-for-woocommerce/?utm_source=settings_file&utm_campaign=free&utm_medium=checkout_files_upload' ) . '">' .
						__( 'Checkout Files Upload for WooCommerce Pro', 'checkout-files-upload-woocommerce' ) . '</a>'
				) . '</em>',
				'custom_attributes' => array( 'disabled' => 'disabled' ),
			),
			array(
				'title'    => __( 'Accepted file types', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'Accepted file types. E.g.: ".jpg,.jpeg,.png". Leave blank to accept all files', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_file_accept_' . $i,
				'default'  => '.jpg,.jpeg,.png',
				'type'     => 'text',
			),
			array(
				'title'    => __( 'Validate image dimensions', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_file_validate_image_dimensions_' . $i,
				'default'  => '',
				'type'     => 'select',
				'options'  => array(
					''               => __( 'Do not validate', 'checkout-files-upload-woocommerce' ),
					'validate_size'  => __( 'Validate exact size', 'checkout-files-upload-woocommerce' ),
					'validate_min'   => __( 'Validate greater than or equal to', 'checkout-files-upload-woocommerce' ),
					'validate_max'   => __( 'Validate less than or equal to', 'checkout-files-upload-woocommerce' ),
					'validate_ratio' => __( 'Validate ratio', 'checkout-files-upload-woocommerce' ),
				),
				'class'    => 'alg_checkout_files_upload_file_validate_image_dimensions',
				'custom_attributes' => array( 'data-file-uploader' => $i ),
			),
			array(
				'desc'     => __( 'Width', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_file_validate_image_dimensions_w_' . $i,
				'default'  => 1,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 1 ),
			),
			array(
				'desc'     => __( 'Height', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_file_validate_image_dimensions_h_' . $i,
				'default'  => 1,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 1 ),
			),
			array(
				'type'     => 'sectionend',
				'id'       => "alg_checkout_files_upload_general_file_options[$i]",
			),
			array(
				'title'    => __( 'Positions', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => "alg_checkout_files_upload_positions_file_options[$i]",
			),
			array(
				'title'    => __( 'Checkout page', 'checkout-files-upload-woocommerce' ),
				'desc'     => sprintf( __( '<a href="%s" target="_blank">Checkout Files Upload for WooCommerce Pro</a> adds 20 more positions to choose from.', 'checkout-files-upload-woocommerce' ), 'https://wpwham.com/products/checkout-files-upload-for-woocommerce/?utm_source=settings_file&utm_campaign=free&utm_medium=checkout_files_upload' ),
				'id'       => 'alg_checkout_files_upload_hook_' . $i,
				'default'  => 'woocommerce_before_checkout_form',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'options'  => array(
					'woocommerce_before_checkout_form' => __( 'Before checkout form', 'checkout-files-upload-woocommerce' ),
					'woocommerce_after_checkout_form'  => __( 'After checkout form', 'checkout-files-upload-woocommerce' ),
					'disable'                          => __( 'Do not add on checkout', 'checkout-files-upload-woocommerce' ),
				),
			),
			array(
				'desc'     => __( 'Position order (i.e. priority)', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_hook_priority_' . $i,
				'default'  => 20,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => '0' ),
			),
			array(
				'title'    => __( '"Thank You" page', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_add_to_thankyou_' . $i,
				'desc'     => __( 'Add', 'checkout-files-upload-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array(
				'title'    => __( '"My Account" page', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_add_to_myaccount_' . $i,
				'desc'     => __( 'Add', 'checkout-files-upload-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array(
				'type'     => 'sectionend',
				'id'       => "alg_checkout_files_upload_positions_file_options[$i]",
			),
			array(
				'title'    => __( 'Labels', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => "alg_checkout_files_upload_labels_file_options[$i]",
			),
			array(
				'title'    => __( 'Label', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'You can use shortcodes here.', 'checkout-files-upload-woocommerce' ) . ' ' .
					__( 'Leave blank to disable label.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_label_' . $i,
				'default'  => __( 'Please select file to upload', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Upload button (single)', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'You can use shortcodes here.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_label_button_single_' . $i,
				'default'  => __( 'Choose File', 'checkout-files-upload-woocommerce' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'type'     => 'sectionend',
				'id'       => "alg_checkout_files_upload_labels_file_options[$i]",
			),
			array(
				'title'    => __( 'Notices', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => "alg_checkout_files_upload_notices_file_options[$i]",
			),
			array(
				'title'    => __( 'Wrong file type', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( '%s will be replaced with file name', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_notice_wrong_file_type_' . $i,
				'default'  => __( 'Wrong file type: "%s"', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Wrong image dimensions', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( '%s will be replaced with file name', 'checkout-files-upload-woocommerce' ) . '. ' .
					__( 'Other replaced values: %current_width%, %current_height%, %required_width%, %required_height%', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_notice_wrong_image_dimensions_' . $i,
				'default'  => __( 'Wrong image dimensions for "%s". Current: %current_width% x %current_height%. Required: %required_width% x %required_height%.', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'Couldn\'t get image dimensions', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( '%s will be replaced with file name', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_notice_no_image_dimensions_' . $i,
				'default'  => __( 'Couldn\'t get image dimensions: "%s"', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'File is required', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_notice_required_' . $i,
				'default'  => __( 'File is required.', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'File was successfully uploaded', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( '%s will be replaced with file name', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_notice_success_upload_' . $i,
				'default'  => __( 'File "%s" was successfully uploaded.', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'title'    => __( 'File was successfully removed', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( '%s will be replaced with file name', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_notice_success_remove_' . $i,
				'default'  =>  __( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'type'     => 'sectionend',
				'id'       => "alg_checkout_files_upload_notices_file_options[$i]",
			),
			array(
				'title'    => __( 'Advanced', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'id'       => "alg_checkout_files_upload_advanced_file_options[$i]",
			),
			array(
				'title'    => __( 'Require products', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'To show this field only if at least one selected product is in cart, enter products here. Leave blank to show for all products.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_show_products_in_' . $i,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $products_options,
			),
			array(
				'title'    => __( 'Require product categories', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'To show this field only if at least one product of selected category is in cart, enter categories here. Leave blank to show for all products.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_show_cats_in_' . $i,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $product_cats_options,
			),
			array(
				'title'    => __( 'Require product tags', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'To show this field only if at least one product of selected tag is in cart, enter tags here. Leave blank to show for all products.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_show_tags_in_' . $i,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $product_tags_options,
			),
			array(
				'type'     => 'sectionend',
				'id'       => "alg_checkout_files_upload_advanced_file_options[$i]",
			),
		);

		return $settings;
	}

}

endif;
