<?php
/**
 * FacturaDirecta.com API Wrapper
 *
 * Handles FacturaDirecta API endpoint requests.
 *
 * @since     1.0.0
 * @package  WC_FacturaDirecta/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Facturadirecta_Api_Wrapper Class.
 */
class WC_Facturadirecta_Api_Wrapper {

	/**
	 * FacturaDirecta account ID.
	 *
	 * @var string
	 */
	protected $account;

	/**
	 * FacturaDirecta API token.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Construct
	 *
	 * @param string $account FacturaDirecta account ID.
	 * @param string $token FacturaDirecta API token.
	 */
	public function __construct( $account, $token ) {
		$this->account = $account;
		$this->token   = $token;
	}

	/**
	 * Get client by ID
	 *
	 * @param int $client_id FacturaDirecta client ID.
	 * @return stdClass
	 */
	public function get_client( $client_id ) {
		return self::do_request( $this->account, $this->token, "clients/{$client_id}.xml", 'GET' );
	}

	/**
	 * Get client by
	 *
	 * @param array $args Array of arguments to get the client.
	 * @return stdClass|WP_Error
	 */
	public function get_client_by( $args ) {
		$clients = self::do_request( $this->account, $this->token, 'clients.xml', 'GET', array(), $args );

		if ( ! is_wp_error( $clients ) && isset( $clients->client ) ) {
			return $clients->client;

		} elseif ( ! isset( $clients->client ) ) {
			return new WP_Error( '404', 'Not Found' );
		}

		return $clients;
	}

	/**
	 * Add client action
	 *
	 * @param WC_Order $order Order to get client data.
	 * @param string   $tax_code Tax ID fo the client.
	 * @param boolean  $is_b2b Is the transtation B2B?.
	 * @return stdClass|WP_Error
	 */
	public function add_client( $order, $tax_code = false, $is_b2b = false ) {

		$data = array(
			'address' => array(
				'line1'    => $order->get_billing_address_1(),
				'line2'    => $order->get_billing_address_2(),
				'city'     => $order->get_billing_city(),
				'province' => $order->get_billing_state(),
				'zipcode'  => $order->get_billing_postcode(),
				'country'  => $order->get_billing_country(),
			),
			'email'   => $order->get_billing_email(),
			'phone'   => $order->get_billing_phone(),
		);

		if ( $is_b2b && $order->billing_company ) {
			$data['name'] = $order->get_billing_company();

		} else {
			$data['personName']    = $order->get_billing_first_name();
			$data['personSurname'] = $order->get_billing_last_name();
			$data['tradeName']     = $order->get_billing_company();
		}

		if ( ! empty( $tax_code ) ) {
			$data['taxCode'] = $tax_code;
		}

		$data = array( 'client' => apply_filters( 'woocommerce_factura_directa_client_data', $data, $order ) );

		self::cdata_recursive( $data );

		$args = array(
			'autoCreateCompanyCode' => 'true',
		);

		return self::do_request( $this->account, $this->token, 'clients.xml', 'POST', $data, $args );
	}

	/**
	 * Get invoice by ID
	 *
	 * @param int $id Invoice ID.
	 * @return Object
	 */
	public function get_invoice( $id ) {
		return self::do_request( $this->account, $this->token, "invoices/{$id}.xml", 'GET' );
	}

	/**
	 * Add invoice
	 *
	 * @param WC_Order $order Order to get invoice data.
	 * @param int      $client_id FacturaDirecta client ID of the invoice.
	 * @param string   $serial Invoice serial.
	 * @return Object
	 */
	public function add_invoice( $order, $client_id, $serial ) {

		$data = array(
			'invoice' => array(
				'client'        => array( 'id' => $client_id ),
				'invoiceDate'   => $order->get_date_completed()->format( 'Ymd' ),
				'currency'      => $order->get_currency(),
				'invoiceSerial' => self::cdata( $serial ),
				'invoiceLines'  => '',
			),
		);

		// Taxes.
		$i_tax       = 0;
		$tax_numbers = array();

		foreach ( $order->get_taxes() as $tax_item ) {
			$tax_number = 'tax' . ( ++$i_tax );

			$tax_numbers[ $tax_item->get_rate_id() ] = $tax_number;

			$data['invoice'][ $tax_number ] = array(
				'name' => $tax_item->get_label(),
				'rate' => str_replace( '%', '', WC_Tax::get_rate_percent( $tax_item->get_rate_id() ) ) * ( 1.00 ),
			);
		}

		// Line item.
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {

			$description = $item->get_name();

			if ( method_exists( $item, 'get_formatted_meta_data' ) ) {

				foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
					$description .= "\n" . $meta->key . ': ' . $meta->value;
				}
			}

			$invoice_line = array(
				'invoiceLine' => array(
					'description' => self::cdata( $description ),
					'quantity'    => $item->get_quantity(),
					'unitPrice'   => $item->get_total() / $item->get_quantity(),
				),
			);

			// Line Item taxes.
			$item_taxes = $item->get_taxes();
			foreach ( $tax_numbers as $rate_id => $tax_number ) {
				if ( isset( $item_taxes['total'][ $rate_id ] ) ) {

					$item_tax_number = 'apply' . ucfirst( $tax_number );

					$invoice_line['invoiceLine'][ $item_tax_number ] = 'true';
				}
			}

			$data['invoice']['invoiceLines'] .= self::generate_xml( $invoice_line );
		}

		// Shipping.
		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$invoice_line = array(
				'invoiceLine' => array(
					'description' => self::cdata( $item->get_name() ),
					'quantity'    => '1',
					'unitPrice'   => $item->get_total(),
				),
			);

