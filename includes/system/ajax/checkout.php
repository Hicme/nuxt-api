<?php

namespace system\ajax;

class Checkout{
  public function __construct()
  {
    add_action( 'wp_ajax_processCheckout', [ $this, 'process_checkout' ] );
    add_action( 'wp_ajax_nopriv_processCheckout', [ $this, 'process_checkout' ] );
  }

  public function process_checkout() {
    
  }
}
