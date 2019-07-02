<?php

namespace system\endpoints;

class Extends_Product
{

  use \system\Instance;

  public function __construct()
  {
    add_action( 'rest_api_init', [ $this, 'add_product_metas' ], 10 );
    add_action( 'rest_api_init', [ $this, 'add_extended_categories' ], 15 );
  }

  public function add_product_metas()
  {
    register_rest_field( 'product',
          'product_data',
          [
            'get_callback'    => [ $this, 'get_product_meta' ],
          ]
    );

    register_rest_field( 'product',
      'sidebar_settings',
      [
        'get_callback'    => [ $this, 'get_sidebar_settings' ],
      ]
    );

  }

  public function add_extended_categories()
  {
    register_rest_field( 'post',
      'extended_categories',
      [
        'get_callback'    => [ $this, 'get_extended_categories' ],
      ]
	  );
  }

  public function get_sidebar_settings( $object, $field_name, $request )
  {

    $id = $object['id'];

    if ( is_wp_error( $id ) ) {
      return $id;
    }

    $data = false;

    if( !empty( $show = get_post_meta( $id, '_show_sidebar', true ) ) ){
      $data = true;
    }

    return $data;
  }

  public function get_product_meta( $object, $field_name, $request )
  {
    $id = $object['id'];

    if ( is_wp_error( $id ) ) {
      return $id;
    }

    $product = wc_get_product( $id );

    $product_data = $this->get_product_data( $product );

    if ( $product->is_type( 'variable' ) && $product->has_child() ) {
      $product_data['variations'] = $this->get_variation_data( $product );
    }

    if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
      $_product = wc_get_product( $product->get_parent_id() );
      $product_data['parent'] = $this->get_product_data( $_product );
    }

