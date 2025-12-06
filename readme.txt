=== VIP User Order ===
Contributors: yourusername
Tags: woocommerce, customer, orders, vip, tags, bangladesh
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display customer tags based on order count from the same phone number. Perfect for Bangladesh phone formats.

== Description ==

VIP User Order plugin helps you track customer loyalty in your WooCommerce store. It automatically identifies returning customers by their phone number and displays customizable tags on orders.

= Features =

* Track orders by phone number (supports +880 and 01 formats)
* Customizable customer tags
* Display tags in order list
* Bangladesh phone number format support (+8801XXXXXXXXX or 01XXXXXXXXX)
* HPOS (High-Performance Order Storage) compatible
* Built-in caching for performance
* Easy-to-use settings interface
* Test tool to verify phone number matching

= Supported Phone Formats =

* +8801712345678 (with country code)
* 8801712345678 (without + sign)
* 01712345678 (local format)
* Automatically normalizes all formats to 01XXXXXXXXX

= Default Tags =

* New Customer (1 order) - Blue
* Repeat Customer (2 orders) - Orange  
* Loyal Customer (3-5 orders) - Green
* VIP Customer (6+ orders) - Purple

You can customize all tags including colors and order ranges!

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > VIP User Tags to configure settings
4. Done! Tags will appear automatically in your order list

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes, WooCommerce must be installed and activated for this plugin to work.

= Does it support HPOS? =

Yes! The plugin fully supports WooCommerce's new High-Performance Order Storage system.

= Can I add my own custom tags? =

Yes! You can add unlimited custom tags with your own labels, colors, and order count ranges.

= What phone formats are supported? =

The plugin supports Bangladesh phone formats:
- +8801XXXXXXXXX (13 digits with country code)
- 01XXXXXXXXX (11 digits local format)
All formats are automatically normalized for matching.

= How does it count orders? =

The plugin counts all orders with the following statuses:
- Completed
- Processing  
- On-Hold
- Pending

Orders with other statuses (cancelled, refunded, etc.) are not counted.

= Is it performance optimized? =

Yes! The plugin uses caching to minimize database queries. Results are cached for 5 minutes.

== Changelog ==

= 1.0.2 =
* Fixed website breaking syntax errors
* Converted all text to English
* Improved Bangladesh phone format handling
* Added better error handling
* Added performance caching
* Improved HPOS compatibility
* Fixed phone number normalization

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.2 =
Critical update: Fixes website breaking errors. Update immediately.
