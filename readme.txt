=== InstaWP WaaS Integration ===
Contributors: instawp, infosatech
Tags: instawp, waas, staging
Requires at least: 5.4
Tested up to: 6.5
Stable tag: 1.0.4
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Using this WordPress plugin you can integrate WaaS with various checkout plugins like WooCommerce and Surecart.

== Description ==

Integration with WooCommerce and SureCart for InstaWP (WordPress as a Service) allows seamless e-commerce functionality within the InstaWP platform. This integration enables users to leverage the robust capabilities of WooCommerce and SureCart, empowering them to set up online stores, manage products, process payments, handle orders, and perform various e-commerce tasks directly through their InstaWP-powered websites.

Please note that this will create a unique WaaS Link for your customers when an order is placed via these checkout plugins, it will auto provision the website via the WaaS. To do that we are going to open our API soon. 

## WooCommerce

Please follow these steps

[https://www.youtube.com/watch?v=sxpVqExRelk](https://www.youtube.com/watch?v=sxpVqExRelk)

## Easy Digital Downloads

Please follow these steps

[https://www.youtube.com/watch?v=b7KTyQbWzFU](https://www.youtube.com/watch?v=b7KTyQbWzFU)

## SureCart 

Please follow these steps

[https://www.youtube.com/watch?v=Dh0EjpKNs2Q](https://www.youtube.com/watch?v=Dh0EjpKNs2Q)


== Changelog ==

= 1.0.4 =

* Added: Auto cancel subscription if order is cancelled in WooCommerce.
* Added: Auto cancel subscription if order is revoked in Easy Digital Downloads.
* Added: Auto cancel subscription if order items are revoked individually in SureCart.
* Added: Shortcode for WooCommerce -> `[instawp_waas_wc_links order_id="2"]`.
* Fixed: Email not sending if Send from app option is checked in WooCommerce.
* Fixed: Email not working in Easy Digital Downloads.

= 1.0.3 =

* Fixed: SureCart email not sending.

= 1.0.2 =

* Added: Easy Digital Downloads Support.

= 1.0.1 =

* Added: Updater
* Fixed: Production URL

= 1.0.0 =

* Initial Release