      return $product_data;
  }

  private function get_product_data( $product )
  {
    if ( is_numeric( $product ) ) {
      $product = wc_get_product( $product );
    }

    if ( ! is_a( $product, 'WC_Product' ) ) {
      return array();
    }

    $prices_precision = wc_get_price_decimals();

    return array(
      'type'               => $product->get_type(),
      'status'             => $product->get_status(),
      'downloadable'       => $product->is_downloadable(),
      'virtual'            => $product->is_virtual(),
      'sku'                => $product->get_sku(),
      'price'              => wc_format_decimal( $product->get_price(), $prices_precision ),
      'regular_price'      => wc_format_decimal( $product->get_regular_price(), $prices_precision ),
      'sale_price'         => $product->get_sale_price() ? wc_format_decimal( $product->get_sale_price(), $prices_precision ) : null,
      'price_html'         => $product->get_price_html(),
      'stock_quantity'     => $product->get_stock_quantity(),
      'in_stock'           => $product->is_in_stock(),
      'backorders_allowed' => $product->backorders_allowed(),
      'backordered'        => $product->is_on_backorder(),
      'sold_individually'  => $product->is_sold_individually(),
      'purchaseable'       => $product->is_purchasable(),
      'featured'           => $product->is_featured(),
      'visible'            => $product->is_visible(),
      'catalog_visibility' => $product->get_catalog_visibility(),
      'on_sale'            => $product->is_on_sale(),
      'product_url'        => $product->is_type( 'external' ) ? $product->get_product_url() : '',
      'button_text'        => $product->is_type( 'external' ) ? $product->get_button_text() : '',
      'weight'             => $product->get_weight() ? wc_format_decimal( $product->get_weight(), 2 ) : null,
      'dimensions'         => array(
        'length' => $product->get_length(),
        'width'  => $product->get_width(),
        'height' => $product->get_height(),
        'unit'   => get_option( 'woocommerce_dimension_unit' ),
      ),
      'reviews_allowed'    => $product->get_reviews_allowed(),
      'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
      'rating_count'       => $product->get_rating_count(),
      'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
      'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
      'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
      'parent_id'          => $product->get_parent_id(),
      'categories'         => wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ),
      'tags'               => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
      'images'             => $this->get_images( $product ),
      'featured_src'       => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
      'attributes'         => $this->get_attributes( $product ),
      'purchase_note'      => wpautop( do_shortcode( wp_kses_post( $product->get_purchase_note() ) ) ),
      'total_sales'        => $product->get_total_sales(),
      'variations'         => array(),
      'parent'             => array(),
    );
    
  }

  private static function get_variation_data( $product ) {
    $prices_precision = wc_get_price_decimals();
    $variations       = array();

    foreach ( $product->get_children() as $child_id ) {

      $variation = wc_get_product( $child_id );

      if ( ! $variation || ! $variation->exists() ) {
        continue;
      }

      $variations[] = array(
        'id'                => $variation->get_id(),
        'created_at'        => date( "Y-m-d H:i:s", $variation->get_date_created() ),
        'updated_at'        => date( "Y-m-d H:i:s", $variation->get_date_modified() ),
        'downloadable'      => $variation->is_downloadable(),
        'virtual'           => $variation->is_virtual(),
        'permalink'         => $variation->get_permalink(),
        'sku'               => $variation->get_sku(),
        'price'             => wc_format_decimal( $variation->get_price(), $prices_precision ),
        'regular_price'     => wc_format_decimal( $variation->get_regular_price(), $prices_precision ),
        'sale_price'        => $variation->get_sale_price() ? wc_format_decimal( $variation->get_sale_price(), $prices_precision ) : null,
        'stock_quantity'    => (int) $variation->get_stock_quantity(),
        'in_stock'          => $variation->is_in_stock(),
        'backordered'       => $variation->is_on_backorder(),
        'purchaseable'      => $variation->is_purchasable(),
        'visible'           => $variation->variation_is_visible(),
        'on_sale'           => $variation->is_on_sale(),
        'weight'            => $variation->get_weight() ? wc_format_decimal( $variation->get_weight(), 2 ) : null,
        'dimensions'        => array(
          'length' => $variation->get_length(),
          'width'  => $variation->get_width(),
          'height' => $variation->get_height(),
          'unit'   => get_option( 'woocommerce_dimension_unit' ),
        ),
        'image'             => $this->get_images( $variation ),
        'attributes'        => $this->get_attributes( $variation ),
      );
    }

    return $variations;
  }

  private function get_images( $product ) {
    $images        = $attachment_ids = array();
    $product_image = $product->get_image_id();

    if ( ! empty( $product_image ) ) {
      $attachment_ids[] = $product_image;
    }

    $attachment_ids = array_merge( $attachment_ids, $product->get_gallery_image_ids() );

    foreach ( $attachment_ids as $position => $attachment_id ) {

      $attachment_post = get_post( $attachment_id );

      if ( is_null( $attachment_post ) ) {
        continue;
      }

      $attachment = wp_get_attachment_image_src( $attachment_id, 'full' );

      if ( ! is_array( $attachment ) ) {
        continue;
      }

      $images[] = array(
        'id'         => (int) $attachment_id,
        'created_at' => date( "Y-m-d H:i:s", $attachment_post->post_date_gmt ),
        'updated_at' => date( "Y-m-d H:i:s", $attachment_post->post_modified_gmt ),
        'src'        => current( $attachment ),
        'title'      => get_the_title( $attachment_id ),
        'alt'        => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
        'position'   => (int) $position,
      );
    }

    if ( empty( $images ) ) {

      $images[] = array(
        'id'         => 0,
        'created_at' => date( "Y-m-d H:i:s", time() ),
        'updated_at' => date( "Y-m-d H:i:s", time() ),
        'src'        => wc_placeholder_img_src(),
        'title'      => __( 'Placeholder', 'woocommerce' ),
        'alt'        => __( 'Placeholder', 'woocommerce' ),
        'position'   => 0,
      );
    }

    return $images;
  }

  protected function get_attribute_options( $product_id, $attribute ) {
    if ( isset( $attribute['is_taxonomy'] ) && $attribute['is_taxonomy'] ) {
      return wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'names' ) );
    } elseif ( isset( $attribute['value'] ) ) {
      return array_map( 'trim', explode( '|', $attribute['value'] ) );
    }

    return array();
  }

  private function get_attributes( $product ) {

    $attributes = array();

    if ( $product->is_type( 'variation' ) ) {

      foreach ( $product->get_variation_attributes() as $attribute_name => $attribute ) {

        $attributes[] = array(
          'name'   => wc_attribute_label( str_replace( 'attribute_', '', $attribute_name ) ),
          'slug'   => str_replace( 'attribute_', '', wc_attribute_taxonomy_slug( $attribute_name ) ),
          'option' => $attribute,
        );
      }
    } else {

      foreach ( $product->get_attributes() as $attribute ) {
        $attributes[] = array(
          'name'      => wc_attribute_label( $attribute['name'] ),
          'slug'      => wc_attribute_taxonomy_slug( $attribute['name'] ),
          'position'  => (int) $attribute['position'],
          'visible'   => (bool) $attribute['is_visible'],
          'variation' => (bool) $attribute['is_variation'],
          'options'   => $this->get_attribute_options( $product->get_id(), $attribute ),
        );
      }
    }

    return $attributes;
  }

  public function get_extended_categories( $object, $field_name, $request )
  {
    $id = $object['id'];

    if ( is_wp_error( $id ) ) {
      return $id;
    }

    $taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
    $post_terms = wp_get_object_terms( $id, array_values( $taxonomies ) );

    $datas = [];

    if( $post_terms && ! is_wp_error( $post_terms ) ){
      foreach ($post_terms as $term ) {
        $datas[] = [
          'term_id'     => $term->term_id,
          'name'        => $term->name,
          'taxonomy'    => $term->taxonomy,
          'description' => $term->description,
          'count'       => $term->count,
          'slug'        => str_replace( get_site_url(), '', get_term_link( $term ) )
        ];
      }
    }else{
      return false;
    }

    return $datas;

  }

}