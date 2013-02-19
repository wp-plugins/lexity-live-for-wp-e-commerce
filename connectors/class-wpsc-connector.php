<?php

/**
 * Include wpsc classes
 */
require_once( WPSC_FILE_PATH . '/wpsc-includes/purchase-log.class.php' );
require_once( WPSC_FILE_PATH . '/wpsc-includes/purchaselogs.class.php' );
require_once( WPSC_FILE_PATH . '/wpsc-includes/country.class.php' );
require_once( WPSC_FILE_PATH . '/wpsc-includes/meta.functions.php' );

/**
 *
 */
class Lexity_WPSC_Cart_Connector extends Lexity_Cart_Connector {

  /**
   * @var array List of country names indexed by ISO code
   */
  private $_countries = array();

  /**
   * Return array of orders matching request parameters
   *
   * @link    http://wiki.shopify.com/Order_%28API%29
   * @param   null|string|array $args Shopify parameters
   * @return  array stdClass objects
   */
  function get_orders( $args = null ) {

    $orders = $this->_query_orders( $this->_capture_args( $args ) );

    $data   = array();
    foreach ( $orders as $id )
      $data[] = $this->_get_order( $id );

    return $this->package_response( 'orders', $data );
  }

  /**
   * Return number of found orders
   *
   * 'nopaging' means get all orders.
   *
   * @return int
   */
  function get_orders_count() {
    return $this->package_response( 'count',
      count( $this->_query_orders( $this->_capture_args( array( 'nopaging' => true ) ) ) )
		);
  }

  /**
   * Return an array of products matching request parameters.
   *
   * @param null $args str|array of Shopify parameters
   * @return array of stdClass objects containing product information
   */
  function get_products( $args = null ) {

    $products = $this->_query_products( $this->_capture_args( $args ) );

    $data = array();
    foreach ( $products as $product_id )
      $data[] = $this->_get_product( $product_id );

    return $this->package_response( 'products', $data );
  }

  /**
   * Return count of products
   *
   * 'nopaging' => get all products
   *
   * @return int
   */
  function get_products_count() {
    return $this->package_response( 'count',
      count( $this->_query_products( $this->_capture_args( array( 'nopaging' => true ) ) ) )
		);
  }

  /**
   * Return array of categories matching requested criteria
   *
   * @link    http://wiki.shopify.com/Custom_Collection_%28API%29
   * @param   null $args str|array Shopify request parameters
   * @return  array
   */
  function get_custom_collections( $args = null ) {

    $categories = $this->_query_custom_collections( $this->_capture_args( $args ) );

    $data = array();
    foreach ( $categories as $category_id )
      $data[] = $this->_get_custom_collection( $category_id );

    return $this->package_response( 'custom_collections', $data );
  }

  /**
   * Return count of shop's custom collections
   *
   * 'nopaging' means get all custom collections.
   *
   * @link    http://wiki.shopify.com/Custom_Collection_%28API%29
   * @return  int
   */
  function get_custom_collections_count() {
    return $this->package_response( 'count',
      count( $this->_query_custom_collections( $this->_capture_args( array( 'nopaging' => true ) ) ) )
		);
  }

  /**
   * Return array of associations between products and custom collections by product_id or collection_id
   *
   * @link http://wiki.shopify.com/Collect_%28API%29
   * @param $args array
   * @return array of stdClass objects
   */
  function get_collects( $args ) {

    $collects = $this->_query_collects( $this->_capture_args( $args ) );

    $data = array();
    foreach ( $collects as $collect ) {
      $data[] = (object)array(
        'collection_id'   => $collect->term_taxonomy_id,
        'product_id'      => $collect->object_id,
        'position'        => $collect->term_order,
        /**
         * Product to Collection relationships are stored in wp_term_relationships table
         * this table doesn't have an id for every relationship. An id is required but
         * it will not be useful for querying by it.
         */
        'id'              => "{$collect->object_id}{$collect->term_taxonomy_id}",
        'created_at'      => null,
        'updated_at'      => null,
        'featured'        => null,
      );
    }
    return $this->package_response( 'collects', $data );
  }

  /**
   * Return count of all collects
   *
   * 'nopaging' means get all collects.
   *
   * @return array
   */
  function get_collects_count() {
    return $this->package_response( 'count',
      count( $this->_query_collects( $this->_capture_args( array( 'nopaging' => true ) ) ) )
	  );
  }

