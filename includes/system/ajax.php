<?php

namespace system;

class Ajax{

    public function __construct()
    {
        add_action( 'wp_ajax_', [ $this, '' ] );
        add_action( 'wp_ajax_nopriv_', [ $this, '' ] );
    }

}