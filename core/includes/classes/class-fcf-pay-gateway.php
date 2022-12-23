<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Fcf_Pay_Gateway
 *
 * This class contains the FCF Pay Gateway functions
 * that are used to receive payments.
 *
 * @package		FCFPAY
 * @subpackage	Classes/Fcf_Pay_Gateway
 * @author		 The FCF Inc
 * @since		1.1.0
 */

class Fcf_Pay_Gateway extends WC_Payment_Gateway
{
    private $api_key;

    private $redirect_url;

    private $notify_url;

    public $environment_url;

    public $amount_percent;

    public $max_amount;

    public $send_items;

    /**
     * WC_FCF_PAY constructor.
     */
    function __construct()
    {
        plugin_dir_url(__FILE__);

        $this->id = "fcf_pay";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("FCF PAY", 'fcf_pay');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("FCF PAY payment gateway", 'Description');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = $this->get_option('title') !== '' ? $this->get_option('title') : __("FCF PAY payment gateway", 'fcf_pay');

        $this->description = __("Easy cryptocurrency payments", 'fcf_pay');

        // Bool. Can be set to true if you want payment fields to show on the checkout
        $this->has_fields = true;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        $this->init_settings();

        // FCF_PAY api_url
        $this->environment_url = $this->settings['environment_url'];

        // FCF_PAY api_key
        $this->api_key = $this->settings['api_key'];

        // FCF_PAY Success URL
        $this->redirect_url = $this->settings['redirect_url'];

        // Amount percent for order confirmation
        $this->amount_percent = $this->settings['amount_percent'];

        // Allow to send items list to FCF Pay
        $this->send_items = $this->settings['send_items'];

        // Maximum percent amount for order confirmation
        $this->max_amount = $this->settings['max_amount'];

        $this->notify_url = str_replace('https:', 'http:', home_url('/checkout/order-received/'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_fcf_pay', array($this, 'check_fcf_pay_response'));
        do_action('woocommerce_set_cart_cookies', true);
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }

    }

    // Build the administration fields for this specific Gateway
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'fcf_pay'),
                'label' => __('Enable this payment gateway', 'fcf_pay'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'fcf_pay'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'fcf_pay'),
                'default' => __('FCF PAY', 'fcf_pay'),
            ),
            'api_key' => array(
                'title' => __('API key *', 'fcf_pay'),
                'type' => 'text',
                'desc_tip' => __('API key from FCF_PAY.', 'fcf_pay'),
            ),
            'environment_url' => array(
                'title' => __('API url', 'fcf_pay'),
                'type' => 'text',
                'desc_tip' => __('You can get the URL from your FCF account dashboard.', 'fcf_pay'),
                'default' => 'https://merchant.fcfpay.com/api/v2'
            ),
            'amount_percent' => array(
                'title' => __('Percent', 'fcf_pay'),
                'type' => 'number',
                'desc_tip' => __('Maximum percentage difference between the price and received funds to consider the transaction successful', 'fcf_pay'),
                'default' => 0
            ),
            'max_amount' => array(
                'title' => __('Max amount', 'fcf_pay'),
                'type' => 'number',
                'desc_tip' => __('Maximum difference in the currency selected between the price and received funds to consider the transaction successful', 'fcf_pay'),
                'default' => 0
            ),
            'redirect_url' => array(
                'title' => __('Redirect URL', 'fcf_pay'),
                'type' => 'text',
                'desc_tip' => __('URL to redirect the shopper after a successful transaction', 'fcf_pay'),
            ),
            'send_items' => array(
                'title' => __('Send items list to FCFPay', 'fcf_pay'),
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => __('When enabled, the plugin will send item description, quantity, price, total information and it will be shown on the checkout page', 'fcf_pay'),
            ),
        );
    }

    /**
     * Submit payment and handle response
     *
     * @param int $order_id
     *
     * @return mixed
     * @throws Exception
     *
     */
    public function process_payment($order_id)
    {
        $customer_order = new WC_Order($order_id);

        wc_reduce_stock_levels($order_id);

        $data = [
            'domain' => parse_url(home_url())['host'], // domain name
        ];

        $query_params = http_build_query($data);
        $environment_url = ($this->environment_url != '') ?$this->environment_url . '/create-order?' . $query_params : 'https://merchant.fcfpay.com/api/v2/create-order?' . $query_params;

        $currency_name = $customer_order->get_currency();
        $currency_code = $this->currency_code($currency_name);

        $payload = array(
            'domain' => parse_url(home_url())['host'], // url without http
            "order_id" => intval($customer_order->get_order_number()),
            "amount" => floatval($customer_order->order_total),
            "currency_name" => $currency_name,
            "currency_code" => $currency_code,
            "order_date" => date('Y-m-d'),
            "redirect_url" => ($this->redirect_url) ? $this->redirect_url . '?order_id='. $order_id . '&key=' . $customer_order->get_order_key() : $this->notify_url. $order_id .'/?key='.$customer_order->get_order_key() ,
        );

        // Check if send_items is equal to 'no'
        if ( $this->send_items === 'no' ) {   
            // Do nothing

        } else {
            
            // Add the items to the order details array
            $items = array();
            $i = 1;
            foreach ( $customer_order->get_items() as $item_id => $item ) {
                $product = $item->get_product();
                $items[$i] = array(
                    'Item Name' => $item->get_name(),
                    'Quantity' => $item->get_quantity(),
                    'Price' => $item->get_total() / $item->get_quantity(),
                    'Total' => $item->get_total(),
                );
                $i++;
                // Add items to payload
                $payload['items'] = $items;
            }
            
        }

        $ssl = false;

        if (is_ssl()) {
            $ssl = true;
        }

        $response = wp_remote_post($environment_url, array(
            'method' => 'POST',
            'body' => http_build_query($payload),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'timeout' => 90,
            'sslverify' => $ssl,
        ));

        if (is_wp_error($response)) {
            throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'fcf_pay'));
        }

        if (empty($response['body'])) {
            throw new Exception(__('Response was empty.', 'fcf_pay'));
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_data['success']) {
            return array(
                'result' => 'success',
                'redirect' => $response_data["data"]["checkout_page_url"],
            );
        } else {
            throw new Exception(__($response_data["errorMessage"], 'fcf_pay'));
        }
    }

    /**
     * Payment system image in checkout page
     * @return mixed|string|void
     */
    function get_icon()
    {
        $icon = '<img style="width:100px;height:auto;max-height:initial" src="' . FCFPAY_PLUGIN_URL . '/core/includes/assets/images/logo.png" alt="Cards" />';

        return apply_filters('fcf_pay_icon', $icon, $this->id);
    }


    /**
     * Get currency code
     * @param $cur string
     *
     * @return int|float
     */
    public function currency_code($cur)
    {
        switch ($cur) {
            case "USD":
                return 840;
                break;
            case "RUB":
                return 643;
                break;
            case "GBP":
                return 826;
                break;
            case "EUR":
                return 978;
                break;
            default:
                return 840;
        }
    }
}
