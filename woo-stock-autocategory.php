<?php
/*
Plugin Name: Auto-Category for WooCommerce In-Stock Products
Description: Automatically sets the chosen category for in-stock products, and removes this category when the product goes out of stock.
Version: 1.0.0
Author: Alex Antsiferov
Author URI: https://yak.supplies
License: GPLv2 or later
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

if (!class_exists('Woo_Stock_Autocategory') && in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	class Woo_Stock_Autocategory
	{
		public $cat_id;

		function __construct()
		{
			// try to load target category id from settings
			$this->cat_id = get_option('wc_autocat_id');

			// bind automatic action to the stock status change in WooCommerce
			if ($this->cat_id) {
				add_action('woocommerce_product_set_stock_status', [$this, 'update_category_on_stock_change'], 10, 3);
				// the below can potentially be called on ANY update to product; note different parameters
				// add_action( 'woocommerce_update_product', [$this, 'update_category_on_stock_change'], 10, 2 );
			}

			//  create our settings section in the products tab
			add_filter('woocommerce_get_sections_products', [$this, 'add_settings_section']);
			add_filter('woocommerce_get_settings_products', [$this, 'return_settings_section'], 10, 2);
			// this validates for the new category id and queues the update of all products
			add_filter('woocommerce_admin_settings_sanitize_option_wc_autocat_id', [$this, 'sanitize_cat_id_setting'], 10, 3);
			
			// this filter can be used to completely prevent the save:
			// add_filter('woocommerce_save_settings_products_autocat', [$this, 'confirm_option_save']);
			// with this action, we can trigger the update right away:
			// add_action('woocommerce_update_options_products_autocat', [$this, 'update_all_products']);
		}


		// Sets/unsets the category for selected product
		public function sync_one_product($stock_status, $product)
		{
			$category_ids = $product->get_category_ids();
			if ($stock_status === 'instock') {
				if (!in_array($this->cat_id, $category_ids)) {
					array_push($category_ids, $this->cat_id);
					$product->set_category_ids($category_ids);
					$product->save();
				}
			} elseif ($stock_status === 'outofstock') {
				if (false !== $key = array_search($this->cat_id, $category_ids)) {
					unset($category_ids[$key]);
					$product->set_category_ids($category_ids);
					$product->save();
				}
			} else {
				error_log('Autocategory: Unexpected stock status: ' . print_r($stock_status, true));
			}
		}
		
		// Action to perform on one product when stock status changes
		public function update_category_on_stock_change($id, $stock_status, $product)
		{
			// check if the category with selected cat_id exists
			$categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids']);
			if (in_array($this->cat_id, $categories)) {
				$this->sync_one_product($stock_status, $product);
			} else {
				error_log('Autocategory: Wrong category id, check the settings: ' . print_r($this->cat_id, true));
			}
		}
		
		// Loops through all products and syncs the category
		// Note: action hook for scheduled callback must be created outside of the class
		public function background_allproducts_update()
		{
			$query = new WC_Product_Query(array('limit' => -1));
			$products = $query->get_products();
			foreach ($products as $product) {
				$stock_status = $product->get_stock_status();
				$this->sync_one_product($stock_status, $product);
			}
		}
		
		// Adds the background action to the queue; NB: action is added outside the class
		public function queue_allproducts_update()
		{
			WC()->queue()->add( 'stock_category_sync', array(), '' );
		}
		
		// Validates the submitted id; if this filter returns false, WC won't save the settings
		public function sanitize_cat_id_setting($value, $option, $raw_value) {
			$value = absint($value);
			if ($this->category_exists($value)) {
				// set the new category id, so we can use it right away, and update products
				$this->cat_id = $value;
				$this->queue_allproducts_update();
			} else {
				$value = NULL;
				WC_Admin_Settings::add_error('Category with this id doesn\'t exist, settings not updated.');
			}
			return $value;
		}
		
		public function category_exists($id) {
			$cat_ids = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids']);
			return in_array($id, $cat_ids);
		}

		// Adds a section in WC product settings
		public function add_settings_section($sections)
		{
			$sections['autocat'] = ('In-stock category');
			return $sections;
		}

		// Called on the settings section open, returns the settings
		public function return_settings_section($settings, $current_section)
		{
			//  only return our settings if the current section is ours
			if ($current_section === 'autocat') {
				return $this->list_settings();
			} else {
				return $settings;
			}
		}

		// Returns the WC-style array with our settings structure
		public function list_settings()
		{
			$settings = array();
			// section title
			$settings[] = array(
				'name' => "In-stock category settings",
				'type' => 'title',
				'desc' => '',
				'id' => 'autocat'
			);
			// text field
			$settings[] = array(
				'name'     => 'Category id',
				'desc_tip' => 'When the changes are saved, the selected category will be set (or unset) for all products depending on their stock status',
				'id'       => 'wc_autocat_id',
				'type'     => 'number',
				'desc'     => 'Category with this id will be maintained for in-stock products',
			);
			// section end
			$settings[] = array('type' => 'sectionend', 'id' => 'autocat');
			return $settings;
		}
	}

	$woo_stock_autocategory = new Woo_Stock_Autocategory();

	// The queue action must be added outside the class:
	add_action('stock_category_sync', [$woo_stock_autocategory, 'background_allproducts_update']);
}