  /**
   * Return where portion of sql query to filter by id, modified or created date
   *
   * Designed to be dual-mode; 1.) an filter initalizer and 2.) a where filter
   *
   * NOTE: Cannot be marked 'private' because it is called via call_user_func()
   *
   * @global       $wpdb
   * @param null|array|string $args_or_where Either array of $args to initialize or the where clause to modify
   * @return void|string
   */
  function _product_range_filter( $args_or_where = null ) {
    static $updated_at_min  = false;
    static $created_at_min  = false;
    static $since_id        = false;

    global $wpdb;

    if ( is_array( $args_or_where ) ) {
      /**
       * Initialize variables needed for the filter
       */
      if ( $args_or_where['created_at_min'] )
        $created_at_min = $this->_mysql_date( $args_or_where['created_at_min'] );
      if ( $args_or_where['updated_at_min'] )
        $updated_at_min = $this->_mysql_date( $args_or_where['updated_at_min'] );
      if ( $args_or_where['since_id'] )
        $since_id = $args_or_where['since_id'];

    } else {
      $where = $args_or_where;

      if ( $since_id )
        $where .= $wpdb->prepare( " AND $wpdb->posts.ID > %s", $since_id );

      if ( $updated_at_min )
        $where .= $wpdb->prepare( " AND $wpdb->posts.post_modified_gmt >= %s", $updated_at_min );

      if ( $created_at_min )
        $where .= $wpdb->prepare( " AND $wpdb->posts.post_date_gmt >= %s", $created_at_min );

      remove_filter( 'posts_where', array( $this, __FUNCTION__ ) );

      return $where;
    }

  }

  /**
   * Return information about the shop
   *
   * @return stdClass
   */
  private function _get_shop(){

    $country = new WPSC_Country( get_option('currency_type') );

    return (object)array( 'currency' => $country->get('code') );
  }

  /**
   * Return array containing requested product
   *
   * @param $id
   * @return stdClass object populated with requested product information
   */
  private function _get_product( $id ) {

    $product  = get_post( $id );
    /**
     * TODO: Return error if no product is found
     */

    if ( ! is_null( $product ) ) {
      $product = (object)array(
        'id'            => $product->ID,
        'handle'        => $product->post_name,
        'title'         => $product->post_title,
        'body_html'     => apply_filters( 'the_content', $product->post_content ),
        'created_at'    => $this->_shopify_mysqldate( $product->post_date_gmt ),
        'published_at'  => $this->_shopify_mysqldate( $product->post_date_gmt ),
        'updated_at'    => $this->_shopify_mysqldate( $product->post_modified_gmt ),
        'tags'          => wp_get_object_terms( $product->ID, 'product_tag', array( 'fields' => 'names' ) ),
        'variants'      => $this->_get_product_variants( $product->ID ),
        'images'        => $this->_get_product_images( $product->ID ),
      );
    }
    return $product;
  }

  /**
   * Return array product images
   *
   * @param $product_id int
   * @return array of stdClass object populated with image information
   */
  private function _get_product_images( $product_id ) {

    $query = new WP_Query( array(
      'post_parent'     => $product_id,
      'post_type'       => 'attachment',
      'posts_per_page'  => 0,
      'post_status'     => 'inherit',
      'post_mime_type'  => 'image/jpeg,image/gif,image/jpg,image/png',
      'order'           => 'ASC',
      'orderby'         => 'menu_order',
      'fields'          => 'ids'
    ));

    $data = array();
    foreach ( $query->posts as $image )
      $data[] = $this->_get_image( $image );

    return $data;
  }

  /**
   * Return array of product variants.
   *
   * @link    http://wiki.shopify.com/Product_Variant_%28API%29
   * @param   $product_id int
   * @return  array
   */
  private function _get_product_variants( $product_id ) {

    /**
     * In WPEC, variants are children of product with status set to inherit.
     */
    $query = new WP_Query( array(
      'post_parent'   => $product_id,
      'post_type'     => 'wpsc-product',
      'posts_per_page'=> 0,
      'post_status'   => 'inherit',
      'fields'        => 'ids'
    ));

    $data = array();
    foreach ( $query->posts as $variant )
      $data[] = $this->_get_variant( $variant );

    return $data;
  }

