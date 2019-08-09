<?php

namespace admin;

class Admin_Startup
{

  public function __construct()
  {
    add_action( 'init', [ $this, 'admin_inits' ] );

    add_action('admin_init', [ $this, 'add_posts_sidebar_settings' ] );
    add_action('add_meta_boxes', [ $this, 'add_sidebar_settings' ] );
    add_action( 'save_post', [ $this, 'save_sidebar_settings' ] );

    add_action( 'admin_head', [ $this, 'admin_menus_reorder' ] );
    add_action( 'admin_menu', [ $this, 'admin_menus' ], 9 );

    add_action('admin_bar_menu', [ $this, 'clear_cache' ], 100);
    add_action( 'init', [ $this, 'detect_clear_cache' ] );

    add_action( 'p_loaded', [ __CLASS__, 'check_nuxt_url' ] );
  }

  public function admin_inits()
  {
    $this->init_settings_pages();
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
  }

  private function init_settings_pages()
  {
    $this->settings_class = new \admin\Settings_Pages();
  }

  public function enqueue_assets()
  {
    wp_enqueue_style( 'pstyles-admin', P_URL_FOLDER . 'assets/css/admin_styles.css', [], P_VERSION, 'screen' );
    wp_enqueue_script( 'pscripts-admin', P_URL_FOLDER . 'assets/js/admin_js.js', [], P_VERSION, true );
    wp_localize_script('pscripts-admin', 'NUXTAPIAJAX',
    [
      'url' => admin_url('admin-ajax.php'),
      'ajax_nonce' => wp_create_nonce('nuxtapi-admin-ajax-nonce'),
    ]
    );
  }

  public function admin_menus_reorder()
  {
    global $submenu;

    if( isset( $submenu['nuxtapi'] ) ){
      unset( $submenu['nuxtapi'][0] );

      // $post_types = $submenu['nuxtapi'][3];
      // unset( $submenu['nuxtapi'][3] );
      // array_unshift( $submenu['nuxtapi'], $post_types );
    }
  }

  public function admin_menus()
  {
    add_menu_page( __( 'Nuxt API', 'nuxtapi' ), __( 'Nuxt API', 'nuxtapi' ), 'activate_plugins', 'nuxtapi', null, 'dashicons-book-alt', '45' );
    add_submenu_page( 'nuxtapi', __( 'Settings', 'nuxtapi' ), __( 'Settings', 'nuxtapi' ), 'activate_plugins', 'nuxtapi_settings', [ $this->settings_class, 'render_content' ] );
  }

  public function add_posts_sidebar_settings()
  {
    $args = array(
      'type' => 'string', 
      'sanitize_callback' => 'sanitize_text_field',
      'default' => true,
    );

    register_setting( 
      'reading',
      'posts_sidebar',
      $args 
    );

    register_setting( 
      'reading',
      'single_post_sidebar',
      $args 
    );

    add_settings_field(
        'posts_sidebar',
        __('Sidebar on posts archive', 'nuxtapi'),
        [ $this, 'posts_sidebar_html' ],
        'reading',
        'default',
        [
          'label_for' => 'posts_sidebar',
          'title' => __( 'Show Sidebar on Posts Archive Page?', 'nuxtapi' ),
          'description' => __( 'Will sidebar be showing on post type archive page.', 'nuxtapi' ),
        ]
    );

    add_settings_field(
        'single_post_sidebar',
        __('Sidebar on single post', 'nuxtapi'),
        [ $this, 'posts_sidebar_html' ],
        'reading',
        'default',
        [
          'label_for' => 'single_post_sidebar',
          'title' => __( 'Show Sidebar on Single Post Page?', 'nuxtapi' ),
          'description' => __( 'Will sidebar be showing on single post page.', 'nuxtapi' ),
        ]
    );
  }

  public function posts_sidebar_html( $args )
  {
    $option = get_option( $args['label_for'], false);
  
    ?>
    <fieldset>
        <legend class="screen-reader-text">
            <span>
              <?php echo $args['title']; ?>
            </span>
        </legend>
        <label for="<?php echo $args['label_for']; ?>">
          <input name="<?php echo $args['label_for']; ?>" type="checkbox" id="<?php echo $args['label_for']; ?>" <?php checked( $option, true ); ?> value="1">
          <?php echo $args['title']; ?>
        </label>
        <p class="description">
        <?php echo $args['description']; ?>
        </p>
    </fieldset>
    <?php
  }

