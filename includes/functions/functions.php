<?php

function nuxt_api()
{
  return \system\StartUp::instance();
}

if( ! function_exists( 'render_input' ) ){
  function render_input( array $args )
  {
    if( empty( $args['id'] ) ){
      return;
    }

    $args['type'] = isset( $args['type'] ) ? $args['type'] : 'text';
    $args['name'] = isset( $args['name'] ) ? $args['name'] : $args['id'];
    $args['class'] = isset( $args['class'] ) ? $args['class'] : '';
    $args['value'] = isset( $args['value'] ) ? $args['value'] : '';
    $args['description'] = isset( $args['description'] ) ? $args['description'] : '';

    $attributes = [];

    if ( ! empty( $args['attributes'] ) && is_array( $args['attributes'] ) ) {

    foreach ( $args['attributes'] as $attribute => $value ) {
      $attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
    }
    }

    ?>
    <p class="input-field-wrapper input-<?php echo $args['id'] ?>">
    <label for="input_filed_<?php echo $args['id'] ?>">
      <?php echo wp_kses_post( $args['label'] ); ?>
    </label>

    <input type="<?php echo esc_attr( $args['type'] ); ?>" id="input_filed_<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" class="<?php echo esc_attr( $args['class'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>" <?php echo implode( ' ', $attributes ); ?> />

    <?php
      if( !empty( $args['description'] ) ){
        echo '<span class="description">'. wp_kses_post( $args['description'] ) .'</span>';
      }
    ?>
    </p>
    <?php
  }
}

if( ! function_exists( 'get_template_html' ) ){
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
}

if( ! function_exists( 'send_reset_password_email' ) ){
  function send_reset_password_email( $reset_key, $user )
  {
    $reset_link = network_site_url( 'login/reset?login=' . $user->user_login . "&key=$reset_key" );
    $subject = __( 'Request to reset password', 'nuxtapi' );
    $headers = [ 'content-type: text/html' ];
    $attachments = false;

    $html = get_template_html( P_PATH . 'templates\email\html-reset-password.php', [ 'reset_link' => $reset_link, 'subject' => $subject ] );

    return wp_mail( $user->user_email, $subject, $html, $headers, $attachments );
  }
}

if (!function_exists('highlight_text')) {
  function highlight_text( array $search_arr, string $text )
  {
    $start = '<span class="highlight_search">';
    $end = '</span>';

    foreach ( $search_arr as $key => $word ) {
      if ( 1 === preg_match( "/($word)/iu", $text ) ) {
        $text = preg_replace(
          "/($word)/iu",
          $start . '\\1' . $end,
          $text
        );

        if ($key === 0) {
          break;
        }
      }
    }

    return $text;
  }
}

if (!function_exists('cut_highlight_text')) {
  function cut_highlight_text( array $search_arr, string $text, int $size = 2 )
  {
    $results    = [];
    $search_pos = [];
    $text       = wp_strip_all_tags( $text );
    $text_array = str_word_count( $text, 1 );

    foreach ( $search_arr as $key => $word ) {
      if ( $pos = array_search( $word, $text_array ) ) {
        $search_pos[] = $pos;
      }
    }

    foreach ( $search_pos as $pos ) {
      $string     = '';
      if ( $pos < $size ) {
        $string .= $text_array[$pos] . ' ';
      } else {
        $string .= '...';
        for ( $i = $pos - $size; $i < $pos; $i++ ) {
          $string .= $text_array[$i] . ' ';
        }
      }

      if ( ( $pos + $size ) > count( $text_array ) ) {
        $string .= '...';
      } else {
        for ( $i = $pos; $i <= $pos + $size; $i++ ) { 
          $string .= $text_array[$i] . ' ';
        }
      }

      $string .= '...';

      $results[] = $string;
    }

    foreach ( $results as $key => $result ) {
      $results[$key] = highlight_text( $search_arr, $result );
    }

    return $results;
  }
}
