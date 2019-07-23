<?php

namespace system\ajax;

class Checkout{
  public function __construct()
  {
    add_action( 'wp_ajax_processCheckout', [ $this, 'process_checkout' ] );
    add_action( 'wp_ajax_nopriv_processCheckout', [ $this, 'process_checkout' ] );
  }

  public function process_checkout() {
    try {

        if ( WC()->cart->is_empty() ) {
          WC()->session->set( 'refresh_totals', true );
          wp_send_json_error( [ 'code' => 125, 'message' => 'We were unable to process order.' ], 405 );
        }

        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
        wc_set_time_limit( 0 );

        do_action( 'woocommerce_before_checkout_process' );

        if ( WC()->cart->is_empty() ) {
          wp_send_json_error( [ 'code' => 110, 'message' => 'Empty cart' ], 405 );
        }

        do_action( 'woocommerce_checkout_process' );

        $errors      = new \WP_Error();
        $posted_data = $this->get_posted_data();

        // Update session for customer and totals.
        $this->update_session( $posted_data );

        // Validate posted data and cart items before proceeding.
        $this->validate_checkout( $posted_data, $errors );

        var_dump( $errors->get_error_messages());die();

        if( wc_notice_count( 'error' ) !== 0 ){
          wp_send_json_error( [ 'code' => 130, 'messages' => $errors->get_error_messages() ], 405 );
        }

        if ( empty( $posted_data['woocommerce_checkout_update_totals'] ) && 0 === wc_notice_count( 'error' ) ) {
            // $this->process_customer( $posted_data );
            $order_id = WC()->checkout->create_order( $posted_data );
            $order    = wc_get_order( $order_id );

            if ( is_wp_error( $order_id ) ) {
                throw new Exception( $order_id->get_error_message() );
            }

            if ( ! $order ) {
                throw new Exception( __( 'Unable to create order.', 'woocommerce' ) );
            }

            do_action( 'woocommerce_checkout_order_processed', $order_id, $posted_data, $order );

            if ( WC()->cart->needs_payment() ) {
                $this->process_order_payment( $order_id, $posted_data['payment_method'] );
            } else {
                $this->process_order_without_payment( $order_id );
            }
        }
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'code' => 400, 'message' => $e->getMessage() ], 405 );
    }
  }

  protected function process_order_payment( $order_id, $payment_method ) {
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

    if ( ! isset( $available_gateways[ $payment_method ] ) ) {
        return;
    }

    // Store Order ID in session so it can be re-used after payment failure.
    WC()->session->set( 'order_awaiting_payment', $order_id );

    // Process Payment.
    $result = $available_gateways[ $payment_method ]->process_payment( $order_id );

    // Redirect to success/confirmation/payment page.
    if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
        $result = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );

        if ( ! is_ajax() ) {
            wp_redirect( $result['redirect'] );
            exit;
        }

        wp_send_json( $result );
    }
  }

  protected function process_order_without_payment( $order_id ) {
    $order = wc_get_order( $order_id );
    $order->payment_complete();
    wc_empty_cart();

    if ( ! is_ajax() ) {
        wp_safe_redirect(
            apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order )
        );
        exit;
    }

    wp_send_json(
        array(
            'result'   => 'success',
            'redirect' => apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order ),
        )
    );
  }

  public function get_posted_data() {
    $data    = [
        'terms'                              => (int) isset( $_POST['terms'] ),
        'createaccount'                      => (int) ! empty( $_POST['createaccount'] ),
        'payment_method'                     => isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '',
        'shipping_method'                    => isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : '',
        'ship_to_different_address'          => ! empty( $_POST['ship_to_different_address'] ) && ! wc_ship_to_billing_address_only(),
        'woocommerce_checkout_update_totals' => isset( $_POST['woocommerce_checkout_update_totals'] ),
    ];

    $alloweds = [
      'billing_email',
      'billing_first_name',
      'billing_last_name',
      'billing_phone',
      'billing_country',
      'billing_state',
      'billing_postcode',
      'billing_address_1',
      'order_comments'
    ];

    foreach ( $alloweds as $key ) {
        $value = isset( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : '';
        $data[ $key ] = apply_filters( 'woocommerce_process_checkout_field_' . $key, $value );
    }

    return apply_filters( 'woocommerce_checkout_posted_data', $data );
  }

  protected function validate_checkout( &$data, &$errors ) {
    $this->validate_posted_data( $data, $errors );
    $this->check_cart_items();

    if ( empty( $data['woocommerce_checkout_update_totals'] ) && empty( $data['terms'] ) && ! empty( $_POST['terms-field'] ) ) { // WPCS: input var ok, CSRF ok.
        $errors->add( 'terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ) );
    }

    if ( WC()->cart->needs_shipping() ) {
        $shipping_country = WC()->customer->get_shipping_country();

        if ( empty( $shipping_country ) ) {
            $errors->add( 'shipping', __( 'Please enter an address to continue.', 'woocommerce' ) );
        } elseif ( ! in_array( WC()->customer->get_shipping_country(), array_keys( WC()->countries->get_shipping_countries() ), true ) ) {
            /* translators: %s: shipping location */
            $errors->add( 'shipping', sprintf( __( 'Unfortunately <strong>we do not ship %s</strong>. Please enter an alternative shipping address.', 'woocommerce' ), WC()->countries->shipping_to_prefix() . ' ' . WC()->customer->get_shipping_country() ) );
        } else {
            $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

            foreach ( WC()->shipping()->get_packages() as $i => $package ) {
                if ( ! isset( $chosen_shipping_methods[ $i ], $package['rates'][ $chosen_shipping_methods[ $i ] ] ) ) {
                    $errors->add( 'shipping', __( 'No shipping method has been selected. Please double check your address, or contact us if you need any help.', 'woocommerce' ) );
                }
            }
        }
    }

    if ( WC()->cart->needs_payment() ) {
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

        if ( ! isset( $available_gateways[ $data['payment_method'] ] ) ) {
            $errors->add( 'payment', __( 'Invalid payment method.', 'woocommerce' ) );
        } else {
            $available_gateways[ $data['payment_method'] ]->validate_fields();
        }
    }

    do_action( 'woocommerce_after_checkout_validation', $data, $errors );
  }

  protected function validate_posted_data( &$data, &$errors ) {
    foreach ( WC()->checkout->get_checkout_fields() as $fieldset_key => $fieldset ) {
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
                    /* translators: %s: field name */
                    $field_label = sprintf( __( 'Shipping %s', 'woocommerce' ), $field_label );
                    break;
                case 'billing':
                    /* translators: %s: field name */
                    $field_label = sprintf( __( 'Billing %s', 'woocommerce' ), $field_label );
                    break;
            }

            if ( in_array( 'postcode', $format, true ) ) {
                $country      = isset( $data[ $fieldset_key . '_country' ] ) ? $data[ $fieldset_key . '_country' ] : WC()->customer->{"get_{$fieldset_key}_country"}();
                $data[ $key ] = wc_format_postcode( $data[ $key ], $country );

                if ( $validate_fieldset && '' !== $data[ $key ] && ! WC_Validation::is_postcode( $data[ $key ], $country ) ) {
                    switch ( $country ) {
                        case 'IE':
                            /* translators: %1$s: field name, %2$s finder.eircode.ie URL */
                            $postcode_validation_notice = sprintf( __( '%1$s is not valid. You can look up the correct Eircode <a target="_blank" href="%2$s">here</a>.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', 'https://finder.eircode.ie' );
                            break;
                        default:
                            /* translators: %s: field name */
                            $postcode_validation_notice = sprintf( __( '%s is not a valid postcode / ZIP.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' );
                    }
                    $errors->add( 'validation', apply_filters( 'woocommerce_checkout_postcode_validation_notice', $postcode_validation_notice, $country, $data[ $key ] ) );
                }
            }

            if ( in_array( 'phone', $format, true ) ) {
                if ( $validate_fieldset && '' !== $data[ $key ] && ! WC_Validation::is_phone( $data[ $key ] ) ) {
                    /* translators: %s: phone number */
                    $errors->add( 'validation', sprintf( __( '%s is not a valid phone number.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ) );
                }
            }

            if ( in_array( 'email', $format, true ) && '' !== $data[ $key ] ) {
                $email_is_valid = is_email( $data[ $key ] );
                $data[ $key ]   = sanitize_email( $data[ $key ] );

                if ( $validate_fieldset && ! $email_is_valid ) {
                    /* translators: %s: email address */
                    $errors->add( 'validation', sprintf( __( '%s is not a valid email address.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ) );
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
                        /* translators: 1: state field 2: valid states */
                        $errors->add( 'validation', sprintf( __( '%1$s is not valid. Please enter one of the following: %2$s', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>', implode( ', ', $valid_states ) ) );
                    }
                }
            }

            if ( $validate_fieldset && $required && '' === $data[ $key ] ) {
                /* translators: %s: field name */
                $errors->add( 'required-field', apply_filters( 'woocommerce_checkout_required_field_notice', sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field_label ) . '</strong>' ), $field_label ) );
            }
        }
    }
  }

  public function check_cart_items() {
    do_action( 'woocommerce_check_cart_items' );
  }

  protected function update_session( $data ) {
    // Update both shipping and billing to the passed billing address first if set.
    $address_fields = array(
        'first_name',
        'last_name',
        'company',
        'email',
        'phone',
        'address_1',
        'address_2',
        'city',
        'postcode',
        'state',
        'country',
    );

    array_walk( $address_fields, array( $this, 'set_customer_address_fields' ), $data );
    WC()->customer->save();

    // Update customer shipping and payment method to posted method.
    $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

    if ( is_array( $data['shipping_method'] ) ) {
        foreach ( $data['shipping_method'] as $i => $value ) {
            $chosen_shipping_methods[ $i ] = $value;
        }
    }

    WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
    WC()->session->set( 'chosen_payment_method', $data['payment_method'] );

    // Update cart totals now we have customer address.
    WC()->cart->calculate_totals();
  }

  protected function set_customer_address_fields( $field, $key, $data ) {
    $billing_value  = null;
    $shipping_value = null;

    if ( isset( $data[ "billing_{$field}" ] ) && is_callable( array( WC()->customer, "set_billing_{$field}" ) ) ) {
        $billing_value  = $data[ "billing_{$field}" ];
        $shipping_value = $data[ "billing_{$field}" ];
    }

    if ( isset( $data[ "shipping_{$field}" ] ) && is_callable( array( WC()->customer, "set_shipping_{$field}" ) ) ) {
        $shipping_value = $data[ "shipping_{$field}" ];
    }

    if ( ! is_null( $billing_value ) && is_callable( array( WC()->customer, "set_billing_{$field}" ) ) ) {
        WC()->customer->{"set_billing_{$field}"}( $billing_value );
    }

    if ( ! is_null( $shipping_value ) && is_callable( array( WC()->customer, "set_shipping_{$field}" ) ) ) {
        WC()->customer->{"set_shipping_{$field}"}( $shipping_value );
    }
  }

  protected function process_customer( $data ) {
    $customer_id = apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );

    if ( ! is_user_logged_in() && ( $this->is_registration_required() || ! empty( $data['createaccount'] ) ) ) {
        $username    = ! empty( $data['account_username'] ) ? $data['account_username'] : '';
        $password    = ! empty( $data['account_password'] ) ? $data['account_password'] : '';
        $customer_id = wc_create_new_customer(
            $data['billing_email'],
            $username,
            $password,
            array(
                'first_name' => ! empty( $data['billing_first_name'] ) ? $data['billing_first_name'] : '',
                'last_name'  => ! empty( $data['billing_last_name'] ) ? $data['billing_last_name'] : '',
            )
        );

        if ( is_wp_error( $customer_id ) ) {
            throw new Exception( $customer_id->get_error_message() );
        }

        wc_set_customer_auth_cookie( $customer_id );

        // As we are now logged in, checkout will need to refresh to show logged in data.
        WC()->session->set( 'reload_checkout', true );

        // Also, recalculate cart totals to reveal any role-based discounts that were unavailable before registering.
        WC()->cart->calculate_totals();
    }

    // On multisite, ensure user exists on current site, if not add them before allowing login.
    if ( $customer_id && is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() ) {
        add_user_to_blog( get_current_blog_id(), $customer_id, 'customer' );
    }

    // Add customer info from other fields.
    if ( $customer_id && apply_filters( 'woocommerce_checkout_update_customer_data', true, $this ) ) {
        $customer = new WC_Customer( $customer_id );

        if ( ! empty( $data['billing_first_name'] ) && '' === $customer->get_first_name() ) {
            $customer->set_first_name( $data['billing_first_name'] );
        }

        if ( ! empty( $data['billing_last_name'] ) && '' === $customer->get_last_name() ) {
            $customer->set_last_name( $data['billing_last_name'] );
        }

        // If the display name is an email, update to the user's full name.
        if ( is_email( $customer->get_display_name() ) ) {
            $customer->set_display_name( $customer->get_first_name() . ' ' . $customer->get_last_name() );
        }

        foreach ( $data as $key => $value ) {
            // Use setters where available.
            if ( is_callable( array( $customer, "set_{$key}" ) ) ) {
                $customer->{"set_{$key}"}( $value );

                // Store custom fields prefixed with wither shipping_ or billing_.
            } elseif ( 0 === stripos( $key, 'billing_' ) || 0 === stripos( $key, 'shipping_' ) ) {
                $customer->update_meta_data( $key, $value );
            }
        }

        /**
         * Action hook to adjust customer before save.
         *
         * @since 3.0.0
         */
        do_action( 'woocommerce_checkout_update_customer', $customer, $data );

        $customer->save();
    }

    do_action( 'woocommerce_checkout_update_user_meta', $customer_id, $data );
  }

  protected function maybe_skip_fieldset( $fieldset_key, $data ) {
    if ( 'shipping' === $fieldset_key && ( ! $data['ship_to_different_address'] || ! WC()->cart->needs_shipping_address() ) ) {
        return true;
    }

    if ( 'account' === $fieldset_key && ( is_user_logged_in() || ( ! $this->is_registration_required() && empty( $data['createaccount'] ) ) ) ) {
        return true;
    }

    return false;
  }

}
