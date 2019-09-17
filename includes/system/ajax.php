<?php

namespace system;

class Ajax{

  public function __construct()
  {
    add_action( 'wp_ajax_nopriv_initCheckoutFields', [ $this, 'initCheckoutFields' ] );
    add_action( 'wp_ajax_initCheckoutFields', [ $this, 'initCheckoutFields' ] );
  }

  public function initCheckoutFields()
  {
    $fieldsets = WC()->checkout->get_checkout_fields();
    $usersets = [];

    $user = is_user_logged_in() ? WC()->customer : false;

    foreach ( $fieldsets as $section => $fields ) {
      foreach ( $fields as $key => $field ) {
        $fieldsets[$section][$key]['section'] = $section;
        $fieldsets[$section][$key]['name'] = $key;

        switch ( $field['type'] ) {
          case 'country':
            $fieldsets[$section][$key]['field_type'] = 'country';
            $fieldsets[$section][$key]['values'] = WC()->countries->get_countries();

            if ( $user ) {
              $usersets[$key] = $user->get_billing_country();
            } else {
              $usersets[$key] = WC()->countries->get_base_country();
            }
            break;
          
          case 'state':
            $fieldsets[$section][$key]['field_type'] = 'state';
            $fieldsets[$section][$key]['values'] = WC()->countries->get_states( $user ? $user->get_billing_country() : WC()->countries->get_base_country() );

            if ( $user ) {
              $usersets[$key] = $user->get_billing_state();
            } else {
              $usersets[$key] = WC()->countries->get_base_state();
            }
            break;
          
          case 'textarea':
            $fieldsets[$section][$key]['field_type'] = 'textarea';
            $fieldsets[$section][$key]['filled'] = '';
            break;

          default:
            $fieldsets[$section][$key]['field_type'] = 'input';

            if ( $user ) {
              if ( $section === 'billing' ) {
                $data = $user->get_billing();
              } elseif( $section === 'shipping' ) {
                $data = $user->get_shipping();
              } else {
                continue;
              }

              $field_key = str_replace( $section . '_', '', $key );
              $usersets[$key] = $data[$field_key];
            } else {
              $usersets[$key] = '';
            }
            break;
        }

      }

    }

    wp_send_json_success( [ 'fields' => $fieldsets, 'user' => $usersets ], 200 );
  }
}
