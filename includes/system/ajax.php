<?php

namespace system;

class Ajax{

  public function __construct()
  {
    add_action( 'wp_ajax_add_product_to_cart', [ $this, 'add_product_to_cart' ] );
    add_action( 'wp_ajax_nopriv_add_product_to_cart', [ $this, 'add_product_to_cart' ] );

    add_action( 'wp_ajax_get_product_cart_content', [ $this, 'get_product_cart_content' ] );
    add_action( 'wp_ajax_nopriv_get_product_cart_content', [ $this, 'get_product_cart_content' ] );

    add_action( 'wp_ajax_delete_product_from_cart', [ $this, 'delete_product_cart' ] );
    add_action( 'wp_ajax_nopriv_delete_product_from_cart', [ $this, 'delete_product_cart' ] );

    add_action( 'wp_ajax_nopriv_log_in_user', [ $this, 'log_in_user' ] );
    add_action( 'wp_ajax_log_in_user', [ $this, 'log_in_user' ] );

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
    if( isset( $_POST['product_id'] ) && get_post_type( sanitize_text_field( $_POST['product_id'] ) ) == 'product' ){

      $product_id = (int) $_POST['product_id'];
      $product_quantity = (int) ( ! empty( $_POST['product_quantity'] ) ? $_POST['product_quantity'] : 1 );

      if( WC()->cart->add_to_cart( $product_id, $product_quantity ) ){

        wp_send_json( [ 'response' => 'added_to_cart', 'text' => 'Product added to cart', 'cart_hash' => WC()->cart->get_cart_hash() ], 200 );
      }
    }

    wp_send_json( [ 'response' => 'not_allowed', 'text' => 'No product id' ], 403 );
  }

  public function get_product_cart_content()
  {

    $datas = [
      'cart_hash' => '',
      'cart_total' => 0,
      'cart_content_count' => 0,
      'products' => [],

    ];

    if( ! WC()->cart->is_empty() ){

      $datas['cart_hash'] = WC()->cart->get_cart_hash();
      $datas['cart_total'] = WC()->cart->get_total();
      $datas['cart_content_count'] = WC()->cart->get_cart_contents_count();

      $items = WC()->cart->get_cart();

      foreach( $items as $item => $values ){
        $datas['products'][] = [
          'product_id'       => $values['data']->get_id(),
          'product_title'    => $values['data']->get_title(),
          'product_image'    => ( !empty( $image = get_post_meta( $values['data']->get_id(), '_thumbnail_id', true ) ) ? wp_get_attachment_image_url( $image, 'full' ) : false ),
          'product_quantity' => $values['quantity'],
          'product_price'    => $values['data']->get_price(),
          'cart_item_key'    => $values['key'],
        ];
      }

    }

    wp_send_json( [ 'response' => 'sucess', 'cart_datas' => $datas ], 200 );
  }

  public function delete_product_cart()
  {
    if( isset( $_POST['cart_item_key'] ) ){
      WC()->cart->remove_cart_item( $_POST['cart_item_key'] );

      wp_send_json( [ 'response' => 'product_deleted' ], 200 );
    }

    wp_send_json( [ 'response' => 'not_allowed' ], 403 );
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
