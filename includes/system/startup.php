<?php

namespace system;

final class StartUp
{

    private $is_dev = true;


    use \system\Instance;


    public function __get( $key )
    {
		if ( in_array( $key, array( 'cache', 'developer' ), true ) ) {
			return $this->$key();
		}
	}
    

    public function __construct()
    {

        $this->includes();

        do_action( 'p_loaded' );
        
        add_filter( 'allowed_http_origins', [ $this, 'add_allowed_origins' ] );
        
    }


    public function is_request( $type )
    {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
    }

    
    private function includes()
    {
        
        \system\Register_Misc::init();
        \system\endpoints\Api_Menu::instance();
        \system\endpoints\Extends_Post::instance();
        \system\endpoints\Extends_Page::instance();
        \system\endpoints\Extends_Product::instance();
        \system\endpoints\Api_Settings::instance();
        \system\endpoints\Api_Sidebars::instance();
        \system\endpoints\Api_product_categories::instance();

        if( $this->is_request( 'cron' ) ){
            new \system\Cron();
        }

        // if( $this->is_request( 'ajax' ) ){
            new \system\Ajax();
        // }

        if( $this->is_request( 'admin' ) ){
            new \admin\Admin_Startup();
        }

    }

    public function add_allowed_origins( $origins ) {

        $origins[] = 'http://localhost:3000';

        return $origins;
    }

    public function cache()
    {
        return \system\Cache::instance();
    }

}
