<?php

namespace system;

class Ajax{

  public function __construct()
  {
    add_action( 'wp_ajax_add_product_to_cart', [ $this, 'add_product_to_cart' ] );
    add_action( 'wp_ajax_nopriv_add_product_to_cart', [ $this, 'add_product_to_cart' ] );

    add_action( 'wp_ajax_get_product_cart_content', [ $this, 'get_product_cart_content' ] );
    add_action( 'wp_ajax_nopriv_get_product_cart_content', [ $this, 'get_product_cart_content' ] );

    add_action( 'wp_ajax_delete_product_to_cart', [ $this, 'delete_product_to_cart' ] );
    add_action( 'wp_ajax_nopriv_delete_product_to_cart', [ $this, 'delete_product_to_cart' ] );

    add_action( 'wp_ajax_nopriv_log_in_user', [ $this, 'log_in_user' ] );

    add_action( 'wp_ajax_nopriv_register_user', [ $this, 'register_user' ] );

    add_action( 'wp_ajax_log_out_user', [ $this, 'log_out_user' ] );

    add_action( 'wp_ajax_get_user_account_info', [ $this, 'get_user_account_info' ] );
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

  public function delete_product_to_cart()
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
        wp_send_json( [ 'response' => 'not_allowed', 'text' => $user->get_error_message() ], 403 );
      }

      wp_send_json( [ 'response' => 'authorized', 'user_id' => $user->ID ], 200 );

    }

    wp_send_json( [ 'response' => 'not_allowed' ], 403 );
  }

  public function register_user()
  {
    if( ! is_user_logged_in() ){
    
      $user_name = sanitize_text_field( $_POST['username'] );
      $password = sanitize_text_field( $_POST['password'] );
      $user_email = sanitize_email( $_POST['email'] );

      $user_id = wp_create_user( $user_name, $password, $user_email );

      if ( is_wp_error( $user_id ) ) {
        $data = [ 'response' => 'error', 'text' => $user_id->get_error_message() ];
      }
      else {
        wp_set_auth_cookie( $user_id );
        $data = [ 'response' => 'sucess', 'user_id' => $user_id, 'status' => 'authorized' ];
      }

      wp_send_json( $data, 201 );
    }

    wp_send_json( [ 'response' => 'not_allowed' ], 403 );
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

  }

}
