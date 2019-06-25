<?php

namespace system;

final class StartUp
{

    use \system\Instance;


    public function __get( $key )
    {
		if ( in_array( $key, array( 'cache', 'methods' ), true ) ) {
			return $this->$key();
		}
	}
    

    public function __construct()
    {

        $this->includes();

        do_action( 'p_loaded' );
        
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
        \system\endpoints\Extends_Product::instance();
        \system\endpoints\Api_Settings::instance();
        \system\endpoints\Api_Sidebars::instance();

        if( $this->is_request( 'cron' ) ){
            new \system\Cron();
        }

        if( $this->is_request( 'ajax' ) ){
            new \system\Ajax();
        }

        if( $this->is_request( 'admin' ) ){
            new \admin\Admin_Startup();
        }

    }


    public function cache()
    {
        return \system\Cache::instance();
    }

}