  /**
   * Return true if post is a product variation otherwise return false.
   *
   * @param $variant_id
   * @return bool
   */
  private function _is_variation( $variant_id ) {
    $post = get_post( $variant_id );
    return 'wpsc-product' == get_post_type( $post ) && 'inherit' == $post->post_status;
  }

  /**
   * Return variant by id
   *
   * @link    http://wiki.shopify.com/Product_Variant_%28API%29
   * @param   int $variant_id
   * @return  stdClass object populated with Shopify variant information
   */
  private function _get_variant( $variant_id ) {

    $variant = get_post( $variant_id );

    if ( ! is_null( $variant ) ) {
      $meta           = get_post_meta( $variant_id, '_wpsc_product_metadata', true );
      $full_price     = get_post_meta( $variant_id, '_wpsc_price', true );
      $special_price  = get_post_meta( $variant_id, '_wpsc_special_price', true );

      /**
      * wpec stores weight in pounds. Shopify expects weight in grams. Let's convert
      */
      $weight = ! empty( $meta['weight'] ) ? (float)$meta['weight'] * 453.59237 : false;

      /**
      * wpsc_calculate_price returns price, but we need compare_to_price
      * Closest that WPEC has is regular price and special price
      */
      $compare_at_price = $full_price > $special_price && $special_price > 0 ? $full_price : null;

      $require_shipping = isset( $meta['no_shipping'] ) && 1 == $meta['no_shipping'] ? 'false' : 'true';

      /**
      * _wpsc_product_metadata option array has a key wpec_taxes_taxable.
      * This array element is set to 'on' when "This product is not taxable." checkbox is activated on Product Edit
      * This is miss leading. I'll follow the UI because that's what the user would expect.
      */
      $taxable = isset( $meta['wpec_taxes_taxable'] ) && 'on' == $meta['wpec_taxes_taxable'] ? 'false' : 'true';

      /**
      * quality_limited is wierd too. It seems to use 0 as true and '' as false
      */
      $inventory_management = isset( $meta['quantity_limited'] ) && 0 == $meta['quantity_limited'] ? 'shopify' : false;

      $inventory_policy = isset( $meta['unpublish_when_none_left'] ) && 1 == $meta['unpublish_when_none_left'] ? 'deny' : 'continue';
      $variations = wp_get_post_terms( $variant->ID, 'wpsc-variation', array('fields'=>'names') );
      $title = array_pop( $variations );

      $variant = (object)array(
        'id'                    => $variant->ID,
        'created_at'            => $this->_shopify_mysqldate( $variant->post_date_gmt ),
        'updated_at'            => $this->_shopify_mysqldate( $variant->post_modified_gmt ),
        'price'                 => wpsc_calculate_price( $variant->ID ),
        'grams'                 => $weight,
        'compare_at_price'      => $compare_at_price,
        'product_id'            => $variant->post_parent,
        'requires_shipping'     => $require_shipping,
        'sku'                   => (string)get_post_meta( $variant->id, '_wpsc_sku', true ),
        'taxable'               => $taxable,
        'inventory_quantity'    => (string)get_post_meta( $variant->ID, '_wpsc_stock', true ),
        'inventory_management'  => $inventory_management,
        'inventory_policy'      => $inventory_policy,
        'title'                 => $title,
      );
    }
    return $variant;
  }

  /**
   * Return object populated with image information with Shopify image attributes.
   *
   * @link    http://wiki.shopify.com/Product_Image_%28API%29
   * @param   $image_id
   * @return  stdClass populated with
   */
  private function _get_image( $image_id ) {
    $image = get_post( $image_id );
    return (object)array(
      'created_at'  => $this->_shopify_mysqldate( $image->post_date_gmt ),
      'updated_at'  => $this->_shopify_mysqldate( $image->post_modified_gmt ),
      'position'    => $image->menu_order,
      'id'          => $image_id,
      'product_id'  => $image->post_parent,
      'src'         => wp_get_attachment_url( $image_id ),
    );
  }

