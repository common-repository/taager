=== Taager ===
Contributors: amralgendy
Tags: Taager, Woocommerce
Requires at least: 6.0
Tested up to: 6.2
Stable tag: 1.16.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Taager Plugin is a one-stop-integration for your WordPress/woo-commerce with Taager account, Connect your Taager account with your store, Plan, Market, and Earn.

== Description ==

Taager offers a one-stop-shop platform for online merchants, Through Tagger, online sellers (Merchants) can start and run their retail businesses without risk, capital, or operational challenges, From merchandising, warehousing, shipping to the collection and profit conversion, it's all handled by Taager.
Taager built this plugin for you to connect your Taager account with your store in order to import all the categories and products in one click to your woo-commerce store also receive the orders you got through your store directly at your Taager's account orders page, Forget the hassle of uploading products and orders and focus on optimizing and achieving your targets.

Please check back frequently for new products as we are constantly expanding our list of products. Stay tuned for new exciting product categories to be added. You can follow us on Facebook and Instagram to stay updated on the latest trends and Taager.com news. With Taager.com, Plan, Market, and Earn.

== Key Features ==

* Ability to work on either Egypt, KSA, UAE or Iraq
* Import categories 
* Import products (by Name, SKU)
* Import the Products details, including product short description, product feature, images, and Taager Product Price provided by taager.com 
* Stock status and update it based on Taager stock
* Update the shipping cost 
* Free delivery feature
* Move the order directly to your Taager's order page at your account to be confirmed and shipped.

== Useful Links ==

