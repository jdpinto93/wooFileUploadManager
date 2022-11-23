<?php
 /**
 * Plugin Name:       Field Upload
 * Plugin URI:        http://www.webmasteryagency.com
 * Description:       Añade un boton en el formulario de pago, en la pagona de gracias por tu compra o en la cuenta del usuario para que pueda subir su comprobante de pago, el cual tu puedas luego ver desde el panel de pedidos de woocommerce
 * Version:           1.1.3
 * Requires at least: 5.2
 * Requires PHP:      7.2.2
 * Author:            Jose Pinto
 * Author URI:        http://www.webmasteryagency.com
 * License:           GPL v3 or later
 * Domain Path: /lang
 * Text Domain _JPinto
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION', '2.1.4' );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION', '2' );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_PATH' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_FILE' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_FILE', __FILE__ );
}

/**
 * Update scripts
 */
require_once( plugin_dir_path( __FILE__ ) . 'includes/checkout-files-upload-woocommerce-update.php' );

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload' ) ) :

/**
 * Main Alg_WC_Checkout_Files_Upload Class
 *
 * @class   Alg_WC_Checkout_Files_Upload
 * @version 2.1.4
 * @since   1.0.0
 */
final class Alg_WC_Checkout_Files_Upload {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $version = '2.1.4';

	/**
	 * @var   Alg_WC_Checkout_Files_Upload The single instance of the class
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main Alg_WC_Checkout_Files_Upload Instance
	 *
	 * Ensures only one instance of Alg_WC_Checkout_Files_Upload is loaded or can be loaded.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @static
	 * @return  Alg_WC_Checkout_Files_Upload - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Alg_WC_Checkout_Files_Upload Constructor.
	 *
	 * @version 2.1.0
	 * @since   1.0.0
	 * @access  public
	 */
	function __construct() {

		// Set up localisation
		load_plugin_textdomain( 'checkout-files-upload-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

		// Include required files
		$this->includes();

		// Admin
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
			// Settings
			require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-section.php' );
			require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-file.php' );
			$this->settings = array();
			$this->settings['general']  = require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-general.php' );
			$this->settings['emails']   = require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-emails.php' );
			$this->settings['template'] = require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-template.php' );
			$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
			for ( $i = 1; $i <= $total_number; $i++ ) {
				$this->settings[ 'file_' . $i ]  = new Alg_WC_Checkout_Files_Upload_Settings_File( $i );
			}
			add_action( 'woocommerce_system_status_report', array( $this, 'add_settings_to_status_report' ) );
			// Version updated
			if ( get_option( 'alg_checkout_files_upload_version', '' ) !== $this->version ) {
				add_action( 'admin_init', array( $this, 'version_updated' ) );
			}
		}

	}
	
	/**
	 * @since   2.1.0
	 */
	public function enqueue_scripts() {
		global $pagenow;
		
		// check if its a page where we need this
		if (
			$pagenow === 'post.php'
			|| ( $pagenow === 'admin.php' && isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] === 'alg_wc_checkout_files_upload' )
		) {
			wp_enqueue_script(
				'wpwham-checkout-files-upload-admin',
				$this->plugin_url() . '/includes/js/admin.js',
				array( 'jquery' ),
				WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION,
				false
			);
			wp_localize_script(
				'wpwham-checkout-files-upload-admin',
				'wpwham_checkout_files_upload_admin',
				array(
					'i18n' => array(
						'confirmation_message' => __( 'Are you sure you want to delete this file? This cannot be undone.', 'checkout-files-upload-woocommerce' ),
					),
				)
			);
		}
	}
	
	/**
	 * @since   2.1.2
	 */
	public function enqueue_styles() {
		global $pagenow;
		
		// check we are on the settings page
		if (
			$pagenow === 'admin.php'
			&& isset( $_REQUEST['tab'] ) && $_REQUEST['tab'] === 'alg_wc_checkout_files_upload'
		) {
			wp_enqueue_style(
				'wpwham-checkout-files-upload-admin',
				$this->plugin_url() . '/includes/css/admin.css',
				array(),
				WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION,
				'all'
			);
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 2.1.1
	 * @since   1.0.0
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();
		$custom_links[] = '';
		if ( 'checkout-files-upload-woocommerce.php' === basename( __FILE__ ) ) {

		}
		return array_merge( $custom_links, $links );
	}

	/**
	 * add settings to WC status report
	 *
	 * @version 2.0.3
	 * @since   2.0.3
	 * @author  WP Wham
	 */
	public static function add_settings_to_status_report() {
		#region add_settings_to_status_report
		$protected_settings = array( 'wpwham_checkout_files_upload_license', 'alg_checkout_files_upload_emails_address' );
		$settings_general   = Alg_WC_Checkout_Files_Upload_Settings_General::get_settings();
		$settings_emails    = Alg_WC_Checkout_Files_Upload_Settings_Emails::get_settings();
		$settings_template  = Alg_WC_Checkout_Files_Upload_Settings_Template::get_settings();
		$settings = array_merge(
			$settings_general, $settings_emails, $settings_template
		);
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			$settings_file_inst = new Alg_WC_Checkout_Files_Upload_Settings_File( $i );
			$settings_file = $settings_file_inst->get_settings();
			$settings = array_merge(
				$settings, $settings_file
			);
		}
		?>
		<table class="wc_status_table widefat" cellspacing="0">
			<thead>
				<tr>
					<th colspan="3" data-export-label="Checkout Files Upload Settings"><h2><?php esc_html_e( 'Checkout Files Upload Settings', 'checkout-files-upload-for-woocommerce' ); ?></h2></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $settings as $setting ): ?>
				<?php 
				if (
					in_array( $setting['type'], array( 'title', 'sectionend' ) ) ||
					! isset( $setting['id'] )
				) {
					continue;
				}
				if ( isset( $setting['title'] ) ) {
					$title = $setting['title'];
				} elseif ( isset( $setting['desc'] ) ) {
					$title = $setting['desc'];
				} else {
					$title = $setting['id'];
				}
				$value = get_option( $setting['id'] ); 
				if ( in_array( $setting['id'], $protected_settings ) ) {
					$value = $value > '' ? '(set)' : 'not set';
				}
				?>
				<tr>
					<td data-export-label="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( $title, 'checkout-files-upload-for-woocommerce' ); ?>:</td>
					<td class="help">&nbsp;</td>
					<td><?php echo is_array( $value ) ? print_r( $value, true ) : $value; ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		#endregion add_settings_to_status_report
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function includes() {
		// Functions
		require_once( 'includes/alg-wc-checkout-files-upload-functions.php' );
		// Core
		$this->core = require_once( 'includes/class-alg-wc-checkout-files-upload.php' );
	}

	/**
	 * version_updated.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function version_updated() {
		foreach ( $this->settings as $section ) {
			foreach ( $section->get_settings() as $value ) {
				if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
					$autoload = isset( $value['autoload'] ) ? ( bool ) $value['autoload'] : true;
					add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}
		update_option( 'alg_checkout_files_upload_version', $this->version );
	}

	/**
	 * Add Checkout Files Upload settings tab to WooCommerce settings.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-wc-settings-checkout-files-upload.php' );
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

}

endif;

if ( ! function_exists( 'alg_wc_checkout_files_upload' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Checkout_Files_Upload to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_WC_Checkout_Files_Upload
	 */
	function alg_wc_checkout_files_upload() {
		return Alg_WC_Checkout_Files_Upload::instance();
	}
}

alg_wc_checkout_files_upload();
