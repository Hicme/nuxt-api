<?php

namespace system;

class Ajax{

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

    add_action( 'wp_ajax_nopriv_log_in_user', [ $this, 'log_in_user' ] );
    add_action( 'wp_ajax_log_in_user', [ $this, 'log_in_user' ] );

    add_action( 'wp_ajax_nopriv_getCountries', [ $this, 'get_countries' ] );
    add_action( 'wp_ajax_getCountries', [ $this, 'get_countries' ] );

    add_action( 'wp_ajax_nopriv_getStates', [ $this, 'get_states' ] );
    add_action( 'wp_ajax_getStates', [ $this, 'get_states' ] );

    add_action( 'wp_ajax_register_user', [ $this, 'register_user' ] );
    add_action( 'wp_ajax_nopriv_register_user', [ $this, 'register_user' ] );

    add_action( 'wp_ajax_nopriv_reset_password', [ $this, 'reset_password' ] );

    add_action( 'wp_ajax_nopriv_validate_keys', [ $this, 'validate_keys' ] );

    add_action( 'wp_ajax_nopriv_try_set_password', [ $this, 'try_set_password' ] );

    add_action( 'wp_ajax_log_out_user', [ $this, 'log_out_user' ] );
    add_action( 'wp_ajax_nopriv_log_out_user', [ $this, 'log_out_user' ] );

    add_action( 'wp_ajax_get_user_account_info', [ $this, 'get_user_account_info' ] );
    add_action( 'wp_ajax_nopriv_get_user_account_info', [ $this, 'get_user_account_info' ] );
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

  public function log_in_user()
  {
    if( ! is_user_logged_in() ){

      $user_data = array();
      $user_data['user_login'] = sanitize_text_field( $_POST['username'] );
      $user_data['user_password'] = sanitize_text_field( $_POST['password'] );
      $user_data['remember'] = sanitize_text_field( $_POST['remember'] );

      $user = wp_signon( $user_data, false );

      if ( is_wp_error( $user ) ) {
        wp_send_json( [ 'response' => 'not_allowed', 'code' => $user->get_error_code() ], 403 );
      }

      $return_user = [
        'ID' => $user->ID,
        'login' => $user->user_login,
        'email' => $user->user_email,
        'date_registerd' => $user->user_registered,
        'status' => $user->user_status,
        'nickname' => $user->user_nicename,
        'dsplay_name' => $user->display_name,
        'first_name' => get_user_meta( $user->ID, 'first_name', true),
        'last_name' => get_user_meta( $user->ID, 'last_name', true),
        'is_admin' => in_array( 'administrator', $user->roles ),
      ];

      wp_send_json( [ 'response' => 'authorized', 'user' => $return_user ], 200 );

    }

    wp_send_json( [ 'response' => 'allready_authorized' ], 403 );
  }

  public function register_user()
  {
    if( ! is_user_logged_in() ){
    
      $user_name = sanitize_text_field( $_POST['email'] );
      $password = sanitize_text_field( $_POST['password'] );
      $user_email = sanitize_email( $_POST['email'] );

      $user_id = wp_create_user( $user_name, $password, $user_email );

      if ( is_wp_error( $user_id ) ) {
        $data = [ 'response' => 'error', 'code' => $user_id->get_error_code(), 'text' => $user_id->get_error_message() ];
        wp_send_json( $data, 403 );
      }
      else {
        $user = get_userdata( $user_id );

        $return_user = [
          'ID' => $user->ID,
          'login' => $user->user_login,
          'email' => $user->user_email,
          'date_registerd' => $user->user_registered,
          'status' => $user->user_status,
          'nickname' => $user->user_nicename,
          'dsplay_name' => $user->display_name,
          'first_name' => get_user_meta( $user->ID, 'first_name', true),
          'last_name' => get_user_meta( $user->ID, 'last_name', true),
          'is_admin' => in_array( 'administrator', $user->roles ),
        ];

        wp_set_auth_cookie( $user_id );
        $data = [ 'response' => 'sucess', 'user' => $return_user, 'status' => 'authorized' ];
        wp_send_json( $data, 201 );
      }
    }

    wp_send_json( [ 'response' => 'allready_authorized', 'code' => 'allready_authorized' ], 403 );
  }

  public function reset_password()
  {
    if( ! is_user_logged_in() ){
      $user = get_user_by( 'email', sanitize_email( $_POST['email'] ) );

      if( $user ){
        $reset_key = get_password_reset_key( $user );
        
        send_reset_password_email( $reset_key, $user );

        $data = [ 'response' => 'sucess', 'text' => __('Your password was successfully reseted. Check your email.', 'nuxtapi') ];
        
        wp_send_json( $data, 200 );
      }else{
        wp_send_json( [ 'response' => 'user_not_found', 'text' => __('Sorry, this email not found.', 'nuxtapi') ], 404 );
      }
    }

    wp_send_json( [ 'response' => 'not_allowed' ], 403 );
  }

  public function validate_keys()
  {
    if( isset( $_REQUEST['key'] ) && isset( $_REQUEST['login'] ) ){
      $status = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );

      if( is_wp_error( $status ) ){
        wp_send_json( [ 'response' => $status->get_error_code(), 'text' => $status->get_error_message() ], 403);
      }
      else {
        wp_send_json( [ 'response' => 'sucess', 'text' => __( 'Code accepted.', 'nuxtapi' ) ], 200);
      }
    }
  }

  public function try_set_password()
  {
    if( isset( $_REQUEST['key'] ) && isset( $_REQUEST['login'] ) ){
      $status = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );

      if( is_wp_error( $status ) ){
        wp_send_json( [ 'response' => $status->get_error_code(), 'text' => $status->get_error_message() ], 403);
      }
      else {
        $user = get_user_by( 'login', $_REQUEST['login'] );
        
        if( $user ){
          wp_set_password( $_POST['password'], $user->ID );
          wp_send_json( [ 'response' => 'sucess', 'text' => __( 'Your password was successfully changed.', 'nuxtapi' ) ], 200);
        }

        wp_send_json( [ 'response' => 'not_found_user', 'text' => __( 'Sorry, your data not recognized.', 'nuxtapi' ) ], 403);
      }
    }
  }

  public function log_out_user()
  {
    if( is_user_logged_in() ){
      wp_destroy_current_session();
      wp_clear_auth_cookie();
      
      wp_send_json( [ 'response' => 'logged_out' ], 202 );
    }

    wp_send_json( [ 'response' => 'unauthorized' ], 401 );
  }

  public function get_user_account_info()
  {
    if( is_user_logged_in() ){
      if( $user = get_user_by( 'ID', get_current_user_id() ) ){
        $return_user = [
          'ID' => $user->ID,
          'login' => $user->user_login,
          'email' => $user->user_email,
          'date_registerd' => $user->user_registered,
          'status' => $user->user_status,
          'nickname' => $user->user_nicename,
          'dsplay_name' => $user->display_name,
          'first_name' => get_user_meta( $user->ID, 'first_name', true),
          'last_name' => get_user_meta( $user->ID, 'last_name', true),
          'is_admin' => in_array( 'administrator', $user->roles ),
        ];

        wp_send_json( [ 'response' => 'sucess', 'user_datas' => $return_user ], 200 );
      }

      wp_send_json( [ 'response' => 'not_found' ], 404 );
    }

    wp_send_json( [ 'response' => 'unauthorized' ], 401 );
  }
}
