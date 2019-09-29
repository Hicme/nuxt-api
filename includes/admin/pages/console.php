<?php

namespace admin\pages;

class Console
{
  public static function exec_comands()
  {
    if( isset( $_REQUEST['_action'] ) && $_REQUEST['_action'] === 'console_commands' ){
      ?>
      <h4 class="console_responce_title"><?php _e( 'Console log', 'wpabcf' ); ?></h4>
      <div class="console_responce">
        <?php
          if( wp_verify_nonce( $_REQUEST['_nonce'], 'console_nonce') ){
            if( $_REQUEST['console_action'] === 'generate-build' ){
              $return  = nuxt_api()->npm->nuxt_generate();
              echo "<pre>{$return}</pre>";
            }

            if( $_REQUEST['console_action'] === 'install' ){
              $return  = nuxt_api()->npm->install_npm_modules();
              echo "<pre>{$return}</pre>";
            }

            if( $_REQUEST['console_action'] === 'build' ){
              $return  = nuxt_api()->npm->nuxt_build();
              echo "<pre>{$return}</pre>";
            }
          } else {
            echo sprintf( '<h3>%s</h3>', __('Wrong token. Try again.', 'wpabcf') );
          }
        ?>
      </div>
      <?php
    }
  }

  public static function render_content()
  {
    add_action('console_result', [ __CLASS__, 'exec_comands' ]);
    add_action('nuxtapi_settings_tab_content', [ __CLASS__, 'get_template' ]);
  }

  public static function get_template()
  {
    include P_PATH . 'includes/admin/templates/console.php';
  }
}
