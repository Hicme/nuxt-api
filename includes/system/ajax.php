<?php

namespace system;

class Ajax{

  public function __construct()
  {
    add_action( 'wp_ajax_getSearchResults', [ $this, 'initSearch' ] );
    add_action( 'wp_ajax_nopriv_getSearchResults', [ $this, 'initSearch' ] );
  }

  public function initSearch()
  {
    if ( isset( $_REQUEST['search'] ) ) {
      global $wpdb;

      $return      = [];
      $search_word = sanitize_text_field( $_REQUEST['search'] );
      $results     = $wpdb->get_results( "SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_type IN ('page', 'post', 'product') AND post_status = 'publish' AND ( post_title LIKE '%{$search_word}%' OR post_content LIKE '%{$search_word}%' )", OBJECT );

      if ( $results ) {
        foreach ( $results as $result ) {
          $return[] = [
            'ID'          => $result->ID,
            'title'       => $this->highlight_text( $search_word, $result->post_title ),
            'description' => $this->cut_highlight_text( $result->post_content, $search_word ),
            'link'        => get_nuxt_permalink( $result->ID )
          ];
        }
      }

      wp_send_json_success( $return, 200 );
    }

    wp_send_json_error( [ 'message' => __( 'No search value.', 'nuxtapi' ) ], 405 );
  }

  private function highlight_text( $serch, $text )
  {
    $start_emp_token = '<span class="highlight_search">';
    $end_emp_token = '</span>';

    $content = preg_replace(
      "/($serch)/iu",
      $start_emp_token . '\\1' . $end_emp_token,
      $text
    );

    return $content;
  }

  private function cut_highlight_text( $text, $search_word, $size = 40 )
  {
    $text = wp_strip_all_tags( $text );
    $highlight = $this->highlight_text( $search_word, $text );
    return $highlight;
  }
}
