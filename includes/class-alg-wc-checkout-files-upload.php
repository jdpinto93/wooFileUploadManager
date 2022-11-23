<?php
/**
 * Checkout Files Upload
 *
 * @version 2.1.4
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 * @author  WP Wham
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Main' ) ) :

class Alg_WC_Checkout_Files_Upload_Main {

	/**
	 * Constructor.
	 *
	 * @version 2.0.0
	 * @since   1.0.0
	 * @todo    [dev] split this file into smaller ones
	 * @todo    [feature] max file size on per file basis
	 * @todo    [feature] hide fields by user role; by country etc.
	 */
	function __construct() {
		if ( 'yes' === get_option( 'alg_wc_checkout_files_upload_enabled', 'yes' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'add_file_admin_order_meta_box' ) );
			add_action( 'init', array( $this, 'process_checkout_files_upload' ) );
			$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
			for ( $i = 1; $i <= $total_number; $i++ ) {
				if ( 'disable' != ( $the_hook = get_option( 'alg_checkout_files_upload_hook_' . $i, 'woocommerce_before_checkout_form' ) ) ) {
					add_action( $the_hook, array( $this, 'add_files_upload_form_to_checkout_frontend' ), get_option( 'alg_checkout_files_upload_hook_priority_' . $i, 20 ) );
				}
				if ( 'yes' === get_option( 'alg_checkout_files_upload_add_to_thankyou_' . $i, 'no' ) ) {
					add_action( 'woocommerce_thankyou',   array( $this, 'add_files_upload_form_to_thankyou_and_myaccount_page' ), PHP_INT_MAX, 1 );
				}
				if ( 'yes' === get_option( 'alg_checkout_files_upload_add_to_myaccount_' . $i, 'no' ) ) {
					add_action( 'woocommerce_view_order', array( $this, 'add_files_upload_form_to_thankyou_and_myaccount_page' ), PHP_INT_MAX, 1 );
				}
			}
			add_action( 'woocommerce_checkout_order_processed',        array( $this, 'add_files_to_order' ), PHP_INT_MAX, 2 );
			add_action( 'woocommerce_after_checkout_validation',       array( $this, 'validate_on_checkout' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_files_to_order_display' ), PHP_INT_MAX );
			add_action( 'woocommerce_email_after_order_table',         array( $this, 'add_files_to_order_display' ), PHP_INT_MAX );
			add_filter( 'woocommerce_email_attachments',               array( $this, 'add_files_to_email_attachments' ), PHP_INT_MAX, 3 );
			add_action( 'wp_enqueue_scripts',                          array( $this, 'enqueue_scripts' ), PHP_INT_MAX );
			add_action( 'wp_ajax_'        . 'alg_ajax_file_upload',    array( $this, 'alg_ajax_file_upload' ) );
			add_action( 'wp_ajax_nopriv_' . 'alg_ajax_file_upload',    array( $this, 'alg_ajax_file_upload' ) );
			add_action( 'wp_ajax_'        . 'alg_ajax_file_delete',    array( $this, 'alg_ajax_file_delete' ) );
			add_action( 'wp_ajax_nopriv_' . 'alg_ajax_file_delete',    array( $this, 'alg_ajax_file_delete' ) );
			add_shortcode( 'alg_wc_cfu_translate', array( $this, 'language_shortcode' ) );
		}
	}

	/**
	 * language_shortcode.
	 *
	 * @version 1.4.5
	 * @since   1.4.5
	 */
	function language_shortcode( $atts, $content = '' ) {
		// E.g.: `[alg_wc_cfu_translate lang="EN,DE" lang_text="Translation for EN and DE" not_lang_text="Translation for other languages"]`
		if ( isset( $atts['lang_text'] ) && isset( $atts['not_lang_text'] ) && ! empty( $atts['lang'] ) ) {
			return ( ! defined( 'ICL_LANGUAGE_CODE' ) || ! in_array( strtolower( ICL_LANGUAGE_CODE ), array_map( 'trim', explode( ',', strtolower( $atts['lang'] ) ) ) ) ) ?
				$atts['not_lang_text'] : $atts['lang_text'];
		}
		// E.g.: `[alg_wc_cfu_translate lang="EN,DE"]Translation for EN and DE[/alg_wc_cfu_translate][alg_wc_cfu_translate not_lang="EN,DE"]Translation for other languages[/alg_wc_cfu_translate]`
		return (
			( ! empty( $atts['lang'] )     && ( ! defined( 'ICL_LANGUAGE_CODE' ) || ! in_array( strtolower( ICL_LANGUAGE_CODE ), array_map( 'trim', explode( ',', strtolower( $atts['lang'] ) ) ) ) ) ) ||
			( ! empty( $atts['not_lang'] ) &&     defined( 'ICL_LANGUAGE_CODE' ) &&   in_array( strtolower( ICL_LANGUAGE_CODE ), array_map( 'trim', explode( ',', strtolower( $atts['not_lang'] ) ) ) ) )
		) ? '' : $content;
	}

	/**
	 * maybe_send_admin_notification.
	 *
	 * @version 2.0.0
	 * @since   1.4.4
	 * @todo    [feature] add "Email type" option (i.e. Plain text / HTML / Multipart)
	 * @todo    [feature] (maybe) add `{admin_email}` replaced value in `$address` (in case of comma separated input)
	 */
	function maybe_send_admin_notification( $action, $order_id, $file_name, $file_num, $file_key = null ) {
		$actions         = get_option( 'alg_checkout_files_upload_emails_actions', array() );
		if ( ! in_array( $action, $actions ) ) {
			return;
		}
		$order           = wc_get_order( $order_id );
		$address         = get_option( 'alg_checkout_files_upload_emails_address', get_option( 'admin_email' ) );
		$subject         = get_option( 'alg_checkout_files_upload_emails_subject',
			__( '[{site_title}] Checkout files upload action in customer order ({order_number}) - {order_date}', 'checkout-files-upload-woocommerce' ) );
		$heading         = get_option( 'alg_checkout_files_upload_emails_heading',
			__( '{action}: File #{file_num}', 'checkout-files-upload-woocommerce' ) );
		$content         = get_option( 'alg_checkout_files_upload_emails_content',
			__( 'File name: {file_name}', 'checkout-files-upload-woocommerce' ) );
		
		if (
			$file_key !== null &&
			$action !== 'remove_file' &&
			get_option( 'alg_checkout_files_upload_emails_do_attach_on_upload', 'yes' ) !== 'no'
		) {
			$files = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_num, true );
			if ( is_array( $files ) && isset( $files[ $file_key ] ) ) {
				$attachments = array( alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $files[ $file_key ]['tmp_name'] );
			}
		}
		
		$replaced_values = array(
			'{action}'       => ( 'remove_file' == $action ?
				get_option( 'alg_checkout_files_upload_emails_action_removed',  __( 'File removed',  'checkout-files-upload-woocommerce' ) ) :
				get_option( 'alg_checkout_files_upload_emails_action_uploaded', __( 'File uploaded', 'checkout-files-upload-woocommerce' ) ) ),
			'{site_title}'   => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
			'{order_date}'   => ( $order ? date_i18n( wc_date_format(), strtotime( $order->get_date_created() ) ) : '' ),
			'{order_number}' => ( $order ? $order->get_order_number()                                             : '' ),
			'{file_name}'    => $file_name,
			'{file_num}'     => $file_num,
		);
		wc_mail(
			$address,
			str_replace( array_keys( $replaced_values ), $replaced_values, $subject ),
			$this->wrap_in_wc_email_template(
				str_replace( array_keys( $replaced_values ), $replaced_values, $content ),
				str_replace( array_keys( $replaced_values ), $replaced_values, $heading ) ),
			"Content-Type: text/html\r\n",
			$attachments
		);
	}

	/**
	 * wrap_in_wc_email_template.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function wrap_in_wc_email_template( $content, $email_heading = '' ) {
		return $this->get_wc_email_part( 'header', $email_heading ) .
			$content .
		str_replace( '{site_title}', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $this->get_wc_email_part( 'footer' ) );
	}

	/**
	 * get_wc_email_part.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function get_wc_email_part( $part, $email_heading = '' ) {
		ob_start();
		switch ( $part ) {
			case 'header':
				wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
				break;
			case 'footer':
				wc_get_template( 'emails/email-footer.php' );
				break;
		}
		return ob_get_clean();
	}

	/**
	 * alg_ajax_file_delete.
	 *
	 * @version 2.1.4
	 * @since   1.3.0
	 */
	function alg_ajax_file_delete() {
		
		// Always start session for this
		@session_start();
		
		if ( ! empty( $_POST['order_id'] ) ) {
			$order_id = sanitize_text_field( $_POST['order_id'] );
		} else {
			$order_id = 0; // pending order
		}
		
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpwham-checkout-files-upload-' . $order_id ) ) {
			// Error
			echo json_encode( array(
				'result'  => 0,
				'message' => __( 'The link you followed has expired.', 'checkout-files-upload-woocommerce' )
			) );
			die();
		}
		
		if ( isset( $_POST['file_uploader'] ) && isset( $_POST['file_key'] ) ) {
			
			$file_uploader = sanitize_text_field( $_POST['file_uploader'] );
			$file_key      = sanitize_text_field( $_POST['file_key'] );
			
			if ( isset( $_POST['order_id'] ) && $_POST['order_id'] > 0 ) {
				$order_id = sanitize_text_field( $_POST['order_id'] );
				$files = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader, true );
				if ( is_array( $files ) ) {
					if ( isset( $files[ $file_key ]['tmp_name'] ) && $files[ $file_key ]['tmp_name'] ) {
						$file_path = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $files[ $file_key ]['tmp_name'];
						unlink( $file_path );
					}
					$file_name = $files[ $file_key ]['name'];
					unset( $files[ $file_key ] );
					update_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader, $files );
				} elseif ( $files > '' ) {
					// backwards compatibility for < v2.0.0
					$file_path = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $files;
					unlink( $file_path );
					$file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $file_uploader, true );
					delete_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader );
					delete_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $file_uploader );
				}
				$this->maybe_send_admin_notification( 'remove_file', $order_id, $file_name, $file_uploader );
				echo json_encode( array(
					'result'  => 1,
					'message' => (
						get_option( 'alg_checkout_files_upload_use_ajax_alert_success_remove', 'no' ) === 'yes' ?
							sprintf(
								( get_option( 'alg_checkout_files_upload_notice_success_remove_' . $file_uploader ) > '' ?
									get_option( 'alg_checkout_files_upload_notice_success_remove_' . $file_uploader )
									: __( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ) ),
								$file_name )
							: '' 
					),
				) );
			} else {
				unlink( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['tmp_name'] );
				echo json_encode( array(
					'result'  => 1,
					'message' => (
						get_option( 'alg_checkout_files_upload_use_ajax_alert_success_remove', 'no' ) === 'yes' ?
							sprintf(
								( get_option( 'alg_checkout_files_upload_notice_success_remove_' . $file_uploader ) > '' ?
									get_option( 'alg_checkout_files_upload_notice_success_remove_' . $file_uploader )
									: __( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ) ),
								$_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['name'] )
							: '' 
					),
				) );
				unset( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ] );
			}
			die();
		} else {
			// Error
			echo json_encode( array(
				'result'  => 0,
				'message' => __( 'Unknown error on file remove.', 'checkout-files-upload-woocommerce' )
			) );
			die();
		}
		
		session_write_close();
	}

	/**
	 * get_file_delete_link.
	 *
	 * @version 2.1.1
	 * @since   2.1.0
	 */
	public function get_file_delete_link( $file_uploader, $file_key = null, $order_id = 0 ) {
		
		$args = array(
			'_wpnonce' => wp_create_nonce( 'wpwham_cfu_checkout_file_delete' ),
		);
			
		if ( $file_key !== null ) {
			
			// files uploaded from v2.0.0 onwards
			$args['wpw_cfu_download_file_uploader'] = $file_uploader;
			$args['wpw_cfu_download_file_key']      = $file_key;
			if ( $order_id > 0 ) {
				$args['wpw_cfu_download_file_order_id'] = $order_id;
			}
			
		} else {
			
			// files uploaded from < v2.0.0
			$args['alg_download_checkout_file'] = $file_uploader;
			if ( 0 != $order_id ) {
				$args['alg_download_checkout_file_order_id'] = $order_id;
			}
			
		}
		
		return add_query_arg( $args );
	}

	/**
	 * get_file_download_link.
	 *
	 * @version 2.1.1
	 * @since   1.4.0
	 */
	public function get_file_download_link( $file_uploader, $file_key = null, $order_id = 0, $add_timestamp = false, $force_download = false ) {
		
		$args = array(
			'_wpnonce' => wp_create_nonce( 'alg_download_checkout_file' ),
		);
			
		if ( $file_key !== null ) {
			
			// files uploaded from v2.0.0 onwards
			$args['wpw_cfu_download_file_uploader'] = $file_uploader;
			$args['wpw_cfu_download_file_key']      = $file_key;
			if ( $order_id > 0 ) {
				$args['wpw_cfu_download_file_order_id'] = $order_id;
			}
			
		} else {
			
			// files uploaded from < v2.0.0
			$args['alg_download_checkout_file'] = $file_uploader;
			if ( 0 != $order_id ) {
				$args['alg_download_checkout_file_order_id'] = $order_id;
			}
			
		}
		
		if ( $add_timestamp ) {
			$args['timestamp'] = time();
		}
		
		if ( $force_download ) {
			$args['force_download'] = true;
		}
		
		return add_query_arg( $args );
	}

	/**
	 * alg_ajax_file_upload.
	 *
	 * @version 2.1.4
	 * @since   1.3.0
	 */
	function alg_ajax_file_upload() {
		
		// Always start session for this
		@session_start();
		
		if ( ! empty( $_POST['order_id'] ) ) {
			$order_id = sanitize_text_field( $_POST['order_id'] );
		} else {
			$order_id = 0; // pending order
		}
		
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpwham-checkout-files-upload-' . $order_id ) ) {
			// Error
			echo json_encode( array(
				'result'  => 0,
				'message' => __( 'The link you followed has expired.', 'checkout-files-upload-woocommerce' )
			) );
			die();
		}
		
		if ( isset( $_FILES['file'] ) && '' != $_FILES['file']['tmp_name'] && isset( $_POST['file_uploader'] ) ) {
			
			$file_uploader = sanitize_text_field( $_POST['file_uploader'] );
			
			// Validate file type
			$validated_type = $this->validate_file_type( $file_uploader, $_FILES['file'] );
			if ( $validated_type !== true ) {
				// Error occurred
				echo json_encode( array(
					'result'     => 0,
					'message'    => $validated_type['message'],
					'error_code' => $validated_type['code'],
				) );
				die();
			}
			
			// Validate image dimensions
			$validated_dimensions = $this->validate_image_dimensions( $file_uploader, $_FILES['file'] );
			if ( $validated_dimensions !== true ) {
				// Error occurred
				echo json_encode( array(
					'result'  => 0,
					'message'    => $validated_dimensions['message'],
					'error_code' => $validated_dimensions['code'],
				) );
				die();
			}
			
			// Handle upload
			$file = $_FILES['file'];
			$tmp_dest_file = tempnam( sys_get_temp_dir(), 'alg' );
			if ( $tmp_dest_file === false ) {
				// Error
				echo json_encode( array(
					'result'  => 0,
					'message' => sprintf( 
						__( 'Server\'s temporary directory (%s) is not writeable. If you are the site owner, please check your permissions.', 'checkout-files-upload-woocommerce' ),
						sys_get_temp_dir()
					),
				) );
				die();
			}
			move_uploaded_file( $file['tmp_name'], $tmp_dest_file );
			$file['tmp_name'] = $tmp_dest_file;
			
			if ( $order_id ) {
				// Add to existing order
				$file_key = $this->add_file_to_order( $file_uploader, $file, $order_id );
				$this->maybe_send_admin_notification( 'upload_file', $order_id, $file['name'], $file_uploader, $file_key );
			} else {
				// Add to session
				$_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][] = $file;
				end( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ] );
				$file_key = key( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ] );
			}
			
			// Success
			$template = get_option( 'wpw_cfu_form_template_uploaded_file', '<tr><td colspan="2">%image% %file_name% %remove_button%</td></tr>' );
			echo json_encode( array(
				'result'   => 1,
				'data'     => '<a href="' . esc_url( $this->get_file_download_link( $file_uploader, $file_key, $order_id ) ) . '" data-file-key="' . $file_key . '">' .
					esc_html( $file['name'] ) . '</a>',
				'data_img' => ( false !== strpos( $template, '%image%' ) ? $this->maybe_get_image( $file_uploader, $file_key, $order_id, true ) : '' ),
				'message'  => (
					get_option( 'alg_checkout_files_upload_use_ajax_alert_success_upload', 'no' ) === 'yes' ?
						sprintf(
							( get_option( 'alg_checkout_files_upload_notice_success_upload_' . $file_uploader ) > '' ?
								get_option( 'alg_checkout_files_upload_notice_success_upload_' . $file_uploader )
								: __( 'File "%s" was successfully uploaded.', 'checkout-files-upload-woocommerce' ) ),
							$file['name']
						)
						: ''
				),
			) );
			exit();
			
		} else {
			// Error
			echo json_encode( array(
				'result'  => 0,
				'message' => __( 'Unknown error on upload.', 'checkout-files-upload-woocommerce' )
			) );
			die();
		}
		
		session_write_close();
	}

	/**
	 * enqueue_scripts.
	 *
	 * @version 2.0.0
	 * @since   1.3.0
	 */
	function enqueue_scripts() {
		wp_enqueue_script( 'alg-wc-checkout-files-upload-ajax', alg_wc_checkout_files_upload()->plugin_url() . '/includes/js/alg-wc-checkout-files-upload-ajax.js',
			array( 'jquery' ), alg_wc_checkout_files_upload()->version, false );
		$max_file_size_mb = get_option( 'alg_checkout_files_upload_max_file_size_mb', 0 );
		wp_localize_script( 'alg-wc-checkout-files-upload-ajax', 'ajax_object', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		) );
		wp_localize_script( 'alg-wc-checkout-files-upload-ajax', 'alg_wc_checkout_files_upload', array(
			'max_file_size'                  => $max_file_size_mb * 1024 * 1024,
			'max_file_size_exceeded_message' => str_replace( '%max_file_size%', $max_file_size_mb,
				get_option( 'alg_checkout_files_upload_max_file_size_exceeded_message',
					__( 'Allowed file size exceeded (maximum %max_file_size% MB).', 'checkout-files-upload-woocommerce' ) )
			),
			'progress_bar_enabled'           => ( get_option( 'alg_checkout_files_upload_use_ajax_progress_bar', 'no' ) === 'yes' ),
		) );
		if ( get_option( 'alg_checkout_files_upload_use_ajax_progress_bar', 'no' ) === 'yes' ) {
			wp_enqueue_style( 'alg-wc-checkout-files-upload-ajax', alg_wc_checkout_files_upload()->plugin_url() . '/includes/css/alg-wc-checkout-files-upload-ajax.css',
				array(), alg_wc_checkout_files_upload()->version, 'all' );
		}
	}
	
	/**
	 * add_files_to_email_attachments.
	 *
	 * @version 2.1.0
	 * @since   1.0.0
	 */
	function add_files_to_email_attachments( $attachments, $status, $order ) {
		if (
			(
				$status === 'new_order' && 
				get_option( 'alg_checkout_files_upload_attach_to_admin_new_order', 'yes' ) === 'yes' 
			) ||
			(
				$status === 'customer_processing_order' &&
				get_option( 'alg_checkout_files_upload_attach_to_customer_processing_order', 'no' ) === 'yes'
			)
		) {
			$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $order->id : $order->get_id() );
			$total_files = get_post_meta( $order_id, '_alg_checkout_files_total_files', true );
			for ( $i = 1; $i <= $total_files; $i++ ) {
				$files = get_post_meta( $order_id, '_alg_checkout_files_upload_' . $i, true );
				if ( is_array( $files ) ) {
					foreach ( $files as $file_key => $file ) {
						$attachments[] = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $file['tmp_name'];
					}
				} else {
					// Backwards compatibility for < v2.0.0
					$file_name = get_post_meta( $order_id, '_alg_checkout_files_upload_' . $i, true );
					if ( $file_name > '' ) {
						$attachments[] = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $file_name;
					}
				}
			}
		}
		return $attachments;
	}
	
	/**
	 * add_files_to_order_display.
	 *
	 * @version 2.1.3
	 * @since   1.0.0
	 */
	function add_files_to_order_display( $order ) {
		$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $order->id : $order->get_id() );
		$html = '';
		$has_files = false;
		$total_files = get_post_meta( $order_id, '_alg_checkout_files_total_files', true );
		for ( $i = 1; $i <= $total_files; $i++ ) {
			$files = get_post_meta( $order_id, '_alg_checkout_files_upload_' . $i, true );
			if ( is_array( $files ) ) {
				foreach ( $files as $file_key => $file ) {
					$has_files = true;
					$html .= __( 'File', 'checkout-files-upload-woocommerce' ) . ': ' . esc_html( $file['name'] ) . '<br />';
				}
			} else {
				// Backwards compatibility for < v2.0.0
				$real_file_name = get_post_meta( $order_id, '_alg_checkout_files_upload_real_name_' . $i, true );
				if ( $real_file_name > '' ) {
					$has_files = true;
					$html .= __( 'File', 'checkout-files-upload-woocommerce' ) . ': ' . esc_html( $real_file_name ) . '<br />';
				}
			}
		}
		if ( $has_files ) {
			$html = '<p>' . $html . '</p>';
		}
		echo apply_filters( 'wpw_cfu_add_files_to_order_display_html', $html );
	}
	
	/**
	 * validate_file_type.
	 *
	 * @version 2.1.0
	 * @since   2.1.0
	 * @author  WP Wham
	 *
	 * @param int|string $file_uploader The numeric key of the File Uploader #X to pull settings from.
	 * @param array      $file          The file to validate.
	 *
	 * @return bool|array Returns true on success, or else an array containing an error code and error message.
	 */
	public function validate_file_type( $file_uploader, $file ) {
		$result = true;
		$files_accepted = get_option( 'alg_checkout_files_upload_file_accept_' . $file_uploader, '.jpg,.jpeg,.png' );
		if ( $files_accepted > '' ) {
			$files_accepted = array_map( 'trim', explode( ',', strtolower( $files_accepted ) ) );
			if ( is_array( $files_accepted ) && ! empty( $files_accepted ) ) {
				$file_type = strtolower( '.' . pathinfo( $file['name'], PATHINFO_EXTENSION ) );
				if ( ! in_array( $file_type, $files_accepted ) ) {
					$result = array(
						'code'    => 'fail_file_type',
						'message' => sprintf(
							( get_option( 'alg_checkout_files_upload_notice_wrong_file_type_' . $file_uploader ) > '' ?
								get_option( 'alg_checkout_files_upload_notice_wrong_file_type_' . $file_uploader )
								: __( 'Wrong file type: "%s"', 'checkout-files-upload-woocommerce' ) ),
							$file['name'] 
						),
					);
				}
			}
		}
		return $result;
	}
	
	/**
	 * validate_image_dimensions.
	 *
	 * @version 2.1.0
	 * @since   1.4.1
	 */
	function validate_image_dimensions( $i, $_file ) {
		$result = true;
		$validate_image_dimensions = get_option( 'alg_checkout_files_upload_file_validate_image_dimensions_' . $i, '' );
		if ( $validate_image_dimensions > '' ) {
			// Validate image dimensions
			$file_name     = $_file['name'];
			$tmp_file_name = $_file['tmp_name'];
			$image_size    = getimagesize( $tmp_file_name );
			if ( is_array( $image_size ) && ! empty( $image_size[0] ) && ! empty( $image_size[1] ) ) {
				$w = get_option( 'alg_checkout_files_upload_file_validate_image_dimensions_w_' . $i, 1 );
				$h = get_option( 'alg_checkout_files_upload_file_validate_image_dimensions_h_' . $i, 1 );
				if (
					( 'validate_size'  === $validate_image_dimensions && ( $image_size[0] != $w || $image_size[1] != $h ) )
					|| ( 'validate_ratio' === $validate_image_dimensions && ( $image_size[0] / $image_size[1] != $w / $h ) )
					|| ( 'validate_min' === $validate_image_dimensions && ( $image_size[0] <= $w || $image_size[1] <= $h ) )
					|| ( 'validate_max' === $validate_image_dimensions && ( $image_size[0] >= $w || $image_size[1] >= $h ) )
				) {
					$notice = ( get_option( 'alg_checkout_files_upload_notice_wrong_image_dimensions_' . $i ) > '' ?
						get_option( 'alg_checkout_files_upload_notice_wrong_image_dimensions_' . $i )
						: __( 'Wrong image dimensions for "%s". Current: %current_width% x %current_height%. Required: %required_width% x %required_height%.', 'checkout-files-upload-woocommerce' ) );
					$replaced_values = array(
						'%current_width%'   => $image_size[0],
						'%current_height%'  => $image_size[1],
						'%required_width%'  => $w,
						'%required_height%' => $h,
					);
					$notice = str_replace( array_keys( $replaced_values ), $replaced_values, $notice );
					$result = array(
						'code'    => 'fail_wrong_dimensions',
						'message' => sprintf( $notice, $file_name ),
					);
				}
			} else {
				$result = array(
					'code'    => 'fail_no_dimensions',
					'message' => sprintf(
						( get_option( 'alg_checkout_files_upload_notice_no_image_dimensions_' . $i ) > '' ?
							get_option( 'alg_checkout_files_upload_notice_no_image_dimensions_' . $i )
							: __( 'Couldn\'t get image dimensions: "%s"', 'checkout-files-upload-woocommerce' ) ),
						$file_name 
					),
				);
			}
		}
		return $result;
	}

	/**
	 * validate_on_checkout.
	 *
	 * @version 2.1.0
	 * @since   1.0.0
	 */
	function validate_on_checkout( $posted ) {
		
		// Maybe start session
		$local_session_started = false;
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
			$local_session_started = true;
		}
		
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if (
				'yes' === get_option( 'alg_checkout_files_upload_enabled_' . $i, 'yes' ) &&
				$this->is_visible( $i ) &&
				'disable' != get_option( 'alg_checkout_files_upload_hook_' . $i, 'woocommerce_before_checkout_form' )
			) {
			
				// validate if file is required
				if (
					get_option( 'alg_checkout_files_upload_required_' . $i, 'no' ) === 'yes' &&
					( ! isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ||
					count( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) < 1 )
				) {
					wc_add_notice(
						( get_option( 'alg_checkout_files_upload_notice_required_' . $i ) > '' ?
							get_option( 'alg_checkout_files_upload_notice_required_' . $i )
							: __( 'File is required.', 'checkout-files-upload-woocommerce' ) ),
						'error' 
					);
					continue;
				}
				if (
					! isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ||
					count( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) < 1
				) {
					// No files uploaded nor required, skip further validations.
					continue;
				}
				
				// Validate file type
				$passed_file_types = true;
				foreach ( $_SESSION[ 'alg_checkout_files_upload_' . $i ] as $file ) {
					$validated_type = $this->validate_file_type( $i, $file );
					if ( $validated_type !== true ) {
						wc_add_notice( $validated_type['message'], 'error' );
						$passed_file_types = false;
					}
				}
				if ( ! $passed_file_types ) {
					continue;
				}
				
				// Maybe validate image dimensions
				$passed_image_dimensions = true;
				foreach ( $_SESSION[ 'alg_checkout_files_upload_' . $i ] as $file ) {
					$validated_dimensions = $this->validate_image_dimensions( $i, $file );
					if ( $validated_dimensions !== true ) {
						wc_add_notice( $validated_dimensions['message'], 'error' );
						$passed_image_dimensions = false;
					}
				}
				if ( ! $passed_image_dimensions ) {
					continue;
				}
				
			}
		}
		
		if ( $local_session_started ) {
			session_write_close();
		}
	}

	/**
	 * add_file_admin_order_meta_box.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_file_admin_order_meta_box() {
		$screen   = 'shop_order';
		$context  = 'side';
		$priority = 'high';
		add_meta_box(
			'alg_wc_checkout_files_upload_metabox',
			__( 'Uploaded Files', 'checkout-files-upload-woocommerce' ),
			array( $this, 'create_file_admin_order_meta_box' ),
			$screen,
			$context,
			$priority
		);
	}

	/**
	 * create_file_admin_order_meta_box.
	 *
	 * @version 2.1.2
	 * @since   1.0.0
	 */
	public function create_file_admin_order_meta_box() {
		$order_id = get_the_ID();
		$html = '';
		$total_files = get_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', true );
		$files_exists = false;
		$allow_delete = apply_filters( 'wpwham_checkout_files_upload_allow_admin_delete_files', true );
		for ( $i = 1; $i <= $total_files; $i++ ) {
			$files = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
			if ( is_array( $files ) ) {
				foreach ( $files as $file_key => $file ) {
					$files_exists = true;
					$html .= '<tr>' .
						'<td style="width:' . ( $allow_delete ? '139' : '174' ) . 'px; word-break: break-word;">' .
						'<a href="' . esc_url( $this->get_file_download_link( $i, $file_key, $order_id, false, false ) ) . '">' .
						esc_html( $file['name'] ) . '</a>' .
						'</td>' .
						'<td style="width:' . ( $allow_delete ? '105' : '70' ) . 'px;">' .
						( $allow_delete ?
							'<a href="' . esc_url( $this->get_file_delete_link( $i, $file_key, $order_id ) ) . '" ' .
							'class="button wpwham-checkout-files-upload-file-delete-button"' .
							'style="padding: 0 5px; line-height: 30px; text-decoration: none;">' .
							'<span class="dashicons dashicons-trash" style="line-height: 30px; font-size: 16px;"></span>' .
							'</a>&nbsp;'
							: ''
						) .
						'<a href="' . esc_url( $this->get_file_download_link( $i, $file_key, $order_id, false, false ) ) . '" ' .
						'class="button" ' .
						'style="padding: 0 5px; line-height: 30px; text-decoration: none;" ' .
						'target="_blank">' .
						'<span class="dashicons dashicons-external" style="line-height: 30px; font-size: 16px;"></span>' .
						'</a>&nbsp;' .
						'<a href="' . esc_url( $this->get_file_download_link( $i, $file_key, $order_id, false, true ) ) . '" ' .
						'class="button" ' .
						'style="padding: 0 5px; line-height: 30px; text-decoration: none;">' .
						'<span class="dashicons dashicons-download" style="line-height: 30px; font-size: 16px;"></span>' .
						'</a>' .
						'</td>' .
						'</tr>';
				}
			} else {
				// backwards compatibility for orders created < v2.0.0
				$order_file_name = $files;
				$real_file_name  = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
				if ( '' != $order_file_name ) {
					$files_exists = true;
					$html .= '<tr>' .
						'<td style="width:139px; word-break: break-word;">' .
						'<a href="' . esc_url( $this->get_file_download_link( $i, null, $order_id, false, false ) ) . '">' .
						esc_html( $real_file_name ) . '</a>' .
						'</td>' .
						'<td style="width:105px;">' .
						'<a href="' . esc_url( $this->get_file_delete_link( $i, null, $order_id ) ) . '" ' .
						'class="button wpwham-checkout-files-upload-file-delete-button"' .
						'style="padding: 0 5px; line-height: 30px; text-decoration: none;">' .
						'<span class="dashicons dashicons-trash" style="line-height: 30px; font-size: 16px;"></span>' .
						'</a>&nbsp;' .
						'<a href="' . esc_url( $this->get_file_download_link( $i, null, $order_id, false, false ) ) . '" ' .
						'class="button" ' .
						'style="padding: 0 5px; line-height: 30px; text-decoration: none;" ' .
						'target="_blank">' .
						'<span class="dashicons dashicons-external" style="line-height: 30px; font-size: 16px;"></span>' .
						'</a>&nbsp;' .
						'<a href="' . esc_url( $this->get_file_download_link( $i, null, $order_id, false, true ) ) . '" ' .
						'class="button" ' .
						'style="padding: 0 5px; line-height: 30px; text-decoration: none;">' .
						'<span class="dashicons dashicons-download" style="line-height: 30px; font-size: 16px;"></span>' .
						'</a>' .
						'</td>' .
						'</tr>';
				}
			}
		}
		if ( $files_exists ) {
			echo '<table>';
			echo $html;
			echo '</table>';
		} else {
			echo '<p><em>' . __( 'No files uploaded.', 'checkout-files-upload-woocommerce' ) . '</em></p>';
		}
	}

	/**
	 * add_files_to_order.
	 *
	 * @version 2.0.0
	 * @since   1.0.0
	 */
	function add_files_to_order( $order_id, $posted ) {
		
		// Always start session for this
		@session_start();
		
		$upload_dir = alg_get_alg_uploads_dir( 'checkout_files_upload' );
		if ( ! file_exists( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if ( isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ) {
				foreach ( $_SESSION[ 'alg_checkout_files_upload_' . $i ] as $file_key => $file ) {
					$file_name          = $file['name'];
					$ext                = pathinfo( $file_name, PATHINFO_EXTENSION );
					$download_file_name = $order_id . '_' . $i . '_' . $file_key . '.' . $ext;
					$file_path          = $upload_dir . '/' . $download_file_name;
					$tmp_file_name      = $file['tmp_name'];
					$file_data          = file_get_contents( $tmp_file_name );
					file_put_contents( $file_path, $file_data );
					unlink( $tmp_file_name );
					$_SESSION[ 'alg_checkout_files_upload_' . $i ][ $file_key ]['tmp_name'] = $download_file_name;
				}
				update_post_meta(
					$order_id,
					'_' . 'alg_checkout_files_upload_' . $i,
					$_SESSION[ 'alg_checkout_files_upload_' . $i ]
				);
			}
			unset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] );
		}
		
		// this is not really the "total files", but rather the total # of file uploaders
		update_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', $total_number );
		
		session_write_close();
	}
	
	/**
	 * add_file_to_order.
	 *
	 * Unlike add_files_to_order(), adds only a single file at a time.
	 * Needed for uploads from My Account / Thank You pages.
	 *
	 * @version 2.0.0
	 * @since   2.0.0
	 */
	function add_file_to_order( $file_uploader, $file, $order_id ) {
		
		$upload_dir = alg_get_alg_uploads_dir( 'checkout_files_upload' );
		if ( ! file_exists( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		
		$files = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader, true );
		if ( ! is_array( $files ) ) {
			$files = array();
		}
		$files[] = $file;
		end( $files );
		$file_key = key( $files );
		
		$file_name          = $file['name'];
		$ext                = pathinfo( $file_name, PATHINFO_EXTENSION );
		$download_file_name = $order_id . '_' . $file_uploader . '_' . $file_key . '.' . $ext;
		$file_path          = $upload_dir . '/' . $download_file_name;
		$tmp_file_name      = $file['tmp_name'];
		$file_data          = file_get_contents( $tmp_file_name );
		file_put_contents( $file_path, $file_data );
		unlink( $tmp_file_name );
		$files[ $file_key ]['tmp_name'] = $download_file_name;
		
		update_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader, $files );
		
		// this is not really the "total files", but rather the total # of file uploaders
		update_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', $total_number );
		
		return $file_key;
	}

	/**
	 * process_checkout_files_upload.
	 *
	 * @version 2.1.0
	 * @since   1.0.0
	 */
	function process_checkout_files_upload() {
		
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		
		// Maybe start session
		$session_needed = false;
		$local_session_started = false;
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if (
				isset( $_GET['alg_download_checkout_file'] ) ||
				isset( $_GET['wpw_cfu_download_file_uploader'] )
			) {
				$session_needed = true;
				break;
			}
		}
		if ( $session_needed ) {
			session_start();
			$local_session_started = true;
		}
		
		// File download (files from < v2.0.0)
		if ( isset( $_GET['alg_download_checkout_file'] ) && isset( $_GET['_wpnonce'] ) && ( false !== wp_verify_nonce( $_GET['_wpnonce'], 'alg_download_checkout_file' ) ) ) {
			$i = sanitize_text_field( $_GET['alg_download_checkout_file'] );
			if ( ! empty( $_GET['alg_download_checkout_file_order_id'] ) ) {
				$order_id = sanitize_text_field( $_GET['alg_download_checkout_file_order_id'] );
				if ( ! ( $order = wc_get_order( $order_id ) ) ) {
					return;
				}
				if ( isset( $_GET['key'] ) ) {
					// Thank you page
					if ( ! $order->key_is_valid( $_GET['key'] ) ) {
						return;
					}
				} elseif ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( ! is_user_logged_in() || $order->get_customer_id() != get_current_user_id() ) {
						return;
					}
				}
				$order_file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
				$tmp_file_name   = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file_name;
				$file_name       = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
			} else {
				$tmp_file_name   = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'];
				$file_name       = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['name'];
			}
			
			$mime_type = false;
			if ( function_exists( 'finfo_open' ) ) {
				$finfo = finfo_open( FILEINFO_MIME_TYPE );
				$mime_type = finfo_file( $finfo, $tmp_file_name );
				finfo_close( $finfo );
			} elseif ( function_exists( 'mime_content_type' ) ) {
				$mime_type = mime_content_type( $tmp_file_name );
			}
			
			// clean all levels of output buffering
			// this is needed in case some 3rd-party plugin interferes with the output buffer
			// see WP Wham ticket #25396
			// props to @Márton Báhidszki https://stackoverflow.com/questions/37205855/php-readfile-bad-charset
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			
			header( "Expires: 0" );
			header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
			header( "Cache-Control: private", false );
			if ( isset( $_GET['force_download'] ) && $_GET['force_download'] === '1' ) {
				header( 'Content-disposition: attachment; filename="' . $file_name . '"' );
			}
			if ( $mime_type ) {
				header( "Content-type: $mime_type" );
			}
			header( "Content-Transfer-Encoding: binary" );
			header( "Content-Length: ". filesize( $tmp_file_name ) );
			readfile( $tmp_file_name );
			exit();
		}
		
		// File download (files from >= v2.0.0)
		if (
			isset( $_GET['wpw_cfu_download_file_uploader'] ) &&
			isset( $_GET['wpw_cfu_download_file_key'] ) &&
			isset( $_GET['_wpnonce'] ) &&
			wp_verify_nonce( $_GET['_wpnonce'], 'alg_download_checkout_file' ) !== false
		) {
			$file_uploader = sanitize_text_field( $_GET['wpw_cfu_download_file_uploader'] );
			$file_key      = sanitize_text_field( $_GET['wpw_cfu_download_file_key'] );
			if ( ! empty( $_GET['wpw_cfu_download_file_order_id'] ) ) {
				$order_id = sanitize_text_field( $_GET['wpw_cfu_download_file_order_id'] );
				if ( ! ( $order = wc_get_order( $order_id ) ) ) {
					return;
				}
				if ( isset( $_GET['key'] ) ) {
					// Thank you page
					if ( ! $order->key_is_valid( $_GET['key'] ) ) {
						return;
					}
				} elseif ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( ! is_user_logged_in() || $order->get_customer_id() != get_current_user_id() ) {
						return;
					}
				}
				$order_files     = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader, true );
				$order_file      = $order_files[ $file_key ];
				$tmp_file_name   = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file['tmp_name'];
				$file_name       = $order_file['name'];
			} else {
				$tmp_file_name   = $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['tmp_name'];
				$file_name       = $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['name'];
			}
			
			$mime_type = false;
			if ( function_exists( 'finfo_open' ) ) {
				$finfo = finfo_open( FILEINFO_MIME_TYPE );
				$mime_type = finfo_file( $finfo, $tmp_file_name );
				finfo_close( $finfo );
			} elseif ( function_exists( 'mime_content_type' ) ) {
				$mime_type = mime_content_type( $tmp_file_name );
			}
			
			// clean all levels of output buffering
			// this is needed in case some 3rd-party plugin interferes with the output buffer
			// see WP Wham ticket #25396
			// props to @Márton Báhidszki https://stackoverflow.com/questions/37205855/php-readfile-bad-charset
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			
			header( "Expires: 0" );
			header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
			header( "Cache-Control: private", false );
			if ( isset( $_GET['force_download'] ) && $_GET['force_download'] === '1' ) {
				header( 'Content-disposition: attachment; filename="' . $file_name . '"' );
			}
			if ( $mime_type ) {
				header( "Content-type: $mime_type" );
			}
			header( "Content-Transfer-Encoding: binary" );
			header( "Content-Length: ". filesize( $tmp_file_name ) );
			readfile( $tmp_file_name );
			exit();
		}
		
		// File delete (files from < v2.0.0)
		if (
			isset( $_GET['alg_download_checkout_file'] )
			&& isset( $_GET['_wpnonce'] )
			&& wp_verify_nonce( $_GET['_wpnonce'], 'wpwham_cfu_checkout_file_delete' ) !== false
			&& apply_filters( 'wpwham_checkout_files_upload_allow_admin_delete_files', true )
		) {
			$i = sanitize_text_field( $_GET['alg_download_checkout_file'] );
			if ( ! empty( $_GET['alg_download_checkout_file_order_id'] ) ) {
				$order_id = sanitize_text_field( $_GET['alg_download_checkout_file_order_id'] );
				if ( ! ( $order = wc_get_order( $order_id ) ) ) {
					return;
				}
				if ( isset( $_GET['key'] ) ) {
					// Thank you page
					if ( ! $order->key_is_valid( $_GET['key'] ) ) {
						return;
					}
				} elseif ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( ! is_user_logged_in() || $order->get_customer_id() != get_current_user_id() ) {
						return;
					}
				}
				$order_file_name = get_post_meta( $order_id, '_alg_checkout_files_upload_' . $i, true );
				if ( ! $order_file_name ) {
					return;
				}
				$tmp_file_name = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file_name;
			} else {
				if ( ! isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'] ) ) {
					return;
				}
				$tmp_file_name = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'];
			}
			
			if ( unlink( $tmp_file_name ) ) {
				if ( $order_id ) {
					delete_post_meta( $order_id, '_alg_checkout_files_upload_' . $i );
					delete_post_meta( $order_id, '_alg_checkout_files_upload_real_name_' . $i );
				}
				unset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] );
			}
		}
		
		// File delete (files from >= v2.0.0)
		if (
			isset( $_GET['wpw_cfu_download_file_uploader'] ) &&
			isset( $_GET['wpw_cfu_download_file_key'] ) &&
			isset( $_GET['_wpnonce'] ) &&
			wp_verify_nonce( $_GET['_wpnonce'], 'wpwham_cfu_checkout_file_delete' ) !== false
			&& apply_filters( 'wpwham_checkout_files_upload_allow_admin_delete_files', true )
		) {
			$file_uploader = sanitize_text_field( $_GET['wpw_cfu_download_file_uploader'] );
			$file_key      = sanitize_text_field( $_GET['wpw_cfu_download_file_key'] );
			if ( ! empty( $_GET['wpw_cfu_download_file_order_id'] ) ) {
				$order_id = sanitize_text_field( $_GET['wpw_cfu_download_file_order_id'] );
				if ( ! ( $order = wc_get_order( $order_id ) ) ) {
					return;
				}
				if ( isset( $_GET['key'] ) ) {
					// Thank you page
					if ( ! $order->key_is_valid( $_GET['key'] ) ) {
						return;
					}
				} elseif ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( ! is_user_logged_in() || $order->get_customer_id() != get_current_user_id() ) {
						return;
					}
				}
				$order_files = get_post_meta( $order_id, '_alg_checkout_files_upload_' . $file_uploader, true );
				if ( ! isset( $order_files[ $file_key ] ) ) {
					return;
				}
				$order_file    = $order_files[ $file_key ];
				$tmp_file_name = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file['tmp_name'];
			} else {
				if ( ! isset( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ] ) ) {
					return;
				}
				$tmp_file_name = $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['tmp_name'];
			}
			
			if ( unlink( $tmp_file_name ) ) {
				if ( $order_files ) {
					unset( $order_files[ $file_key ] );
					update_post_meta( $order_id, '_alg_checkout_files_upload_' . $file_uploader, $order_files );
				}
				unset( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ] );
			}
		}
		
		if ( $local_session_started ) {
			session_write_close();
		}
	}

	/**
	 * is_visible.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function is_visible( $i, $order_id = 0 ) {
		// Include by product id
		$products_in = get_option( 'alg_checkout_files_upload_show_products_in_' . $i, '' );
		if ( ! empty( $products_in ) ) {
			$do_skip_by_products = true;
			if ( 0 != $order_id ) {
				$the_order = wc_get_order( $order_id );
				$the_items = $the_order->get_items();
			} else {
				$the_items = WC()->cart->get_cart();
			}
			foreach ( $the_items as $cart_item_key => $values ) {
				if ( in_array( $values['product_id'], $products_in ) ) {
					$do_skip_by_products = false;
					break;
				}
			}
			if ( $do_skip_by_products ) return false;
		}
		// Include by product category
		$categories_in = get_option( 'alg_checkout_files_upload_show_cats_in_' . $i, '' );
		if ( ! empty( $categories_in ) ) {
			$do_skip_by_cats = true;
			if ( 0 != $order_id ) {
				$the_order = wc_get_order( $order_id );
				$the_items = $the_order->get_items();
			} else {
				$the_items = WC()->cart->get_cart();
			}
			foreach ( $the_items as $cart_item_key => $values ) {
				$product_categories = get_the_terms( $values['product_id'], 'product_cat' );
				if ( empty( $product_categories ) ) continue;
				foreach( $product_categories as $product_category ) {
					if ( in_array( $product_category->term_id, $categories_in ) ) {
						$do_skip_by_cats = false;
						break;
					}
				}
				if ( ! $do_skip_by_cats ) break;
			}
			if ( $do_skip_by_cats ) return false;
		}
		// Include by product tag
		$tags_in = get_option( 'alg_checkout_files_upload_show_tags_in_' . $i, '' );
		if ( ! empty( $tags_in ) ) {
			$do_skip_by_tags = true;
			if ( 0 != $order_id ) {
				$the_order = wc_get_order( $order_id );
				$the_items = $the_order->get_items();
			} else {
				$the_items = WC()->cart->get_cart();
			}
			foreach ( $the_items as $cart_item_key => $values ) {
				$product_tags = get_the_terms( $values['product_id'], 'product_tag' );
				if ( empty( $product_tags ) ) continue;
				foreach( $product_tags as $product_tag ) {
					if ( in_array( $product_tag->term_id, $tags_in ) ) {
						$do_skip_by_tags = false;
						break;
					}
				}
				if ( ! $do_skip_by_tags ) break;
			}
			if ( $do_skip_by_tags ) return false;
		}
		return true;
	}

	/**
	 * get_the_form_part_label.
	 *
	 * @version 2.1.0
	 * @since   1.3.0
	 * @todo    [dev] add `do_shortcode()` to all notices
	 */
	function get_the_form_part_label( $i ) {
		$label = get_option( 'alg_checkout_files_upload_label_' . $i ) > '' ?
			get_option( 'alg_checkout_files_upload_label_' . $i )
			: __( 'Please select file to upload', 'checkout-files-upload-woocommerce' );
		$label = do_shortcode( $label );
		if ( $label > '' ) {
			$template = get_option( 'alg_checkout_files_upload_form_template_label',
				'<tr><td colspan="2"><label for="%field_id%">%field_label%</label>%required_html%</td></tr>' );
			$required_html = ( 'yes' === get_option( 'alg_checkout_files_upload_required_' . $i, 'no' ) ) ?
				'&nbsp;<abbr class="required" title="required">*</abbr>' : '';
			return str_replace(
				array( '%field_id%', '%field_label%', '%required_html%' ),
				array( 'alg_checkout_files_upload_' . $i, $label, $required_html ),
				$template
			);
		}
		return $label;
	}

	/**
	 * maybe_get_image.
	 *
	 * @version 2.1.2
	 * @since   1.4.0
	 * @todo    [dev] use another way to check if it's an image (i.e. not `getimagesize()`)
	 */
	function maybe_get_image( $file_uploader, $file_key = null, $order_id = 0, $add_timestamp = false ) {
		
		// Maybe start session
		$local_session_started = false;
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
			$local_session_started = true;
		}
		
		$tmp_file_name = false;
		if ( 0 != $order_id ) {
			$passed = false;
			if ( $order = wc_get_order( $order_id ) ) {
				if ( ! empty( $_REQUEST['key'] ) ) {
					// Thank you page
					if ( $order->key_is_valid( $_REQUEST['key'] ) ) {
						$passed = true;
					}
				} else {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( is_user_logged_in() && $order->get_customer_id() == get_current_user_id() ) {
						$passed = true;
					}
				}
			}
			if ( $passed ) {
				$files = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_uploader, true );
				if ( $file_key !== null ) {
					$tmp_file_name = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $files[ $file_key ]['tmp_name'];
				} else {
					// Backwards compatibility for < v2.0.0
					$tmp_file_name = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $files;
				}
			}
		} elseif ( isset( $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['tmp_name'] ) ) {
			$tmp_file_name = $_SESSION[ 'alg_checkout_files_upload_' . $file_uploader ][ $file_key ]['tmp_name'];
		}
		
		if ( $local_session_started ) {
			session_write_close();
		}
		
		if ( $tmp_file_name && @is_array( getimagesize( $tmp_file_name ) ) ) {
			$image = '<img style="display: inline-block; margin: 10px; ' . get_option( 'alg_checkout_files_upload_form_image_style', 'width:64px;' ) . '" src="' . esc_url( $this->get_file_download_link( $file_uploader, $file_key, $order_id, $add_timestamp ) ) . '">';
		} else {
			$image = '<img style="display: inline-block; margin: 10px; ' . get_option( 'alg_checkout_files_upload_form_image_style', 'width:64px;' ) . '" src="' . alg_wc_checkout_files_upload()->plugin_url() . '/assets/images/default_file_image.png' . '">';
		}
		
		return apply_filters( 'wpw_cfu_maybe_get_image', $image, $tmp_file_name );
	}

	/**
	 * get_the_form.
	 *
	 * @version 2.1.4
	 * @since   1.3.0
	 * @todo    [feature] more options for "delete" button styling (i.e. `&times;`)
	 */
	public function get_the_form( $file_uploader, $files, $order_id = 0 ) {
		
		$html = '';
		$html .= '<div id="alg_checkout_files_upload_form_' . $file_uploader . '">';
		$html .= get_option( 'alg_checkout_files_upload_form_template_before', '<table>' );
		$html .= $this->get_the_form_part_label( $file_uploader );
		
		$button_html = '';
		$button_html .= '<div style="margin-bottom:5px;">';
		$button_html .= '<input type="button" ' .
			'id="alg_checkout_files_upload_button_' . $file_uploader . '" ' .
			'class="alg_checkout_files_upload_button" ' .
			'data-file-uploader="' . $file_uploader . '" ' .
			'value="' . ( get_option( 'alg_checkout_files_upload_label_button_single_' . $file_uploader ) > '' ?
				get_option( 'alg_checkout_files_upload_label_button_single_' . $file_uploader )
				: __( 'Choose File', 'checkout-files-upload-woocommerce' ) ) . '" ' .
			'style="' . ( $files ? 'display: none;' : '' ). '" />';
		$button_html .= '<input type="file" data-file-uploader="' . $file_uploader . '" ' .
			'name="alg_checkout_files_upload_' . $file_uploader . '" ' .
			'id="alg_checkout_files_upload_' . $file_uploader . '" ' .
			'class="alg_checkout_files_upload_file_input" ' .
			'accept="' . get_option( 'alg_checkout_files_upload_file_accept_' . $file_uploader, '.jpg,.jpeg,.png' ) . '" ' .
			'style="display: none;">';
		$button_html .= '</div>';
		
		$field_html = '';
		$field_html .= '<div id="alg_checkout_files_upload_result_' . $file_uploader . '" ' .
			'class="alg_checkout_files_upload_result_' . $file_uploader . '" ' .
			'data-file-uploader="' . $file_uploader . '" ' .
			'style="display: none; margin-bottom: 5px;">';
		$uploaded_file_template = get_option(
			'wpw_cfu_form_template_uploaded_file',
			'%image% %file_name% %remove_button%'
		);
		$replaced_values = array(
			'%image%'         => '<span class="alg_checkout_files_upload_result_image" ' .
				'style="vertical-align: middle;"></span> ',
			'%file_name%'     => '<span class="alg_checkout_files_upload_result_file_name" ' .
				'style="vertical-align: middle;"></span> ',
			'%remove_button%' => '<a href="" class="alg_checkout_files_upload_result_delete" ' .
				'style="vertical-align: middle;' . get_option( 'alg_checkout_files_upload_form_style_ajax_delete', 'color:red;' ) . '" ' .
				'title="' . __( 'Remove', 'checkout-files-upload-woocommerce' ) . '">&times;</a>',
		);
		$field_html .= str_replace( array_keys( $replaced_values ), $replaced_values, $uploaded_file_template );
		$field_html .= '</div>';
		if ( is_array( $files ) ) {
			foreach ( $files as $file_key => $file ) {
				$field_html .= '<div class="alg_checkout_files_upload_result_' . $file_uploader . '" ' .
					'data-file-uploader="' . $file_uploader . '" ' .
					'style="margin-bottom: 5px;">';
				$replaced_values['%image%'] = strpos( $uploaded_file_template, '%image%' ) !== false ?
					'<span class="alg_checkout_files_upload_result_image" style="vertical-align: middle;">' . 
					$this->maybe_get_image( $file_uploader, $file_key, $order_id ) .
					'</span>'
					: '';
				$replaced_values['%file_name%'] = strpos( $uploaded_file_template, '%file_name%' ) !== false ?
					'<span class="alg_checkout_files_upload_result_file_name" style="vertical-align: middle;">' .
					'<a href="' . esc_url( $this->get_file_download_link( $file_uploader, $file_key, $order_id ) ) . '" ' .
					'data-file-key="' . $file_key . '" target="_blank">' .
					esc_html( $file['name'] ) .
					'</a>' .
					'</span> '
					: '';
				$field_html .= str_replace( array_keys( $replaced_values ), $replaced_values, $uploaded_file_template );
				$field_html .= '</div>';
			}
		} elseif ( $files > '' ) {
			// Backwards compatibility for < v2.0.0
			$field_html .= '<div class="alg_checkout_files_upload_result_' . $file_uploader . '" ' .
				'data-file-uploader="' . $file_uploader . '" ' .
				'style="margin-bottom: 5px;">';
			$replaced_values['%image%'] = strpos( $uploaded_file_template, '%image%' ) !== false ?
				'<span class="alg_checkout_files_upload_result_image" style="vertical-align: middle;">' . 
				$this->maybe_get_image( $file_uploader, null, $order_id ) .
				'</span>'
				: '';
			$replaced_values['%file_name%'] = strpos( $uploaded_file_template, '%file_name%' ) !== false ?
				'<span class="alg_checkout_files_upload_result_file_name" style="vertical-align: middle;">' .
				'<a href="' . esc_url( $this->get_file_download_link( $file_uploader, null, $order_id ) ) . '" ' .
				'data-file-key="false" target="_blank">' .
				esc_html( $files ) .
				'</a>' .
				'</span> '
				: '';
			$field_html .= str_replace( array_keys( $replaced_values ), $replaced_values, $uploaded_file_template );
			$field_html .= '</div>';
		}
		
		$field_template = get_option(
			'alg_checkout_files_upload_form_template_field_ajax',
			'<tr><td colspan="2">%button_html% %field_html%</td></tr>'
		);
		$replaced_values = array(
			'%button_html%' => $button_html,
			'%field_html%'  => $field_html,
			'%image%'       => '', // as of v2.0.0, if %image% is here it is no longer used. Use "Uploaded File" template instead.
		);
		$html .= str_replace( array_keys( $replaced_values ), $replaced_values, $field_template );
		
		$html .= get_option( 'alg_checkout_files_upload_form_template_after', '</table>' );
		$html .= '<input type="hidden" id="alg_checkout_files_upload_order_id_' . $file_uploader  . '"  name="alg_checkout_files_upload_order_id_' . $file_uploader  . '" value="' . $order_id . '">';
		$html .= '<input type="hidden" id="alg_checkout_files_upload_order_key_' . $file_uploader . '"  name="alg_checkout_files_upload_order_key_' . $file_uploader . '" value="' .
			( isset( $_REQUEST['key'] ) ? esc_html( $_REQUEST['key'] ) : 0 ) . '">';
		$html .= '<input type="hidden" id="wpwham-checkout-files-upload-nonce-' . $file_uploader  . '"  name="wpwham-checkout-files-upload-nonce-' . $file_uploader  . '" value="' . wp_create_nonce( 'wpwham-checkout-files-upload-' . $order_id ) . '">';
		$html .= '</div>';
		if ( get_option( 'alg_checkout_files_upload_use_ajax_progress_bar', 'no' ) === 'yes' ) {
			$html .= '<div id="alg-wc-checkout-files-upload-progress-wrapper-' . $file_uploader . '" ' .
				'class="alg-wc-checkout-files-upload-progress-wrapper" ' .
				'style="display: none;">' .
				'<div class="alg-wc-checkout-files-upload-progress-bar" style=""></div>' .
				'<div class="alg-wc-checkout-files-upload-progress-status">0%</div>' .
			'</div>';
		}
		
		$html = apply_filters( 'wpw_checkout_files_upload_form_html', $html );
		$html = apply_filters( 'wpw_checkout_files_upload_form_ajax_html', $html ); // for backwards compatibility
		return $html;
	}

	/**
	 * add_files_upload_form_to_thankyou_and_myaccount_page.
	 *
	 * @version 2.0.0
	 * @since   1.0.0
	 */
	function add_files_upload_form_to_thankyou_and_myaccount_page( $order_id ) {
		$html = '';
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		$current_filter = current_filter();
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if ( 'yes' === get_option( 'alg_checkout_files_upload_enabled_' . $i, 'yes' ) && $this->is_visible( $i, $order_id ) ) {
				if (
					( 'yes' === get_option( 'alg_checkout_files_upload_add_to_thankyou_'  . $i, 'no' ) && 'woocommerce_thankyou'   === $current_filter ) ||
					( 'yes' === get_option( 'alg_checkout_files_upload_add_to_myaccount_' . $i, 'no' ) && 'woocommerce_view_order' === $current_filter )
				) {
					$files     = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
					$file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
					if ( is_array( $files ) ) {
						$html .= $this->get_the_form( $i, $files, $order_id );
					} elseif ( $file_name ) {
						// has legacy (< v2.0.0 uploaded file)
						$html .= $this->get_the_form( $i, $file_name, $order_id );
					} else {
						// no files uploaded to this order
						$html .= $this->get_the_form( $i, array(), $order_id );
					}
				}
			}
		}
		echo $html;
	}

	/**
	 * add_files_upload_form_to_checkout_frontend.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_files_upload_form_to_checkout_frontend() {
		$this->add_files_upload_form_to_checkout_frontend_all();
	}

	/**
	 * add_files_upload_form_to_checkout_frontend_all.
	 *
	 * @version 2.0.0
	 * @since   1.0.0
	 */
	function add_files_upload_form_to_checkout_frontend_all( $is_direct_call = false ) {
		
		// Maybe start session
		$local_session_started = false;
		if ( ! session_id() && ! headers_sent() ) {
			session_start();
			$local_session_started = true;
		}
		
		$html = '';
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		if ( ! $is_direct_call ) {
			$current_filter = current_filter();
			$current_filter_priority = alg_current_filter_priority();
		}
		for ( $i = 1; $i <= $total_number; $i++ ) {
			$is_filter_ok = ( $is_direct_call ) ? true : (
				$current_filter === get_option( 'alg_checkout_files_upload_hook_' . $i, 'woocommerce_before_checkout_form' ) &&
				$current_filter_priority == get_option( 'alg_checkout_files_upload_hook_priority_' . $i, 20 )
			);
			if (
				get_option( 'alg_checkout_files_upload_enabled_' . $i, 'yes' ) === 'yes' &&
				$is_filter_ok &&
				$this->is_visible( $i )
			) {
				$files = isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ? $_SESSION[ 'alg_checkout_files_upload_' . $i ] : false;
				$html .= $this->get_the_form( $i, $files );
			}
		}
		echo $html;
		
		if ( $local_session_started ) {
			session_write_close();
		}
	}

}

endif;

return new Alg_WC_Checkout_Files_Upload_Main();
