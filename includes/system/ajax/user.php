<?php

namespace system\ajax;

class User{

  public function __construct()
  {
    add_action( 'wp_ajax_getUser', [ $this, 'getUser' ] );
    add_action( 'wp_ajax_nopriv_getUser', [ $this, 'getUser' ] );

    add_action( 'wp_ajax_nopriv_logInUser', [ $this, 'logInUser' ] );
    add_action( 'wp_ajax_logInUser', [ $this, 'logInUser' ] );

    add_action( 'wp_ajax_logOut', [ $this, 'logOut' ] );
    add_action( 'wp_ajax_nopriv_logOut', [ $this, 'logOut' ] );

    add_action( 'wp_ajax_registerUser', [ $this, 'registerUser' ] );
    add_action( 'wp_ajax_nopriv_registerUser', [ $this, 'registerUser' ] );

    add_action( 'wp_ajax_nopriv_resetPassword', [ $this, 'resetPassword' ] );

    add_action( 'wp_ajax_nopriv_validateKeys', [ $this, 'validateKeys' ] );

    add_action( 'wp_ajax_nopriv_tryChangePassword', [ $this, 'tryChangePassword' ] );
  }



  public function getUser()
  {
    if( is_user_logged_in() ){
      if( $user = $this->get_datas() ){
        wp_send_json_success( $user, 200 );
      }

      wp_send_json_error( [ 'code' => 606, 'message' => 'Not found' ], 404 );
    }

    wp_send_json_error( [ 'code' => 600, 'message' => 'Unauthorized' ], 401 );
  }

  public function logInUser()
  {
    if ( ! is_user_logged_in() ) {

      $user_data = [];
      $user_data['user_login']    = sanitize_text_field( $_POST['username'] );
      $user_data['user_password'] = sanitize_text_field( $_POST['password'] );
      $user_data['remember']      = sanitize_text_field( $_POST['remember'] );

      $user = wp_signon( $user_data, false );

      if ( is_wp_error( $user ) ) {
        wp_send_json_error( [ 'code' => 601, 'message' => $user->get_error_code() ], 405 );
      }

      wp_send_json_success( $this->get_datas(), 200 );
    }

    wp_send_json_error( [ 'code' => 600, 'message' => 'Allready authorized' ], 405 );
  }

  public function logOut()
  {
    if( is_user_logged_in() ){
      wp_destroy_current_session();
      wp_clear_auth_cookie();
      
      wp_send_json_success( true, 200 );
    }

    wp_send_json_error( [ 'code' => 600, 'message' => 'Unauthorized' ], 401 );
  }

  public function registerUser()
  {
    if ( ! is_user_logged_in() ) {
    
      $user_email       = sanitize_email( $_POST['email'] );
      $password         = $_POST['password'];
      $confirm_password = $_POST['confirmPassword'];

      if ( $password !== $confirm_password ) {
        wp_send_json_error( [ 'code' => 650, 'message' => 'Passwords do not match.' ], 405 );
      }

      $user_id = wp_create_user( $user_email, $password, $user_email );

      if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( [ 'code' => 651, 'message' => $user_id->get_error_message() ], 405 );
      } else {
        wp_set_auth_cookie( $user_id );
        wp_send_json_success( $this->get_datas( $user_id ), 200 );
      }
    }

    wp_send_json_error( [ 'code' => 600, 'message' => 'Allready authorized' ], 405 );
  }

  public function resetPassword()
  {
    if( ! is_user_logged_in() ){
      $user = get_user_by( 'email', sanitize_email( $_POST['email'] ) );

      if( $user ){
        $reset_key = get_password_reset_key( $user );
        send_reset_password_email( $reset_key, $user );

        wp_send_json_success( [ 'message' => __('Your password was successfully reseted. Check your email.', 'nuxtapi'), 'key' => $reset_key, 'class' => 'alert-success' ], 200 );
      }else{
        wp_send_json_error( [ 'code' => 600, 'message' => __('Sorry, this email not found.', 'nuxtapi'), 'class' => 'alert-danger' ], 404 );
      }
    }

    wp_send_json_error( [ 'code' => 600, 'message' => 'Allready authorized' ], 405 );
  }

  public function validateKeys()
  {
    if( isset( $_REQUEST['key'] ) && isset( $_REQUEST['login'] ) ){
      $status = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );

      if( is_wp_error( $status ) ){
        wp_send_json_error( [ 'code' => 640, 'message' => $status->get_error_message() ], 403 );
      }
      else {
        wp_send_json_success( true, 200 );
      }
    }
  }

  public function tryChangePassword()
  {
    if( isset( $_REQUEST['key'] ) && isset( $_REQUEST['login'] ) ){
      $status = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );

      if( is_wp_error( $status ) ){
        wp_send_json_error( [ 'code' => 650, 'message' => $status->get_error_message(), 'class' => 'alert-danger' ], 403 );
      } else {

        if ( $_POST['password'] !== $_POST['confirmPassword'] ) {
          wp_send_json_error( [ 'code' => 650, 'message' => __( 'Passwords do not match.', 'nuxtapi' ), 'class' => 'alert-danger' ], 405 );
        }

        $user = get_user_by( 'login', $_REQUEST['login'] );
        
        if( $user ){
          wp_set_password( $_POST['password'], $user->ID );
          wp_send_json_success( [ 'message' => __( 'Your password was successfully changed.', 'nuxtapi' ), 'class' => 'alert-success' ], 200 );
        }

        wp_send_json_error( [ 'code' => 650, 'message' => __( 'Sorry, your data not recognized.', 'nuxtapi' ), 'class' => 'alert-danger' ], 403 );
      }
    }
  }



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
}
