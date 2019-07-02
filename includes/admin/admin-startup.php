<?php

namespace admin;

class Admin_Startup
{

    public function __construct()
    {
        add_action('admin_init', [ $this, 'add_posts_sidebar_settings' ] );

        add_action('add_meta_boxes', [ $this, 'add_sidebar_settings' ] );
        add_action( 'save_post', [ $this, 'save_sidebar_settings' ] );
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
    
}