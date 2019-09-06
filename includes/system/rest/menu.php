<?php

namespace system\rest;

class Menu extends \WP_REST_Controller
{

  private $menu_items = [];

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'register_menu_route' ], 10 );
    add_action( 'init', [ $this, 'clear_cache' ] );
    add_action('after_setup_theme', [ __CLASS__, 'register_nav_menus' ] );
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
      
      $locations = get_nav_menu_locations();

      if( isset( $locations['primary_navigation'] ) && ( $this->menu_items = wp_get_nav_menu_items( $locations['primary_navigation'] ) ) ){

        $data = $this->get_menu();

        nuxt_api()->cache->set( 'main_menu', $data );

      }else{
        return [];
      }

    }

    return new \WP_REST_Response( $data, 200 );
  }

  private function get_menu()
  {
    $menus = [];

    if( !empty( $this->menu_items ) ){
      foreach ( $this->menu_items as $item ) {
        if( $item->menu_item_parent == 0 ){

          $children = $this->get_children( $item );

          $menus[$item->menu_order] = [
            'ID'        => (int) $item->ID,
            'object_id' => (int) $item->object_id,
            'title'     => $item->title,
            'url'       => str_replace( get_site_url(), '', $item->url ),
            'menu_type' => $item->object,
            'target'    => $item->target,
            'classes'   => implode( ' ', $item->classes ),
            'submenu' => boolval( $children ),
            'children' => $children,
          ];
        }
      }
    }

    return $menus;
  }

  private function get_children( $menu_item )
  {

    $datas = [];

    if( !empty( $this->menu_items ) ){
      foreach ( $this->menu_items as $key => $item ) {
        if( $item->menu_item_parent == $menu_item->ID ){

          $children = $this->get_children( $item );

          $datas[] = [
            'ID'        => (int) $item->ID,
            'object_id' => (int) $item->object_id,
            'title'     => $item->title,
            'url'       => str_replace( get_site_url(), '', $item->url ),
            'menu_type' => $item->object,
            'target'    => $item->target,
            'classes'   => implode( ' ', $item->classes ),
            'submenu' => boolval( $children ),
            'children' => $children,
          ];
        }
      }
    }

    return $datas;
  }

  public function clear_cache()
  {
    if( is_admin() && isset( $_POST['nav-menu-data'] ) ){
      nuxt_api()->cache->delete( 'main_menu' );
    }
  }

  public static function register_nav_menus()
  {
    register_nav_menus([
      'primary_navigation' => __( 'Primary Navigation', 'nuxtapi' ),
    ]);
  }

}
