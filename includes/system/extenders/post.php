<?php

namespace system\extenders;

class Post extends \WP_REST_Controller
{

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'add_extended_categories' ], 10 );
    add_action( 'rest_api_init', [ $this, 'post_images' ], 10 );
  }

  public function post_images()
  {
    register_rest_field( 'post',
      'images_url',
      [
        'get_callback'    => [ $this, 'get_image' ],
      ]
	  );
  }

  public function add_extended_categories()
  {
    register_rest_field( 'post',
      'extended_categories',
      [
        'get_callback'    => [ $this, 'get_extended_categories' ],
      ]
	  );
  }

  public function get_image( $object, $field_name, $request )
  {
    $id = $object['id'];

    if ( is_wp_error( $id ) ) {
      return $id;
    }

    if ( isset( $object['featured_media'] ) ) {
      return $this->get_images_urls( $object['featured_media'] );
    }
  }

  public function get_extended_categories( $object, $field_name, $request )
  {
    $id = $object['id'];

    if ( is_wp_error( $id ) ) {
      return $id;
    }

    $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
    $post_terms = wp_get_object_terms( $id, array_values( $taxonomies ) );

    $datas = [];

    if( $post_terms && ! is_wp_error( $post_terms ) ){
      foreach ($post_terms as $term ) {
        $datas[] = [
          'term_id'     => $term->term_id,
          'name'        => $term->name,
          'taxonomy'    => $term->taxonomy,
          'description' => $term->description,
          'count'       => $term->count,
          'slug'        => $term->slug,
          'link'        => str_replace( get_site_url(), '', get_term_link( $term ) )
        ];
      }
    }else{
      return false;
    }

    return $datas;
  }

  private function get_images_urls( $image_id )
  {
    $is_image = wp_attachment_is_image( $image_id );

    if ( $is_image ) {
      $img_url          = wp_get_attachment_url( $image_id );
      $meta             = wp_get_attachment_metadata( $image_id );
      $img_url_basename = wp_basename( $img_url );
      $urls             = [];

      if ( ! empty( $meta['sizes'] ) ) {
        $urls['full'] = $img_url;

        foreach ( $meta['sizes'] as $key => $size ) {
          $urls[$key] = str_replace( $img_url_basename, $size['file'], $img_url );
        }

        return $urls;
      }
    }

    return false;
  }

}