			// Item taxes.
			$item_taxes = $item->get_taxes();
			foreach ( $tax_numbers as $rate_id => $tax_number ) {
				if ( isset( $item_taxes['total'][ $rate_id ] ) ) {

					$item_tax_number = 'apply' . ucfirst( $tax_number );

					$invoice_line['invoiceLine'][ $item_tax_number ] = 'true';
				}
			}

			$data['invoice']['invoiceLines'] .= self::generate_xml( $invoice_line );
		}

		// Payments.
		$data['invoice']['payments'] = array(
			'payment' => array(
				'dueDate'     => $order->get_date_completed()->format( 'Ymd' ),
				'paymentDate' => $order->get_date_completed()->format( 'Ymd' ),
				'paymentMean' => self::get_payment_code( $order->get_payment_method() ),
			),
		);

		return self::do_request( $this->account, $this->token, 'invoices.xml', 'POST', $data );
	}

	/**
	 * Retrun temporary url to download pdf
	 *
	 * @param int $invoice_id FacturaDirect invoice ID.
	 * @return string
	 */
	public function get_invoice_pdf_url( $invoice_id ) {

		return self::do_request( $this->account, $this->token, "invoices/{$invoice_id}.pdf", 'GET', array(), array(
			'resultType' => 'url',
		), false );
	}

	/**
	 * Send invoice by email to customer
	 *
	 * @param int $invoice_id FacturaDirect invoice ID.
	 * @return stdClass|WP_Error
	 */
	public function send_invoice_email( $invoice_id ) {
		return self::do_request( $this->account, $this->token, "invoices/send/{$invoice_id}.xml", 'POST' );
	}

	/**
	 * Return facturadirecta payment code
	 *
	 * @param string $wc_payment_method WooCommerce payment method ID.
	 * @return string
	 */
	private static function get_payment_code( $wc_payment_method ) {
		switch ( $wc_payment_method ) {
			case 'cod':
				$facturadirecta_payment = '01';
				break;
			case 'cheque':
				$facturadirecta_payment = '11';
				break;
			case 'paypal':
				$facturadirecta_payment = '20';
				break;
			case 'bacs':
				$facturadirecta_payment = '04';
				break;
			default:
				$facturadirecta_payment = '19';
				break;
		}

		return $facturadirecta_payment;
	}

	/**
	 * Do request action
	 *
	 * @param string  $account FacturaDirecta account ID.
	 * @param string  $token FacturaDirecta API token.
	 * @param string  $endpoint API endpoint.
	 * @param string  $http_method GET|POST|PUT.
	 * @param array   $data Data to send to endpoint.
	 * @param array   $args Extra query args.
	 * @param boolean $output_object When TRUE, returned object.
	 * @return stdClass|WP_Error
	 */
	private static function do_request( $account, $token, $endpoint, $http_method, $data = array(), $args = array(), $output_object = true ){

		$url = "https://{$account}.facturadirecta.com/api/{$endpoint}?api_token={$token}";
		foreach ( $args as $key => $val ) {
			$url .= "&{$key}={$val}";
		}

		$response = wp_remote_request( $url, array(
			'method'      => $http_method,
			'timeout'     => 30,
			'redirection' => 5,
			'blocking'    => true,
			'headers'     => array(
				'Content-Type' => 'text/xml',
			),
			'body'        => self::generate_xml( $data ),
		) );

		if ( ! is_wp_error( $response ) ) {
			if ( $response['response']['code'] != '200' ) {
				$response = new WP_Error( $response['response']['code'], $response['response']['message'] );
			} else {
				$response = $response['body'];
				if ( $output_object ) {
					$response = self::xmlstring_to_object( $response );
				}
			}
		}

		return $response;
	}

	/**
	 * Generate XML from data
	 *
	 * @param array $data Input array to generate the XML string.
	 * @return bool
	 */
	private static function generate_xml( $data ) {
		$xml = '';
		foreach ( $data as $key => $value ) {
			$xml .= "<$key>";
			if ( is_array( $value ) ) {
				$xml .= self::generate_xml( $value );
			} else {
				$xml .= $value;
			}
			$xml .= "</$key>";
		}
		return $xml;
	}

	/**
	 * Return the value within cdata wrapper.
	 *
	 * @param string $value The input value.
	 * @return string
	 */
	private static function cdata( $value ) {
		if ( $value ) {
			return '<![CDATA[' . $value . ']]>';
		} else {
			return '';
		}

	}

	/**
	 * Applies the cdata function recursively to every member of an array.
	 *
	 * @param array $data The input array.
	 */
	public static function cdata_recursive( &$data ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::cdata_recursive( $value );
			} else {
				$data[ $key ] = self::cdata( $value );
			}
		}
		return $data;
	}

	/**
	 * Parse xml string to Object
	 *
	 * @param string $xml XML string to buid the object.
	 * @return stdClass
	 */
	private static function xmlstring_to_object( $xml ) {
		if ( ! is_object( $xml ) ) {
			$xml = simplexml_load_string( $xml, null, LIBXML_NOCDATA );
		}

		$response = new stdClass();

		foreach ( $xml as $key => $value ) {

			$avalue = (array) $value;
			$count  = count( $avalue );

			if ( $count == 1 ) {
				$val            = current( $avalue );
				$response->$key = ( 'false' === $val ? false : $val );
			} elseif ( $count == 0 ) {
				$response->$key = false;
			} else {
				$response->$key = self::xmlstring_to_object( $value );
			}
		}

		return $response;
	}
}
