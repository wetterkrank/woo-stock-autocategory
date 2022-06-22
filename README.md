# woo-stock-autocategory
A plugin for WooCommerce to automatically keep in-stock products in a selected category.  
Inititally developed for https://www.rococo-vintage.com/

Installation:
- Copy plugin files into the wp-content/plugins/woo-stock-autocategory directory
- Activate the plugin at Plugins page

Setup:
- Create the Woocommerce product category that will be used for in-stock products
- Open Woocommerce settings > Products tab > In-stock category section
- Enter the new category's id

All the in-stock products will be automatically added to this category.
When a product's stock status changes, the category will be automatically updated for this product.

You can trigger the full syncronisation, if needed, at the settings page by saving the settings again.
