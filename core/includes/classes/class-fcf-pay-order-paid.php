<?php


class Fcf_Pay_Order_Paid {

	public function __construct() {
		add_filter( 'manage_edit-shop_order_columns', [$this, 'fcf_pay_add_amount_currently_paid_to_orders_table'] );
		add_action( 'manage_shop_order_posts_custom_column', [$this, 'fcf_pay_add_amount_currently_paid_to_orders_table_content'] );
	}


	public function fcf_pay_add_amount_currently_paid_to_orders_table( $columns ) {
		$columns['fcf_pay_deposited_amount'] = 'Deposited amount';
		return $columns;
	}


	public function fcf_pay_add_amount_currently_paid_to_orders_table_content( $column ) {

		global $post;
		$coin_amount = wc_get_order_item_meta( $post->ID, 'fcf_pay_deposited_amount' );
        $currency = wc_get_order_item_meta( $post->ID, 'fcf_pay_deposited_currency' );
        $usd = wc_get_order_item_meta( $post->ID, 'fcf_pay_deposited_amount_in_usd' );
        $amount = '';
        if($usd){
            $amount .= ' ~ ' . $usd .'$';
        }

		if ( 'fcf_pay_deposited_amount' === $column && $coin_amount != '' ) {
		    $deposited_amount = $this->decimal_notation($coin_amount) . ' ' . $currency . $amount;
            echo esc_html($deposited_amount);
		}

	}

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

new Fcf_Pay_Order_Paid();