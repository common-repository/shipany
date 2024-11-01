=== ShipAny WooCommerce: Ship, Label, Tracking ===
Contributors: shipany
Tags: shipping, SF Express, tracking, label, 順豐, ZTO, 中通, Jumppoint, Kerry, ShipAny, WooCommerce, Woo Commerce, Woocom, Shipping, label creation, label printing, shipping rates
Requires at least: 4.1
Tested up to: 6.5.5
Stable tag: 1.1.60
Requires PHP: 5.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ShipAny one-stop logistics platform interconnects WooCommerce to multiple logistics service providers (including SF Express, Kerry Express, Jumppoint, SF Cold-Chain, Alfred Locker, Hongkong Post, SF Locker, Convenience Store, etc.) so merchants can enjoy full-set features of logistics automation which disrupt the manual logistics process and bring E-Commerce to new generation.

== Description ==

E-commerce has strong demand for logistics services. ShipAny one-stop logistics platform interconnects multiple e-commerce platforms to multiple logistics service providers (including SF Express, Kerry Express, Jumppoint, SF Cold-Chain, Alfred Locker, Hongkong Post, SF Locker, Convenience Store, etc.), so that merchants can enjoy full-set features of logistics automation for E-Commerce platform which disrupt the manual logistics process and bring E-Commerce to new generation.

= Key Features =

* Auto Onboarding: Self-serve register in ShipAny portal to start shipping instantly and not requiring courier accounts application separately.
* One-Click Connect: Connect online store to ShipAny (and all couriers) by one-click install store plugin (e.g. Wordpress app). Merchant could also enjoy ShipAny diversified logistics couriers through ShipAny web portal or connect ShipAny Open API for all operations.
* Logistics Automation: Automatically submit the shipping order to the logistics provider, request on-site collection, and automatically generate and print the waybill.
* Diversified Options: door-to-door pickup and delivery, smart locker, convenience store pickup and frozen cold-chain shipping.
* Warehouse Fulfilment Service: E-Commerce orders could be pick-n-pack, shipped and fulfilled automatically if products pre-stored in ShipAny partner warehouse.

== Installation ==

= Minimum Requirements =

* WordPress 4.1 or greater
* WooCommerce 2.4 or greater
* PHP version 5.6 or greater

= Countries supported =

ShipAny is currently available for stores located in Hong Kong.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your browser. To do an automatic install of ShipAny-WooCommerce-Plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type **ShipAny** and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

1. Unzip the files and upload the folder into your plugins folder (/wp-content/plugins/) overwriting older versions if they exist

2. Activate the plugin in your WordPress admin area.

= Set up =

