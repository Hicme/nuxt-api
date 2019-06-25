<?php

namespace system;

class Register_Misc
{

  public static function init()
  {
    add_action('after_setup_theme', [ __CLASS__, 'register_nav_menus' ] );
    add_action('widgets_init', [ __CLASS__, 'register_widgets_areas' ] );
  }

  public static function register_nav_menus()
  {

    register_nav_menus([
      'primary_navigation' => __( 'Primary Navigation', 'nuxtapi' ),
    ]);

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