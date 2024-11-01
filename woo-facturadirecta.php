<?php
/**
 * Plugin Name: WooCommerce FacturaDirecta
 * Description: WooCommerce integration to your FacturaDirecta billing system. Automatic creation of invoices and customers in your FacturaDirecta account.
 * Author: FacturaDirecta
 * Version: 1.1.0
 * Author URI: https://www.facturadirecta.com/
 * Requires at least: 4.4
 * Tested up to: 4.9
 * Text Domain: woo-facturadirecta
 * Domain Path: /languages/
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! class_exists( 'WC_Facturadirecta' ) ) :

/**
 * Main WooCommerce Facturadirecta Class
 *
 * @class WC_Facturadirecta
 * @version	1.1.0
 */
class WC_Facturadirecta {

	/**
	 * @var string
	 */
	public static $version = '1.1.0';

	/**
	 * @var string
	 */
	private static $min_wc_version = '3.0.0';

	/**
	 * Get the plugin url.
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 * @return string
	 */

	public static function plugin_path(){
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get templates passing attributes and including the file.
	 * @return string
	 */
	public static function get_template( $template_name, $args = array() ){
		if ( function_exists( 'wc_get_template' ) ) {
			wc_get_template( $template_name, $args, '', self::plugin_path() . '/templates/' );
		}
	}

	/**
	 * Init plugin
	 */
	public static function init(){
		add_action( 'plugins_loaded', array( __CLASS__, 'load' ) );
		add_filter( 'woocommerce_integrations', array( __CLASS__, 'add_integration' ) );
	}

	/**
	 * Include files
	 */
	public static function load(){

		load_plugin_textdomain( 'woo-facturadirecta', false, basename( dirname( __FILE__ ) ) . '/languages' );

		if (  ( ! defined( 'WC_VERSION' ) || ! class_exists( 'WC_Integration' ) ) && current_user_can( 'activate_plugins' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_inactive_notice' ) );

		} elseif ( version_compare( WC_VERSION, self::$min_wc_version, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_min_version_notice' ) );

		} else {
			include_once( 'includes/wc-facturadirecta-api-wrapper.php' );
			include_once( 'includes/wc-facturadirecta-integration.php' );
			include_once( 'includes/wc-facturadirecta-tax-code-validator.php' );

			//EU VAT Number plugin compatibility
			if ( defined('WC_EU_VAT_VERSION') ) {
				include_once( 'includes/wc-facturadirecta-eu-vat-number.php' );
			}
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public static function add_integration( $integrations ) {
		$integrations[] = 'WC_Facturadirecta_Integration';
		return $integrations;
	}

	/**
	 * Called when WooCommerce is inactive to display an inactive notice.
	 */
	public static function woocommerce_inactive_notice() {
		?>
		<div id="message" class="error">
			<p>
			<?php
				printf( esc_html__( "%sWooCommerce FacturaDirecta is inactive%s. The WooCommerce plugin must be active for FacturaDirecta to work. Please install & activate %sWooCommerce%s.",  'woo-facturadirecta' ), '<strong>', '</strong>', '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">', '</a>' );
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Called when WooCommerce does not meet min version required.
	 */
	public static function woocommerce_min_version_notice() {
		?>
		<div id="message" class="error">
			<p>
			<?php
				printf( esc_html__( 'WooCommerce FacturaDirecta - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woo-facturadirecta' ), self::$min_wc_version, WC_VERSION );
			?>
			</p>
		</div>
		<?php
	}
}

WC_Facturadirecta::init();

endif;
