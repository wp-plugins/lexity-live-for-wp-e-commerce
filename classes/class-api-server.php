<?php
/**
 *
 */
class Lexity_API_Server {

  /**
   * @var Lexity_Plugin_Base
   */
  var $plugin;

  /**
   * @var string
   */
  var $connector_type;

  /**
   * @var Lexity_Cart_Connector
   */
  var $_cart_connector;

  /**
   * @var array
   */
  private $_routes = array();

  /**
   * @param string $connector_type
   * @param bool|object $plugin
   */
  function __construct( $connector_type, $plugin = false ) {
    $this->connector_type = $connector_type;
    $this->plugin = $plugin;
  }

  /**
   *
   */
  function authenticate() {
    if ( empty( $_SERVER['QUERY_STRING'] ) ) {
      $error_message = __( 'The credentials are missing from the url', 'lexity' );
    } else {
      parse_str( $_SERVER['QUERY_STRING'], $query );
      if ( empty( $query['login'] ) || empty( $query['token'] ) ) {
        $error_message = __( 'One or more credentials are missing from url', 'lexity' );
      }
    }
    if ( isset( $error_message ) ) {
      header( 'WWW-Authenticate: Basic realm="Lexity API"' );
      header( 'HTTP/1.0 403 Forbidden' );
      self::http_error( 403, 'Forbidden', $error_message );
    }
    $credentials = $this->plugin->get_form_settings( 'account' );
    return $query['login'] == $credentials['login'] && $query['token'] == $credentials['token'];
  }
  /**
   * @return Lexity_Cart_Connector
   */
  function get_cart_connector() {
    if ( ! isset( $this->_cart_connector ) ) {
      /**
       * @var Lexity_Cart_Connector $this->_cart_connector
       */
      require_once( dirname( __FILE__ ) . '/class-cart-connector.php' );
      $this->_cart_connector = Lexity_Cart_Connector::get_new( $this->connector_type );
    }
    return $this->_cart_connector;
  }

  /**
   *
   */
  function initialize_routes() {
    $cart_connector = $this->get_cart_connector();

    $this->register_route( '',                              array( $this,           'get_index' ) );
    $this->register_route( 'orders.json',                   array( $cart_connector, 'get_orders') );
    $this->register_route( 'orders/count.json',             array( $cart_connector, 'get_orders_count' ) );
    $this->register_route( 'products.json',                 array( $cart_connector, 'get_products') );
    $this->register_route( 'products/count.json',           array( $cart_connector, 'get_products_count' ) );
    $this->register_route( 'collects.json',                 array( $cart_connector, 'get_collects') );
    $this->register_route( 'collects/count.json',           array( $cart_connector, 'get_collects_count' ) );
    $this->register_route( 'custom_collections.json',       array( $cart_connector, 'get_custom_collections') );
    $this->register_route( 'custom_collections/count.json', array( $cart_connector, 'get_custom_collections_count' ) );
    $this->register_route( 'script_tag.json',               array( $this,           'get_script_tag' ), array(
      'POST' => array( $this, 'post_script_tag' ),
    ));
  }

  /**
   *
   */
  function post_script_tag( $post ) {
    if ( ! isset( $post->asset ) ) {
      self::http_error(
        400, 'Bad Request', __( 'The POSTed JSON payload did not contain an "asset" property.', 'lexity' )
      );
    } else {
      $script_tag = $post->asset;
      if ( isset( $script_tag->key ) && 'script' == $script_tag->key && isset( $script_tag->value ) ) {
        $this->plugin->update_script_tag( $script_tag->value );
      } else {
        self::http_error(
          400, 'Bad Request', __(
            'The POSTed JSON payload did not contain valid "key" or "script" subproperties of the "asset" property.',
            'lexity'
          )
        );
      }

    }
  }

  /**
   * @return array
   */
  function get_script_tag() {
    return $this->_cart_connector->package_response( 'script_tag', $this->plugin->get_script_tag( ) );
  }
  /**
   * Allow the plugin to register a route.
   *
   * This approach to routes is very simplistic and only implements what Lexity Live needs.
   * It would need to be rearchitected significantly to support more than these simplistic needs.
   *
   * @param string $route_slug
   * @param callback $provider
   * @param bool|array $args
   */
  function register_route( $route_slug, $provider, $args = array() ) {
    $args = wp_parse_args( $args, array(
      'slug' => $route_slug,
      'GET'  => $provider,
      'POST' => false,
    ));
    $this->_routes[$route_slug] = $args;
  }

  /**
   * @param string $route_slug
   * @return array|bool
   */
  function get_route( $route_slug ) {
    return isset( $this->_routes[$route_slug] ) ? $this->_routes[$route_slug] : false;
  }

  /**
   * @param string $route_slug
   *
   * @return string
   */
  function get_route_url( $route_slug ) {
    return site_url( "/lexity-api/{$route_slug}" );
  }