  public function add_sidebar_settings()
  {
    $screens = [ 'page' ];
    add_meta_box( 'myplugin_sectionid', __( 'Sidebar Settings', 'nuxtapi' ), [ $this, 'sidebar_settings_html' ], $screens, 'side', 'core' );
  }

  public function sidebar_settings_html( $post, $meta )
  {
    ?>
    <div class="components-base-control__field">
      <?php wp_nonce_field( plugin_basename(__FILE__), '_sidebar_nonce' ); ?>
      <input id="_show_sidebar" class="components-checkbox-control__input" type="checkbox" name="_show_sidebar" <?php checked( get_post_meta( $post->ID, '_show_sidebar', true ) ) ?> value="1">
      <label class="components-checkbox-control__label" for="_show_sidebar"><?php _e( 'Show sidebar?', 'nuxtapi' ); ?></label>
    </div>
    <?php
  }

  public function save_sidebar_settings( $post_id ) {

    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
      return;

    if( ! current_user_can( 'edit_post', $post_id ) )
      return;

    if ( !isset( $_POST['_sidebar_nonce'] ) || ! wp_verify_nonce( $_POST['_sidebar_nonce'], plugin_basename(__FILE__) ) )
    return;

    if ( ! isset( $_POST['_show_sidebar'] ) ){
      update_post_meta( $post_id, '_show_sidebar', false );
    }

    update_post_meta( $post_id, '_show_sidebar', sanitize_text_field( $_POST['_show_sidebar'] ) );
  }

  public function clear_cache( $admin_bar )
  {
    $admin_bar->add_menu( array(
    'id'    => 'abc_f',
    'title' => 'Nuxt API',
    'href'  => '/wp-admin/admin.php?page=nuxtapi_settings',
    'meta'  => array(
      'title' => __( 'Nuxt API', 'nuxtapi' ),
    ),
    ));

    $admin_bar->add_menu( array(
    'id'    => 'abc_cache',
    'parent' => 'abc_f',
    'title' => 'Clear Cache',
    'href'  => add_query_arg( ['abc_nonce' => wp_create_nonce( 'abcf_clear_cahce' ), 'abcf-clear-cahce' => 1], $_SERVER['REQUEST_URI'] ),
    'meta'  => array(
      'title' => __( 'Clear Cache',' nuxtapi' ),
      'class' => 'abcf_clear_cache'
    ),
    ));
  }

  public function detect_clear_cache()
  {
    if ( isset( $_GET['abcf-clear-cahce'] ) ) {
    if ( wp_verify_nonce( $_GET['abc_nonce'], 'abcf_clear_cahce' ) ) {
      nuxt_api()->cache->delete_all();
    }
    }
  }

  public static function check_nuxt_url()
  {
    if( file_exists( ABSPATH . 'nuxtjs/package.json' ) ) {
      $config = json_decode(file_get_contents( ABSPATH . 'nuxtjs/package.json' ), true );

      if( $config['url'] !== get_site_url() ) {
        $backup = fopen( ABSPATH . 'nuxtjs/package_backup.json', "w" );
        fwrite( $backup, json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES  ) );
        fclose( $backup );

        $config['name'] = get_option( 'blogname', false );
        $config['url'] = get_site_url();

        $handle = fopen( ABSPATH . 'nuxtjs/package.json', "w" );
        fwrite( $handle, json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES  ) );
        fclose( $handle );

        add_action( 'admin_notices', function() {
          ?>
            <div class="notice notice-error">
              <p>
                <?php echo sprintf(
                   __( 'Looks like you change domain name or move site. You need to <a href="%s">generate new build</a>. Otherwise nothing won\'t work correctly.', 'nuxtapi' ),
                   '/wp-admin/admin.php?page=nuxtapi_settings&tab=console'
                ); ?>
              </p>
            </div>
          <?php
        } );
      }
    }
  }
}