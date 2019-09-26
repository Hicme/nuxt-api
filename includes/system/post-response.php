<?php

namespace system;

class Post_Response
{
  public static function response( $post )
  {
    if (!is_object($post)) {
      $post = get_post($post);
    }

    $data = [];

    $data['author'] = (int) $post->post_author;
    $data['date'] = self::prepare_date_response($post->post_date_gmt, $post->post_date);
    $data['date_gmt'] = self::prepare_date_response($post->post_date_gmt);
    $data['id'] = $post->ID;
    $data['modified'] = self::prepare_date_response($post->post_modified_gmt, $post->post_modified);
    $data['modified_gmt'] = self::prepare_date_response($post->post_modified_gmt);
    $data['parent'] = (int) $post->post_parent;
    $data['slug'] = $post->post_name;
    $data['guid'] = array(
      'rendered' => apply_filters('get_the_guid', $post->guid, $post->ID),
      'raw'      => $post->guid,
    );
    $data['title'] = array(
      'raw'      => $post->post_title,
      'rendered' => get_the_title($post->ID),
    );
    $data['content'] = array(
      'raw'      => $post->post_content,
      'rendered' => apply_filters('the_content', $post->post_content),
    );
    $data['excerpt'] = array(
      'raw'      => $post->post_excerpt,
      'rendered' => self::prepare_excerpt_response($post->post_excerpt, $post),
    );
    $data['show_sidebar'] = ! empty( get_post_meta($post->ID, '_show_sidebar', false) ) ?  true : false;

    return $data;
  }

  protected static function prepare_date_response($date_gmt, $date = null)
  {
    // Use the date if passed.
    if (isset($date)) {
      return mysql_to_rfc3339($date);
    }

    // Return null if $date_gmt is empty/zeros.
    if ('0000-00-00 00:00:00' === $date_gmt) {
      return null;
    }

    // Return the formatted datetime.
    return mysql_to_rfc3339($date_gmt);
  }

  protected static function prepare_excerpt_response($excerpt, $post)
  {
    /** This filter is documented in wp-includes/post-template.php */
    $excerpt = apply_filters('the_excerpt', $excerpt, $post);

    if (empty($excerpt)) {
      return '';
    }

    return $excerpt;
  }
}