  /**
   * @param string $javascript
   * @return string
   */
  function make_script_tag( $javascript ) {
    while ( is_array( $javascript ) )
      $javascript = reset( $javascript );
    $javascript = str_replace( '"', '\"', $javascript );
    return <<<JSON
{
  "asset": {
    "key": "script",
    "value": "{$javascript}"
  }
}
JSON;
  }

  /**
   *
   */
  function get_index() {
    $args_html = array();
    foreach( $this->get_cart_connector()->get_default_args() as $arg_name => $arg_value ) {
      $html_id = "{$arg_name}-%route_slug%";
      $args_html[] =<<<HTML
<label for="{$html_id}">{$arg_name}</label> <input id="{$html_id}" type="text" name="{$arg_name}" value="{$arg_value}" />
HTML;
    }
    $args_html = implode( "\n<br/>", $args_html );

    $html = array();
    foreach( $this->_routes as $route_slug => $route ) {
      $url = $this->get_route_url( $route_slug );
      if ( $route['GET'] )
        if ( ! empty( $route_slug ) ) {
          $html_id = "get-route-{$route_slug}";
          $args_replaced_html = str_replace( '%route_slug%', $route_slug, $args_html );
          $html[] = <<<HTML
<li>
  <div id="get-{$route_slug}">
    <label for="{$html_id}">GET</label> <span id="{$html_id}">{$url}</span> <br/>
    <form method="get" action="{$url}">
      {$args_replaced_html} <input type="submit" value="GET">
    </form>
  </div>
</li>
HTML;
        }
      if ( $route['POST'] ) {
        $value = false;
        if ( is_callable( $route['GET'] ) ) {
          $value = call_user_func( $route['GET'], $_GET );
          while ( is_array( $value ) )
            $value = reset( $value );
          if ( is_string( $value ) )
            $value = esc_html( $this->make_script_tag( $value ) );
        }
        $html_id = "post-route-{$route_slug}";
        $html[] = <<<HTML
<li>
  <div id="post-{$route_slug}">
    <label for="{$html_id}">POST</label> <span id="{$html_id}">{$url}</span> <br/>
    <form method="post" action="{$url}">
      <textarea rows="7" cols="100" name="json">{$value}</textarea><br/>
      <input type="submit" value="POST">
    </form>
  </div>
</li>
HTML;
      }
    }
    $html = '<div><ul>' . implode( "\n", $html ) . '</ul></div>';
    $title = "Lexity API for {$_SERVER['HTTP_HOST']}";
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="utf-8" />
  <meta name="robots" content="noindex">
  <title>{$title}</title>
  <style type="text/css">
    li > div > label { font-weight: bold;}
    li { margin: 0 0 0.5em 0;}
    li form { margin-left: 1.5em;}
  </style>
</head>
<body>
  <h1>{$title}</h1>
  {$html}
</body>
</html>
HTML;
    return $html;
  }

  /**
   *
   */
  function serve( $url_path ) {
    $data = array();

    if ( ! $this->authenticate() ) {
      self::http_error(
        403, 'Forbidden', __( 'The credentials did not authenticate.', 'lexity' )
      );
    }

    $this->initialize_routes();
    $route = $this->get_route( $url_path );
    $http_method = $_SERVER['REQUEST_METHOD'];
    if ( ! $route || ! $route[$http_method] ) {
    /**
     * Matched the /lexity-api/ URL but did not match any valid endpoint, therefore give a 404
     */
      self::http_error(
        404, 'API Endpoint Not Found',
        __( 'The URL Endpoint you requested is not valid for the Lexity API.', 'lexity' )
      );
    } else {
      /**
       * Set status header, but it can be overridden inside the connector
       */
      status_header( 200 );
      switch ( $http_method ) {
        case 'GET':
          $input = $_GET;
          break;

        case 'POST':
          /**
           * Capture whatever has been posted, assume a JSON object
           *
           * @var string object|array $post
           *
           * @see http://edwin.baculsoft.com/2011/12/how-to-handle-json-post-request-using-php/
           */
          $input = urldecode( file_get_contents( "php://input" ) );

          parse_str( $input, $json );

          $input = isset( $json['json'] ) ? $json['json'] : $input;

          $json = json_decode( $input );

          if ( empty( $json ) )
            $json = json_decode( stripslashes( $input ) );

          if ( empty( $json ) )
            $json = $input;

          $input = $json;

          break;

        default:
          $input = array();
          break;
      }
      $data = call_user_func( $route[$http_method], $input );
      if ( 'GET' == $http_method ) {
        if ( is_string( $data ) && false !== strpos( $data, '<!DOCTYPE html>' ) ) {
          header( "Content-Type: text/html; charset=UTF-8" );
          echo $data;
        } else {
          header( "Content-Type: application/json; charset=UTF-8" );
          echo json_encode( $data );
        }
      }
      exit;
    }
  }

  /**
   * @param $code
   * @param $description
   * @param $message
   */
  static function http_error( $code, $description, $message ) {
    header( "HTTP/1.1 {$code} {$description}" );
    header( "Content-Type: text/html; charset=UTF-8" );
    echo "<h1>{$code} {$description}</h1>";
    echo $message;
    die(1);
  }
}

