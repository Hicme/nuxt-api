<?php

namespace admin;

class Settings_Pages
{

  protected $tab = false;
  protected $tab_links = [];


  public function __construct()
  {
    if( empty( $_GET['tab'] ) ){
      $this->tab = 'general';
    }else{
      $this->tab = esc_attr( $_GET['tab'] );
    }

    $this->set_tab_links();
    $this->load_tab_class();
  }

  public function set_tab_links()
  {
    $this->tab_links[ 'console' ] = [ 'title' => __( 'Console Comands', 'wpabcf' ), 'callback' => [ '\admin\pages\Nuxt', 'render_content' ] ];

    apply_filters( 'set_tab_links', $this->tab_links );
  }

  public function get_tab_link()
  {
    ob_start();
    
    foreach( $this->tab_links as $link => $tab ){
      if( $this->tab == $link ){
        echo '<a href="admin.php?page=wpabcf_settings&tab='. $link .'" class="active">'. $tab['title'] .'</a>';
      }else{
        echo '<a href="admin.php?page=wpabcf_settings&tab='. $link .'" class="">'. $tab['title'] .'</a>';
      }
    }

    echo ob_get_clean();
  }

  public function load_tab_class()
  {
    if( isset( $_GET['page'] ) && $_GET['page'] !== 'wpabcf_settings' ){
      return;
    }

    if( is_array( $this->tab_links[$this->tab]['callback'] ) && isset( $this->tab_links[$this->tab]['callback'][0] ) && is_object( $this->tab_links[$this->tab]['callback'][0] ) ){
      call_user_func( [ $this->tab_links[$this->tab]['callback'][0], $this->tab_links[$this->tab]['callback'][1] ] );
    }else{
      call_user_func( $this->tab_links[$this->tab]['callback'] );
    }
  }

  public function render_content()
  {
    include P_PATH . 'includes/admin/templates/settings.php';
  }
}
