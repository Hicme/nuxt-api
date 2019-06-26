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
    WC()->cart->add_to_cart( 54, 1 );
  }

  public function get_product_cart_content()
  {
    $items = WC()->cart->get_cart();
  }

  public function delete_product_to_cart()
  {
    
  }

  public function log_in_user()
  {
    
  }

  public function register_user()
  {
    
  }

  public function log_out_user()
  {
    
  }

  public function get_user_account_info()
  {

  }

}
