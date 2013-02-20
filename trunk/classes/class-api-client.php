<?php
if ( ! class_exists( 'Lexity_API_Client' ) ) {
  /**
   *
   */
  class Lexity_API_Client extends RESTian_Client {
    /**
     * @var string Slug identifying the Lexity App, i.e. 'live', etc.
     */
    var $lexity_app;

    /**
     * @var string Slug identifying the shopping cart, i.e. 'wpecommerce', etc.
     */
    var $cart_platform;

    /**
     *
     */
    function initialize() {

      $this->api_version = '1.0.0';

      $this->base_url = 'https://lexity.com/api/account';
      $this->auth_type = 'lexity_api';

      RESTian::register_auth_provider( 'lexity_api', 'Lexity_API_Auth_Provider', dirname( __FILE__ ) . '/class-auth-provider.php' );

      $this->register_var( 'app', 	    'usage=query|type=string' );
      $this->register_var( 'platform',  'usage=query|type=string' );
      $this->register_var( 'login', 		'usage=query|type=string' );
      $this->register_var( 'password', 	'usage=query|type=string' );
      $this->register_var( 'skey',      'usage=query|type=string' );

      $this->register_var( 'public_id', 'usage=query|type=string' );
      $this->register_var( 'token',     'usage=query|type=string' );

      $this->register_var_set( 'login_vars', 'public_id,token' );
      $this->register_var_set( 'create_account_vars', 'app,platform,login,password,skey' );

      $this->register_action( 'login',          'path=/login|var_set=login_vars' );
      $this->register_action( 'create_account', 'path=/create|var_set=create_account_vars'  );

//    $this->register_action( 'authenticate', 	'path=/create|var_set=create_account_vars'  );

    }

    /**
     * Create an account.
     *
     * @param array $credentials
     *
     * @return object|RESTian_Response
     */
    function create_account( $credentials ) {
      return $this->invoke_action( 'create_account', $credentials, array( 'credentials' => $credentials ) );
    }

    /**
     * Login.
     *
     * @param array $grant
     *
     * @return object|RESTian_Response
     */
    function login( $grant ) {
      return $this->invoke_action( 'login', $grant, array( 'grant' => $grant )  );
    }

    /**
     * Subclass so you can load the required class.
     *
     * @return Lexity_API_Auth_Provider
     */
    function get_auth_provider() {
      require_once( dirname( __FILE__ ) . '/class-auth-provider.php' );
      return parent::get_auth_provider();
    }

    /**
     * For Lexity's API we need to create an account to authenticate the first time after which we use login.
     *
     * @return bool|RESTian_Service
     */
    function get_auth_service() {
      $this->initialize_client();
      if ( isset( $this->_grant['public_id'] ) ) {
        $this->_auth_service = $this->get_service( 'login' );
      } else {
        $this->_auth_service = $this->get_service( 'create_account' );
      }
      return $this->_auth_service;
    }

    /**
     * @param RESTian_Request $request
     */
    function prepare_request( $request ) {
      switch ( $request->service->service_name ) {
        case 'create_account':
          $request->vars = $request->get_credentials();
          break;
        case 'login':
          $request->vars = $request->get_grant();
          break;
      }
    }
  }
}
