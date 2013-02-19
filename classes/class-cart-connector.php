<?php

/**
 *
 */
class Lexity_Cart_Connector {

  /**
   * @var string
   */
  var $connector_type;

  /**
   * @var string
   */
  var $iso8601_format = 'Y-m-d\TH:i:s';

  /**
   * @var string
   */
  var $mysql_format = 'Y-m-d H:i:s';

  /**
   * @param string $connector_type
   * @return Lexity_Cart_Connector
   */
  static function get_new( $connector_type ) {
    $connector = false;
    $connector_file = realpath( dirname( __FILE__ ) . "/../connectors/class-{$connector_type}-connector.php" );
    if ( !file_exists( $connector_file ) ) {
      trigger_error( sprintf( __( '%s not found (The file to define the Lexity %s connector does not exist.)', 'lexity' ), $connector_file, $connector_type ) );
    } else {
      $connector = require_once($connector_file);
      $connector->connector_type = $connector_type;
    }
    return $connector;
  }

  /**
   * Transform Shopify parameters into WordPress WP_Query compatible arguments.
   *
   * @return array with WordPress WP_Query friendly arguments
   */
  function get_default_args() {
    return array(
      'limit'           => 50,
      'page'            => 1,
      'since_id'        => false,
      'created_at_min'  => false,
      'updated_at_min'  => false,
      'nopaging'        => false,
      'product_id'      => false,
      'collection_id'   => false,
    );
  }

  function package_response( $property_name, $data ) {
    return array( $property_name => $data );
  }
  /**
   * Transform Shopify parameters into WordPress WP_Query compatible arguments.
   *
   * @param null|string|array $args Shopify request parameters
   * @return array with WordPress WP_Query friendly arguments
   */
  protected function _capture_args( $args = null ) {
    $args = wp_parse_args( $args, $this->get_default_args() );

    /**
     * wp_query uses paged ( NOT page ) to indicate what page to return
     */
    $args['paged'] = $args['page'];
    unset( $args['page'] );

    if ( 1 < $args['paged'] )
      $args['offset'] = ( $args['paged'] - 1 ) * $args['limit'];
    else
      $args['offset'] = 0;

    if ( ! isset( $args['nopaging'] ) ) {
      $args['nopaging'] = false;
    }

    return $args;
  }

  /**
   * @param $method
   */
  private function _no_method( $method ) {
    trigger_error( sprintf( __( 'Your Lexity %s connector needs to provide a %s() method.', 'lexity' ), $this->connector_type, $method ) );
  }

  protected function _shopify_timestamp( $timestamp ) {
    return date( $this->iso8601_format, $timestamp );
  }

  /**
   * Convert MySQL data to Shopify date format
   *
   * @param $mysql_date
   * @return string of date in YYYY-mm-ddThh:mm:ss
   */
  protected function _shopify_mysqldate( $mysql_date ) {
    return date( $this->iso8601_format, strtotime( $mysql_date ) );
  }

  /**
   * Convert date in ISO8601 format to MySQL
   *
   * @param $iso8601_date
   * @return string of date in YYYY-mm-dd hh:mm:ss
   */
  protected function _mysql_date( $iso8601_date ) {
    return date( $this->mysql_format, strtotime( $iso8601_date ) );
  }

  /**
   * @return array
   */
  function get_orders() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_orders_count() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_products() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_products_count() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_collects() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_collects_count() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_custom_collections() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

  /**
   * @return array
   */
  function get_custom_collections_count() {
    $this->_no_method( __FUNCTION__ );
    return array();
  }

}
