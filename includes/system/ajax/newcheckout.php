<?php

namespace system\ajax;

class Newcheckout{
  public function __construct()
  {
    add_action( 'wp_ajax_updateSession', [ $this, 'updateSession' ] );
    add_action( 'wp_ajax_nopriv_updateSession', [ $this, 'updateSession' ] );

    add_action( 'wp_ajax_getCheckoutFields', [ $this, 'getCheckoutFields' ] );
    add_action( 'wp_ajax_nopriv_getCheckoutFields', [ $this, 'getCheckoutFields' ] );
  }


  public function updateSession()
  {
    if ( WC()->cart->is_empty() && ! is_customize_preview() ) {
      wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
    }

    $this->save_user_session();

    wp_send_json_success( [ 'message' => 'Datas updated' ], 200 );
  }

  public function getCheckoutFields()
  {
    $user_datas       = false;
    $shipping_methods = [];
    $payment_methods  = [];
    $checkout_fields  = [];
    $validation       = [];

    if ( $this->maybe_recived_user() ) {
      $this->save_user_session();
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
      'payment_methods'  => $payment_methods
    ], 200 );
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
    nuxt_api()->user->save_session();
  }

  private function get_user_datas()
  {
    $datas = [];
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
              $sections[$section][$key]['values'] = WC()->countries->get_states(
                WC()->customer->get_billing_country()
              );
              break;

            case 'select':
              $sections[$section][$key]['field_type'] = 'select';
              $sections[$section][$key]['values'] = [];
              break;

            default:
            $sections[$section][$key]['field_type'] = $field['type'];
          }
        }
      }
    }

    return $sections;
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

          if ( $validate_fieldset && '' !== $data[ $key ] && ! WC_Validation::is_postcode( $data[ $key ], $country ) ) {
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
          if ( $validate_fieldset && '' !== $data[ $key ] && ! WC_Validation::is_phone( $data[ $key ] ) ) {
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

    return $validations;
  }
}
