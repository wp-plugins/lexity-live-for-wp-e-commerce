<?php
/**
 * Currently not used, only here for documentation of methods expected for cart connectors.
 */
interface Lexity_Cart_Connector_Interface {
  function get_orders( $args = null );
  function get_orders_count();
  function get_products( $args = null );
  function get_products_count();
  function get_custom_collections( $args = null );
  function get_custom_collections_count();
  function get_collects( $args = null );
  function get_collects_count();
}
