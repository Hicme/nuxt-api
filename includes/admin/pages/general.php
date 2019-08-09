<?php

namespace admin\pages;

class General
{
  public static function sanitize_return( $value )
  {
    nuxt_api()->cache->delete( 'main_settings' );
    return esc_attr( $value );
  }

  public static function sanitize_checkbox( $value )
  {
    nuxt_api()->cache->delete( 'main_settings' );
    if( is_null( $value ) ){
      return false;
    }else{
      return esc_attr( $value );
    }
  }

  public static function render_content()
  {
    add_action('admin_init', [ __CLASS__, 'register_options' ]);
    add_action('nuxtapi_settings_tab_content', [ __CLASS__, 'get_template' ]);
  }

  public static function register_options()
  {
    wp_enqueue_media();
    register_setting( 'p-settings', 'nuxtapi_debug', [ __CLASS__, 'sanitize_checkbox' ] );

    add_settings_section(
      'id_p_general',
      'General Settings',
      [ __CLASS__, 'settings_html' ],
      'p_general_settings'
    );

    add_settings_field(
      'id_debug',
      'Debug',
      [ __CLASS__, 'id_debug_html' ],
      'p_general_settings',
      'id_p_general'
    );
  }

  public static function get_template()
  {
    include P_PATH . 'includes/admin/templates/general.php';
  }

  public static function settings_html()
  {
    echo '<p>Here you can set up API keys and others.</p>';
  }

  public static function id_debug_html()
  {
      render_input( [
          'id'          => 'id_debug',
          'label'       => '',
          'type'        => 'checkbox',
          'name'        => 'nuxtapi_debug',
          'value'       => '1',
          'attributes'  => ( get_option( 'nuxtapi_debug', false ) ? [ 'checked' => 'checked' ] : [] ) ,
          'description' => 'Enable debug mode?',
      ] );
  }
}
