=== FacturaDirecta (classic) for WooCommerce ===
Contributors: oscargare, facturadirecta
Tags: woocommerce, facturadirecta, invoices
Requires at least: 4.4
Requires PHP: 5.6
Tested up to: 4.9
Stable tag: 1.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce integration to your FacturaDirecta billing system. Automatic creation of invoices and customers in your FacturaDirecta account.

== Description ==

FacturaDirecta plugin for WooCommerce allows you to automate all your business billing. Send custom invoices and manage your online store the easiest way. 

> Note: the plugin works only for FacturaDirecta classic. If you have any questions about the plugin, you can contact us [here](https://www.facturadirecta.com/ayuda/).

That's what FacturaDirecta plugin for WooCommerce does:

* Every time an order is successfully completed, the invoice (and your customer) is automatically created in your FacturaDirecta account.
* It allows you to attach the PDF invoice in the completed order email.
* It allows you to add a link to download the invoice to the view-order page of the user's account.
* It allows you to add a Tax ID field (with format validation) in the chekout.

= Also in FacturaDirecta: =

* Customize and send your invoices by mail, duplicate, download and much more.
* Create refund and EU invoices.
* Control your invoicing anywhere and using any device.
* Upload your expenses.
* Perform bank reconciliation.
* Autofill your taxes models (303, 130, 111, 115...)
* Manage your customers, suppliers and employees.
* Analyze your business data anytime.

= Getting Started =

* Sign up at FacturaDirecta and get your API key.
* Install and activate FacturaDirecta plugin.
* Go to WooCommerce -> Settings -> Integrations -> FacturaDirecta and set you API key.
* Enjoy!

== Installation ==

= Minimum Requirements =

* PHP version 5.6 or greater
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)
* WordPress 4.4+
* WooCommerce 3.0+
* An account in [FacturaDirecta](https://www.facturadirecta.com/) classic.

= Manual installation =

The manual installation method involves downloading WooCommerce FacturaDirecta plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Screenshots ==

1. Settings.
2. Tax ID field in the checkout.
3. Download invoice link.
4. PDF invoice in the completed order email.

== Changelog ==

= 1.1 - 2018.10.01 =
* Added: 'woocommerce_factura_create_invoice' filter to allow developers to stop the send invoice process when order changes the status to completed.
* Added: 'woocommerce_factura_directa_client_data' filter to allow developers to modify client data before adding them to FacturaDirecta.

= 1.0 - 2018.02.13 =
* First Release
