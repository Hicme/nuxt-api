<?php
/**
 * Plugin Name: Nuxt API Extener
 * Description: Nuxt API Extener
 * Version: 0.0.3
 * Author: Support
 * Author URI: https://prosvit.design
 * Text Domain: nuxtapi
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'P_VERSION' ) ) {
	define( 'P_VERSION', '0.0.3' );
}

if ( ! defined( 'P_PATH' ) ) {
	define( 'P_PATH', dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'P_URL_FOLDER' ) ) {
	define( 'P_URL_FOLDER', plugin_dir_url( __FILE__ ) );
}

if( ! defined( 'REST_NAMESPASE' ) ) {
	define( 'REST_NAMESPASE', 'nuxt/v1' );
}

register_activation_hook( __FILE__, 'p_activate' );

register_deactivation_hook( __FILE__, 'p_deactivate' );

include P_PATH . 'autoloader.php';
include P_PATH . 'includes/functions/functions.php';

nuxt_api();

function p_activate()
{

}

function p_deactivate()
{
    
}
