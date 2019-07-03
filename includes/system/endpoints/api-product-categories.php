<?php

namespace system\endpoints;

class Api_product_categories extends \WP_REST_Controller
{

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'register_product_categories_route' ], 10 );
  }

  public function register_product_categories_route()
  {
    register_rest_route( REST_NAMESPASE, '/product-categories', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [ $this, 'get_all_items' ],
    ] );

    register_rest_route( REST_NAMESPASE, '/product-categories/(?P<id>[\w]+)', [
      'methods'  => \WP_REST_Server::READABLE,
      'callback' => [ $this, 'get_item' ],
    ] );
  }

  public function get_all_items( $request )
  {
    $args = array(
      'taxonomy'     => 'product_cat',
      'orderby'      => 'name',
      'show_count'   => 0,
      'pad_counts'   => 0,
      'hierarchical' => 1,
      'title_li'     => '',
      'hide_empty'   => 0
    );

    $categories = get_categories( $args );

    $data = [];

    if( !empty( $categories ) ){
      foreach ( $categories as $category ) {
        $data[] = [
          'id'          => (int) $category->term_id,
          'count'       => $category->count,
          'description' => $category->description,
          'link'        => str_replace( get_site_url(), '', get_term_link( $category ) ),
          'name'        => $category->name,
          'slug'        => $category->slug,
          'taxonomy'    => $category->taxonomy,
          'parent'      => $category->parent,
        ];
      }
    }

    return new \WP_REST_Response( $data, 200 );

  }

  public function get_item( $request )
  {

    $id = (int) $request['id'];

    $args = array(
      'taxonomy'     => 'product_cat',
      'orderby'      => 'name',
      'show_count'   => 1,
      'pad_counts'   => 0,
      'hierarchical' => 1,
      'title_li'     => '',
      'hide_empty'   => 0,
      'include'      => $id
    );

    $categories = get_categories( $args );

    $data = [];

    if( !empty( $categories ) ){
      foreach ( $categories as $category ) {
        $data[] = [
          'id'          => (int) $category->term_id,
          'count'       => $category->count,
          'description' => $category->description,
          'link'        => get_term_link( $category ),
          'name'        => $category->name,
          'slug'        => $category->slug,
          'taxonomy'    => $category->taxonomy,
          'parent'      => $category->parent,
        ];
      }
    }

    return new \WP_REST_Response( $data, 200 );

  }

}
