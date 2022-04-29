<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Fcf_Pay_Helpers
 *
 * This class contains repetitive functions that
 * are used globally within the plugin.
 *
 * @package		FCFPAY
 * @subpackage	Classes/Fcf_Pay_Helpers
 * @author		 The FCF Inc
 * @since		1.0.7
 */
class Fcf_Pay_Helpers{

    public function decimal_notation($float) {
        $parts = explode('E', $float);

        if(count($parts) === 2){
            $exp = abs(end($parts)) + strlen($parts[0]);
            $decimal = number_format($float, $exp);
            return rtrim($decimal, '.0');
        }
        else{
            return $float;
        }
    }

}
