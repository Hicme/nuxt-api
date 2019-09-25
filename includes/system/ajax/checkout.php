<?php

namespace system\ajax;

class Checkout{
  public function __construct()
  {
    add_action( 'wp_ajax_updateSession', [ $this, 'updateSession' ] );
    add_action( 'wp_ajax_nopriv_updateSession', [ $this, 'updateSession' ] );

    add_action( 'wp_ajax_processCheckoutFields', [ $this, 'processCheckoutFields' ] );
    add_action( 'wp_ajax_nopriv_processCheckoutFields', [ $this, 'processCheckoutFields' ] );

    add_action( 'wp_ajax_processOrder', [ $this, 'processOrder' ] );
    add_action( 'wp_ajax_nopriv_processOrder', [ $this, 'processOrder' ] );

    add_action( 'wp_ajax_getOrder', [ $this, 'getOrder' ] );
    add_action( 'wp_ajax_nopriv_getOrder', [ $this, 'getOrder' ] );
  }


  public function updateSession()
  {
    if ( WC()->cart->is_empty() && ! is_customize_preview() ) {
      wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
    }

    $this->save_user_session();

    wp_send_json_success( [ 'message' => 'Datas updated' ], 200 );
  }

  public function processCheckoutFields()
  {
    $user_datas       = false;
    $shipping_methods = [];
    $payment_methods  = [];
    $checkout_fields  = [];
    $validation       = [];
    $cart_content     = false;

    if ( $this->maybe_recived_user() ) {
      $this->save_user_session();
      $cart_content = nuxt_api()->get_cart;
    } else {
      $user_datas = $this->get_user_datas();
    }

    $shipping_methods = $this->get_shipping_methods();
    $payment_methods  = $this->get_payment_methods();
    $checkout_fields  = $this->get_checkout_fields();
    $validation       = $this->validate_fields();

    wp_send_json_success( [
      'checkout_fields'  => $checkout_fields,
      'validation'       => $validation,
      'user_datas'       => $user_datas,
      'shipping_methods' => $shipping_methods,
      'payment_methods'  => $payment_methods,
      'cart_content'     => $cart_content
    ], 200 );
  }

