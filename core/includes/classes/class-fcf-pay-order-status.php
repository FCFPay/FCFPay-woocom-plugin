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
        add_action('admin_head-edit.php',[$this, 'addCustomImportButton']);
    }

    public function addCustomImportButton()
    {
        global $current_screen;

        // Not our post type, exit earlier
        // You can remove this if condition if you don't have any specific post type to restrict to.
        if ('shop_order' != $current_screen->post_type) {
            return;
        }
        $base_url = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http' ) . '://' .  $_SERVER['HTTP_HOST'];
        $url = $base_url . $_SERVER["REQUEST_URI"] . '&fcf=update';
        ?>
        <script type="text/javascript">
            jQuery(document).ready( function($)
            {
                jQuery(jQuery(".wrap .page-title-action").after('<a href="<?php echo $url?>" class="button button-primary" style="margin:9px">Update all</a>'));
            });
        </script>
        <?php
    }

    function fcfpay_check_canceled_orders()
    {
        $cpt = 'shop_order';
        if (isset($_GET['post_type']) && $_GET['post_type'] == $cpt && isset($_GET['fcf']) && $_GET['fcf'] == 'update') {
            FCFPAY()->helpers->fcf_pay_update_orders();

            $base_url = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http' ) . '://' .  $_SERVER['HTTP_HOST'];
            $url = $base_url . $_SERVER["REQUEST_URI"];

            $parsed = parse_url($url);
            $query = $parsed['query'];

            parse_str($query, $params);

            unset($params['fcf']);
            $back_url = http_build_query($params);
            $back_url = $base_url . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) . '?' . $back_url;
            wp_safe_redirect($back_url);
            die;
        }
    }

}

new Fcf_Pay_Order_Status();