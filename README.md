=== SnappBox===
Contributors: your-wporg-username
Tags: woocommerce, shipping, delivery, tracking, orders
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WordPress/WooCommerce integration for Snappbox that retrieves and displays order status via the WordPress HTTP API (wp_remote_get).

== Description ==

Snappbox helps you track and display delivery statuses inside WordPress. This plugin provides:

* A secure HTTP client implementation via `wp_remote_get` (no cURL dependency).
* A simple PHP API for fetching an order’s current status from your Snappbox account.
* Optional shortcode to render an order’s status on any page/post.
* Filter hooks to customize headers, base URL, and output.

**Key points**

* Uses the **WordPress HTTP API** for compatibility with a wide range of hosting environments.
* Easy to extend with actions/filters.
* No front-end framework required; minimal styling to blend with your theme.

> Note: You’ll need a valid Snappbox API token and base URL from your Snappbox account.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/snappbox/` or install via the WordPress admin “Plugins → Add New”.
2. Activate the plugin through “Plugins”.
3. Define your credentials (recommended via `wp-config.php`) and, if needed, set the base URL.

Example in `wp-config.php`:

```php
// Snappbox credentials (example)
define( 'SNAPPBOX_API_TOKEN', 'Bearer YOUR_ACCESS_TOKEN' );
```

If your site sets a global base URL, ensure it is available (e.g., in `functions.php` or a small mu-plugin):

```php
// Example base URL
$api_base_url = 'https://api.snappbox.example.com';
```

== Usage ==

### 1) PHP API (for developers)

The plugin ships a class for fetching an order status:

```php
// Example usage in a theme or custom plugin:
if ( class_exists( 'SnappOrderStatus' ) ) {
    $snapp = new SnappOrderStatus(); // Token read from SNAPPBOX_API_TOKEN
    $status = $snapp->get_order_status( 'ORDER_ID_123' );

    if ( $status ) {
        // Do something with $status (object decoded from JSON)
        echo esc_html( $status->current_state ?? 'Unknown state' );
    }
}
```

### 2) Shortcode (optional)

Place this shortcode into a post or page:

```
[snappbox_order_status order_id="ORDER_ID_123"]
```

*Attributes*
- `order_id` (required): The Snappbox order ID you want to display.

> If you don’t plan to expose a shortcode publicly, you can remove or disable it.

== Frequently Asked Questions ==

= Does this require cURL? =

No. It uses the **WordPress HTTP API** (`wp_remote_get`) which supports multiple transports and is more portable than raw cURL.

= Where do I set the API token? =

Define `SNAPPBOX_API_TOKEN` in `wp-config.php`. You can also filter headers at runtime (see Filters).

= How do I change the Snappbox base URL? =

Set `$api_base_url` globally before the plugin initializes (for example in a small mu-plugin), or use the filter `snappbox/api_base_url`.

= Is there caching? =

This plugin doesn’t cache responses by default. You can wrap calls with your own transient/object cache, or hook into the request/response filters.

= Is the output translatable? =

Yes. Strings are wrapped in translation functions and a text domain is provided. Include a `.pot` file in `/languages` if distributing publicly.

== Screenshots ==

1. Front-end example of order status block.
2. Shortcode usage in the block editor.
3. Basic settings/credentials (if you add an admin page).

== Changelog ==

= 1.0 =
* Initial release.
* HTTP requests via `wp_remote_get`.
* Basic class API and optional shortcode.
* Filters for headers and base URL.

== Upgrade Notice ==

= 1.0 =
First stable release. Review the README to set your `SNAPPBOX_API_TOKEN` and base URL before use.

== Developer Notes ==

See inline documentation and filters in the main class.

== Privacy ==

This plugin connects to the Snappbox API to fetch order data you request. It does not collect or transmit personal data to third parties other than Snappbox. Ensure your privacy policy reflects any data processing performed by your site and by Snappbox.

== Development ==

* Issues: https://yourdomain.tld/snappbox/issues
* Docs: https://yourdomain.tld/snappbox/docs

Pull requests are welcome. Please follow WordPress coding standards and include PHPCS checks.
