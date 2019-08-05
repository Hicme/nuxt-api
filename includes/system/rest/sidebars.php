<?php

namespace system\rest;

class Sidebars extends \WP_REST_Controller
{
  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'register_left_sidebar_route' ], 10 );
    add_action( 'rest_api_init', [ $this, 'register_footer_sidebar_route' ], 15 );
    add_action( 'widgets.php', [ $this, 'clear_cache' ], 5 );
    add_action( 'delete_widget', [ $this, 'clear_cache' ], 5 );
    add_action('widgets_init', [ __CLASS__, 'register_widgets_areas' ] );
  }

  public function register_left_sidebar_route()
  {
    register_rest_route( REST_NAMESPASE, '/left_sidebar', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [ $this, 'get_left_sidebar_items' ],
    ] );
  }

  public function register_footer_sidebar_route()
  {
    register_rest_route( REST_NAMESPASE, '/footer_sidebar', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [ $this, 'get_footer_sidebar_items' ],
    ] );
  }

  public function get_left_sidebar_items( $request )
  {
    if( ! ( $data = nuxt_api()->cache->get( 'left_sidebar' ) ) ){
      $data = [];

      global $wp_registered_widgets;

      $sidebars_widgets = wp_get_sidebars_widgets();
      
      if( !empty( $sidebars_widgets['sidebar-left'] ) ){
        foreach( $sidebars_widgets['sidebar-left'] as $widget ) {
          $data[] = $this->prepare_widget_response( $wp_registered_widgets[$widget] );
        }
      }else{
        return [];
      }

      nuxt_api()->cache->set( 'left_sidebar', $data );

    }

    return new \WP_REST_Response( $data, 200 );
  }

  public function get_footer_sidebar_items( $request )
  {
    if( ! ( $data = nuxt_api()->cache->get( 'footer_sidebar' ) ) ){
      $data = [];

      global $wp_registered_widgets;

      $sidebars_widgets = wp_get_sidebars_widgets();
      
      if( empty( $sidebars_widgets['sidebar-footer-left'] ) && empty( $sidebars_widgets['sidebar-footer-right'] ) ){
        return [];
      }

      if( !empty( $sidebars_widgets['sidebar-footer-left'] ) ){
        foreach( $sidebars_widgets['sidebar-footer-left'] as $widget ) {
          $data['footer-left'][] = $this->prepare_widget_response( $wp_registered_widgets[$widget] );
        }
      }

      if( !empty( $sidebars_widgets['sidebar-footer-right'] ) ){
        foreach( $sidebars_widgets['sidebar-footer-right'] as $widget ) {
          $data['footer-right'][] = $this->prepare_widget_response( $wp_registered_widgets[$widget] );
        }
      }

      nuxt_api()->cache->set( 'footer_sidebar', $data );

    }

    return new \WP_REST_Response( $data, 200 );
  }

  private function prepare_widget_response( $widget )
  {
    global $wp_registered_sidebars, $sidebars_widget;

    $args = array();
    
    $args['id'] = $widget['id'];
    $args['classname'] = $widget['classname'];

    $widget['has_output'] = ( 0 < $widget['params'][0]['number'] ) ? true : false;

    $widget['instance_number'] = $widget['params'][0]['number'];

    $widget['instance'] = $this->get_widget_instance( $widget, $widget['params'][0]['number'] );

    $widget['widget_output'] = $this->get_the_widget( $widget, $args );

    unset( $widget['params'] );
    unset( $widget['callback'] );

    return $widget;
  }

  private function get_widget_instance( $widget, $instance_number ) {
    if ( true === $widget['has_output'] ) {
      $instances = get_option( $widget['callback'][0]->option_name );
      $instance = $instances[ $instance_number ];
      return $instance;
    }
    return false;
  }
  
  private function get_the_widget( $widget, $args ) {
    $the_widget = '';

    $default_args = array(
      'before_widget' => sprintf( '<section id="%1$s" class="widget %2$s">', $args['id'], $args['classname'] ),
      'after_widget'  => '</section>',
      'before_title'  => '<h2 class="widget-title">',
      'after_title'   => '</h2>',
    );

    $args = wp_parse_args( $args, $default_args );

    ob_start();
      $widget['callback'][0]->display_callback( $args, $widget['params'][0]['number'] );
      $the_widget = ob_get_contents();
    ob_end_clean();

    return $the_widget;
  }

  public function clear_cache()
  {
    nuxt_api()->cache->delete( 'left_sidebar' );
    nuxt_api()->cache->delete( 'footer_sidebar' );
  }

  public static function register_widgets_areas()
  {
    $config = [
      'before_widget' => '<section class="widget %1$s %2$s">',
      'after_widget'  => '</section>',
      'before_title'  => '<h3 class="widget__title">',
      'after_title'   => '</h3>'
    ];
  
    register_sidebar([
        'name'          => __( 'Left Sidebar', 'nuxtapi' ),
        'id'            => 'sidebar-left'
    ] + $config);

    register_sidebar([
      'name'          => __( 'Footer Left Sidebar', 'nuxtapi' ),
      'id'            => 'sidebar-footer-left'
    ] + $config);

    register_sidebar([
      'name'          => __( 'Footer Right Sidebar', 'nuxtapi' ),
      'id'            => 'sidebar-footer-right'
    ] + $config);
  }

}
