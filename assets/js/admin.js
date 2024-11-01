/* global woocommerce_facturadirecta_admin_params */

jQuery( function( $ ) {
	$( 'a.edit_address' ).click( function(){				
		$('p.facturadirecta-invoice-link').hide();
	} );

	/* Add extra bulk action options to update invoice number from FacturaDirecta 
	   Using Javascript until WordPress core fixes: https://core.trac.wordpress.org/ticket/16031.
	*/
	$('<option>').val('facturadirecta_update').text(woocommerce_facturadirecta_admin_params.facturadirecta_update_text).appendTo('select[name="action"]');
	$('<option>').val('facturadirecta_update').text(woocommerce_facturadirecta_admin_params.facturadirecta_update_text).appendTo('select[name="action2"]');								
});