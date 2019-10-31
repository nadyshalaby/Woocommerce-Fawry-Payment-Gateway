<?php

defined('ABSPATH') or die('No scripting kidding');

/**
 * Fawry Payment Gateway for woocommerce
 */

class WC_Fawry_Gateway extends WC_Payment_Gateway
{


  const FAWRY_API_ENDPOINT = 'https://www.atfawry.com/ECommercePlugin/FawryPay.jsp';
  const FAWRY_TEST_API_ENDPOINT = 'https://atfawry.fawrystaging.com/ECommercePlugin/FawryPay.jsp';

  /**
   * Class constructor, more about it in Step 3
   */
  public function __construct()
  {

    $this->id = 'fawry'; // payment gateway plugin ID
    $this->icon = FAWRY_PLUGIN_URI . 'images/logo_small.png'; // URL of the icon that will be displayed on checkout page near your gateway name
    $this->has_fields = true; // in case you need a custom credit card form
    $this->method_title = __('Fawry Payment Gateway', 'fawry');
    $this->method_description = __('Pay for your Order with any Credit or Debit Card or through Fawry Machines', 'fawry'); // will be displayed on the options page
    $this->description = $this->get_option('description', __('Pay for your Order with any Credit or Debit Card or through Fawry Machines', 'fawry'));
    
    // gateways can support subscriptions, refunds, saved payment methods,
    // but in this tutorial we begin with simple payments
    $this->supports = array(
      'products'
    );
    
    // Method with all the options fields
    $this->init_form_fields();
    
    // Load the settings.
    $this->init_settings();
    
    $this->title = $this->get_option('title');
    $this->enabled = $this->get_option('enabled');
    $this->testmode = 'yes' === $this->get_option('testmode');
    $this->merchant_key = $this->get_option('merchant_key');
    $this->secret_key = $this->get_option('secret_key');
    $this->expiry = $this->get_option('expiry');
    $this->language = $this->get_option('language');
    $this->instructions = $this->get_option( 'instructions', $this->description );
    
    // This action hook saves the settings
    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    
    // We need custom JavaScript to obtain a token
    add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

    // You can also register a webhook here
    add_action('woocommerce_api_fawry-payment-success', array($this, 'success_webhook'));
    add_action('woocommerce_api_fawry-payment-failed', array($this, 'failed_webhook'));
  }

