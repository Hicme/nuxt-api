<?php

namespace system;

class Ajax
{

  public function __construct()
  {
    add_action('wp_ajax_getSearchResults', [$this, 'initSearch']);
    add_action('wp_ajax_nopriv_getSearchResults', [$this, 'initSearch']);

    add_action('wp_ajax_loadPreview', [$this, 'loadPreview']);
    add_action('wp_ajax_nopriv_loadPreview', [$this, 'loadPreview']);
  }


  private function prepera_rating_system($search_array, $datas)
  {
    $result_rate = [];
    // сначала есть ли вся фраза. потом надо как-то считать сколько разных слов встречается в тексте и считать рейтинг
    foreach ($search_array as $wKey => $word) {
      foreach ($datas as $dKey => $data) {
        if (!isset($result_rate[$data->ID])) {
          $result_rate[$data->ID] = 0;
        }

        $pattern = "/{$word}/";

        $found_title = preg_match_all($pattern, $data->post_title, $res1);
        $found_text = preg_match_all($pattern, $data->post_content, $res2);

        if ($wKey === 0) {
          if ($found_title > 0) {
            $result_rate[$data->ID] = $result_rate[$data->ID] + 100;
          }

          if ($found_text > 0) {
            $result_rate[$data->ID] = $result_rate[$data->ID] + 50;
          }
        } else {
          if ($found_title > 0) {
            $result_rate[$data->ID] = $result_rate[$data->ID] + $found_title + 20;
          }

          if ($found_text > 0) {
            $result_rate[$data->ID] = $result_rate[$data->ID] + $found_text;
          }
        }
      }
    }

    $counts = count($datas);
    
    foreach ($result_rate as $id => $rate) {
      if ($rate === 0) {
        unset($result_rate[$id]);
      } else {
        $math = $result_rate[$id] / $counts;
        if ( $math < 0.1 ) {
          unset($result_rate[$id]);
        }
      }
    }

    arsort($result_rate);

    $return    = [];
    $iteration = 0;
    foreach ($result_rate as $key => $rating) {
      $iteration++;
      $return[] = $datas[$key];

      if ( $iteration >= 10 ) {
        break;
      }
    }

    return $return;
  }

  public function initSearch()
  {
    if (isset($_REQUEST['search'])) {
      global $wpdb;

      $return         = [];
      $search_reg     = '';
      $search_phrase  = esc_sql($_REQUEST['search']);
      $search_array   = str_word_count($search_phrase, 1, '1234567890');
      $count          = count($search_array);

      if ($count > 1) {
        array_unshift($search_array, $search_phrase);
        $count = count($search_array);
      }

      foreach ($search_array as $key => $word) {
        if ($key + 1 < $count) {
          $search_reg .= $word . '| ';
        } else {
          $search_reg .= $word . ' ';
        }
      }

      $results = $wpdb->get_results("SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE post_type IN ('page', 'post', 'product') AND post_status = 'publish' AND ( post_title REGEXP '{$search_reg}' OR post_content REGEXP '{$search_reg}' ) GROUP BY ID", OBJECT_K);

      if ($results) {
        $sorted = $this->prepera_rating_system($search_array, $results);


        foreach ($sorted as $result) {
          $return[] = [
            'ID'          => $result->ID,
            'title'       => highlight_text($search_array, $result->post_title),
            'description' => cut_highlight_text($search_array, $result->post_content),
            'link'        => str_replace(get_site_url(), '', get_permalink($result->ID))
          ];
        }
      } else {
        $return[] = [
          'ID'          => 0,
          'title'       => __('Nothing found.', 'nuxtapi'),
          'description' => '',
          'link'        => false
        ];
      }

      wp_send_json_success($return, 200);
    }

    wp_send_json_error(['message' => __('No search value.', 'nuxtapi')], 405);
  }

  public function loadPreview()
  {
    if (
      isset($_REQUEST['id'])
      && current_user_can('administrator')
    ) {
      $revisions = wp_get_post_revisions(intval($_REQUEST['id']));

      $last_revision = false;

      foreach ($revisions as $revision) {
        if (false !== strpos($revision->post_name, "{$revision->post_parent}-revision")) {
          $last_revision = $revision;
          break;
        }
      }

      if ($last_revision) {
        wp_send_json_success(nuxt_api()->prepareJsonPost($last_revision), 200);
      }
    }

    wp_send_json_error(['message' => __('Nothing to show.', 'nuxtapi')], 405);
  }
}
