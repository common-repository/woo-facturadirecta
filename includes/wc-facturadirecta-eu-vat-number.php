<?php
/**
 * EU VAT Number integration
 *
 * @class       WC_Facturadirecta_EU_VAT_Number
 * @version     1.0.0
 * @category    Class
 * @author      OscarGare
 */
class WC_Facturadirecta_EU_VAT_Number{
	
	/**
	 * Init
	 */ 
	public static function init(){		
		add_filter( 'woocommerce_facturadirecta_is_b2b', array( __CLASS__, 'is_b2b_transaction' ), 10, 3 );
		add_filter( 'woocommerce_facturadirecta_tax_code', array( __CLASS__, 'vat_number' ), 10, 2 );
	}

	/**
	 * Is B2B transaction
	 * @param bool $is_b2b
	 * @param string $tax_code
	 * @param WC_Order $order
	 * @return bool
	 */ 
	public static function is_b2b_transaction( $is_b2b, $tax_code, $order ){
		$b2b_transaction = get_post_meta( $order->id, '_vat_number', true ) ? true : false;		 
		return $is_b2b || $b2b_transaction;
	}

	/**
	 * Return VAT number
	 * @param string $tax_code
	 * @param WC_Order $order
	 * @return string
	 */ 
	public static function vat_number( $tax_code, $order ){
		$vat_number = get_post_meta( $order->id, '_vat_number', true );		
		return $vat_number ? $vat_number : $tax_code;
	}
}
WC_Facturadirecta_EU_VAT_Number::init();