  /**
   * Return object populated with information about specific order
   *
   * @link http://wiki.shopify.com/Order_%28API%29
   * @param $order_id int
   * @return stdClass
   */
  function _get_order( $order_id ) {

    $o                = new stdClass();
    $billing_address  = new stdClass();
    $shipping_address = new stdClass();

    $states     = $this->_get_states();

    $shop       = $this->_get_shop();
    $purchase   = new wpsc_purchaselogs_items( $order_id );
    /**
     * TODO: Return error if no order is found
     */

    $gateway_class = $purchase->extrainfo->gateway;
    if ( class_exists( $gateway_class ) ) {
      $merchant = new $gateway_class( $order_id );
      $gateway  = $merchant->name;
    } else {
      $gateway  = null;
    }

    $billing_address->address1      = $purchase->userinfo['billingaddress']['value'];
    $billing_address->city          = $purchase->userinfo['billingcity']['value'];
    $billing_address->country_code  = $purchase->userinfo['billingcountry']['value'];
    $billing_address->country       = $this->_get_country($billing_address->country_code)->country;
    $billing_address->first_name    = $purchase->userinfo['billingfirstname']['value'];
    $billing_address->last_name     = $purchase->userinfo['billinglastname']['value'];
    $billing_address->name          = sprintf( '%s %s', $billing_address->first_name, $billing_address->last_name );
    $billing_address->phone         = $purchase->userinfo['billingphone']['value'];
    $billing_address->province      = $purchase->shippingstate( $purchase->userinfo['billingstate']['value'] );
    $billing_address->province_code = array_search( $billing_address->province, $states );
    $billing_address->zip           = $purchase->userinfo['billingpostcode']['value'];
    /**
     * Following Shopify fields don't have equivalent in WPEC
     */
    $billing_address->company       = null;
    $billing_address->address2      = null;
    $billing_address->latitude      = null;
    $billing_address->longitude     = null;

    $shipping_address->address1      = $purchase->shippinginfo['shippingaddress']['value'];
    $shipping_address->city          = $purchase->shippinginfo['shippingcity']['value'];
    $shipping_address->country_code  = $purchase->shippinginfo['shippingcountry']['value'];
    $shipping_address->country       = $this->_get_country($shipping_address->country_code)->country;
    $shipping_address->first_name    = $purchase->shippinginfo['shippingfirstname']['value'];
    $shipping_address->last_name     = $purchase->shippinginfo['shippinglastname']['value'];
    $shipping_address->name          = sprintf( '%s %s', $shipping_address->first_name, $shipping_address->last_name );
    $shipping_address->province      = $purchase->shippingstate( $purchase->shippinginfo['shippingstate']['value'] );
    $shipping_address->province_code = array_search( $shipping_address->province, $states );
    $shipping_address->zip           = $purchase->shippinginfo['shippingpostcode']['value'];
    $shipping_address->company       = null;
    $shipping_address->address2      = null;
    $shipping_address->latitude      = null;
    $shipping_address->longitude     = null;
    $shipping_address->phone         = null;

    $o->billing_address           = $billing_address;
    $o->shipping_address          = $shipping_address;

    $o->browser_ip                = null;
    $o->buyer_accepts_marketing   = null;
    $o->cancel_reason             = null;
    $o->cancelled_at              = null;
    $o->cart_token                = $purchase->extrainfo->sessionid;
    $o->closed_at                 = null;
    $o->created_at                = $this->_shopify_timestamp( $purchase->extrainfo->date );
    $o->currency                  = $shop->currency;
    $o->discount_codes            = ( !empty( $purchase->extrainfo->discount_data ) ) ? array( $purchase->extrainfo->discount_data ) : array();
    $o->email                     = $purchase->userinfo['billingemail']['value'];
    $o->financial_status          = $this->_get_financial_status( $purchase->extrainfo->processed );
    $o->fulfilments               = $this->_get_order_fulfilments( $order_id );
    $o->gateway                   = $gateway;
    $o->order_id                  = $order_id;
    $o->landing_site              = null;
    $o->landing_site_ref          = null;
    $o->line_items                = $this->_get_order_line_items( $order_id );
    $o->name                      = $billing_address->name;
    $o->note                      = $purchase->extrainfo->notes;
    $o->note_attributes           = null;
    $o->order_number              = $order_id;
    $o->payment_details           = null; // wpec doesn't capture cc information, its sent directly to merchant
    $o->referring_site            = null;
    $o->risk_details              = null;
    $o->shipping_lines            = $this->_get_order_shipping_lines( $order_id );
    $o->tax_lines                 = $this->_get_order_tax_lines( $order_id );
    $o->token                     = $purchase->extrainfo->sessionid;
    $o->update_at                 = null;
    $o->total_line_items_price    = 0;
    $o->total_weight              = 0;
    foreach ( $o->line_items as $item ) {
      $o->total_line_items_price  += (float) $item->price * $item->quantity;
      $o->total_weight            += (float) $item->grams * $item->quantity;
    }
    $o->total_line_items_price    = (string)$o->total_line_items_price;
    $o->total_weight              = (string)$o->total_weight;
    $o->total_tax                 = (string)$purchase->extrainfo->wpec_taxes_total;
    $o->total_price               = (string)$purchase->extrainfo->totalprice;
    $o->subtotal_price            = (string)( $purchase->extrainfo->totalprice - $o->total_tax );

    return $o;
  }

