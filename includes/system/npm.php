<?php

namespace system;

class Npm
{
  use \system\Instance;

  private $supported_post_types = [
    'post',
    'page',
    'product',
  ];

  public function check_npm()
  {
    if ( function_exists('exec') ) {
      $output = exec('npm -v', $out, $err);

      return $err ? false : $output;
    }

    return false;
  }

  public function install_npm_modules()
  {
    $output = shell_exec('cd ' . ABSPATH . '/nuxtjs && npm install');

    return $output;
  }

  public function nuxt_generate()
  {
    $output = shell_exec('cd ' . ABSPATH . '/nuxtjs && npm run generate');

    return $output;
  }

  public function nuxt_build()
  {
    $output = shell_exec('cd ' . ABSPATH . '/nuxtjs && npm run build');

    return $output;
  }

  public function nuxt_generate_page( string $type, string $url )
  {
    if ( empty( $type || $url ) ) {
      throw new Exception("Need specify type and url of page.", 1);
    }

    if ( ! in_array( $type, $this->supported_post_types ) ) {
      throw new Exception("Unsupported post type.", 1);
    }

    if ( strpos($url, get_site_url()) !== false ) {
      $url = str_replace( get_site_url(), '', $url );
    }

    $output = shell_exec('cd ' . ABSPATH . '/nuxtjs && npm run generate:single -- --type='. $type .' --link='. $url .'');

    return $output;
  }
}
