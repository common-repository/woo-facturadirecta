<?php
/**
 * Order PDF invoice link
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/order-pdf-invoice-link.php.
 *
 * HOWEVER, on occasion author will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	https://docs.woocommerce.com/document/template-structure/
 * @author  oscargare
 * @package WC_Facturadirecta/Templates
 * @version 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<a href="<?php echo $invoice_url ?>" class="pdf-link" title="<?php _e( 'Download PDF Invoice', 'woo-facturadirecta' ); ?>"><span><?php _e( 'Download PDF Invoice', 'woo-facturadirecta' ); ?></span></a>