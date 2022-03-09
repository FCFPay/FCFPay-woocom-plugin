<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Fcf_Pay_Run
 *
 * Thats where we bring the plugin to life
 *
 * @package        FCFPAY
 * @subpackage    Classes/Fcf_Pay_Run
 * @author         The FCF Inc
 * @since        1.0.0
 */
class Fcf_Pay_Run
{

    /**
     * Our Fcf_Pay_Run constructor
     * to run the plugin logic.
     *
     * @since 1.0.0
     */
    function __construct()
    {
        $this->add_hooks();
    }

    /**
     * Registers all WordPress and plugin related hooks
     *
     * @access    private
     * @return    void
     * @since    1.0.0
     */
    private function add_hooks()
    {

        add_action('plugin_action_links_' . FCFPAY_PLUGIN_BASE, array($this, 'add_plugin_action_link'), 20);
        add_shortcode('fcf_pay_order', array($this, 'fcf_pay_order_shortcode'));
        add_action('woocommerce_thankyou', array($this, 'fcf_pay_order_received_table'), 10, 2 );
        add_action('admin_enqueue_scripts', array($this, 'enqueue_backend_scripts_and_styles'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts_and_styles'), 20);
        add_filter('woocommerce_get_settings_products', array($this, 'wc_custom_settings_tab_content'), 20, 2);
        register_activation_hook(FCFPAY_PLUGIN_FILE, array($this, 'activation_hook_callback'));
        register_deactivation_hook(FCFPAY_PLUGIN_FILE, array($this, 'deactivation_hook_callback'));

    }

    /**
     * Adds action links to the plugin list table
     *
     * @access    public
     * @param array $links An array of plugin action links.
     *
     * @return    array    An array of plugin action links.
     * @since    1.0.0
     *
     */
    public function add_plugin_action_link($links)
    {
        $links['fcf_settings'] = sprintf('<a href="%s" title="Settings">%s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=fcf_pay'), __('Settings', 'fcfpay-payment-gateway'));

        return $links;
    }

    /**
     * Add the shortcode callback for [fcf_pay_order]
     *
     * @access    public
     * @return    string    The customized content by the shortcode.
     * @throws Exception
     * @since    1.0.0
     */
    public function fcf_pay_order_shortcode()
    {
        $order_id = intval($_GET['order_id']);
        $key = sanitize_text_field($_GET['key']);
        $order = new WC_Order($order_id);
        $deposited_amount = wc_get_order_item_meta($order_id, 'fcf_pay_deposited_amount');
        $currency = wc_get_order_item_meta($order_id, 'fcf_pay_deposited_currency');
        $usd = wc_get_order_item_meta($order_id, 'fcf_pay_deposited_amount_in_usd');
        if ($key === $order->get_order_key()):
            $deposited_html = $deposited_amount === '' ? 0 : esc_html__($deposited_amount . ' ' . $currency);
            $usd_html = ($usd) ? '<th scope="row">' . $usd . '</th>' : '';
            return '<style>
                table {
                    max-width: 960px;
                    margin: 10px auto;
                }

                caption {
                    font-size: 1.6em;
                    font-weight: 400;
                    padding: 10px 0;
                }

                thead th {
                    font-weight: 400;
                    background: #8a97a0;
                    color: #fff;
                }

                tr {
                    background: #f4f7f8;
                    border-bottom: 1px solid #fff;
                    margin-bottom: 5px;
                }

                tr:nth-child(even) {
                    background: #e8eeef;
                }

                th, td {
                    text-align: left;
                    padding: 20px;
                    font-weight: 300;
                    text-transform: capitalize;
                }
            </style>
            <table>
                <caption>' . __('Order Information') . '</caption>
                <thead>
                <tr>
                    <?php if ($usd): ?>
                        <th scope="col">'. __('Amount in USD') . '</th>
                    <?php endif; ?>
                    <th scope="col">' . __('Deposited amount') . '</th>
                    <th scope="col">' . __('Status') . '</th>
                    <th scope="col">' . __('Total') . '</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    ' . $usd_html . '
                    <th scope="row">' . $deposited_html . '</th>
                    <td>' . $order->get_status() . '</td>
                    <td>' . $order->get_total() . '</td>
                </tr>
                </tbody>
            </table>';
        endif;
    }

    /**
     * Add tables for order received page
     *
     * @access    public
     * @return    string    Order received table content.
     * @throws Exception
     * @since    1.0.0
     */
    public function fcf_pay_order_received_table( $order_id ) {
        $labels = [
            __('Deposited amount', 'fcf_pay') => 'fcf_pay_deposited_amount',
            __('Crypto type', 'fcf_pay') => 'fcf_pay_deposited_currency'
        ];

        echo '<h2>' . __('Order extra info', 'fcfpay-payment-gateway') . '</h2>';
        echo '<table><tbody>';

        foreach( $labels as $label => $meta ){
            if($meta == 'fcf_pay_deposited_amount'){
                $deposited_amount = FCFPAY()->helpers->decimal_notation(wc_get_order_item_meta( $order_id, $meta ));
                echo '<tr><th>'.esc_html($label).':</th><td>'.esc_html($deposited_amount).'</td></tr>';
            }else{
                $crypto_type = wc_get_order_item_meta( $order_id, $meta );
                echo '<tr><th>'.esc_html($label).':</th><td>'.esc_html($crypto_type).'</td></tr>';
            }
        }

        echo '</tbody></table>';
    }

    /**
     * Enqueue the backend related scripts and styles for this plugin.
     * All of the added scripts andstyles will be available on every page within the backend.
     *
     * @access    public
     * @return    void
     * @since    1.0.0
     *
     */
    public function enqueue_backend_scripts_and_styles()
    {
        wp_enqueue_style('fcfpay-backend-styles', FCFPAY_PLUGIN_URL . 'core/includes/assets/css/backend-styles.css', array(), FCFPAY_VERSION, 'all');
        wp_enqueue_script('fcfpay-backend-scripts', FCFPAY_PLUGIN_URL . 'core/includes/assets/js/backend-scripts.js', array('jquery'), FCFPAY_VERSION, true);
        wp_localize_script('fcfpay-backend-scripts', 'fcfpay', array(
            'plugin_name' => __(FCFPAY_NAME, 'fcfpay-payment-gateway'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'security_nonce' => wp_create_nonce("fcf-pay-nonce"),
        ));
    }


    /**
     * Enqueue the frontend related scripts and styles for this plugin.
     *
     * @access    public
     * @return    void
     * @since    1.0.0
     *
     */
    public function enqueue_frontend_scripts_and_styles()
    {
        wp_enqueue_style('fcfpay-frontend-styles', FCFPAY_PLUGIN_URL . 'core/includes/assets/css/frontend-styles.css', array(), FCFPAY_VERSION, 'all');
        wp_enqueue_script('fcfpay-frontend-scripts', FCFPAY_PLUGIN_URL . 'core/includes/assets/js/frontend-scripts.js', array('jquery'), FCFPAY_VERSION, true);
        wp_localize_script('fcfpay-frontend-scripts', 'fcfpay', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'security_nonce' => wp_create_nonce("fcf-pay-nonce"),
        ));
    }


    /*
     * This function is called on activation of the plugin
     *
     * @access	public
     * @since	1.0.0
     *
     * @return	void
     */
    public function activation_hook_callback()
    {

    }

    /*
     * This function is called on deactivation of the plugin
     *
     * @access	public
     * @since	1.0.0
     *
     * @return	void
     */
    public function deactivation_hook_callback()
    {

    }

}