  public function processOrder()
  {
    try {
      wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
      wc_set_time_limit( 0 );

      do_action( 'woocommerce_before_checkout_process' );

      if ( WC()->cart->is_empty() ) {
        wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
      }

      do_action( 'woocommerce_checkout_process' );

      $this->save_user_session();
      $posted_data = $this->get_post_data();
      $validation  = $this->validate_fields();

      if( ! empty( $validation ) ){
        wp_send_json_error( [ 'code' => 130, 'validation' => $validation ], 405 );
      }

      if ( 0 === wc_notice_count( 'error' ) ) {
          // $this->process_customer( $posted_data );
          $order_id = WC()->checkout->create_order( $posted_data );
          $order    = wc_get_order( $order_id );

          if ( is_wp_error( $order_id ) ) {
            wp_send_json_error( [ 'code' => 133, 'message' => $order_id->get_error_message() ], 405 );
          }

          if ( ! $order ) {
            wp_send_json_error( [ 'code' => 135, 'message' => __( 'Unable to create order.', 'woocommerce' ) ], 405 );
          }

          do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );

          if ( WC()->cart->needs_payment() ) {
              $this->process_order_payment( $order_id, $posted_data['payment_method'] );
          } else {
              $this->process_order_without_payment( $order_id );
          }
      } else {
        wp_send_json_error( [ 'code' => 137, 'message' => 'wc_notice_count' ], 405 );
      }
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'code' => 400, 'message' => $e->getMessage() ], 405 );
    }
  }

  public function getOrder()
  {
    if ( isset( $_REQUEST['orderId'] ) && get_post_type( sanitize_text_field( $_REQUEST['orderId'] ) ) == 'shop_order' ) {
      $order = wc_get_order( sanitize_text_field( $_REQUEST['orderId'] ) );

      if ( $order->get_order_key() == $_REQUEST['orderKey'] ) {
        $products = [];

        foreach ( $order->get_items() as $item ) {
          $products[] = [
            'productId'   => $item->get_product_id(),
            'productlink' => get_permalink( $item->get_product_id() ),
            'title'       => get_the_title( $item->get_product_id() ),
            'quantity'    => $item->get_quantity(),
            'total'       => $item->get_total(),
          ];
        }

        wp_send_json_success( [
          'orderId'         => $order->get_id(),
          'date'            => wc_format_datetime( $order->get_date_created() ),
          'status'          => $order->get_status(),
          'total'           => $order->get_formatted_order_total(),
          'paymentMethod'   => $order->get_payment_method_title(),
          'shippingMethod'  => $order->get_shipping_method(),
          'products'        => $products,
          'paymentAddress'  => $order->get_formatted_billing_address(),
          'shippingAddress' => $order->get_formatted_shipping_address(),
        ], 200 );
      }
    }

    wp_send_json_error( [ 'code' => 500, 'message' => 'No order found or wrong key.' ], 405 );
  }



  private function maybe_recived_user()
  {
    return isset( $_POST['user_datas'] );
  }

  private function get_post_data()
  {
    $skipped = array();
    $data    = array(
        'payment_method'            => isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '',
        'shipping_method'           => isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : '',
        'ship_to_different_address' => ( isset( $_POST['ship_to_different_address'] ) && $_POST['ship_to_different_address'] != 'false' ) && ! wc_ship_to_billing_address_only(),
    );

    foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
        if ( $this->maybe_skip_fieldset( $fieldset_key, $data ) ) {
          $skipped[] = $fieldset_key;
          continue;
        }

        foreach ( $fieldset as $key => $field ) {
          $type = sanitize_title( isset( $field['type'] ) ? $field['type'] : 'text' );

          switch ( $type ) {
              case 'checkbox':
                $value = isset( $_POST[ $key ] ) ? 1 : '';
                break;
              case 'multiselect':
                $value = isset( $_POST[ $key ] ) ? implode( ', ', wc_clean( wp_unslash( $_POST[ $key ] ) ) ) : '';
                break;
              case 'textarea':
                $value = isset( $_POST[ $key ] ) ? wc_sanitize_textarea( wp_unslash( $_POST[ $key ] ) ) : '';
                break;
              case 'password':
                $value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
                break;
              default:
                $value = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : '';
                break;
          }

          $data[ $key ] = $value;
        }
    }

    if ( in_array( 'shipping', $skipped, true ) && ( WC()->cart->needs_shipping_address() || wc_ship_to_billing_address_only() ) ) {
      foreach ( $this->get_checkout_fields( 'shipping' ) as $key => $field ) {
        $data[ $key ] = isset( $data[ 'billing_' . substr( $key, 9 ) ] ) ? $data[ 'billing_' . substr( $key, 9 ) ] : '';
      }
    }

    return $data;
  }

  private function save_user_session()
  {
    WC()->session->set( 'chosen_shipping_methods', empty( $_POST['shipping_method'] ) ? '' : [$_POST['shipping_method']] );
    WC()->session->set( 'chosen_payment_method', empty( $_POST['payment_method'] ) ? '' : $_POST['payment_method'] );
    WC()->customer->set_props(
      array(
        'billing_first_name' => isset( $_POST['billing_first_name'] ) ? wp_unslash( $_POST['billing_first_name'] ) : null,
        'billing_last_name'  => isset( $_POST['billing_last_name'] ) ? wp_unslash( $_POST['billing_last_name'] ) : null,
        'billing_company'    => isset( $_POST['billing_company'] ) ? wp_unslash( $_POST['billing_company'] ) : null,
        'billing_address_1'  => isset( $_POST['billing_address_1'] ) ? wp_unslash( $_POST['billing_address_1'] ) : null,
        'billing_address_2'  => isset( $_POST['billing_address_2'] ) ? wp_unslash( $_POST['billing_address_2'] ) : null,
        'billing_city'       => isset( $_POST['billing_city'] ) ? wp_unslash( $_POST['billing_city'] ) : null,
        'billing_state'      => isset( $_POST['billing_state'] ) ? wp_unslash( $_POST['billing_state'] ) : null,
        'billing_postcode'   => isset( $_POST['billing_postcode'] ) ? wp_unslash( $_POST['billing_postcode'] ) : null,
        'billing_country'    => isset( $_POST['billing_country'] ) ? wp_unslash( $_POST['billing_country'] ) : null,
        'billing_email'      => isset( $_POST['billing_email'] ) ? wp_unslash( $_POST['billing_email'] ) : null,
        'billing_phone'      => isset( $_POST['billing_phone'] ) ? wp_unslash( $_POST['billing_phone'] ) : null,
      )
    );

    if ( wc_ship_to_billing_address_only() || ( isset( $_POST['ship_to_different_address'] ) && $_POST['ship_to_different_address'] == 'false' ) ) {
      WC()->customer->set_props(
        array(
          'shipping_country'   => isset( $_POST['billing_country'] ) ? wp_unslash( $_POST['billing_country'] ) : null,
          'shipping_state'     => isset( $_POST['billing_state'] ) ? wp_unslash( $_POST['billing_state'] ) : null,
          'shipping_postcode'  => isset( $_POST['billing_postcode'] ) ? wp_unslash( $_POST['billing_postcode'] ) : null,
          'shipping_city'      => isset( $_POST['billing_city'] ) ? wp_unslash( $_POST['billing_city'] ) : null,
          'shipping_address_1' => isset( $_POST['billing_address_1'] ) ? wp_unslash( $_POST['billing_address_1'] ) : null,
          'shipping_address_2' => isset( $_POST['billing_address_2'] ) ? wp_unslash( $_POST['billing_address_2'] ) : null,
        )
      );
    } else {
      WC()->customer->set_props(
        array(
          'shipping_country'   => isset( $_POST['shipping_country'] ) ? wp_unslash( $_POST['shipping_country'] ) : null,
          'shipping_state'     => isset( $_POST['shipping_state'] ) ? wp_unslash( $_POST['shipping_state'] ) : null,
          'shipping_postcode'  => isset( $_POST['shipping_postcode'] ) ? wp_unslash( $_POST['shipping_postcode'] ) : null,
          'shipping_city'      => isset( $_POST['shipping_city'] ) ? wp_unslash( $_POST['shipping_city'] ) : null,
          'shipping_address_1' => isset( $_POST['shipping_address_1'] ) ? wp_unslash( $_POST['shipping_address_1'] ) : null,
          'shipping_address_2' => isset( $_POST['shipping_address_2'] ) ? wp_unslash( $_POST['shipping_address_2'] ) : null,
        )
      );
    }

    WC()->customer->save();
    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();
  }

  private function get_user_datas()
  {
    $datas = [
      'shipping_method'           => false,
      'payment_method'            => false,
      'ship_to_different_address' => false,
    ];
    foreach ( WC()->customer->get_billing() as $key => $field ) {
      $datas['billing_' . $key] = $field;
    }

    foreach ( WC()->customer->get_shipping() as $key => $field ) {
      $datas['shipping_' . $key] = $field;
    }

    return $datas;
  }

  private function get_shipping_methods()
  {
    $data = [];

    WC()->shipping->calculate_shipping( WC()->cart->get_shipping_packages() );

    $shipping_methods = WC()->shipping->get_packages();

    foreach( $shipping_methods[0]['rates'] as $id => $shipping_method ){
      $data[] = [
        'method_id' => $shipping_method->method_id,
        'type'      => $shipping_method->method_id,
        'id'        => $shipping_method->id,
        'name'      => $shipping_method->label,
        'price'     => wc_price( $shipping_method->cost ),
        'taxes'     => $shipping_method->taxes,
      ];
    }
  
    if( !empty( $data ) ){
      return $data;
    }else{
      return false;
    }
  }

  private function get_payment_methods()
  {
    $data = [];
    
    foreach( WC()->payment_gateways->get_available_payment_gateways() as $key => $gateway ){
      $data[] = [
        'id'                => $gateway->id,
        'order_button_text' => $gateway->order_button_text,
        'title'             => $gateway->get_title(),
        'description'       => $gateway->get_description(),
      ];
    }

    if( !empty( $data ) ){
      return $data;
    }else{
      return false;
    }
  }

  private function get_checkout_fields()
  {
    $sections = WC()->checkout->get_checkout_fields();

    foreach ( $sections as $section => $fields ) {
      foreach ( $fields as $key => $field ) {
        $sections[$section][$key]['section'] = $section;
        $sections[$section][$key]['name'] = $key;

        if( ! isset( $field['type'] ) ) {
          $sections[$section][$key]['field_type'] = 'input';
        } else {
          switch ( $field['type'] ) {
            case 'country':
              $sections[$section][$key]['field_type'] = 'select';
              $sections[$section][$key]['values'] = WC()->countries->get_countries();
              break;

            case 'state':
              $sections[$section][$key]['field_type'] = 'state';
              $values = WC()->countries->get_states( WC()->customer->get_billing_country() );

              if ( empty( $values ) ) {
                $values = false;
              }

              $sections[$section][$key]['values'] = $values;
              break;

            case 'select':
              $sections[$section][$key]['field_type'] = 'select';
              $sections[$section][$key]['values'] = [];
              break;

            case 'email':
              $sections[$section][$key]['field_type'] = 'input';
              break;

            case 'tel':
              $sections[$section][$key]['field_type'] = 'input';
              break;

            default:
            $sections[$section][$key]['field_type'] = $field['type'];
          }
        }
      }
    }

    return $sections;
  }

  private function is_registration_required() {
    return 'yes' !== get_option( 'woocommerce_enable_guest_checkout' );
  }

  private function maybe_skip_fieldset( $fieldset_key, $data ) {
    if ( 'shipping' === $fieldset_key &&
      ( ! $data['ship_to_different_address'] ||
      ! WC()->cart->needs_shipping_address() )
    ) {
        return true;
    }

    if ( 'account' === $fieldset_key &&
      (
         is_user_logged_in() ||
        ( ! $this->is_registration_required() &&
        empty( $data['createaccount'] ) ) 
      )
      ) {
        return true;
    }

    return false;
  }

  private function validate_fields()
  {
    if ( ! $this->maybe_recived_user() ) {
      return false;
    }

    $validations = [];
    $data        = $this->get_post_data();

    foreach ( $this->get_checkout_fields() as $fieldset_key => $fieldset ) {
      $validate_fieldset = true;
      if ( $this->maybe_skip_fieldset( $fieldset_key, $data ) ) {
        $validate_fieldset = false;
      }

      foreach ( $fieldset as $key => $field ) {
        if ( ! isset( $data[ $key ] ) ) {
          continue;
        }

        $required    = ! empty( $field['required'] );
        $format      = array_filter( isset( $field['validate'] ) ? (array) $field['validate'] : array() );
        $field_label = isset( $field['label'] ) ? $field['label'] : '';

        switch ( $fieldset_key ) {
          case 'shipping':
            $field_label = sprintf( __( 'Shipping %s', 'woocommerce' ), $field_label );
            break;

          case 'billing':
            $field_label = sprintf( __( 'Billing %s', 'woocommerce' ), $field_label );
            break;
        }

        if ( in_array( 'postcode', $format, true ) ) {
          $country      = isset( $data[ $fieldset_key . '_country' ] ) ? $data[ $fieldset_key . '_country' ] : WC()->customer->{"get_{$fieldset_key}_country"}();
          $data[ $key ] = wc_format_postcode( $data[ $key ], $country );

          if ( $validate_fieldset && '' !== $data[ $key ] && ! \WC_Validation::is_postcode( $data[ $key ], $country ) ) {
            switch ( $country ) {
              case 'IE':
                $postcode_validation_notice = sprintf( __( '%1$s is not valid. You can look up the correct Eircode <a target="_blank" href="%2$s">here</a>.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', 'https://finder.eircode.ie' );
                break;

              default:
                $postcode_validation_notice = sprintf( __( '%s is not a valid postcode / ZIP.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
            }
            $validations[$fieldset_key][$key] = $postcode_validation_notice;
          }
        }

        if ( in_array( 'phone', $format, true ) ) {
          if ( $validate_fieldset && '' !== $data[ $key ] && ! \WC_Validation::is_phone( $data[ $key ] ) ) {
            $validations[$fieldset_key][$key] = sprintf( __( '%s is not a valid phone number.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
          }
        }

        if ( in_array( 'email', $format, true ) && '' !== $data[ $key ] ) {
          $email_is_valid = is_email( $data[ $key ] );
          $data[ $key ]   = sanitize_email( $data[ $key ] );

          if ( $validate_fieldset && ! $email_is_valid ) {
            $validations[$fieldset_key][$key] = sprintf( __( '%s is not a valid email address.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
            continue;
          }
        }

        if ( '' !== $data[ $key ] && in_array( 'state', $format, true ) ) {
          $country      = isset( $data[ $fieldset_key . '_country' ] ) ? $data[ $fieldset_key . '_country' ] : WC()->customer->{"get_{$fieldset_key}_country"}();
          $valid_states = WC()->countries->get_states( $country );

          if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
            $valid_state_values = array_map( 'wc_strtoupper', array_flip( array_map( 'wc_strtoupper', $valid_states ) ) );
            $data[ $key ]       = wc_strtoupper( $data[ $key ] );

            if ( isset( $valid_state_values[ $data[ $key ] ] ) ) {
              // With this part we consider state value to be valid as well, convert it to the state key for the valid_states check below.
              $data[ $key ] = $valid_state_values[ $data[ $key ] ];
            }

            if ( $validate_fieldset && ! in_array( $data[ $key ], $valid_state_values, true ) ) {
              $validations[$fieldset_key][$key] = sprintf( __( '%1$s is not valid. Please enter one of the following: %2$s', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', implode( ', ', $valid_states ) );
            }
          }
        }

        if ( $validate_fieldset && $required && '' === $data[ $key ] ) {
          $validations[$fieldset_key][$key] = sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
        }
      }
    }

    if ( WC()->cart->needs_shipping() ) {
      $shipping_country = WC()->customer->get_shipping_country();

      if ( empty( $shipping_country ) ) {
        $validations['shipping_method'] = __( 'Please enter an address to continue.', 'woocommerce' );
      } elseif ( ! in_array( WC()->customer->get_shipping_country(), array_keys( WC()->countries->get_shipping_countries() ), true ) ) {
        $validations['shipping_method'] = sprintf( __( 'Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce' ), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country() );
      } else {
        $chosen_shipping_methods = $data['shipping_method'];

        foreach ( WC()->shipping()->get_packages() as $i => $package ) {
          if ( ! isset( $package['rates'][ $chosen_shipping_methods ] ) ) {
            $validations['shipping_method'] = __( 'No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce' );
          }
        }
      }
    }

    if ( WC()->cart->needs_payment() ) {
      $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

      if ( ! isset( $available_gateways[ $data['payment_method'] ] ) ) {
        $validations['payment_method'] = __( 'Invalid payment method.', 'woocommerce' );
      } else {
        ob_start();
          $available_gateways[ $data['payment_method'] ]->validate_fields();
        $content = ob_get_clean();

        if ( ! empty( $content ) ) {
          $validations['payment_method'] = $content;
        }
      }
    }

    return $validations;
  }

  private function process_order_payment( $order_id, $payment_method ) {
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
    $order = wc_get_order( $order_id );

    if ( ! isset( $available_gateways[ $payment_method ] ) ) {
      wp_send_json_error( [ 'code' => 140, 'message' => __( 'Wrong Payment Method.', 'woocommerce' ) ], 405 );
    }

    WC()->session->set( 'order_awaiting_payment', $order_id );

    $result = $available_gateways[ $payment_method ]->process_payment( $order_id );

    if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
        wp_send_json_success([
          'result'    => 'success',
          'order_id'  => $order_id,
          'order_key' => $order->get_order_key(),
        ], 200 );
    }
  }

  private function process_order_without_payment( $order_id ) {
    $order = wc_get_order( $order_id );
    $order->payment_complete();
    wc_empty_cart();

    if ( ! is_ajax() ) {
        wp_safe_redirect(
            apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order )
        );
        exit;
    }

    wp_send_json_success([
        'result'    => 'success',
        'order_id'  => $order->get_id(),
        'order_key' => $order->get_order_key()
      ],
      200
    );
  }
}
