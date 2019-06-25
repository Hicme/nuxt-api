<?php

namespace system;

class Cache
{
  private $expire;

  use \system\Instance;

  public function __construct( $expire = 36000 )
  {
    $this->expire = $expire;
    
    $this->check_folder();

    $this->check_expire();
  }

  private function check_folder()
  {
    if ( !file_exists( P_PATH . 'cache' ) && !is_dir( P_PATH . 'cache' ) ) {
      mkdir( P_PATH . 'cache' );
    }
  }

  private function check_expire()
  {
    $files = glob( P_PATH . 'cache/' . 'cache.*' );

    if ( $files ) {
      foreach ( $files as $file ) {
        $time = substr( strrchr( $file, '.' ), 1 );

        if ( $time < time() ) {
          if ( file_exists( $file ) ) {
            unlink( $file );
          }
        }
      }
    }
  }

  public function set( $key, $datas )
  {
    $this->delete( $key );

    $file =  P_PATH . 'cache/' . 'cache.' . preg_replace( '/[^A-Z0-9\._-]/i', '', $key ) . '.' . ( time() + $this->expire );

    $handle = fopen( $file, 'w' );

    flock( $handle, LOCK_EX );

    fwrite( $handle, json_encode( $datas ) );

    fflush( $handle );

    flock( $handle, LOCK_UN );

    fclose( $handle );
  }

  public function get( $key )
  {
    $files = glob( P_PATH . 'cache/' . 'cache.' . preg_replace( '/[^A-Z0-9\._-]/i', '', $key ) . '.*' );

    if ( $files ) {
      $handle = fopen( $files[0], 'r' );

      flock( $handle, LOCK_SH );

      $data = fread( $handle, filesize( $files[0] ) );

      flock( $handle, LOCK_UN );

      fclose( $handle );

      return json_decode( $data, true );
    }

    return false;
  }

  public function delete( $key )
  {
    $files = glob( P_PATH . 'cache/' . 'cache.' . preg_replace( '/[^A-Z0-9\._-]/i', '', $key ) . '.*' );

    if ( $files ) {
      foreach ( $files as $file ) {
        if ( file_exists( $file ) ) {
          unlink( $file );
        }
      }
    }
  }
}