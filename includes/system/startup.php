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
        add_action('init', [$this, 'add_cors_http_header']);
        add_filter('flush_rewrite_rules_hard','__return_false');
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
        \system\extenders\Post::instance();
        \system\extenders\Page::instance();
        \system\extenders\Product::instance();
        \system\rest\Settings::instance();
        \system\rest\Menu::instance();
        \system\rest\Sidebars::instance();

        if( $this->is_request( 'cron' ) ){
            new \system\Cron();
        }

        if( $this->is_request( 'ajax' ) ){
            new \system\Ajax();
            new \system\ajax\Cart();
            new \system\ajax\Checkout();
            new \system\ajax\User();
        }

        if( $this->is_request( 'admin' ) ){
            new \admin\Admin_Startup();
        }
    }

    public function add_allowed_origins( $origins )
    {
        $origins[] = 'http://localhost:3000';
        $origins[] = 'https://localhost:3000';
        return $origins;
    }

    public function add_cors_http_header()
    {
        header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Action, Authorization, multipart/form-data");
        header("Access-Control-Allow-Credentials: true");
    }

    public function cache()
    {
        return \system\Cache::instance();
    }

}
