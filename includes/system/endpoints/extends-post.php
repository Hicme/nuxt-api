<?php

namespace system\endpoints;

class Extends_Post extends \WP_REST_Controller
{

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'add_sidebar_settings' ], 10 );
  }

  public function add_sidebar_settings()
  {
    register_rest_field( 'post',
	        'sidebar_settings',
	        [
	          'get_callback'    => [ $this, 'get_sidebar_settings' ],
	        ]
	    );
  }

  public function get_sidebar_settings( $object, $field_name, $request )
  {

    $id = $object['id'];

    if ( is_wp_error( $id ) ) {
      return $id;
    }

    $data = false;

    if( !empty( $show = get_post_meta( $id, '_show_sidebar', true ) ) ){
      $data = true;
    }

    return $data;
  }

}
