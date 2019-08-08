<?php

namespace admin\pages;

class Nuxt
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
              $output = shell_exec('cd ' . ABSPATH . '/nuxtjs && npm run generate');
              echo "<pre>$output</pre>";
            }

            if( $_REQUEST['console_action'] === 'install' ){
              $output = shell_exec('cd ' . ABSPATH . '/nuxtjs && npm install');
              echo "<pre>$output</pre>";
            }
          } else {
            echo sprintf( '<h3>%s</h3>', __('Wrong token. Try again.', 'wpabcf') );
          }
        ?>
      </div>
      <?php
    }
  }

  public static function check_npm()
  {
    $output = exec('npm -v', $out, $err);
    return $err ? false : $output;
  }

  public static function render_content()
  {
    add_action('console_result', [ __CLASS__, 'exec_comands' ]);
    add_action('wpadbcf_settings_tab_content', [ __CLASS__, 'get_template' ]);
  }

  public static function get_template()
  {
    include P_PATH . 'includes/admin/templates/nuxt.php';
  }
}
