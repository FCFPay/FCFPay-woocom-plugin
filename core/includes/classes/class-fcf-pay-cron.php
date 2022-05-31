<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

class Fcf_Pay_Cron{

    /**
     * fcf_pay cron constructor.
     */
    public function __construct() {

        add_filter( 'cron_schedules', [ $this, 'fcf_pay_cron_schedules' ] );

        if ( ! wp_next_scheduled( 'fcf_pay_cron_hook' ) ) {
            wp_schedule_event( time(), 'every_fifteen_minutes', 'fcf_pay_cron_hook' );
        }

        add_action( 'fcf_pay_cron_hook', [ $this, 'fcf_pay_cron_function' ] );

    }

    /**
     * Function that runs cron
     */
    public function fcf_pay_cron_function() {
        FCFPAY()->helpers->fcf_pay_update_orders();
    }

    public function fcf_pay_cron_schedules( $schedules ) {

        $schedules['every_fifteen_minutes'] = array(
            'interval' => 15 * 60,
            'display'  => __( 'Every 15 Minutes', 'fcf_pay' ),
        );

        return $schedules;
    }

}
