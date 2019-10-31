<?php

/*
 * Plugin Name: WooCommerce Fawry Payment Gateway
 * Plugin URI: https://corecave.com/woocommerce/fawry-payment-gateway-plugin.html
 * Description: Pay for your Order with any Credit or Debit Card or through Fawry Machines
 * Author: Nady Shalaby
 * Author URI: https://corecave.com
 * Version: 1.0.0
 */

defined('ABSPATH') or die('No scripting kidding');

/**
 * Check if WooCommerce is active
 * */
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	exit;
}


defined('FAWRY_PLUGIN_PATH') or define('FAWRY_PLUGIN_PATH', plugin_dir_path(__FILE__));
defined('FAWRY_PLUGIN_URI') or define('FAWRY_PLUGIN_URI', plugin_dir_url(__FILE__));

/**
 * Load plugin textdomain.
 */
function fawry_load_textdomain()
{
	load_plugin_textdomain('fawry_textdomain', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('init', 'fawry_load_textdomain');

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
function fawry_add_gateway_class($gateways)
{
	$gateways[] = 'WC_Fawry_Gateway'; // your class name is here
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'fawry_add_gateway_class');

require_once plugin_dir_path(__FILE__) . '/inc/redefine-pluggable-functions.php';

/*
 * Load `WC_Fawry_Gateway` class on 'plugins_loaded' hook. 
 */
add_action('plugins_loaded', 'fawry_init_gateway_class');

function fawry_init_gateway_class()
{
	require_once plugin_dir_path(__FILE__) . '/inc/class-wc-fawry-gateway.php';
}
