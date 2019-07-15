<?php

function nuxt_api()
{
    return \system\StartUp::instance();
}

function get_template_html( $template_path, $args = array() )
{
    if ( ! empty( $args ) && is_array( $args ) ) {
		extract( $args );
    }
    
    if ( ! file_exists( $template_path ) ) {
        return false;
    }

    ob_start();
    include $template_path;
    return ob_get_clean();
}

function send_reset_password_email( $reset_key, $user )
{
    $reset_link = network_site_url( 'login/reset?login=' . $user->user_login . "&key=$reset_key" );
    $subject = __( 'Request to reset password', 'nuxtapi' );
    $headers = [ 'content-type: text/html' ];
    $attachments = false;

    $html = get_template_html( P_PATH . 'templates\email\html-reset-password.php', [ 'reset_link' => $reset_link, 'subject' => $subject ] );


    return wp_mail( $user->user_email, $subject, $html, $headers, $attachments );

}
