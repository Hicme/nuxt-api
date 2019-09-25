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

      $return         = [];
      $search_reg     = '';
      $search_phrase  = esc_sql( $_REQUEST['search'] );
      $search_array   = str_word_count( $search_phrase, 1 );
      $search_array[] = $search_phrase;
      $count          = count ( $search_array );

      foreach ( $search_array as $key => $word ) {
        if ( $key + 1 < $count ) {
          $search_reg .= $word . '| ';
        } else {
          $search_reg .= $word . ' ';
        }
      }

      $results = $wpdb->get_results( "SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_type IN ('page', 'post', 'product') AND post_status = 'publish' AND ( post_title REGEXP '{$search_reg}' OR post_content REGEXP '{$search_reg}' ) GROUP BY ID", OBJECT );

      if ( $results ) {
        foreach ( $results as $result ) {
          $return[] = [
            'ID'          => $result->ID,
            'title'       => $this->highlight_text( $search_array, $result->post_title ),
            'description' => $this->cut_highlight_text( $search_array, $result->post_content ),
            'link'        => get_permalink( $result->ID )
          ];
        }
      }

      wp_send_json_success( $return, 200 );
    }

    wp_send_json_error( [ 'message' => __( 'No search value.', 'nuxtapi' ) ], 405 );
  }

  private function highlight_text( array $search_arr, string $text )
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
      }
    }

    return $text;
  }

  private function cut_highlight_text( array $search_arr, string $text, int $size = 2 )
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
      $results[$key] = $this->highlight_text( $search_arr, $result );
    }

    return $results;
  }
}
