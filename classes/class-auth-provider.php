<?php

/**
 *
 */
class Lexity_API_Auth_Provider extends RESTian_Auth_Provider_Base {
  /**
   * @return array
   */
  function get_new_credentials() {
    return array(
      'app'           => '',
      'platform'      => '',
      'domain'        => '',
      'login'         => '',
      'password'      => '',
      'skey'          => '',
      'email'         => '',
      'user_password' => '',
    );
  }
  /**
   * @return array
   */
  function get_new_grant() {
    return array(
      'public_id' => '',
      'token'     => '',
    );
  }

  /**
   * On activation generate all the credentials that Lexity wants
   *
   * @param array $credentials
   * @return array
   */
  function prepare_credentials( $credentials ) {
    foreach( explode( '|', 'app|platform|domain|skey|login|password' ) as $credentials_key ) {
      if ( empty( $credentials[$credentials_key] ) ) {
        switch ( $credentials_key ) {
          case 'app':
            $credentials['app'] = $this->api->lexity_app;
            break;

          case 'platform':
            $credentials['platform'] = $this->api->cart_platform;
            break;

          case 'domain':
            /**
             * 'domain' is a misnomer, Lexity really wants root URL for the site
             */
            $credentials['domain'] = site_url();
            break;

          case 'skey':
            /**
             * Grabbing 16 characters value for for Lexity skey.
             * Picked 7 as starting point arbitrarily.
             */
            $credentials['skey'] = wp_generate_password( 16 );
            break;

          case 'login':
            /**
             * Setting login as the domain name plus app name then 6 random chars tacked to the end to make unguessable.
             * Choosed the number of 6 characters arbitrarily.
             */
            $root_sans_protocol = preg_replace( '#^https?://(.*)$#', '$1', $credentials['domain'] );
            $credentials['login'] = "{$root_sans_protocol}_{$credentials['app']}_" . wp_generate_password( 6 );
            break;

          case 'password':
            /**
             * Picked 32 characters which should be long enough for most purposes.
             */
            $credentials['password'] = wp_generate_password( 32 );
            break;

        }
      }
    }
    return $credentials;
  }

  /**
   * On activation generate all the grant that Lexity wants
   *
   * @param array $grant
   * @param array $credentials
   * @return array
   */
  function prepare_grant( $grant, $credentials ) {
    if ( empty( $grant['token'] ) ) {
      $grant['token'] = md5( $credentials['skey'] . $credentials['password'] );
    }
    return $grant;
  }

  /**
   * Determine if provided credentials represent a viable set of credentials
   *
   * Default behavior ensures that all credential elements exist, i.e. if username and password are required
   * this code ensures both username and password have a value. Subclasses can add or relax requirements using
   * their own algorithms as required.
   *
   * @param array $credentials
   * @return bool
   */
  function is_credentials( $credentials ) {
    $is_credentials = false;
    if ( empty( $credentials['email'] ) || empty( $credentials['user_password'] ) ) {
      $this->message = 'You must enter both an email address and a password';
    } else if ( empty( $credentials['email'] ) ) {
      $this->message = 'You must enter an email address';
    } else if ( empty( $credentials['user_password'] ) ) {
      $this->message = 'You must enter a password';
    } else {
      $is_credentials = true;
    }
    return $is_credentials;
  }

  /**
   * Test to see if the request has prerequisites required to authenticate, i.e. credentials or grant.
   *
   * Defaults to making sure that the request has valid credentials; subclasses can modify as required.
   *
   * @param array $credentials
   * @return bool
   */
  function has_prerequisites( $credentials ) {
    $has_prerequisites = false;
    switch ( $this->api->request->service->service_name ) {
      case 'create_account':
        /**
         * Let's assume it does since the user doesn't control any of this
         */
        $has_prerequisites = true;
        break;

      default:
        $has_prerequisites = parent::has_prerequisites( $credentials );
        break;

    }
    return $has_prerequisites;
  }

  /**
   * @param array $grant
   * @return bool
   */
  function is_grant( $grant ) {
    return parent::is_grant( $grant );
  }

  /**
   * @param RESTian_Request $request
   */
  function prepare_request( $request ) {
    $auth_settings = $request->get_credentials();
    $request->set_credentials( $this->extract_credentials( $auth_settings ) );
    $request->set_grant( $this->extract_grant( $auth_settings ) );
  }

  /**
   * Takes the response and capture the grant in the format $this->is_grant() will validate
   *
   * @param RESTian_Response $response
   */
  function capture_grant( $response ) {
    /**
     * This next line effectively just retrieves the 'token' which is just md5( skey + password ).
     */
    $response->grant = $response->request->get_grant();
    /**
     * Then for the pièce de résistance, if we get a public_id we are golden. If not, auth failed.
     */
    $response->grant['public_id'] = isset( $response->data->public_id ) ? $response->data->public_id : false;
  }

  /**
   *
   * $this->context should contain a RESTian_Request
   *
   * @see: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#2xx_Success
   *
   * @param RESTian_Response $response
   * @return bool
   */
  function authenticated( $response ) {
    $authenticated = preg_match( '#^(200|204)$#', $response->status_code );
    if ( $authenticated && isset( $response->data->error ) ) {
      $this->message = $response->data->error;
      $authenticated = false;
    }
    return $authenticated;
  }

}
