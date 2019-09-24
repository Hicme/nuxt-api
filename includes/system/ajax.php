<?php

namespace system;

class Ajax{

  public function __construct()
  {
    add_action( 'wp_ajax_updateTotals', [ $this, 'updateTotals' ] );
    add_action( 'wp_ajax_nopriv_updateTotals', [ $this, 'updateTotals' ] );

    add_action( 'wp_ajax_nopriv_processCheckoutFields', [ $this, 'processCheckoutFields' ] );
    add_action( 'wp_ajax_processCheckoutFields', [ $this, 'processCheckoutFields' ] );
  }

  public function updateTotals()
  {
    if ( WC()->cart->is_empty() && ! is_customize_preview() ) {
      wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
    }

    WC()->session->set( 'chosen_shipping_methods', empty( $_POST['shipping_method'] ) ? '' : [$_POST['shipping_method']] );
    WC()->session->set( 'chosen_payment_method', empty( $_POST['payment_method'] ) ? '' : $_POST['payment_method'] );
    WC()->customer->set_props(
      array(
        'billing_country'   => isset( $_POST['billing_country'] ) ? wp_unslash( $_POST['billing_country'] ) : null,
        'billing_state'     => isset( $_POST['billing_state'] ) ? wp_unslash( $_POST['billing_state'] ) : null,
        'billing_postcode'  => isset( $_POST['billing_postcode'] ) ? wp_unslash( $_POST['billing_postcode'] ) : null,
        'billing_city'      => isset( $_POST['billing_city'] ) ? wp_unslash( $_POST['billing_city'] ) : null,
        'billing_address_1' => isset( $_POST['billing_address_1'] ) ? wp_unslash( $_POST['billing_address_1'] ) : null,
        'billing_address_2' => isset( $_POST['billing_address_2'] ) ? wp_unslash( $_POST['billing_address_2'] ) : null,
        'billing_phone' => isset( $_POST['billing_phone'] ) ? wp_unslash( $_POST['billing_phone'] ) : null,
      )
    );

    if ( wc_ship_to_billing_address_only() ) {
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
    
    wp_send_json_success( [ 'message' => 'Datas updated' ], 200 );
  }

  public function processCheckoutFields()
  {
    $user = false;
    $usersets = [];
    $usersets['ship_to_different_address'] = false;
    $usersets['shipping_method'] = false;
    $usersets['payment_method'] = false;

    if ( $this->meybe_post_request() ) {
      $user = $this->get_posted_user_data();
      $usersets['ship_to_different_address'] = $user['ship_to_different_address'];
    } elseif ( is_user_logged_in() ) {
      $user = WC()->customer;
    }

    $fieldsets = WC()->checkout->get_checkout_fields();

    foreach ( $fieldsets as $section => $fields ) {
      $validate_fieldset = false;

      if ( $user ) {
        if ( ! is_a( $user, 'WC_Customer' ) ) {
          $validate_fieldset = true;

          if ( $this->maybe_skip_fieldset( $section, $user ) ) {
            $validate_fieldset = false;
          }
        }
      }


      foreach ( $fields as $key => $field ) {
        $fieldsets[$section][$key]['section'] = $section;
        $fieldsets[$section][$key]['name'] = $key;

        if ( $user && $this->meybe_post_request() ) {
          if ( $error = $this->validate_field( $validate_fieldset, $key, $field, $section, $user ) ) {
            $fieldsets[$section][$key]['validation'] = $error;
          }
        }

        if ( isset( $field['type'] ) && $field['type'] === 'country' ) {
          $fieldsets[$section][$key]['field_type'] = 'select';
          $fieldsets[$section][$key]['values'] = WC()->countries->get_countries();

          if ( $user ) {
            if ( is_a( $user, 'WC_Customer' ) ) {
              $usersets[$key] = $user->get_billing_country();
            } else {
              $usersets[$key] = isset( $user[$key] ) ? $user[$key] : '';
            }
          } else {
            $usersets[$key] = WC()->countries->get_base_country();
          }
        } elseif( isset( $field['type'] ) && $field['type'] === 'state' ) {
          $fieldsets[$section][$key]['field_type'] = 'state';
          $fieldsets[$section][$key]['values'] = WC()->countries->get_states( $user ? $user->get_billing_country() : WC()->countries->get_base_country() );

          if ( $user ) {
            if ( is_a( $user, 'WC_Customer' ) ) {
              $usersets[$key] = $user->get_billing_state();
            } else {
              $usersets[$key] = isset( $user[$key] ) ? $user[$key] : '';
            }
          } else {
            $usersets[$key] = WC()->countries->get_base_state();
          }
        } elseif( isset( $field['type'] ) && $field['type'] === 'textarea' ) {
          $fieldsets[$section][$key]['field_type'] = 'textarea';
          $fieldsets[$section][$key]['filled'] = '';

          if ( $user ) {
            if ( is_a( $user, 'WC_Customer' ) ) {
              $usersets[$key] = '';
            } else {
              $usersets[$key] = isset( $user[$key] ) ? $user[$key] : '';
            }
          } else {
            $usersets[$key] = '';
          }
        } else {
          $fieldsets[$section][$key]['field_type'] = 'input';

          if ( $user ) {
            if ( is_a( $user, 'WC_Customer' ) ) {
              if ( $section === 'billing' ) {
                $data = $user->get_billing();
              } elseif( $section === 'shipping' ) {
                $data = $user->get_shipping();
              } else {
                continue;
              }

              $field_key = str_replace( $section . '_', '', $key );
              $usersets[$key] = !empty( $data[$field_key] ) ? $data[$field_key] : '';
            } else {
              $usersets[$key] = isset( $user[$key] ) ? $user[$key] : '';
            }
          } else {
            $usersets[$key] = '';
          }
        }

      }

    }

    $shipping = [
      'methods' => $this->get_shipping_methods(),
    ];

    $payment = [
      'methods' => $this->get_payment_methods(),
    ];

    if ( $user && $this->meybe_post_request() ) {
      $shipping['validation'] = $this->validate_shipping();
      $payment['validation'] = $this->validate_payment();
    }

    wp_send_json_success( [
      'fields' => $fieldsets,
      'user' => $usersets,
      'shipping' => $shipping,
      'payment' => $payment,
    ], 200 );
  }

  private function get_posted_user_data()
  {
    $skipped = [];
    $data = [
      'ship_to_different_address' => ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only(),
    ];

    foreach ( WC()->checkout->get_checkout_fields() as $fieldset_key => $fieldset ) {
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

      return $data;
    }else{
      return false;
    }
  }

  private function get_posted_methods()
  {
    if ( ! isset( $_REQUEST['posted_data'] ) ) {
      return false;
    }

    $data = [
      'payment_method'                     => isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '',
      'shipping_method'                    => isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : '',
      'woocommerce_checkout_update_totals' => isset( $_POST['woocommerce_checkout_update_totals'] ),
    ];

    return $data;
  }

  private function validate_shipping()
  {
    if ( WC()->cart->needs_shipping() ) {
      $shipping_country = WC()->customer->get_shipping_country();

      if ( empty( $shipping_country ) ) {
          return __( 'Please enter an address to continue.', 'woocommerce' );
      } elseif ( ! in_array( WC()->customer->get_shipping_country(), array_keys( WC()->countries->get_shipping_countries() ), true ) ) {
          /* translators: %s: shipping location */
          return sprintf( __( 'Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce' ), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country() );
      } else {
          $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

          foreach ( WC()->shipping()->get_packages() as $i => $package ) {
              if ( ! isset( $chosen_shipping_methods[ $i ], $package['rates'][ $chosen_shipping_methods[ $i ] ] ) ) {
                  return __( 'No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce' );
              }
          }
      }
    }
  }

  private function validate_payment()
  {
    if ( WC()->cart->needs_payment() ) {
      $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

      if ( ! isset( $available_gateways[ $data['payment_method'] ] ) ) {
          return __( 'Invalid payment method.', 'woocommerce' );
      } else {
          ob_start();
            $available_gateways[ $data['payment_method'] ]->validate_fields();
          return ob_get_clean();
      }
    }
  }

  private function validate_field( $validate_fieldset, $key, $field, $section, $user )
  {
    $required    = ! empty( $field['required'] );
    $format      = array_filter( isset( $field['validate'] ) ? (array) $field['validate'] : [] );
    $field_label = isset( $field['label'] ) ? $field['label'] : '';

    switch ( $section ) {
      case 'shipping':
          $field_label = sprintf( __( 'Shipping %s', 'woocommerce' ), $field_label );
          break;
      case 'billing':
          $field_label = sprintf( __( 'Billing %s', 'woocommerce' ), $field_label );
          break;
    }

    if ( in_array( 'postcode', $format, true ) ) {
      $country      = isset( $user[ $section . '_country' ] ) ? $user[ $section . '_country' ] : WC()->customer->{"get_{$section}_country"}();
      $user[ $key ] = wc_format_postcode( $user[ $key ], $country );

      if ( $validate_fieldset && '' !== $user[ $key ] && ! \WC_Validation::is_postcode( $user[ $key ], $country ) ) {
          switch ( $country ) {
              case 'IE':
                  $postcode_validation_notice = sprintf( __( '%1$s is not valid. You can look up the correct Eircode <a target="_blank" href="%2$s">here</a>.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', 'https://finder.eircode.ie' );
                  break;
              default:
                  $postcode_validation_notice = sprintf( __( '%s is not a valid postcode / ZIP.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
          }
          return $postcode_validation_notice;
      }
    }

    if ( in_array( 'phone', $format, true ) ) {
      if ( $validate_fieldset && '' !== $user[ $key ] && ! \WC_Validation::is_phone( $user[ $key ] ) ) {
        return sprintf( __( '%s is not a valid phone number.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
      }
    }

    if ( in_array( 'email', $format, true ) && '' !== $user[ $key ] ) {
      $email_is_valid = is_email( $user[ $key ] );
      $user[ $key ]   = sanitize_email( $user[ $key ] );

      if ( $validate_fieldset && ! $email_is_valid ) {
        return sprintf( __( '%s is not a valid email address.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
      }
    }

    if ( '' !== $user[ $key ] && in_array( 'state', $format, true ) ) {
      $country      = isset( $user[ $section . '_country' ] ) ? $user[ $section . '_country' ] : WC()->customer->{"get_{$section}_country"}();
      $valid_states = WC()->countries->get_states( $country );

      if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
          $valid_state_values = array_map( 'wc_strtoupper', array_flip( array_map( 'wc_strtoupper', $valid_states ) ) );
          $user[ $key ]       = wc_strtoupper( $user[ $key ] );

          if ( isset( $valid_state_values[ $user[ $key ] ] ) ) {
              $user[ $key ] = $valid_state_values[ $user[ $key ] ];
          }

          if ( $validate_fieldset && ! in_array( $user[ $key ], $valid_state_values, true ) ) {
            return sprintf( __( '%1$s is not valid. Please enter one of the following: %2$s', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', implode( ', ', $valid_states ) );
          }
      }
    }

    if ( $validate_fieldset && $required && '' === $user[ $key ] ) {
      return sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
    }
  }


  protected function meybe_post_request()
  {
    if ( ! isset( $_REQUEST['posted_data'] ) ) {
      return false;
    }

    return true;
  }

  protected function maybe_skip_fieldset( $fieldset_key, $data )
  {
    if ( 'shipping' === $fieldset_key && ( ! $data['ship_to_different_address'] || ! WC()->cart->needs_shipping_address() ) ) {
        return true;
    }

    return false;
  }

}
