<?php

namespace admin;

class Admin_Startup
{

    public function __construct()
    {
        add_action('add_meta_boxes', [ $this, 'add_sidebar_settings' ] );
        add_action( 'save_post', [ $this, 'save_sidebar_settings' ] );
    }

    public function add_sidebar_settings()
    {
        $screens = [ 'post', 'page' ];
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