Want to know everything about Taager plugin check this [link](https://www.youtube.com/playlist?list=PLmEy_hlZ7kB9iu8Osq7UjHpgaj8UeDjOL)

== Screenshots ==

1. User login
2. Country selection page
3. Account page
4. Importing products from Taager
5. Importing products success
6. Imported product
7. Updating shipping costs

== Frequently Asked Questions ==

= Can I change the country after I choose it? =

Currently no, you have to choose the country for the first time carefully, as the plugin works on one country only per store.

= What do I need to use this plugin? =

You need to have an account at Taager.com. If you don't have an account yet, Sign up from [here](https://taager.com/auth/register) it is Totally free, then activate the plugin and sign in using your email address/Taager id provided at your Taager account.

= What is Taager.com? =

Taager offers a one-stop-shop platform for online merchants, Through Tagger, online sellers (Merchants) can start and run their retail businesses without risk, capital, or operational challenges, From merchandising, warehousing, and shipping to the collection and profit conversion, it's all handled by Taager.

= Why Join Taager.com? =

* 2000+ products and 30+ categories enable you to start your e-commerce business 
* Taager confirm your received orders, package and ship them to your customers 
* 24/7 support

== Translations ==

This plugin currently operates in English (en) and Arabic (ar)

== Installation ==

1. Install using the WordPress built-in Plugin installer, or Extract the zip file and drop the contents in the wp-content/plugins/ directory of your WordPress installation.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Press the 'Taager-منصة تاجر' button.
4. Choose the country
5. Then press 'ربط المتجر'
6. Add your email address/Taager id and password connected to your Taager account and then 'تسجيل الدخول'، Now you are ready to get profits through your store.

== Changelog ==

= 1.16.0 =

* Checkout with credit card now accepts apple pay

= 1.15.0 =

* Checkout with credit card payment is now available

= 1.14.2 =

* Fixed an issue with importing products by categories

= 1.14.1 =

* Fixed an issue where installing the plugin was throwing a critical error

= 1.14.0 =

* Provinces will now be shown in English when webstore language is not Arabic

= 1.13.0 =

* Filtered out the product videos while importing product media
* Added IRQ support for eligible users
* Added phone number hint in phone number fields in checkout form

= 1.12.1 =

* Changed product and orders hourly update to be less frequent after ensuring its consistency

= 1.12.0 =

* Fixed an issue where allowed minimum price was calculated incorrectly when enabling free shipping on products
* Added a change to decrease the duplicate calls to update products and orders
* Change schedule of products and orders update to be distributed evenly across time

= 1.11.0 =

* Fixed a bug where scheduled updates of orders and products were not triggering properly

= 1.10.6 =

* Added additional tracking for product images upload

= 1.10.5 =

* Added tracking for product images upload

= 1.10.4 =

* Fixed an issue where sometimes the products import page would get stuck when importing some categories
* Fixed multiple PHP warnings

= 1.10.3 =

* Changed API timeout to 60s instead of the default 5s to accomodate for high latency
* Fixed an issue where products and orders were updated multiple times within one hour

= 1.10.2 =

* Added tracking for hourly fetch of products and orders
* Fixed an issue where product variants data were fetched hourly even if the product is in draft status

= 1.10.1 =

* Fixed an issue where if a wordpress error is thrown while order is being placed, the order is considered rejected
* Fixed an issue where if a product has no saved SKU, it would call the API fetching all products

= 1.10.0 =

* Add error handling for failing HTTP requests
* Added mixpanel tracking for API calls

= 1.9.3 =

* If initialization process is not complete after logging in to the plugin, the merchant is stuck where they are logged in but can't do any actions

= 1.9.2 =

* Fixed an issue with phone number validation on order placement when plugin selected country is UAE

= 1.9.1 =

* Updated querying of orders to use order_id if it exists instead of order_num
* Fixed fetching of scheduled cron jobs names to schedule the products and orders updater

= 1.9.0 =

* Fixed an issue with some undefined indecies and varaibles being accessed

= 1.8.1 =

* Fixed an issue that occurs if no cron jobs are scheduled

= 1.8.0 =

* Update hourly scheduled actions to alternate between updating products and orders
* Updated wordpress version support to 6.1

= 1.7.16 =
* Added localization to the plugin (Now available in English and Arabic languages)

= 1.7.15 =
* Fixed an issue with provinces active state and revenue

= 1.7.14 =
* Fixed an issue with updating shipping fees

= 1.7.13 =
* Added tracking events for products import success and failure
* Fixed an issue where country selection page is not shown if user has access to UAE but not KSA

= 1.7.12 =
* Fixed an issue where orders were being cancelled after a period of time of them being placed

= 1.7.10 =
* Showing rejection reasons if an order is rejected

= 1.7.9 =
* Added event tracking

= 1.7.8 =
* Fixed issue where shipping fees were incorrectly set after placing an order

= 1.7.7 =
* Fixed issue where importing variants doesn't reflect product `specifications` and `how to use` data

= 1.7.6 =
* Fixed plugin version doesn't update correctly in account page and orders info

= 1.7.5 =
* Fixed Taager product price is displayed incorrectly in variant products
* Fixed changing product price to a price lower than the taager least price on woocommerce plugin is allowed
* Fixed free shipping minimum product price is calculated incorrectly

= 1.7.4 =
* Updated the phone validation for SAU to be 10 digits
* Fixed trailing '?' character at the end of the request url if no data is passed to call_api function

= 1.7.3 =
* Usage of HTTP API instead of CURL

= 1.7.2 =
* Removed the usage of `session_start()` & `$_SESSION`
* Removed .bak and .pot files
* Tested up to value updated to latest Wordpress version
* Escaped echo'd variables and options
* Sanitizing input data
* Generic functions name change

= 1.7.1 =
* Fixed issue where users cannot add more than one variant to cart

= 1.7.0 =
* Added Variant groups support in plugin
* Fixed Products and orders scheduled update

= 1.6.2 =
* Fixed An issue where users logging in by email instead of phonenumber would get incorrect username and password error

= 1.6.0 =
* Added multitenancy in plugin
* Integrated new login API to replace old login flow
* Phone number length validation adapts to selected country
* Fixed Order Ids and statuses now correctly reflect on orders page on Wordpress

= 1.5.3 =
* Added a new field `pluginVersion` in orders placed, that reflects the plugin version used when this order was placed

= 1.5.2 =
* Fixed Issues with duplicate products' attributes not being updated

= 1.5.1 =
* Products stock status now relies on a new flag `isProductAvailableToSell` that reflects product availability, visibility to seller and expiry

== Upgrade Notice ==

= 1.7.14 =
This version fixes a bug with updating the shipping fees of provinces

= 1.7.12 =
This version fixes a bug with orders getting cancelled automatically

= 1.8.0 =
This version fixes an issue where a timeout sometimes occures when updating products and orders data
