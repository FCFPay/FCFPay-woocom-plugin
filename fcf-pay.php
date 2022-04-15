<?php
/**
 * FCF-Pay
 *
 * @package       FCFPAY
 * @author        The FCF Inc
 * @license       gplv3
 * @version       1.0.4
 *
 * @wordpress-plugin
 * Plugin Name:   FCFPAY Payment Gateway
 * Plugin URI:    https://fcfpay.com/
 * Description:   Making cryptocurrency payments easy!
 * Version:       1.0.4
 * Author:        The FCF Inc
 * Author URI:    https://frenchconnection.finance/
 * Text Domain:   fcf-pay
 * Domain Path:   /languages
 * License:       GPLv3
 * License URI:   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with FCF-Pay. If not, see <https://www.gnu.org/licenses/gpl-3.0.html/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Plugin name
define( 'FCFPAY_NAME',			'FCF-Pay' );

// Plugin version
define( 'FCFPAY_VERSION',		'1.0.4' );

// Plugin Root File
define( 'FCFPAY_PLUGIN_FILE',	__FILE__ );

// Plugin base
define( 'FCFPAY_PLUGIN_BASE',	plugin_basename( FCFPAY_PLUGIN_FILE ) );

// Plugin Folder Path
define( 'FCFPAY_PLUGIN_DIR',	plugin_dir_path( FCFPAY_PLUGIN_FILE ) );

// Plugin Folder URL
define( 'FCFPAY_PLUGIN_URL',	plugin_dir_url( FCFPAY_PLUGIN_FILE ) );

// Plugin API Url
define( 'FCFPAY_API_URL',       'https://dev-merchant.mysite.am/api/v1/' );

/**
 * Load the main class for the core functionality
 */
require_once FCFPAY_PLUGIN_DIR . 'core/class-fcf-pay.php';

/**
 * The main function to load the only instance
 * of our master class.
 *
 * @author   The FCF Inc
 * @since   1.0.0
 * @return  object|Fcf_Pay
 */
function FCFPAY() {
	return Fcf_Pay::instance();
}
FCFPAY();

add_action('plugins_loaded', 'fcf_pay_init');
function fcf_pay_init()
{
    include_once('core/includes/classes/class-fcf-pay-gateway.php');

    add_filter('woocommerce_payment_gateways', 'add_fcf_pay_gateway');

    /**
     * Add the gateway
     **/
    function add_fcf_pay_gateway($methods)
    {
        $methods[] = Fcf_Pay_Gateway::class;
        return $methods;
    }

}