  /**
   * Return array containing 1 fulfillment for this order.
   *
   * @param $order_id int
   * @return array
   */
  private function _get_order_fulfilments( $order_id ) {

    $order = new WPSC_Purchase_Log( $order_id );
    /**
     * TODO: Return error if no order is found
     */

    /**
     * WPEC does not allow to split an order into multiple fulfillment items.
     * Return value can only contain one item.
     */
    return array( (object)array(
      'id'                => "{$order_id}-1",   // 1 = fullfillment number
      'order_id'          => $order_id,
      'tracking_company'  => $order->get( 'shipping_method' ),
      'tracking_number'   => $order->get( 'track_id' ),
      'status'            => null,
      'created_at'        => null,
      'updated_at'        => null,
      'receipt'           => null,
      'line_items'        => $this->_get_order_line_items( $order_id ),
    ));
  }

  /**
   * Return shipping lines for an order
   *
   * @param $order_id int
   * @return array
   */
  private function _get_order_shipping_lines( $order_id ) {

    $order = new WPSC_Purchase_Log( $order_id );
    /**
     * TODO: Return error if no order is found
     */
    /**
     * WPEC doesn't allow to track multiple fulfilments within an order
     * This can only return 1 item.
     */
    return array( (object)array(
      'code'  => $order->get( 'shipping_method' ),
      'price' => $order->get( 'base_shipping' ),
    ));
  }

  /**
   * Return array of line items for an order
   *
   * @param $order_id int
   * @return array
   */
  private function _get_order_line_items( $order_id ) {

    $order  = new WPSC_Purchase_Log( $order_id );
    /**
     * TODO: Return error if no order is found
     */

    $tracking_number = $order->get( 'track_id' );
    $purchased_items = $order->get_cart_contents();

    $data = array();
    foreach ( $purchased_items as $index => $item ) {

      $is_variation = $this->_is_variation( $item->prodid );
      $product = $is_variation? $this->_get_variant( $item->prodid ): $this->_get_product( $item->prodid );

      $data[] = (object)array(
        /**
         * I'm not sure if this is correct - I couldn't find documentation about fulfillment_service attribute
         */
        'fulfillment_service' => 'manual',
        /**
         * I'm guessing that if item has a tracking number therefore the order has been fulfilled.
         */
        'fulfillment_status'  => ! empty( $tracking_number ) ? 'fulfilled' : null,
        'grams'               => $this->_if_missing( $product, 'grams' ),
        'id'                  => "{$order_id}-1-{$index}",
        'price'               => $item->price,
        'quantity'            => $item->quantity,
        'requires_shipping'   => 0 == (int) $item->no_shipping,
        'sku'                 => $this->_if_missing( $product, 'sku' ),
        'title'               => $this->_if_missing( $product, 'title' ),
        /**
         * if product is a variation then product_id should be set to id of parent product
         * otherwise set it to id of the product
         */
        'product_id'          => $is_variation ? $this->_if_missing( $product, 'product_id' ) : $this->_if_missing( $product, 'id' ),
        'variant_id'          => $is_variation ? $this->_if_missing( $product, 'id' ) : null,
        'variant_title'       => $is_variation ? $this->_if_missing( $product, 'title' ) : null,
        'name'                => $item->name,
      );
    }

    return $data;
  }

