<?php

namespace system\ajax;

class Cart{

  public function __construct()
  {
    add_action( 'wp_ajax_getCartProducts', [ $this, 'getCartProducts' ] );
    add_action( 'wp_ajax_nopriv_getCartProducts', [ $this, 'getCartProducts' ] );

    add_action( 'wp_ajax_addCartProduct', [ $this, 'addCartProduct' ] );
    add_action( 'wp_ajax_nopriv_addCartProduct', [ $this, 'addCartProduct' ] );

    add_action( 'wp_ajax_deleteCartProduct', [ $this, 'deleteCartProduct' ] );
    add_action( 'wp_ajax_nopriv_deleteCartProduct', [ $this, 'deleteCartProduct' ] );

    add_action( 'wp_ajax_addCoupon', [ $this, 'addCoupon' ] );
    add_action( 'wp_ajax_nopriv_addCoupon', [ $this, 'addCoupon' ] );

    add_action( 'wp_ajax_removeCoupon', [ $this, 'removeCoupon' ] );
    add_action( 'wp_ajax_nopriv_removeCoupon', [ $this, 'removeCoupon' ] );

    add_action( 'wp_ajax_nopriv_getStates', [ $this, 'getStates' ] );
    add_action( 'wp_ajax_getStates', [ $this, 'getStates' ] );
  }



  public function getCartProducts()
  {
    wp_send_json_success( self::get_cart(), 200 );
  }

  public function addCartProduct()
  {
    if( isset( $_REQUEST['id'] ) && get_post_type( sanitize_text_field( $_REQUEST['id'] ) ) == 'product' ){

      $product_id = (int) $_REQUEST['id'];
      $product_quantity = (int) ( ! empty( $_REQUEST['quantity'] ) ? $_REQUEST['quantity'] : 1 );

      if( WC()->cart->add_to_cart( $product_id, $product_quantity ) ){
        wp_send_json_success([
          'message' => 'Added to cart',
          'data' => self::get_cart(),
        ], 200);
      }else{
        wp_send_json_error( [ 'code' => 105, 'message' => 'Can not buy this product' ], 405 );
      }
    }

    wp_send_json_error( [ 'code' => 102, 'message' => 'No product id' ], 405 );
  }

  public function deleteCartProduct()
  {
    if( isset( $_REQUEST['item_key'] ) ){
      WC()->cart->remove_cart_item( $_POST['item_key'] );

      wp_send_json_success([
        'message' => 'Product deleted',
        'data' => self::get_cart(),
      ], 200);
    }

    wp_send_json_error( [ 'code' => 104, 'message' => 'No Item Key' ], 405 );
  }

  public function addCoupon()
  {
    if( isset( $_REQUEST['coupon'] ) ){
      if( ! WC()->cart->is_empty() && WC()->cart->apply_coupon( sanitize_text_field( $_REQUEST['coupon'] ) ) ){
        WC()->cart->calculate_totals();
        wp_send_json_success([
          'message' => 'Added coupon',
          'data' => self::get_cart(),
        ], 200);
      }else{
        wp_send_json_error( [ 'code' => 101, 'message' => 'Empty Cart or not valid coupon' ], 405 );
      }
    }else{
      wp_send_json_error( [ 'code' => 103, 'message' => 'No coupon code' ], 405 );
    }
  }

  public function removeCoupon()
  {
    if( isset( $_REQUEST['coupon'] ) ){
      if( WC()->cart->remove_coupon( sanitize_text_field( $_REQUEST['coupon'] ) ) ){
        WC()->cart->calculate_totals();
        wp_send_json_success([
          'message' => 'Coupon deleted',
          'data' => self::get_cart(),
        ], 200);
      }else{
        wp_send_json_error( [ 'code' => 101, 'message' => 'Empty Cart' ], 405 );
      }
    }else{
      wp_send_json_error( [ 'code' => 103, 'message' => 'No coupon code' ], 405 );
    }
  }

  public function getStates()
  {
    if( isset( $_REQUEST['code'] ) ){
      wp_send_json_success( WC()->countries->get_states( sanitize_text_field( $_REQUEST['code'] ) ), 200 );
    }else{
      wp_send_json_error( [ 'code' => 109, 'message' => 'No country code.' ], 405 );
    }
  }



  public static function get_cart()
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
      $datas['tax']           = wc_price( WC()->cart->get_subtotal_tax() );
      $datas['shipping']      = wc_price( WC()->cart->get_shipping_total() );
      
      if( $coupons = WC()->cart->get_coupons() ){
        $temp = [];
        foreach ( $coupons as $code => $coupon ) {
            $temp[$code] = [
              'code' => $coupon->get_code(),
              'amount' => wc_price( $coupon->get_amount() ),
              'description' => $coupon->get_description(),
            ];
        }

        $datas['coupons']       = $temp;
      }else{
        $datas['coupons']       = [];
      }

      $datas['fees']          = WC()->cart->get_fees();
      $datas['subtotal']      = wc_price( WC()->cart->get_subtotal() );
      $datas['total']         = wc_price( WC()->cart->get_total(false) );
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

    return $datas;
  }
}
