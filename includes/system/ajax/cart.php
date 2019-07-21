<?php

namespace system\ajax;

class Cart{

  public function __construct()
  {
    add_action( 'wp_ajax_AddCartProduct', [ $this, 'add_product_to_cart' ] );
    add_action( 'wp_ajax_nopriv_AddCartProduct', [ $this, 'add_product_to_cart' ] );

    add_action( 'wp_ajax_addCoupon', [ $this, 'add_coupon' ] );
    add_action( 'wp_ajax_nopriv_addCoupon', [ $this, 'add_coupon' ] );

    add_action( 'wp_ajax_removeCoupon', [ $this, 'remove_coupon' ] );
    add_action( 'wp_ajax_nopriv_removeCoupon', [ $this, 'remove_coupon' ] );

    add_action( 'wp_ajax_userUpdateCheckoutData', [ $this, 'update_checkout_user_datas' ] );
    add_action( 'wp_ajax_nopriv_userUpdateCheckoutData', [ $this, 'update_checkout_user_datas' ] );

    add_action( 'wp_ajax_userGetCheckoutData', [ $this, 'get_checkout_user_datas' ] );
    add_action( 'wp_ajax_nopriv_userGetCheckoutData', [ $this, 'get_checkout_user_datas' ] );

    add_action( 'wp_ajax_getShippingMethods', [ $this, 'get_shipping_methods' ] );
    add_action( 'wp_ajax_nopriv_getShippingMethods', [ $this, 'get_shipping_methods' ] );

    add_action( 'wp_ajax_getPaymentMethods', [ $this, 'get_payment_methods' ] );
    add_action( 'wp_ajax_nopriv_getPaymentMethods', [ $this, 'get_payment_methods' ] );

    add_action( 'wp_ajax_getCartProducts', [ $this, 'get_cart_products' ] );
    add_action( 'wp_ajax_nopriv_getCartProducts', [ $this, 'get_cart_products' ] );

    add_action( 'wp_ajax_deleteCartProduct', [ $this, 'delete_cart_product' ] );
    add_action( 'wp_ajax_nopriv_deleteCartProduct', [ $this, 'delete_cart_product' ] );

    add_action( 'wp_ajax_nopriv_getCountries', [ $this, 'get_countries' ] );
    add_action( 'wp_ajax_getCountries', [ $this, 'get_countries' ] );

    add_action( 'wp_ajax_nopriv_getStates', [ $this, 'get_states' ] );
    add_action( 'wp_ajax_getStates', [ $this, 'get_states' ] );
  }

  public function add_product_to_cart()
  {
    if( isset( $_POST['id'] ) && get_post_type( sanitize_text_field( $_POST['id'] ) ) == 'product' ){

      $product_id = (int) $_POST['id'];
      $product_quantity = (int) ( ! empty( $_POST['quantity'] ) ? $_POST['quantity'] : 1 );

      if( WC()->cart->add_to_cart( $product_id, $product_quantity ) ){
        wp_send_json_success( [ 'message' => 'Added to cart', 'data' => WC()->cart->get_cart_hash() ], 200 );
      }else{
        wp_send_json_error( [ 'code' => 105, 'message' => 'Can not buy this product' ], 405 );
      }
    }

    wp_send_json_error( [ 'code' => 102, 'message' => 'No product id' ], 405 );
  }

  public function add_coupon()
  {
    if( isset( $_POST['coupon'] ) ){
      if( ! WC()->cart->is_empty() && WC()->cart->apply_coupon( sanitize_text_field( $_POST['coupon'] ) ) ){
        WC()->cart->calculate_totals();
        wp_send_json_success( [ 'message' => 'Added coupon' ], 200 );
      }else{
        wp_send_json_error( [ 'code' => 101, 'message' => 'Empty Cart or not valid coupon' ], 405 );
      }
    }else{
      wp_send_json_error( [ 'code' => 103, 'message' => 'No coupon code' ], 405 );
    }
  }

  public function remove_coupon()
  {
    if( isset( $_POST['coupon'] ) ){
      if( WC()->cart->remove_coupon( sanitize_text_field( $_POST['coupon'] ) ) ){
        WC()->cart->calculate_totals();
        wp_send_json_success( [ 'message' => 'Coupon deleted' ], 200 );
      }else{
        wp_send_json_error( [ 'code' => 101, 'message' => 'Empty Cart' ], 405 );
      }
    }else{
      wp_send_json_error( [ 'code' => 103, 'message' => 'No coupon code' ], 405 );
    }
  }

  public function update_checkout_user_datas()
  {
    if ( WC()->cart->is_empty() && ! isset( $_POST['name'] ) && ! isset( $_POST['value'] ) ) {
      wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
    }

    $allowed = [
      'billing_email',
      'billing_first_name',
      'billing_last_name',
      'billing_phone',
      'billing_country',
      'billing_state',
      'billing_postcode',
      'billing_address_1',
      'shipping_method',
      'payment_method'
    ];

    $name = wc_clean( wp_unslash( $_POST['name'] ) );
    $value = wc_clean( wp_unslash( $_POST['value'] ) );

    if( ! in_array( $name, $allowed ) ){
      wp_send_json_error( [ 'code' => 401, 'message' => 'Not allowed method' ], 405 );
    }

    if( $name == 'shipping_method' || $name == 'payment_method' ){
      $posted_shipping_methods = ( $name == 'shipping_method' ? [$value] : [] );

      WC()->session->set( 'chosen_shipping_methods', $posted_shipping_methods );
      WC()->session->set( 'chosen_payment_method', ( $name == 'payment_method' ? $value : '' ) );
    }else{
      WC()->customer->set_props( [ $name => $value ] );
    }

    WC()->customer->save();
    WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
    
    wp_send_json_success( [ 'message' => 'Datas updated' ], 200 );
  }

