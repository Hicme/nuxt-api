<?php

namespace system;

class User
{
  use \system\Instance;

  public function get_datas( $user_id = false )
  {
    if ( $user_id === false ) {
      $user_id = get_current_user_id();
    }

    if ( $user = get_userdata( $user_id ) ) {

      return [
        'ID'             => $user->ID,
        'login'          => $user->user_login,
        'email'          => $user->user_email,
        'date_registerd' => $user->user_registered,
        'status'         => $user->user_status,
        'nickname'       => $user->user_nicename,
        'dsplay_name'    => $user->display_name,
        'first_name'     => get_user_meta( $user->ID, 'first_name', true),
        'last_name'      => get_user_meta( $user->ID, 'last_name', true),
        'is_admin'       => in_array( 'administrator', $user->roles ),
      ];
    }

    return false;
  }

  public function save_session()
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
}