  /**
   * Return array of tax lines for an order
   *
   * @param int $order_id
   * @return array
   */
  private function _get_order_tax_lines( $order_id ) {

    $order = new WPSC_Purchase_Log( $order_id );
    /**
     * TODO: Return error if no order is found
     */
    /**
     * WPEC doesn't allow to track multiple taxes for an order
     * This can only return 1 item.
     */
    return array( (object)array(
      'price' => $order->get( 'wpec_taxes_total' ),
      'rate'  => $order->get( 'wpec_taxes_rate' ),
      'title' => null,
    ));
  }

  /**
   * Return custom collection object by custom collection id.
   *
   * @link    http://wiki.shopify.com/Custom_Collection_%28API%29
   * @param   int $product_category_id
   * @return  stdClass
   */
  private function _get_custom_collection( $product_category_id ) {

    /**
     * Shopify Custom Collections are represented in WordPress as terms of wpsc_product_category custom taxonomy
     */
    $category = get_term( $product_category_id, 'wpsc_product_category' );
    /**
     * TODO: Return error if category is not found.
     */

    $image = wpsc_get_categorymeta( $product_category_id, 'image' );

    return (object)array(
      'body_html'         => apply_filters( 'the_content', $category->description ),
      'handle'            => $category->slug,
      'id'                => $product_category_id,
      'published_at'      => null,
      'sort_order'        => 'manual',
      'template_suffix'   => null,
      'title'             => $category->name,
      'updated_at'        => null,
      'image'             => empty( $image ) ? null : (object)array(
        'src'             => WPSC_CATEGORY_URL . $image,
        'created_at'      => null,
      ),
    );
  }

  /**
   * Find products matching request arguments and return an array of their ids.
   *
   * @param $args array of WP_Query compatible query arguments
   * @return array
   */
  private function _query_products( $args ) {

    /**
     * Initialize variables needed for the filter
     */
    $this->_product_range_filter( $args );

    $callback = array( $this, '_product_range_filter' );
    add_filter( 'posts_where', $callback );
    $query = new WP_Query( array(
      'post_type'     => 'wpsc-product',
      'posts_per_page'=> $args['limit'],
      'nopaging'      => $args['nopaging'],
      'paged'         => $args['paged'],
      'orderby'       => 'ID',
      'order'         => 'ASC',
      'post_status'   => 'publish',
      'post_parent'   => 0,
      'fields'        => 'ids',
    ));

    return $query->posts;
  }

  /**
   * Find orders matching request arguments and return an array of their ids
   *
   * @param $args array of WP_Query friendly arguments
   * @return array of order ids
   */
  private function _query_orders( $args ) {

    global $wpdb;

    $since_id       = false;
    $created_at_min = false;

    if ( $args['since_id'] )
      $since_id = $wpdb->prepare( 'AND id > %s', $args['since_id'] );

    if ( $args['created_at_min'] )
      $created_at_min = $wpdb->prepare( 'AND date > %s', strtotime( $args['created_at_min'] ) );

    $offset = (int)$args['offset'];
    $limit  = (int)$args['limit'];

    $paging = $args['nopaging'] ? false : $wpdb->prepare("LIMIT %d, %d", $offset, $limit );

    $sql = 'SELECT id FROM ' . WPSC_TABLE_PURCHASE_LOGS . " WHERE 1=1 {$since_id} {$created_at_min} ORDER BY id ASC {$paging}";

    $results = $wpdb->get_results( $sql );

    $order_ids = array();
    foreach ( $results as $order )
      $order_ids[] = $order->id;

    return $order_ids;
  }

  /**
   * Find custom collections matching request arguments and return array of their ids
   *
   * @param $args array
   * @return array of category ids
   */
  private function _query_custom_collections( $args ) {

    global $wpdb;

    $since_id = '';

    if ( $args['since_id'] )
      $since_id = $wpdb->prepare( 'AND term_id > %s', $args['since_id'] );

    $offset = (int)$args['offset'];
    $limit  = (int)$args['limit'];

    if ( $args['nopaging'] )
      $paging = '';
    else
      $paging = "LIMIT $offset, $limit";

    $sql = <<<SQL
SELECT
  {$wpdb->terms}.term_id
FROM
  {$wpdb->term_taxonomy}
LEFT JOIN
  {$wpdb->terms} ON {$wpdb->term_taxonomy}.term_id={$wpdb->terms}.term_id
WHERE
  {$wpdb->term_taxonomy}.taxonomy='wpsc_product_category'
  {$since_id}
ORDER BY
  {$wpdb->terms}.term_id ASC
  {$paging}
SQL;

    $results = $wpdb->get_results( $sql );

    $term_ids = array();
    foreach ( $results as $result )
      $term_ids[] = $result->term_id;

    return $term_ids;
  }

