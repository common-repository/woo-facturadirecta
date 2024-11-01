/* global woocommerce_facturadirecta_checkout_params */
jQuery( function( $ ) {

	$('#billing_country').on( 'change', function(){
		if ( $(this).val() !== woocommerce_facturadirecta_checkout_params.base_country ) {
			$('label[for="billing_tax_code"]').find('abbr.required').remove();
		}else if( $('label[for="billing_tax_code"]').find('abbr.required').length == 0 ) {
			$('label[for="billing_tax_code"]').append(woocommerce_facturadirecta_checkout_params.required_content);
		}

		$('#billing_tax_code_field').toggleClass('validate-required', $(this).val() == woocommerce_facturadirecta_checkout_params.base_country );
		if ( $('#billing_tax_code_field').hasClass('woocommerce-invalid') || $('#billing_tax_code_field').hasClass('woocommerce-validated') ) {
			$('#billing_tax_code').trigger('change');
		}
	} );
});