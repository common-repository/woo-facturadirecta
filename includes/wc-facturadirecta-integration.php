<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Facturadirecta Integration Class
 *
 * @class       WC_Facturadirecta_Integration
 * @version     1.0.0
 * @category    Abstract Class
 * @author      OscarGare
 */
class WC_Facturadirecta_Integration extends WC_Integration {

	/**
	 * @var string
	 */
	protected $account;

	/**
	 * @var string
	 */
	protected $token;

	/**
	 * @var string
	 */
	protected $attach_pdf;

	/**
	 * @var string
	 */
	protected $frontend_link;

	/**
	 * @var string
	 */
	protected $checkout_tax_code;

	/**
	 * @var string
	 */
	protected $tax_code_label;

	/**
	 * @var string
	 */
	protected $tax_code_placeholder;

	/**
	 * @var string
	 */
	protected $tax_code_required;

	/**
	 * @var string
	 */
	protected $debug;

	/**
	 * @var object
	 */
	protected $logger;

	/**
	 * @var string
	 */
	protected $attach_file;

	/**
	 * Constructor. Init and hook in the integration.
	 */
	public function __construct(){

		$this->id                 = 'facturadirecta';
		$this->method_title       = __( 'FacturaDirecta', 'woo-facturadirecta' );
		$this->method_description = sprintf( __( 'FacturaDirecta accounting integration with WooCommerce. If you do not have a FacturaDirecta account try it %shere%s.', 'woo-facturadirecta'), '<a href="https://www.facturadirecta.com" target="_blank">', '</a>' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->account 		 		= $this->get_option( 'account' );
		$this->token		 		= $this->get_option( 'token' );
		$this->serial		 		= $this->get_option( 'serial' );
		$this->attach_pdf	 		= $this->get_option( 'attach_pdf' );
		$this->frontend_link 		= $this->get_option( 'frontend_link' );
		$this->checkout_tax_code	= $this->get_option( 'checkout_tax_code' );
		$this->tax_code_label		= $this->get_option( 'tax_code_label' );
		$this->tax_code_placeholder = $this->get_option( 'tax_code_placeholder' );
		$this->tax_code_required 	= $this->get_option( 'tax_code_required' );
		$this->debug		 		= $this->get_option( 'debug' );

		// Init logger
		$this->logger = new WC_Logger();

		// Actions
		add_action( 'woocommerce_update_options_integration_facturadirecta', array( $this, 'process_admin_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Check if the user has enabled the plugin functionality, but hasn't provided an api key
		if ( ! empty( $this->account ) && ! empty( $this->token ) ) {
			$this->init_facturadirecta();

		} elseif ( ! ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) ) {
			add_action( 'admin_notices', array( $this, 'account_settings_empty' ) );
		}
	}

	/**
	 * Initialize hooks to sync with FacturaDirecta
	 */
	private function init_facturadirecta() {
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_shop_order_columns' ) );
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'shop_order_columns' ), 20 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'shop_order_columns' ), 20 );
		add_action( 'load-edit.php', array( $this, 'bulk_action' ) );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'invoice_admin_order_data' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'create_invoice' ) );

		// Frontend invoice link
		if ( 'yes' === $this->frontend_link ) {
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_pdf_invoice_link' ) );
		}

		// Attach PDF invoice
		if ( 'yes' === $this->attach_pdf ) {
			add_filter( 'woocommerce_email_attachments', array( $this, 'pdf_invoice_email_attachment' ), 10, 3 );
			add_action( 'woocommerce_order_status_completed_notification', array( $this, 'remove_attach_file' ), 20 );
		}

		// Invoice download
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) && isset( $_GET['invoice_id'] ) && intval( $_GET['invoice_id'] ) > 0 && isset( $_GET['_pdf_download_invoice'] ) ) {
			add_action( 'admin_init', array( $this, 'admin_download_invoice' ) );

		} elseif ( 'yes' === $this->frontend_link && isset( $_GET['order'] ) && intval( $_GET['order'] ) > 0 && isset( $_GET['download_pdf_invoice'] ) && $_GET['download_pdf_invoice'] == 'true' ) {
			add_action ( 'init', array( $this, 'frontend_download_invoice' ) );
		}

		//Add TAX/VAT number to checkout field
		if ( 'yes' === $this->checkout_tax_code ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'tax_code_checkout_field' ) );
			add_filter( 'woocommerce_admin_billing_fields', array( $this, 'tax_code_admin_field' ) );
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'tax_code_checkout_validation' ) );
			add_action( 'wp_enqueue_scripts', array( $this,'frontend_scripts' ) );
		}
	}

	/**
	 * Write the message to log
	 *
	 * @param String $message
	 */
	private function write_log( $message ) {

		// Check if log is enabled
		if ( 'yes' === $this->debug ) {
			// Add to logger
			$this->logger->add( 'facturadirecta', $message );
		}
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'account' => array(
				'title'             => __( 'Account ID', 'woo-facturadirecta' ),
				'type'              => 'text',
				'description'       => __( 'Enter with your Account ID (facturadirecta.com subdomain account)', 'woo-facturadirecta' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'token' => array(
				'title'             => __( 'API Token', 'woo-facturadirecta' ),
				'type'              => 'text',
				'description'       => __( 'Enter with your API Token. You can find this in "Settings" drop-down (top right corner) > API and external applications.', 'woo-facturadirecta' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'serial' => array(
				'title'             => __( 'Numbering Series', 'woo-facturadirecta' ),
				'type'              => 'text',
				'description'       => __( 'Enter a numeration series for your FacuraDirecta invoices e.g. "WC" to generate invoices WC1, WC2 etc.', 'woo-facturadirecta' ),
				'desc_tip'          => true,
				'default'           => 'WC-'
			),
			'attach_pdf' => array(
				'title'             => __( 'Attach invoice', 'woo-facturadirecta' ),
				'type'              => 'checkbox',
				'label'             => __( 'Activate attached invoice as PDF to completed order email', 'woo-facturadirecta' ),
				'default'           => 'no',
				'description'       => __( 'Send invoice to customer as PDF file on "Completed order" email', 'woo-facturadirecta' ),
			),
			'frontend_link' => array(
				'title'             => __( 'Download invoice link', 'woo-facturadirecta' ),
				'type'              => 'checkbox',
				'label'             => __( 'Add a link for download invoice to My Account -> View Order page', 'woo-facturadirecta' ),
				'default'           => 'yes'
			),
			'checkout_tax_code' => array(
				'title'             => __( 'Tax number checkout field', 'woo-facturadirecta' ),
				'type'              => 'checkbox',
				'label'             => __( 'Add a Tax number field to checkout.', 'woo-facturadirecta' ),
				'default'           => 'yes'
			),
			'tax_code_label' => array(
				'title'             => __( 'Tax number field label', 'woo-facturadirecta' ),
				'type'              => 'text',
				'description'       => __( 'Enter label for tax number field.', 'woo-facturadirecta' ),
				'desc_tip'          => true,
				'default'           => __( 'Tax Number', 'woo-facturadirecta' )
			),
			'tax_code_placeholder' => array(
				'title'             => __( 'Tax number placeholder', 'woo-facturadirecta' ),
				'type'              => 'text',
				'description'       => __( 'Enter placeholder for tax number field.', 'woo-facturadirecta' ),
				'desc_tip'          => true,
				'default'           => _x( 'Enter tax number', 'woo-facturadirecta' )
			),
			'tax_code_required' => array(
				'title'    => __( 'Tax number required handling', 'woo-facturadirecta' ),
				'default'  => '',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'options'  => array(
					''       => __( 'No required', 'woo-facturadirecta' ),
					'all'    => __( 'Required for all countries', 'woo-facturadirecta' ),
					'base' 	 => __( 'Required for shop location country only', 'woo-facturadirecta' )
				)
			),
			'debug' => array(
				'title'             => __( 'Debug Log', 'woo-facturadirecta' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'woo-facturadirecta' ),
				'default'           => 'no',
				'description'       => __( 'Log events such as API requests', 'woo-facturadirecta' ),
			)
		);
	}

	/**
	 * Admin scripts
	 */
	public function admin_scripts() {
		$screen       = get_current_screen();
		$screen_id    = $screen ? $screen->id : '';

		if ( 'shop_order' ===  $screen_id || 'edit-shop_order' ===  $screen_id ){
			wp_register_script( 'woocommerce_facturadirecta_admin', WC_Facturadirecta::plugin_url() . '/assets/js/admin.js', array( 'woocommerce_admin' ), WC_Facturadirecta::$version, true );
			wp_localize_script( 'woocommerce_facturadirecta_admin', 'woocommerce_facturadirecta_admin_params', array(
				'facturadirecta_update_text' => __( 'Update invoice number from FacturaDirecta', 'woo-facturadirecta' )
			) );
			wp_enqueue_script( 'woocommerce_facturadirecta_admin' );


		}
	}

	/**
	 * Frontend scripts
	 */
	public function frontend_scripts(){
		if ( is_checkout() && 'base' === $this->tax_code_required ) {

			wp_register_script( 'woocommerce_facturadirecta_checkout', WC_Facturadirecta::plugin_url() . '/assets/js/checkout.js', array( 'woocommerce' ), WC_Facturadirecta::$version, true );
			wp_localize_script( 'woocommerce_facturadirecta_checkout', 'woocommerce_facturadirecta_checkout_params', array(
				'base_country' 		=> WC()->countries->get_base_country(),
				'required_content'	=> ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>'
			));
			wp_enqueue_script( 'woocommerce_facturadirecta_checkout' );
		}
	}

	/**
	 * Called when token or account are empty
	 */
	public function account_settings_empty() {
		$url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=facturadirecta' );
		?>
		<div class="notice notice-warning">
			<p>
			<?php
				printf( esc_html__( '%sWooCommerce FacturaDirecta is inactive%s because no account ID and API token have been provided. Enter your account ID and your API token in %ssetting page%s.',  'woo-facturadirecta' ), '<strong>', '</strong>', '<a href="' . $url . '">', '</a>'  );
			?>
			</p>
		</div>
		<?php
	}

	/**
	 * Define custom columns for orders.
	 *
	 * @param  array $existing_columns
	 * @return array
	 */
	public function shop_order_columns( $existing_columns ) {

		$columns = array();
		foreach ( $existing_columns as $key => $val ) {
			if ( in_array( $key, array( 'order_actions', 'wc_actions' ) ) ) {
				$columns['invoice_number'] = __( 'Invoice', 'woo-facturadirecta' );
			}
			$columns[$key] = $val;
		}

		return $columns;
	}

	/**
	 * Output custom columns for orders.
	 *
	 * @param string $column
	 */
	public function render_shop_order_columns( $column ) {
		global $post;
		if ( $column == 'invoice_number' ) {
			$invoice_number = get_post_meta( $post->ID, '_facturadirecta_invoice_number', true );
			$invoice_id = get_post_meta( $post->ID, '_facturadirecta_invoice_id', true );

			echo $this->get_admin_invoice_url( $invoice_number, $invoice_id );
		}
	}

	/**
	 * Return a admin url for a invoice
	 *
	 * @param string $invoice_number
	 * @param string $invoice_id
	 */
	private function get_admin_invoice_url( $invoice_number, $invoice_id ) {
		$invoice_url = wp_nonce_url( add_query_arg( 'invoice_id', $invoice_id, remove_query_arg('invoice_id') ), 'facturadirecta_download_invoice', '_pdf_download_invoice' );
		return sprintf( "%s$invoice_number%s", '<a href="' . $invoice_url . '" target="_blank">', '</a>' );
	}

	/**
	 * Process the new bulk actions for update invoice number from FacturaDirecta
	 */
	public function bulk_action() {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action        = $wp_list_table->current_action();

		// Bail out if this is not a facturadirecta action
		if ( $action !== 'facturadirecta_update' ) {
			return;
		}

		$this->write_log( 'Bulk update' );

		// Instance API Wrapper
		$api = new WC_Facturadirecta_Api_Wrapper( $this->account, $this->token );

		$changed = 0;

		$post_ids = array_map( 'absint', (array) $_REQUEST['post'] );

		foreach ( $post_ids as $post_id ) {
			$this->update_invoice_data( $api, $post_id );
			$changed++;
		}

		$sendback = add_query_arg( array( 'post_type' => 'shop_order', 'facturadirecta_updated' => true, 'changed' => $changed ), '' );

		$this->write_log( 'End bulk update. ' . $changed . ' orders updated' );

		wp_redirect( esc_url_raw( $sendback ) );
		exit();
	}

	/**
	 * Update invoice number
	 *
	 * @param Object $api
	 * @param int $order_id
	 */
	private function update_invoice_data( $api, $order_id ) {

		if ( $invoice_id = get_post_meta( $order_id, '_facturadirecta_invoice_id', true ) ) {

			$invoice = $api->get_invoice( $invoice_id ) ;

			if ( ! is_wp_error( $invoice ) ) {
				update_post_meta( $order_id, '_facturadirecta_invoice_number', $invoice->invoiceNumberFormatted );

			} elseif( $invoice->get_error_code() == '404' ) {
				update_post_meta( $order_id, '_facturadirecta_invoice_id', '' );
				update_post_meta( $order_id, '_facturadirecta_invoice_number', '' );
			} else {
				$this->write_log( 'Error updating number for invoice : ' . invoice_id . ' - ' . $invoice->get_error_code() . '; ' . $invoice->get_error_message() );
			}
		}
	}

	/**
	 * Show confirmation message for bulk updated
	 */
	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		// Bail out if not on shop order list page
		if ( 'edit.php' !== $pagenow || 'shop_order' !== $post_type || ! isset( $_REQUEST[ 'facturadirecta_updated' ] ) ) {
			return;
		}

		$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;
		$message = sprintf( _n( 'Invoice number updated.', '%s orders changed.', $number, 'woo-facturadirecta' ), number_format_i18n( $number ) );
		echo '<div class="updated"><p>' . $message . '</p></div>';
	}

	/**
	 * Add VAT/TAX number to order admin fields
	 */
	public function tax_code_admin_field( $fields ) {
		$fields['tax_code'] = array(
			'label' => $this->tax_code_label,
		);
		return $fields;
	}

	/**
	 * Add Invoice link to order admin fields
	 */
	public function invoice_admin_order_data( $order ) {
		if ( ! $invoice_id = get_post_meta( $order->get_id(), '_facturadirecta_invoice_id', true ) ) {
			return; // Only
		}

		$invoice_number = get_post_meta( $order->get_id(), '_facturadirecta_invoice_number', true );
		$invoice_url	= $this->get_admin_invoice_url( $invoice_number, $invoice_id );
		printf( '<p class="facturadirecta-invoice-link"><strong style="display:block;">%s:</strong> %s</p>', __( 'Invoice', 'woo-facturadirecta' ), $invoice_url );
	}

	/**
	 * Create invoice when order is marked as completed
	 *
	 * @param int $order_id Order ID.
	 */
	public function create_invoice( $order_id ) {
		$invoice_id = get_post_meta( $order_id, '_facturadirecta_invoice_id', true );

		if ( $invoice_id || ! apply_filters( 'woocommerce_factura_create_invoice', true, $order_id ) ) {
			return; // Only do this once.
		}

		$this->write_log( 'Creating invoice for order id: ' . $order_id );

		// Get order
		$order = wc_get_order( $order_id );

		// Instance API Wrapper
		$api = new WC_Facturadirecta_Api_Wrapper( $this->account, $this->token );

		// Create client
		$client_id = $this->get_client_id( $api, $order );

		if ( $client_id ) {

			// Create new invoice
			$invoice = $api->add_invoice( $order, $client_id, $this->serial );

			if ( ! is_wp_error( $invoice ) ) {

				$this->write_log( 'Invoice created: ' . $invoice->id );

				update_post_meta( $order_id, '_facturadirecta_invoice_id', $invoice->id );
				update_post_meta( $order_id, '_facturadirecta_invoice_number', $invoice->invoiceNumberFormatted );

			} else {
				$this->write_log( 'Error creating invoice: ' . $invoice->get_error_message() );
			}

		}
	}

	/**
	 * Return a client ID from order
	 *
	 * @param Object $api
	 * @param Object $order
	 */
	private function get_client_id( $api, $order ) {

		$client_id = false;
		$tax_code = apply_filters( 'woocommerce_facturadirecta_tax_code', get_post_meta( $order->get_id(), '_billing_tax_code', true ), $order );

		// Client is a user
		if ( $order->get_customer_id() && ( $client_id = get_user_meta( $order->get_customer_id(), '_facturadirecta_client_id', true ) ) ) {

			$client = $api->get_client( $client_id );

			if ( is_wp_error( $client ) ) {
				$client_id = false;
				$this->write_log( 'Error: ' . $client->get_error_message() );
			}
		}

		// No client, find client in FacturaDirecta
		if ( ! $client_id && ( $client = $this->find_client( $api, $order->get_billing_email(), $tax_code ) ) ) {
			//client found
			$client_id = $client->id;

		}

		//No client found
		if ( ! $client_id ) {
			// Create new client
			$client_id = false;

			$this->write_log( 'Creating new client' );

			// Is B2B transaction
			$is_b2b = apply_filters( 'woocommerce_facturadirecta_' . strtolower( $order->get_billing_country() ) . '_is_b2b', ! empty( $tax_code ) , $tax_code );
			$is_b2b = apply_filters( 'woocommerce_facturadirecta_is_b2b', $is_b2b , $tax_code, $order );

			// Create new client
			$client = $api->add_client( $order, $tax_code, $is_b2b);

			if ( is_wp_error( $client ) ) {
				$this->write_log( 'Error: ' . $client->get_error_message() );
			} else {
				// Get client ID
				$client_id = $client->id;
			}
		}

		//Save factura directa client_id
		if ( $order->get_customer_id() && $client_id ) {
			update_user_meta( $order->get_customer_id(), '_facturadirecta_client_id', $client_id );

			$this->write_log( 'Updated customer user : ' . $order->get_customer_id() . ' -> client id: ' . $client_id );
		}

		return $client_id;
	}

	/**
	 * Find a client in FacturaDirecta
	 *
	 * @param Object $api
	 * @param string $email
	 * @param string $tax_code
	 */
	private function find_client( $api, $email, $tax_code = false ) {
		$client = false;

		if ( ! empty( $tax_code ) ) {
			$this->write_log( 'Find client by tax code: ' . $tax_code );

			$client = $api->get_client_by( array( 'taxCode' => $tax_code ) );

			if ( is_wp_error( $client ) ) {
				$this->write_log( 'Client not found.' );
				$client = false;
			}
		}

		if ( ! $client and ! empty( $email ) ) {
			$this->write_log( 'Find client by email: ' . $email );

			$client = $api->get_client_by( array( 'email' => $email ) );

			if ( is_wp_error( $client ) ) {

				$this->write_log( 'Client not found.' );
				$client = false;

			} elseif( $tax_code && ( $client->taxCode !== $tax_code ) ) {

				$this->write_log( 'Client found but CIF not match.' );
				$client = false;
			}
		}

		return $client;
	}

	/**
	* Admin download invoice pdf file
	*/
	public function admin_download_invoice() {

		if ( wp_verify_nonce( $_GET['_pdf_download_invoice'], 'facturadirecta_download_invoice' ) ) {

			$api = new WC_Facturadirecta_Api_Wrapper( $this->account, $this->token );
			$url = $api->get_invoice_pdf_url( intval( $_GET['invoice_id'] ) );

			if ( ! is_wp_error( $url ) ) {
				wp_redirect( $url );
				exit;
			} else {
				wp_die( $url, $url->get_error_code() );
			}
		} else {
			wp_die( 'Link expired' );
		}

	}

	/**
	 * Frontend download invoice pdf
	 */
	public function frontend_download_invoice() {
		if ( ! is_user_logged_in() ) {
			$redirect = wc_get_page_permalink( 'myaccount' );
			wp_safe_redirect( $redirect );
		}

		$order 			= wc_get_order( $_GET['order'] );
		$current_user 	= wp_get_current_user();

		// Check the current user ID matches the ID of the user who placed the order
		if ( $order && $order->get_customer_id() == $current_user->ID ) {

			$api = new WC_Facturadirecta_Api_Wrapper( $this->account, $this->token );

			if ( $invoice_id = get_post_meta( $order->get_id(), '_facturadirecta_invoice_id', true ) ) {

				$url = $api->get_invoice_pdf_url( $invoice_id );

				if ( is_wp_error( $url ) ) {
					wp_die( __( 'File not found.', 'woocommerce' ) );
				} else {

					$filename = get_post_meta( $order->get_id(), '_facturadirecta_invoice_number', true ) . '.pdf';
					WC_Download_Handler::download_file_force( $url, $filename );
				}
			}

		}
	}

	/**
	 * Add VAT/TAX number to checkout fields
	 */
	public function tax_code_checkout_field( $checkout_fields ) {
		$billing_country = isset( $_POST['billing_country'] ) ? wc_clean( $_POST['billing_country'] ) : WC()->customer->get_billing_country();

		$checkout_fields['billing']['billing_tax_code'] =  array(
			'label'        => $this->tax_code_label,
			'required'     => $this->is_tax_code_required( $billing_country ),
			'class'        => array( 'form-row-wide' ),
			'autocomplete' => 'tax-code',
			'placeholder'  => $this->tax_code_placeholder
		);

		return $checkout_fields;
	}

	/**
	 * VAT/TAX number checkout validation
	 */
	public function tax_code_checkout_validation( $posted ) {

		$billing_country = isset( $posted['billing_country'] ) ? $posted['billing_country'] : '';

		if ( isset( $posted['billing_tax_code'] ) && ! empty( $posted['billing_tax_code'] ) && ! apply_filters( 'woocommerce_facturadirecta_' . strtolower( $billing_country ) . '_tax_code_validate', true, $posted['billing_tax_code'] ) ) {
			wc_add_notice( '<strong>' . $this->tax_code_label . ' </strong> ' . __( 'is not valid.', 'woo-facturadirecta' ) , 'error' );
		}
	}

	/**
	 * Return is tax code is required
	 *
	 * @param string $billing_country
	 * @return boolean
	 */
	private function is_tax_code_required( $billing_country ) {
		return $this->tax_code_required === 'all' || ( $this->tax_code_required === 'base' && $billing_country == WC()->countries->get_base_country() );
	}

	/**
	 * Add invoice download link to view order template
	 */
	public function order_pdf_invoice_link( $order ) {
		if ( ! ( $invoice_id = get_post_meta( $order->get_id(), '_facturadirecta_invoice_id', true ) ) ) {
			return; // Invoice not found
		}

		$url = add_query_arg( array(
				'order'					=> $order->get_id(),
				'download_pdf_invoice'  => 'true'
		) );

		WC_Facturadirecta::get_template( 'order/order-pdf-invoice-link.php', array( 'invoice_url' => $url ) );
	}

	/**
	 * Mark an order with a status.
	 */
	public function send_invoice_email() {
		if ( current_user_can( 'manage_woocommerce' ) && check_admin_referer( 'facturadirecta-send-invoice-email' ) ) {

			$invoice_id = absint( $_GET['invoice_id'] );

			$this->write_log( 'Send Deemail invoice id: ' . $invoice_id );

			$api = new WC_Facturadirecta_Api_Wrapper( $this->account, $this->token );

			$response = $api->send_invoice_email( $invoice_id );

			if ( is_wp_error( $response ) ) {
				$this->write_log( 'Error sending email invoice: ' . $response->get_error_message() );
			} else {
				$this->write_log( 'Invoice send by email OK' );
			}
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		die();
	}

	/**
	 * Attach pdf invoice to Customer Completed Order Email
	 */
	public function pdf_invoice_email_attachment( $attachments, $email_id, $order ) {

		$this->attach_file = false;

		if ( $email_id == 'customer_completed_order' && $invoice_id = get_post_meta( $order->get_id(), '_facturadirecta_invoice_id', true ) ) {

			$this->write_log( 'Attachmenting invoice pdf to customer_completed_order email. Order id ' . $order->get_id() . ', invoice id : ' . $invoice_id );

			$api = new WC_Facturadirecta_Api_Wrapper( $this->account, $this->token );
			$url = $api->get_invoice_pdf_url( $invoice_id );

			if ( ! is_wp_error( $url ) ) {

				$temp_file = download_url( $url );

				if ( ! is_wp_error( $temp_file ) ) {
					$this->attach_file = dirname($temp_file) . '/' . get_post_meta( $order->get_id(), '_facturadirecta_invoice_number', true ) . '.pdf';
					rename( $temp_file, $this->attach_file );

					//attachment
					$attachments[] = $this->attach_file;

				} else {
					$this->write_log( 'Error getting pdf url: ' . $url->get_error_message() );
				}

			} else {
				$this->write_log( 'Error downlading pdf from url: ' . $temp_file->get_error_message() );
			}
		}

		return $attachments;
	}

	/**
	 * Remove temp file
	 */
	public function remove_attach_file() {
		if ( $this->attach_file ) {
			unlink( $this->attach_file );
		}
	}

}