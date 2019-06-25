<?php

namespace system\endpoints;

class Api_Settings extends \WP_REST_Controller
{

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'register_settings_route' ], 10 );
  }

  public function register_settings_route()
  {
    register_rest_route( REST_NAMESPASE, '/settings', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [ $this, 'get_items' ],
    ] );
  }

  public function get_items( $request )
  {

    if( ! ( $data = nuxt_api()->cache->get( 'main_settings' ) ) ){
      $data = [];
      
      $data['site_title'] = get_option( 'blogname', false );
      $data['site_logo'] =  get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' )[0] : false;
      $data['site_language'] = get_option( 'WPLANG', false );
      $data['site_url'] = get_option( 'siteurl', false );
      $data['ajax_url'] = admin_url( 'admin-ajax.php' );
      $data['posts_per_page'] = get_option( 'posts_per_page', 15 );
      $data['front_page'] = get_option( 'page_on_front', false );

      nuxt_api()->cache->set( 'main_settings', $data );

    }

    return new \WP_REST_Response( $data, 200 );
  }

}
