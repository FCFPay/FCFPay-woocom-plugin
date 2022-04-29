<?php

class Fcf_Pay_Order_Status
{
    private $amount_percent;

    private $max_amount;

    private $api_key;

    public function __construct()
    {
        $settings = get_option('woocommerce_fcf_pay_settings');
        $this->amount_percent = !empty($settings['amount_percent']) ? (int)$settings['amount_percent'] : '';
        $this->max_amount = !empty($settings['max_amount']) ? (int)$settings['max_amount'] : '';
        $this->api_key = !empty($settings['api_key']) ? $settings['api_key'] : '';
        add_action('admin_init', [$this, 'fcfpay_check_canceled_orders']);
    }

    function fcfpay_check_canceled_orders()
    {
        $cpt = 'shop_order';
        if (isset($_GET['post_type']) && $_GET['post_type'] == $cpt) {
            $orders = wc_get_orders(array(
                    'limit' => -1,
                    'type' => 'shop_order',
                    'status' => array('wc-cancelled', 'wc-pending', 'wc-on-hold')
                )
            );
            if (!empty($orders)) {
                $ids = [];
                foreach ($orders as $order) {
                    if ($order->get_payment_method() == 'fcf_pay') {
                        $ids[] = $order->get_id();
                    }
                }

                $payload = wp_json_encode(array(
                    "order_ids" => $ids
                ));

                $ssl = false;

                if (is_ssl()) {
                    $ssl = true;
                }

                $response = wp_remote_post(get_option('woocommerce_fcf_pay_settings')['environment_url'] . '/check-orders', array(
                    'method' => 'POST',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'content-type' => 'application/json'
                    ),
                    'body' => $payload,
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

                if (!$response_data['success']) {
                    return new WP_Error('not_found', 'Unique id does\'nt exists.', array('status' => 404));
                }

                foreach ($response_data['data'] as $check_order) {
                    $dep_amount = $check_order["amount"] != '' ? $check_order["amount"] : 0;
                    $decimal = $check_order["decimal"] != '' ? $check_order["decimal"] : 0;
                    $amount = $dep_amount / pow(10, $decimal);
                    $order_id = $check_order["order_id"];
                    $deposited = $check_order["deposited"];
                    $currency = $check_order["currency"];
                    $deposited_amount = $check_order["fiat_amount"];
                    $order = wc_get_order($order_id);

                    if (!empty($order)) {
                        if ($deposited) {
                            $total = (float)$order->get_total();
                            $percent = 100 - (($deposited_amount / $total) * 100);

                            if (is_null($deposited_amount)) {
                                $status = 'on-hold';
                            } elseif ($this->amount_percent === '' && $this->max_amount === '' && $deposited_amount >= $total) {
                                $status = 'completed';
                            } else {
                                if (($this->amount_percent >= $percent && $this->max_amount > 0 && ($total - $deposited_amount) <= $this->max_amount) || ($this->amount_percent >= $percent && $this->max_amount <= 0) || ($total - $deposited_amount) <= $this->max_amount) {
                                    $status = 'completed';
                                } else {
                                    $status = 'processing';
                                }
                            }
                        } else {
                            $status = 'processing';
                        }

                        $deposited_status = $status == 'completed' ? 'Completed' : 'Pending';

                        $order->update_status($status);
                        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_amount', $amount);
                        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_currency', $currency);
                        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_amount_in_usd', $deposited_amount);
                        wc_update_order_item_meta($order_id, 'fcf_pay_deposited_status', $deposited_status);
                    }
                }
            }
        }
    }

}

new Fcf_Pay_Order_Status();