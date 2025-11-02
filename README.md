=== snappbox===
Contributors: @samooel, @snappbox
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

### Step 1: Download the Plugin
Open your browser and go to:  
https://snapp-box.com/wordpress-plugin  
Click the **“Download SnappBox Plugin for Free”** button.  
Fill out the displayed form (Business Name, Mobile Number, and Email) and click **Submit**.  
After submitting, the plugin file will automatically be downloaded.  

⚙️ **Step 2: Install the Plugin in WordPress**  
Log in to your WordPress dashboard (**wp-admin**).  
From the left menu, go to **Plugins → Add New**.  
Click **Upload Plugin** and select the downloaded zip file.  
After installation, click **Activate Plugin**.  

⚠️ Note: WooCommerce must already be installed and activated.  

🔑 **Step 3: Obtain and Register the Token (Easier Method)**  
After activating the plugin, a **SnappBox** option will appear in the WordPress menu. Open it.  
Click the **“Get Token”** button.  
On the opened page, enter your store name and phone number.  
Once submitted, your **API Token** will be displayed.  
Copy this token and paste it into the relevant field at the top of the plugin settings page.  
Click **Save Settings**.  

🛠 **Step 4: Plugin Basic Settings**

1. **Set Store Location**  
   - Pin your store location on the map.  
   - Enter your full store address in the text field.  

2. **Store Information**  
   - Enter your store name and phone number.  

3. **Enable SnappBox Shipping Method**  
   - Check the enable box → SnappBox will appear as a shipping option on the checkout page.  

4. **Select Cities and Regions**  
   - Choose the city or region where you want to provide delivery service.  

5. **Shipping Payment Methods**  
   - **Cash on Delivery**: Enable by ticking the checkbox.  
   - **Flat Rate**: Enter a fixed delivery fee (in Rials).  
   - **Free Shipping**: Define a minimum purchase amount for free shipping.  

6. **Connect to WooCommerce Shipping Zones**  
   - Go to **WooCommerce → Settings → Shipping → Shipping Zones**.  
   - Select or create a zone.  
   - Click **Add Shipping Method → SnappBox**.  
   - Save the settings.  

7. **Use SnappBox Wallet**  
   - If you have an organizational account, shipping costs can be deducted directly from your SnappBox wallet balance.  

✅ **Step 5: Test and Verify Functionality**  
Place a test order and choose SnappBox on the checkout page.  
In **WooCommerce → Orders**, open the order and make sure the SnappBox method is registered.  
Track the delivery status from the WordPress panel or the SnappBox mobile app.  

🎉 Now your store is ready to automatically and quickly deliver orders using the SnappBox fleet!  


== Source Code ==

This plugin includes minified JavaScript and CSS files for optimal performance. The source code for all compressed files is available within the plugin:

**JavaScript Source Files:**
- `assets/js/mapbox-gl-rtl-text.source.js` - Source code for the minified `mapbox-gl-rtl-text.js` file

All minified files have corresponding source files with `.source.js` extension for code review and development purposes.

== External Services ==

This plugin uses the following external resources that must be loaded via URL and cannot be imported individually:

**Mapbox RTL Text Plugin:**
- **URL**: `https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js`
- **Purpose**: Provides right-to-left text support for Mapbox GL JS
- **Usage**: Loaded dynamically via `maplibregl.setRTLTextPlugin()` method
- **Note**: This is a third-party library that must be loaded from the official CDN as it cannot be bundled or imported individually
- **Privacy**: It sends a basic request (without personal or sensitive data)

**Snapp Maps Style:**
- **URL**: `https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json`
- **Purpose**: Custom map styling for Snapp Maps integration
- **Usage**: Used as the map style configuration for Mapbox GL JS
- **Note**: This is a proprietary map style that must be loaded from Snapp Maps servers
- **Privacy**: It sends a basic request (without personal or sensitive data)

**Snapp Box Plugin Configuration:**
- **URL**: `https://assets.snapp-box.com/static/plugin/woo-config.json`
- **Purpose**: Main configuration file for Snapp Box plugin settings and parameters
- **Usage**: Loaded dynamically to configure plugin behavior and minimum wallet credit requirements
- **Note**: This is the primary configuration source that must be loaded from Snapp Box servers to ensure up-to-date settings
- **Privacy**: It sends a basic request (without personal or sensitive data)


Service provider: SnappBox
Terms of Service: https://snapp-box.com/terms
Privacy Policy: https://snapp-box.com/privacy

== Build Tools ==

This plugin uses standard web development practices with minified assets for production. The source files are included for transparency and to comply with WordPress.org guidelines for human-readable code.