  /**
   * Plugin options, we deal with it in Step 3 too
   */
  public function init_form_fields()
  {
    $this->form_fields = array(
      'enabled' => array(
        'title'       => 'Enable/Disable',
        'label'       => 'Enable Fawry Gateway',
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
      ),
      'title' => array(
        'title'       => 'Title',
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'fawry'),
        'default'     => __('Fawry Pay', 'fawry'),
        'desc_tip'    => true,
      ),
      'expiry' => array(
        'title'       => 'Unpaid Order Expiry(Hours)',
        'type'        => 'text',
        'description' => __('This controls the un paid order expiry which the user created during checkout.', 'fawry'),
        'default'     => "2",
        'desc_tip'    => true,
      ),
      'description' => array(
        'title'       => 'Description',
        'type'        => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'fawry'),
        'default'     => __('Pay for your Order with any Credit or Debit Card or through Fawry Machines.', 'fawry'),
      ),
      'instructions' => array(
        'title'       => 'Instructions',
        'type'        => 'textarea',
        'description' => __('This controls the instructions which the user sees during checkout.', 'fawry'),
        'default'     => '',
      ),
      'testmode' => array(
        'title'       => 'Test mode',
        'label'       => 'Enable Test Mode',
        'type'        => 'checkbox',
        'description' => 'Place the payment gateway in test mode using test API keys.',
        'default'     => 'yes',
        'desc_tip'    => true,
      ),
      'language' => array(
        'title'       => 'Language',
        'label'       => 'Choose Language',
        'type'        => 'select',
        'options'     => [
          'ar-eg' => __('Arabic', 'fawry'),
          'en-gb' => __('English', 'fawry'),
        ],
        'description' => 'Language of the payment popup.',
        'default'     => 'ar-eg',
      ),
      'merchant_key' => array(
        'title'       => 'Merchant Key',
        'type'        => 'text'
      ),
      'secret_key' => array(
        'title'       => 'Secret Key',
        'type'        => 'password',
      )
    );
  }

  /**
   * You will need it if you want your custom credit card form, Step 4 is about it
   */
  public function payment_fields()
  { }

  /*
 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
 */
  public function payment_scripts()
  {

    // we need JavaScript to process a token only on cart/checkout pages, right?
    if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
      return;
    }

    // if our payment gateway is disabled, we do not have to enqueue JS too
    if ('no' === $this->enabled) {
      return;
    }

    // no reason to enqueue JavaScript if API keys are not set
    if (empty($this->merchant_key) || empty($this->secret_key)) {
      return;
    }

    // do not work with card detailes without SSL unless your website is in a test mode
    if (!$this->testmode && !is_ssl()) {
      return;
    }
  }

  /*
  * Fields validation, more in Step 5
 */
  public function validate_fields()
  { }

  /*
 * We're processing the payments here, everything about it is in Step 5
 */
  public function process_payment($order_id)
  {
    global $woocommerce;

    // we need it to get any order detailes
    $order = wc_get_order($order_id);

    $signature = wp_generate_uuid4();

    update_option("fawry-$order_id-signature", $signature);

    /*
    * Array with parameters for API interaction
    */

    $items = [];

    foreach ($order->get_items() as $item) {
      // The WC_Product object
      $product = $item->get_product();

      array_push($items, [
        "productSKU" => $product->get_sku(),
        "description" => $item->get_name(),
        "price" => $item->get_total(),
        "quantity" => $item->get_quantity(),
      ]);
    }

    // Add shipping cost
    array_push($items, [
      "productSKU" => __('Shipping', 'fawry'),
      "description" => $order->get_shipping_method(),
      "price" => $order->get_total_shipping(),
      "quantity" => 1,
    ]);


    $cart = [
      "successPageUrl" => home_url("/wc-api/fawry-payment-success/"),
      "failerPageUrl" => home_url("/wc-api/fawry-payment-failed/"),
      "chargeRequest" => json_encode([
        "language" => $this->language,
        "signature" => $signature,
        "merchantCode" => $this->merchant_key,
        "merchantRefNumber" => $order_id,
        "customer" => [
          "name" => sprintf("%s %s", $order->get_billing_first_name(), $order->get_billing_last_name()),
          "mobile" => $order->get_billing_phone(),
          "email" => $order->get_billing_email(),
          "customerProfileId" => $order->get_customer_id(),
        ],
        "order" => [
          "description" => $order->get_customer_note(),
          "expiry" => $this->expiry ?: "2",
          "orderItems" => $items,
        ],
      ])
    ];

    $url = add_query_arg($cart, $this->testmode ? self::FAWRY_TEST_API_ENDPOINT : self::FAWRY_API_ENDPOINT);

    // Mark as on-hold (we're awaiting the cheque)
    $order->update_status('pending', __('Awaiting fawry payment', 'fawry'));

    // Remove cart
    $woocommerce->cart->empty_cart();

    // Return thank you redirect
    return array(
      'result' => 'success',
      'redirect' => $url
    );
  }

  /** 
   * In case of a successful webhook,
   * @example: https://example.com/wc-api/fawry-payment-success/
   */
  public function success_webhook()
  {
    $resp = json_decode(stripcslashes($_GET['chargeResponse']));

    $order = wc_get_order($resp->merchantRefNumber);
    // If order not found.
    if (!$order) {
      wc_add_notice(sprintf(__('Order #%d not found', 'fawry'), $resp->merchantRefNumber), 'error');
      wp_redirect(wc_get_cart_url());
      exit;
    }

    $order->payment_complete($resp->fawryRefNumber);
    $order->reduce_order_stock();

    wc_add_notice(__("Your payment for order#{$resp->merchantRefNumber} has succeeded.", "fawry"));

    wp_redirect($this->get_return_url($order));
    exit;
  }

  /**
   * In case of a failure webhook,
   * @example: https://example.com/wc-api/fawry-payment-failed/
   */
  public function failed_webhook()
  {

    $order_id = filter_input(INPUT_GET, 'merchantRefNum', FILTER_VALIDATE_INT);

    $order = wc_get_order($order_id);

    // If order not found.
    if (!$order) {
      wc_add_notice(sprintf(__('Order #%d not found', 'fawry'), $order_id), 'error');
      wp_redirect(wc_get_cart_url());
      exit;
    }

    $order->update_status('failed', __('Fawry payment failure', 'fawry'));

    wp_redirect($this->get_return_url($order));

    exit;
  }
}
