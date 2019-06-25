<?php

function loader( $class )
{
	$file = P_PATH . 'includes/' . str_replace( '\\', '/', strtolower( str_replace( '_', '-', $class ) ) ) . '.php';

	if ( is_file( $file ) ) {
        include_once( $file );
        
		return true;
	} else {
		return false;
	}
}

spl_autoload_register('loader');