1. Sign up for free at [www.shipany.io](https://portal.shipany.io/user/register). 

2. You can then retrieve your Access Token from your Settings at upper-right button after login [portal.shipany.io](https://portal.shipany.io/).

3. After you activate the plugin, in the WooCommerce Setting page, go to the Shipping Tab. Enter your API Token and save.

4. Remember to top-up logistics fee in [portal.shipany.io](https://portal.shipany.io/) before the first shipment.

== Frequently Asked Questions ==

== Screenshots ==

1. Self-Register and Connect Logistics Couriers Instantly
2. Top-up Logistics Fee by Credit Card or Bank Transfer
3. Best Online Store Logistics Shipping Rate with Real-time Quotation & Rate Comparison
4. ShipAny Portal for Comprehensive Logistics Operations
5. Parcel Collection Request Submission through WooCommerce Backend or ShipAny Portal
6. Waybill Printing through WooCommerce Backend or ShipAny Portal
7. Unified Delivery Tracking for All Logistics Couriers

== Changelog ==

= 1.1.60 =
- Enhanced locker listing speed

= 1.1.59 =
- Bug fix

= 1.1.58 =
- Fix writing wrong country on checkout page

= 1.1.57 =
- Bug fix

= 1.1.56 =
- Bug fix

= 1.1.55 =
- Bug fix

= 1.1.54 =
- Removed dependency on polyfill[.]io to address supply chain attack vulnerability.

= 1.1.53 =
- Removed dependency on polyfill[.]io to address supply chain attack vulnerability.

= 1.1.52 =
- Removed dependency on polyfill[.]io to address supply chain attack vulnerability.

= 1.1.51 =
- Support Thailand region

= 1.1.50 =
- Fixed location list bug

= 1.1.49 =
- Skip translations for GTranslate

= 1.1.48 =
- Skip translations for GTranslate

= 1.1.47 =
- Support passing customers' note to ShipAny

= 1.1.46 =
- Bug fix

= 1.1.45 =
- Bug fix

= 1.1.44 =
- Support for WooCommerce Blocks

= 1.1.43 =
- Fixed label regeneration unexpectedly when order was updated

= 1.1.42 =
- Fixed label generation getting billing address line issue

= 1.1.41 =
- Fixed Checkout page bug
- Support Self Drop-off

= 1.1.40 =
- Fixed Macau locker option bug

= 1.1.39 =
- Fixed compatibility issue with wordpress 6.0.X or earlier

= 1.1.38 =
- Fixed bug of not regenerating waybill label after order update

= 1.1.37 =
- Fixed setting page bug

= 1.1.36 =
- Fixed checkout page bug

= 1.1.35 =
- Support FamilyMart C2C for TW

= 1.1.34 =
- Bug fix

= 1.1.33 =
- Bug fix

= 1.1.32 =
- Bug fix

= 1.1.31 =
- Bug fix

= 1.1.30 =
- Bug fix

= 1.1.29 =
- Bug fix

= 1.1.28 =
- Add support for [High-Performance Order Storage](https://woo.com/document/high-performance-order-storage/)

= 1.1.27 =
- Enhance user experience on shopping cart page

= 1.1.26 =
- Enhance shipment auto creation

= 1.1.25 =
- Bug fix

= 1.1.24 =
- Bug fix

= 1.1.23 =
- Bug fix

= 1.1.22 =
- Enhance location list performance

= 1.1.21 =
- Bug fix

= 1.1.20 =
- Bug fix

= 1.1.19 =
- Bug fix

= 1.1.18 =
- Enhanced Order edit feature
- Fixed potential Order edit fatal error

= 1.1.17 =
- Bug fix

= 1.1.16 =
- Bug fix

= 1.1.15 =
- Fixed location list bug

= 1.1.14 =
- Enhance Order edit feature
- Fixed plugin conflict

= 1.1.13 =
- Bug fix

= 1.1.12 =
- Add back missing version number

= 1.1.11 =
- Hotfix E_ERROR

= 1.1.10 =
- Bug fix

= 1.1.9 =
- Fixed potential setting page keeps loading
- Fixed create order failed if no amount item exists

= 1.1.8 =
- Bug fix

= 1.1.7 =
- Fixed missing field when create order

= 1.1.6 =
- Hotfix

= 1.1.5 =
- Fixed location list bug
- Fixed potential pay by receiver bug

= 1.1.4 =
- Fixed potentail push notification fail
- Fixed location list bug
- Enhanced translation
- Enhanced connect access token flow

= 1.1.3 =
- Fixed redeclare function exception

= 1.1.2 =
- Fixed WooCommerce Order number passing bug
- Fixed item SKU parsing bug when creating an international order

= 1.1.1 =
- Bug fix

= 1.1.0 =
- Support Traditional Chinese Language
- Enhanced ShipAny connection establishment

= 1.0.73 =
- Fixed bug in handling locker address during checkout

= 1.0.72 =
- Support taking product variants in item description

= 1.0.71 =
- Fixed virtual products related bugs

= 1.0.70 =
- Fixed potential waybill download failure in the order list page
- Fixed potential location list showing an unexpected result

= 1.0.69 =
- Fixed order page related bug

= 1.0.68 =
- Fixed incomplete customer note
- Enhance location list feature

= 1.0.67 =
- Enhance location list filter feature

= 1.0.66 =
- Fix checkout related bugs

= 1.0.65 =
- Support SF Express (Cold Chain)

= 1.0.64 =
- Enhanced checkout flow for local pickup shipping method

= 1.0.63 =
- Enhanced SF Express International shipment

= 1.0.62 =
- Fix self-customize checkout page cannot show ShipAny pick-up dialog
- Fix potential cannot create new page issue

= 1.0.61 =
- Enhanced ShipAny connection establishment

= 1.0.60 =
- Fix potential passing an inaccurate item weight to ShipAny

= 1.0.59 =
- Enhanced ShipAny connection establishment

= 1.0.58 =
- Support SF Express Macau shipment
- Support customize the ShipAny Order Ref

= 1.0.57 =
- Fix potential conflict with certain recovering orders plugin
- Hide the shipping price when the value is zero for local pickup

= 1.0.56 =
- Fix local pick up not show the shipping fee

= 1.0.55 =
- Fix unexpected renaming of all local pickup shipping methods during checkout

= 1.0.54 =
- Fix weight unit conversion bug
- Enhance system performance

= 1.0.53 =
- Fix potential WordPress Multisite cannot download waybill

= 1.0.52 =
- Fix fail to create order when some product dimensions value is defined

= 1.0.51 =
- Support SF E-comm Box

= 1.0.50 =
- Fix potential conflict with certain performance tuning plugin

= 1.0.49 =
- Fix potential courier list cannot be loaded on the setting page

= 1.0.48 =
- Fix potential shipping method disappeared on the checkout page

= 1.0.47 =
- Fix custom “Change address” Text Revert to Default After Changing Shipping Option
- Fix the warning caused by direct access order properties

= 1.0.46 =
- Fix potential ShipAny pick-up dialog cannot be loaded when single shipping method is defined

= 1.0.45 =
- Fix auto-create order feature fails when certain payment gateway plugins installed
- Fix rate query related bugs for UPS

= 1.0.44 =
- Support SF Express Pay By Receiver
- Support customize the locker listing Change Address button wording
- Fix rate query related bugs

= 1.0.43 =
- Fix shipping method duplicated when certain couriers are set as default courier

= 1.0.42 =
- Fix locker list minimum checkout amount setting not take effect for order contains Bundle Product

= 1.0.41 =
- Fix potential country field value empty issue

= 1.0.40 =
- Fix potential cache issue when updating the plugin
- Enhanced locker listing UI for mobile view

= 1.0.39 =
- Fix locker listing modal not close when changing the shipping method
- Bug fix

= 1.0.38 =
- Support UPS courier
- Enhanced system performance and logging

= 1.0.37 =
- Enhanced locker listing UI for mobile view
- Fixed locker listing error caused by variable scope

= 1.0.36 =
- Fixed potential locker listing location filter issue

= 1.0.35 =
- Fixed potential locker listing translation issue in IOS devices
- Support enable/disable writing tracking note to WooCommerce internal note

= 1.0.34 =
- Fixed potential locker listing show unexpected district and area
- Fixed potential Class not included uncaught error

= 1.0.33 =
- Support Locker/Store List Minimum Checkout Amount for Free Shipping
- Bug fix

= 1.0.32 =
- Fixed potential locker listing issue

= 1.0.31 =
- Support Alfred locker listing
- Enhance Order edit feature
- Enrich order notes information during create order
- Fix WordPress Multisite cannot download waybill
- Fix conflict with WPML plugin

= 1.0.30 =
- Remove unnecessary API call to improve performance

= 1.0.29 =
- Support more order status in active status from ShipAny

= 1.0.28 =
- Support active status and tracking number update from ShipAny

= 1.0.27 =
- Support customised tracking note message

= 1.0.26 =
- Fixed local pickup option sometimes enabled by cannot be detected
- Fixed checkout page sometimes goes completely blank in some Wordpress UI theme
- Enhance to allow enable or disable locker listing function

= 1.0.25 =
- Fixed waybill family name issue and locker listing in certain theme

= 1.0.24 =
- Fixed potential locker listing not show issue

= 1.0.23 =
- Support batch create ShipAny orders in order list view
- Support Chinese UI in frontend locker listing
- Better UI to enable locker listing in setting

= 1.0.22 =
- Fix potential issue of courier list cannot be loaded

= 1.0.21 =
- Support checkout locker listing
- Bug fix

= 1.0.20 =
- Fixed invalid JSON potential issue when create order
- Fixed sender company name not passing to ShipAny
- Reduce plugin file size
- Enhance logging

= 1.0.19 =
* Fixed recreate order button text display issue

= 1.0.18 =
* Fixed potential slow issue if conflict with other plugin

= 1.0.17 =
* Enhance error message if API timeout

= 1.0.16 =
* Fixed Havi cold chain temperature type issue and potential API timeout issue

= 1.0.15 =
* Fix create order occasionally keep loading and enhance logging

= 1.0.14 =
* Support edit order(Shipping address, item qty)
* Add estimated shipping price under order status
* Fixed column width in order list page
* Disable send pickup request after the order is cancelled
* Fixed package weight/dimensions related issue.

= 1.0.13 =
* Support Havi Cold-Chain courier

= 1.0.12 =
* Fixed empty space at front of API token issue

= 1.0.11 =
* Fixed store address country field cannot be updated after plugin installed

= 1.0.10 =
* Fixed default courier setting cannot be saved
* Fixed occasionally waybill label cannot be downloaded

= 1.0.9 =
* Fixed parcel weight calculation

= 1.0.8 =
* Fixed empty API token properly

= 1.0.7 =
* Fixed empty API token input error

= 1.0.6 =
* Fixed Class cannot be loaded during activate plugin

= 1.0.5 =
* Fixed Auth class cannot be loaded during activate plugin

= 1.0.4 =
* Fixed Logger Driver loading sequence error during activate plugin

= 1.0.3 =
* Remove duplicate trunk folder that will cause plugin activate failed

= 1.0.2 =
* Fix banner image

= 1.0.1 =
* First release version of ShipAny WooCommerce plugin: Ship, Label, Track
* Feature - Support SF Express, ZTO Express and Zeek logistics couriers
* Feature - Place delivery order automatically
* Feature - Generate waybill automatically
* Feature - Support parcel collection request
* Feature - Support tracking number and tracking URL notification to consumers

== Upgrade Notice ==
