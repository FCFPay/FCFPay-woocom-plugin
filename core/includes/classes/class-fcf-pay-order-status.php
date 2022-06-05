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
        add_action('admin_head-edit.php', [$this, 'fcfpay_update_all_button_callback']);

        add_filter( 'manage_edit-shop_order_columns', [$this, 'set_custom_edit_shop_order_columns'] );
        add_action( 'manage_shop_order_posts_custom_column' , [$this, 'custom_shop_order_column'], 10, 2 );
        add_action( 'add_meta_boxes', [$this, 'add_shop_order_meta_box'] );
        add_action( 'save_post', [$this, 'save_shop_order_meta_box_data'] );
    }

    public function fcfpay_update_all_button_callback()
    {
        global $current_screen;

        // Not our post type, exit earlier
        // You can remove this if condition if you don't have any specific post type to restrict to.
        if ('shop_order' != $current_screen->post_type) {
            return;
        }
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $url = $base_url . $_SERVER["REQUEST_URI"] . '&fcf=update';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                jQuery(jQuery(".wrap .page-title-action").after('<a href="<?php echo $url?>" class="button button-primary" style="margin:9px">Update all</a>'));
            });
        </script>
        <?php
    }

    public function fcfpay_check_canceled_orders()
    {
        $cpt = 'shop_order';
        if (isset($_GET['post_type']) && $_GET['post_type'] == $cpt && isset($_GET['fcf']) && $_GET['fcf'] == 'update') {
            FCFPAY()->helpers->fcf_pay_update_orders();

            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
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


    public function set_custom_edit_shop_order_columns($columns)
    {
        $columns['disable_update_status'] = __('Disable status update', 'fcfpay-payment-gateway');
        return $columns;
    }

    // Add the data to the custom columns for the order post type:
    public function custom_shop_order_column($column, $post_id)
    {
        switch ($column) {

            case 'disable_update_status' :
                echo esc_html(get_post_meta($post_id, 'disable_update_status', true));
                break;

        }
    }

    // For display and saving in order details page.
    public function add_shop_order_meta_box()
    {

        add_meta_box(
            'disable_update_status',
            __('FCFPay Settings', 'fcfpay-payment-gateway'),
            [$this, 'shop_order_display_callback'],
            'shop_order'
        );

    }

    // For displaying.
    public function shop_order_display_callback($post)
    {

        $status = get_post_meta($post->ID, 'disable_update_status', true);
        $checked = $status === '1' ? 'checked' : '';
        echo '<label><input type="checkbox" name="disable_update_status" ' . $checked . '>Disable status update</label>';
    }

    // For saving.
    public function save_shop_order_meta_box_data($post_id)
    {

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'shop_order' == $_POST['post_type']) {
            if (!current_user_can('edit_shop_order', $post_id)) {
                return;
            }
        }

        // Make sure that it is set.
        $status = isset($_POST['disable_update_status']) ? 1 : 0;

        // Update the meta field in the database.
        update_post_meta($post_id, 'disable_update_status', $status);
    }

}

new Fcf_Pay_Order_Status();