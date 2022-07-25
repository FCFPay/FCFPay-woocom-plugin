<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HELPER COMMENT START
 * 
 * This class contains all of the plugin related settings.
 * Everything that is relevant data and used multiple times throughout 
 * the plugin.
 * 
 * To define the actual values, we recommend adding them as shown above
 * within the __construct() function as a class-wide variable. 
 * This variable is then used by the callable functions down below. 
 * These callable functions can be called everywhere within the plugin 
 * as followed using the get_plugin_name() as an example: 
 * 
 * FCFPAY->settings->get_plugin_name();
 * 
 * HELPER COMMENT END
 */

/**
 * Class Fcf_Pay_Settings
 *
 * This class contains all of the plugin settings.
 * Here you can configure the whole plugin data.
 *
 * @package		FCFPAY
 * @subpackage	Classes/Fcf_Pay_Settings
 * @author		 The FCF Inc
 * @since		1.1.0
 */
class Fcf_Pay_Endpoints{

	/**
	 * Namespace
	 *
	 * @var		string
	 * @since   1.1.0
	 */
    private $namespace = 'fcf-pay/v1';

    /**
     * Amount percent
     *
     * @var		integer
     * @since   1.1.0
     */
    private $amount_percent;

    /**
     * Max amount
     *
     * @var		integer
     * @since   1.1.0
     */
    private $max_amount;

    private $api_key;

	/**
	 * Our Fcf_Pay_Settings constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.1.0
	 */
    /**
     * WC_FCF_PAY_Api_Endpoints constructor.
     */
    public function __construct() {
        // Settings
        $settings = get_option( 'woocommerce_fcf_pay_settings' );
        $this->amount_percent = ! empty( $settings['amount_percent'] ) ? (int) $settings['amount_percent'] : '';
        $this->max_amount = ! empty( $settings['max_amount'] ) ? (int) $settings['max_amount'] : '';
        $this->api_key = !empty($settings['api_key']) ? $settings['api_key'] : '';

        // Routes
        $this->register_api_routes();
    }

    /**
     * Register routes for API endpoints
     */
    public function register_api_routes(){
        add_action( 'rest_api_init', function () {

            register_rest_route( $this->namespace, '/check-order', array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this,'check_order'),
            ) );

            register_rest_route( $this->namespace, '/order-status', array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this,'order_status'),
            ) );

        } );
    }

    /**
     * Check if order exists
     *
     * @param $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function check_order($request){
        $order = wc_get_order( $request['order_id'] );
        if (!$order) {
            return new WP_Error( 'not_found', 'Order not found', array('status' => 404) );
        }

        $response = new WP_REST_Response(
            array(
                'success' => true,
            )
        );
        $response->set_status(200);

        return $response;
    }

    /**
     * Change order status
     *
     * @param $request
     *
     * @return WP_Error|WP_REST_Response
     * @throws Exception
     */
    public function order_status($request){

        $payload = array(
            "order_id" => $request['data']["order_id"]
        );

        $ssl = false;

        if (is_ssl()) {
            $ssl = true;
        }

        $response = wp_remote_post(get_option('woocommerce_fcf_pay_settings')['environment_url'] . '/check-order', array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'content-type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 90,
            'sslverify' => $ssl,
        ));

        if (is_wp_error($response)) {
            $this->fcf_error_response('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.');
        }

        if (empty($response['body'])) {
            $this->fcf_error_response('Response was empty.');
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if (!$response_data['success']) {
            $this->fcf_error_response('Order id does\'nt exists.');
        }

        $amount = $response_data['data']["total_fiat_amount"] / pow(10, $response_data['data']["txs"][0]["decimal"]);
        $order_id = $response_data['data']["order_id"];
        $deposited = $response_data['data']["txs"][0]["deposited"];
        $currency = $response_data['data']["txs"][0]["currency"];
        $deposited_amount = $response_data['data']["txs"][0]["fiat_amount"];
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->fcf_error_response('Order not found');
        }

        if($deposited){
            $total = (float) $order->get_total();
            $percent = 100 - ( ( $deposited_amount / $total ) * 100 );

            if(is_null($deposited_amount)){
                $status = 'on-hold';
            }elseif($this->amount_percent === '' && $this->max_amount === '' && $deposited_amount >= $total){
                $status = 'completed';
            }else{
                if( ($this->amount_percent >= $percent && $this->max_amount > 0 && ($total - $deposited_amount) <= $this->max_amount) || ($this->amount_percent >= $percent && $this->max_amount <= 0) || ($total - $deposited_amount) <= $this->max_amount){
                    $status = 'completed';
                }else{
                    $status = 'processing';
                }
            }
        }else{
            $status = 'processing';
        }

        $deposited_status = $status == 'completed' ? 'Completed' : 'Pending';

        $order->update_status($status);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_amount', $amount);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_currency', $currency);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_amount_in_usd', $deposited_amount);
        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_status', $deposited_status);
        $response = new WP_REST_Response(
            array(
                'success' => true,
            )
        );
        $response->set_status(200);

        return $response;
    }


    public function fcf_error_response( $message ){
        $response = new WP_REST_Response(
            array(
                'success' => false,
                'message' => $message,
            )
        );
        $response->set_status(404);

        return $response;
    }
}
