/**
 * Checkout Files Upload for WooCommerce - admin scripts
 *
 * @version 2.1.0
 * @since   2.1.0
 * @author  WP Wham
 */

(function( $ ){
	
	$( document ).ready( function(){
		
		/*
		 * post metabox
		 */
		$( '.wpwham-checkout-files-upload-file-delete-button' ).on( 'click', function(){
			return confirm( wpwham_checkout_files_upload_admin.i18n.confirmation_message );
		});
		
		/*
		 * settings page - File Uploader #X
		 */
		var toggleImageWidthHeight = function() {
			var fileUploader = false;
			var show = false;
			$( '.alg_checkout_files_upload_file_validate_image_dimensions' ).each( function(){
				fileUploader = $( this ).data( 'file-uploader' );
				if ( $( this ).val() > '' ) {
					show = true;
					return false;
				}
			});
			if ( show ) {
				$( '#alg_checkout_files_upload_file_validate_image_dimensions_w_' + fileUploader ).closest( 'tr' ).show();
				$( '#alg_checkout_files_upload_file_validate_image_dimensions_h_' + fileUploader ).closest( 'tr' ).show();
			} else {
				$( '#alg_checkout_files_upload_file_validate_image_dimensions_w_' + fileUploader ).closest( 'tr' ).hide();
				$( '#alg_checkout_files_upload_file_validate_image_dimensions_h_' + fileUploader ).closest( 'tr' ).hide();
			}
		}
		$( '.alg_checkout_files_upload_file_validate_image_dimensions' ).on( 'change', toggleImageWidthHeight );
		toggleImageWidthHeight();
		
	});
	
})( jQuery );
