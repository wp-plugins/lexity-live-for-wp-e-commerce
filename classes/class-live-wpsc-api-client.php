<?php
if ( ! class_exists( 'Lexity_Live_WPSC_API_Client' ) ) {
  require( dirname( __FILE__ ) . '/class-api-client.php' );
  /**
   *
   */
  class Lexity_Live_WPSC_API_Client extends Lexity_API_Client {

    /**
     *
     */
    function initialize() {
      $this->lexity_app = 'live';
      $this->cart_platform = 'wpecommerce';
      parent::initialize();
    }
  }
}