  /**
   * Find collects by product_id or collection_id
   *
   * @param $args array
   * @return array of arrays
   */
  private function _query_collects( $args ) {

    global $wpdb;

    if ( $args['product_id'] )
      $product_id = $wpdb->prepare( " AND {$wpdb->term_relationships}.object_id=%s", $args['product_id']  );
    else
      $product_id = '';

    if ( $args['collection_id'] )
      $collection_id = $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id=%s", $args['collection_id'] );
    else
      $collection_id = '';

    $offset = (int)$args['offset'];
    $limit  = (int)$args['limit'];

    if ( $args['nopaging'] )
      $paging = '';
    else
      $paging = "LIMIT $offset, $limit";

    $sql = <<<SQL
SELECT
  *
FROM
  {$wpdb->term_relationships}
LEFT JOIN
  {$wpdb->term_taxonomy} ON {$wpdb->term_taxonomy}.term_id={$wpdb->term_relationships}.term_taxonomy_id
WHERE
  {$wpdb->term_taxonomy}.taxonomy='wpsc_product_category'
  {$product_id}
  {$collection_id}
  {$paging}
SQL;

    $collects_arrays = $wpdb->get_results( $sql );

    return $collects_arrays;
  }

  /**
   * Return an array of countries with an ISO country code as key and country name as value
   *
   * @return array
   */
  private function _get_countries() {

    global $wpdb;

    if ( 0 == count( $this->_countries ) ) {

      $results = $wpdb->get_results( "SELECT * FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `visible`= '1' ORDER BY `country` ASC", OBJECT );

      $countries = array();
      foreach ( $results as $result )
        $countries[ $result->isocode ] = $result;

      $this->_countries = $countries;

    }
    return $this->_countries;
  }

  /**
   * Return country for a specific country code
   *
   * @param string $iso_code
   * @return stdClass
   */
  private function _get_country( $iso_code ) {
    $countries  = $this->_get_countries();
    if ( isset( $countries[$iso_code] ) ) {
      $country = $countries[$iso_code];
    } else {
      $properties = 'id|country|isocode|symbol|symbol_html|code|has_regions|tax|continent|visible';
      $country = (object)array_fill_keys( explode( '|', $properties ), null );
    }
    return $country;
  }

  /**
   * Return an array of states with code as key and country name as value
   *
   * @return array
   */
  private function _get_states() {

    global $wpdb;
    $states = array();
    $results = $wpdb->get_results( 'SELECT code, name FROM ' . WPSC_TABLE_REGION_TAX, ARRAY_A );

    foreach ( $results as $result ) {
      $states[$result['code']] = $result['name'];
    }

    return $states;

  }

  /**
   * Return Financial Status: authorized, paid, pending, refunded or voided
   *
   * @param $processed_code int
   * @return null|string
   */
  private function _get_financial_status( $processed_code ){

    $status = null;
    switch( $processed_code ) :
      case WPSC_Purchase_Log::ORDER_RECEIVED :
        $status = 'authorized';
        break;
      case WPSC_Purchase_Log::REFUND_PENDING :
        $status = 'pending';
        break;
      case WPSC_Purchase_Log::REFUNDED :
        $status = 'refunded';
        break;
      case WPSC_Purchase_Log::ACCEPTED_PAYMENT :
        $status = 'paid';
        break;
    endswitch;

    return $status;
  }

  /**
   * Return value of an object's property, or null if it is not found.
   * @param $object
   * @param $property
   *
   * @return string
   */
  private function _if_missing( $object, $property ) {
    return is_object( $object ) && ! empty( $object->$property ) ? $object->$property : null;
  }
}

return new Lexity_WPSC_Cart_Connector();
