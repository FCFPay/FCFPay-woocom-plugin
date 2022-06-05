<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Fcf_Pay' ) ) :

	/**
	 * Main Fcf_Pay Class.
	 *
	 * @package		FCFPAY
	 * @subpackage	Classes/Fcf_Pay
	 * @since		1.1.0
	 * @author		 The FCF Inc
	 */
	final class Fcf_Pay {

		/**
		 * The real instance
		 *
		 * @access	private
		 * @since	1.1.0
		 * @var		object|Fcf_Pay
		 */
		private static $instance;

		/**
		 * FCFPAY helpers object.
		 *
		 * @access	public
		 * @since	1.1.0
		 * @var		object|Fcf_Pay_Helpers
		 */
		public $helpers;

		/**
		 * Throw error on object clone.
		 *
		 * Cloning instances of the class is forbidden.
		 *
		 * @access	public
		 * @since	1.1.0
		 * @return	void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'You are not allowed to clone this class.', 'fcfpay-payment-gateway' ), '1.1.0' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @access	public
		 * @since	1.1.0
		 * @return	void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'You are not allowed to unserialize this class.', 'fcfpay-payment-gateway' ), '1.1.0' );
		}

		/**
		 * Main Fcf_Pay Instance.
		 *
		 * Insures that only one instance of Fcf_Pay exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @access		public
		 * @since		1.1.0
		 * @static
		 * @return		object|Fcf_Pay	The one true Fcf_Pay
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Fcf_Pay ) ) {
				self::$instance					= new Fcf_Pay;
				self::$instance->base_hooks();
				self::$instance->includes();
				self::$instance->helpers		= new Fcf_Pay_Helpers();
				self::$instance->endpoints		= new Fcf_Pay_Endpoints();
				self::$instance->cron           = new Fcf_Pay_Cron();

				//Fire the plugin logic
				new Fcf_Pay_Run();

				/**
				 * Fire a custom action to allow dependencies
				 * after the successful plugin setup
				 */
				do_action( 'FCFPAY/plugin_loaded' );
			}

			return self::$instance;
		}

		/**
		 * Include required files.
		 *
		 * @access  private
		 * @since   1.1.0
		 * @return  void
		 */
		private function includes() {
			require_once FCFPAY_PLUGIN_DIR . 'core/includes/classes/class-fcf-pay-helpers.php';
			require_once FCFPAY_PLUGIN_DIR . 'core/includes/classes/class-fcf-pay-endpoints.php';
			require_once FCFPAY_PLUGIN_DIR . 'core/includes/classes/class-fcf-pay-order-paid.php';
			require_once FCFPAY_PLUGIN_DIR . 'core/includes/classes/class-fcf-pay-order-status.php';
			require_once FCFPAY_PLUGIN_DIR . 'core/includes/classes/class-fcf-pay-cron.php';

			require_once FCFPAY_PLUGIN_DIR . 'core/includes/classes/class-fcf-pay-run.php';
		}

		/**
		 * Add base hooks for the core functionality
		 *
		 * @access  private
		 * @since   1.1.0
		 * @return  void
		 */
		private function base_hooks() {
			add_action( 'plugins_loaded', array( self::$instance, 'load_textdomain' ) );
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @access  public
		 * @since   1.1.0
		 * @return  void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'fcfpay-payment-gateway', FALSE, dirname( plugin_basename( FCFPAY_PLUGIN_FILE ) ) . '/languages/' );
		}

	}

endif; // End if class_exists check.