  public function get_checkout_user_datas()
  {
    $datas = [
      'billing_email'      => WC()->customer->get_billing_email(),
      'billing_first_name' => WC()->customer->get_billing_first_name(),
      'billing_last_name'  => WC()->customer->get_billing_last_name(),
      'billing_phone'      => WC()->customer->get_billing_phone(),
      'billing_country'    => WC()->customer->get_billing_country(),
      'billing_state'      => WC()->customer->get_billing_state(),
      'billing_postcode'   => WC()->customer->get_billing_postcode(),
      'billing_address_1'  => WC()->customer->get_billing_address_1(),
      'shipping_method'    => WC()->session->get( 'chosen_shipping_methods' )[0],
      'payment_method'     => WC()->session->get( 'chosen_payment_method' ),
    ];

    wp_send_json_success( $datas, 200 );
  }

  public function get_shipping_methods()
  {
    $valid_methods = [];

    WC()->shipping->calculate_shipping( WC()->cart->get_shipping_packages() );

    $shipping_methods = WC()->shipping->get_packages();

    foreach( $shipping_methods[0]['rates'] as $id => $shipping_method ){
      $valid_methods[] = [
        'method_id' => $shipping_method->method_id,
        'type'      => $shipping_method->method_id,
        'id'        => $shipping_method->id,
        'name'      => $shipping_method->label,
        'price'     => $shipping_method->cost,
        'taxes'     => $shipping_method->taxes,
      ];
    }
  
    if( !empty( $valid_methods ) ){
      wp_send_json_success( $valid_methods, 200 );
    }else{
      wp_send_json_error( [ 'code' => 120, 'message' => 'No shipping methods.' ], 405 );
    }
  }

  public function get_payment_methods()
  {
    if( !WC()->cart->is_empty() ){
      $data = [];
      
      foreach( WC()->payment_gateways->get_available_payment_gateways() as $key => $gateway ){
        $data[] = [
          'id'                => $gateway->id,
          'order_button_text' => $gateway->order_button_text,
          'title'             => $gateway->get_title(),
          'description'       => $gateway->get_description(),
        ];
      }

      wp_send_json_success( $data, 200 );
    }else{
      wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
    }
  }

  public function get_cart_products()
  {

    $datas = [
      'hash'          => '',
      'tax'           => 0,
      'shipping'      => 0,
      'coupons'       => [],
      'fees'          => [],
      'discount'      => 0,
      'subtotal'      => 0,
      'total'         => 0,
      'content_count' => 0,
      'products'      => [],

    ];

    if( ! WC()->cart->is_empty() ){

      $datas['hash']          = WC()->cart->get_cart_hash();
      $datas['tax']           = WC()->cart->get_subtotal_tax();
      $datas['shipping']      = WC()->cart->get_shipping_total();
      
      if( $coupons = WC()->cart->get_coupons() ){
        $temp = [];
        foreach ( $coupons as $code => $coupon ) {
            $temp[$code] = [
              'code' => $coupon->get_code(),
              'amount' => $coupon->get_amount(),
              'description' => $coupon->get_description(),
            ];
        }

        $datas['coupons']       = $temp;
      }else{
        $datas['coupons']       = [];
      }

      $datas['fees']          = WC()->cart->get_fees();
      $datas['subtotal']      = WC()->cart->get_subtotal();
      $datas['total']         = WC()->cart->get_total(false);
      $datas['content_count'] = WC()->cart->get_cart_contents_count();

      $items = WC()->cart->get_cart();

      foreach( $items as $item => $values ){
        $datas['products'][] = [
          'id'            => $values['data']->get_id(),
          'item_key'      => $values['key'],
          'title'         => $values['data']->get_title(),
          'slug'          => $values['data']->get_slug(),
          'image'         => ( !empty( $image = get_post_meta( $values['data']->get_id(), '_thumbnail_id', true ) ) ? wp_get_attachment_image_url( $image, 'full' ) : false ),
          'quantity'      => $values['quantity'],
          'price'         => $values['data']->get_price(),
          'regular_price' => $values['data']->get_regular_price(),
          'sale_price'    => $values['data']->get_sale_price(),
        ];
      }

    }

    wp_send_json_success( $datas, 200 );
  }

  public function delete_cart_product()
  {
    if( isset( $_POST['item_key'] ) ){
      WC()->cart->remove_cart_item( $_POST['item_key'] );

      wp_send_json_success( [ 'message' => 'Product deleted' ], 200 );
    }

    wp_send_json_error( [ 'code' => 104, 'message' => 'No Item Key' ], 405 );
  }

  public function get_countries()
  {
    wp_send_json_success( WC()->countries->get_countries(), 200 );
  }

  public function get_states()
  {
    if( isset( $_POST['code'] ) ){
      wp_send_json_success( WC()->countries->get_states( sanitize_text_field( $_POST['code'] ) ), 200 );
    }else{
      wp_send_json_error( [ 'code' => 109, 'message' => 'No country code.' ], 405 );
    }
  }
}
