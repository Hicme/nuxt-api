<?php

namespace system\endpoints;

class Api_Menu extends \WP_REST_Controller
{

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'register_menu_route' ], 10 );

    add_action( 'init', [ $this, 'clear_cache' ] );
  }

  public function register_menu_route()
  {
    register_rest_route( REST_NAMESPASE, '/menu', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [ $this, 'get_items' ],
    ] );
  }

  public function get_items( $request )
  {

    if( ! ( $data = nuxt_api()->cache->get( 'main_menu' ) ) ){
      $data = [];
      
      $locations = get_nav_menu_locations();

      if( isset( $locations['primary_navigation'] ) && ( $menu_items = wp_get_nav_menu_items( $locations['primary_navigation'] ) ) ){

        foreach( $menu_items as $menu_item ){
          $data[] = [
            'ID'        => (int) $menu_item->ID,
            'object_id' => (int) $menu_item->object_id,
            'title'     => $menu_item->title,
            'url'       => str_replace( get_site_url(), '', $menu_item->url ),
            'menu_type' => $menu_item->object,
            'target'    => $menu_item->target,
            'classes'   => implode( ' ', $menu_item->classes ),
          ];
        }

        nuxt_api()->cache->set( 'main_menu', $data );

      }else{
        return new \WP_Error( 'no_menus', 'No menu created!', array( 'status' => 404 ) );
      }

    }

    return new \WP_REST_Response( $data, 200 );
  }

  public function clear_cache()
  {
    if( is_admin() && isset( $_POST['nav-menu-data'] ) ){
      nuxt_api()->cache->delete( 'main_menu' );
    }
  }

}
