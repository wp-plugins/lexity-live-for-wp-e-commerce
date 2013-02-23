<?php

/**
 *
 */
class Lexity_Plugin_Base extends Sidecar_Plugin_Base {

  var $SCRIPT_TAG = 'lexity_script_tag';

  /**
   *
   */
  function initialize_plugin() {

    $this->css_base = 'lexity';

    $this->add_form(
      'account', array(
        'label' => __( 'Lexity Account Credentials', 'lexity' ),
      )
    );

    if ( is_admin() ) {
      add_action( 'all_admin_notices', array( $this, 'all_admin_notices' ) );
    } else {
      global $wp_version;
      $api_listener = version_compare( $wp_version, '3.5', '<' ) ? 'template_redirect' : 'do_parse_request';
      add_filter( $api_listener, array( $this, 'do_parse_request' ) );
      add_action( 'wp_head', array( $this, 'wp_head' ) );
    }
    if ( $this->is_submit_plugin_options_update() ) {
      /**
       * We only need to redirect to the 'live' tab when we are using
       * in a page request that is a HTTP POST to options.php with action='update'
       */
      add_action( "pre_update_option_{$this->option_name}", array( $this, 'pre_update_plugin_option' ), 11, 2 ); // 11 to come after Sidecar's hook
      add_action( "update_option_{$this->option_name}", array( $this, 'update_plugin_option' ), 10, 2 );
    }
  }

  /**
   * Display an "Authentication Successful" message on the Live tab with redirected on authentication.
   */
  function all_admin_notices() {
    if ( isset( $_GET['authenticated'] ) && 'true' == $_GET['authenticated'] ) {
      /**
       * @var Sidecar_Admin_Page $page
       */
      $page = $this->get_current_admin_page();
      if ( $page && $page->is_current_tab( 'live' ) ) {
        add_settings_error( $this->option_name, 'sidecar-updated', __( 'Authentication successful. Settings saved.', 'sidecar' ), 'updated' );
      }
    }
  }

  /**
   * Returns true if we are running during a submit of a plugin options update.
   *
   * @return bool
   */
  function is_submit_plugin_options_update() {
    global $pagenow;
    return 'options.php' == $pagenow && isset( $_POST['action'] ) && 'update' == $_POST['action'] && isset( $_POST['option_page'] );
  }

  /**
   * Check to see if authenticated and if so redirect to the 'Live' tab.
   * This one is called if the settings have NOT been updated.
   */
  function pre_update_plugin_option( $new_value, $old_value ) {
    $this->_maybe_redirect_to_live_tab( $this->_settings );
    return $new_value;
  }

  /**
   * Check to see if authenticated and if so redirect to the 'Live' tab.
   * This one is called if the settings HAVE been updated.
   */
  function update_plugin_option( $old_value, $new_value ) {
    $this->_maybe_redirect_to_live_tab( $new_value );
    return $old_value;
  }

  /**
   * Tests to see if the $settings passed to either pre_update_plugin_option()
   * or update_plugin_option() has 'authenticated' not empty.
   */
  private function _maybe_redirect_to_live_tab( $settings ) {
    if ( ! empty( $settings['_account']['authenticated'] ) ) {
      wp_redirect( $this->get_admin_page( 'settings' )->get_tab_url( 'live' ) . '&authenticated=true' );
      remove_action( "pre_update_option_{$this->option_name}", array( $this, 'pre_update_plugin_option' ), 11 );
      remove_action( "update_option_{$this->option_name}", array( $this, 'update_plugin_option' ), 10 );
      exit;
    }
  }

  /**
   *
   */
  function initialize_admin() {
    $this->add_admin_page( 'settings', array( 'menu_title' => __( 'Lexity Live for WPEC', 'lexity' ), ) );

    $this->register_url( 'admin_spinner', admin_url( 'images/wpspin_light.gif' ) );

    $this->register_url( 'learn_more',    'http://lexity.com/learn' );
    $this->register_url( 'support',       'http://support.lexity.com' );
    $this->register_url( 'app_gallery',   'http://lexity.com/apps/live' );

    $this->register_image( 'lexity_icon', 'lexity-icon.png' );

    $this->add_meta_link( __( "Visit Developer's Site", 'lexity' ), 'http://newclarity.net' );
    $this->add_meta_link( __( 'Lexity Support', 'lexity' ), 'support' );
    $this->add_meta_link( __( 'Learn about Lexity', 'lexity' ), 'learn_more' );

  }

  /**
   * @param Sidecar_Admin_Page $page
   */
  function initialize_admin_page( $page ) {
    switch ( $page->page_name ) {
      case 'settings':

        $page->icon = $this->lexity_icon_url;

        $page->add_tab(
          'live', __( 'Live', 'lexity' ), array(
            'page_title' => __( 'Your Lexity Live Admin Console', 'lexity' ),
          )
        );
        $page->add_tab(
          'account', __( 'Account', 'lexity' ), array(
            'page_title' => __( 'Link your Account', 'lexity' ),
            'form' => true,
            'requires_api' => true,
          )
        );
        $page->add_tab(
          'about', __( 'About', 'lexity' ), array(
            'page_title' => sprintf( __( 'About %s', 'lexity' ), $this->plugin_title ),
          )
        );
        $page->add_tab(
          'support', __( 'Support', 'lexity' ), array(
            'page_title' => __( 'Get Support', 'lexity' ),
          )
        );

        $page->set_auth_form( 'account' );

        break;
    }
  }

  /**
   * @param Sidecar_Form $form
   */
  function initialize_form( $form ) {
    switch ( $form->form_name ) {
      case 'account':

        $form->add_button( 'save', __( 'Save Changes', 'lexity' ) );
        $form->add_button( 'clear', __( 'Clear info', 'lexity' ), array( 'button_type' => 'secondary' ) );

        $hidden_field = array(
          'type' => 'hidden',
          'required' => true,
        );
        $form->add_field( 'app',      $hidden_field );
        $form->add_field( 'platform', $hidden_field );
        $form->add_field( 'domain',   $hidden_field );
        $form->add_field( 'login',    $hidden_field );
        $form->add_field( 'password', $hidden_field );
        $form->add_field( 'skey',     $hidden_field );

        $form->add_field( 'public_id',$hidden_field );
        $form->add_field( 'token',    $hidden_field );

        $form->add_field(
          'email', array(
            'label'     => __( 'Account Email', 'lexity' ),
            'help'      => __( 'The email address used for your Lexity account', 'lexity' ),
            'validator' => FILTER_SANITIZE_EMAIL,
            'required'  => true,
          )
        );
        $form->add_field(
          'user_password', array(
            'label'    => __( 'Account password', 'lexity' ),
            'type'     => 'password',
            'help'     => __( 'The password used for your Lexity account', 'lexity' ),
            'required' => true,
          )
        );

        break;

    }
  }

  /**
   * Display the support tab.
   */
  function the_settings_support_tab() {
    $support_domain = preg_replace( '#^http://(.*)$#', '$1', $this->support_url );
    echo <<<HTML
<p>If you need help with Lexity's extension or services, or just want to learn more about us, please visit us at <a target="_blank" href="{$this->support_url}">{$support_domain}</a>.</p>
HTML;
  }

  /**
   *
   */
//  function initialize_postback() {
//    //require_once( $this->api_loader );
//  }

  /**
   *
   * @param array $input
   * @param Sidecar_form $form
   *
   * @return array
   */
  function validate_settings( $input, $form ) {
    return $input;
  }

  /**
   * @param array $input
   * @param Sidecar_Form $form
   * @param array $settings
   *
   * @return array
   */
  function encrypt_settings( $input, $form, $settings ) {
    if ( 'account' == $form->form_name )
      $input['user_password'] = base64_encode( $input['user_password'] );
    return $input;
  }

  /**
   * @param array $loaded
   * @param Sidecar_Form $form
   * @param array $settings
   *
   * @return array
   */
  function decrypt_settings( $loaded, $form, $settings ) {
    if ( 'account' == $form->form_name )
      $loaded['user_password'] = base64_decode( $loaded['user_password'] );
    return $loaded;
  }

  /**
   *
   */
  function do_parse_request( $continue ) {
    $api_root_regex = preg_quote( trim( Sidecar::installed_dir(), '/' ) . '/lexity-api' );
    if ( preg_match( "#^{$api_root_regex}/(.*?)$#", $_SERVER['REQUEST_URI'], $matches ) ) {
      $this->initialize();
      $api_server = new Lexity_API_Server( 'wpsc', $this );
      $api_server->serve( empty( $matches[1] ) ? '' : strtok( $matches[1], '?' ) );
      $continue = false;
    }
    return $continue;
  }

  /**
   * Callback for wp_head to add Script Tag to WordPress' footer
   */
  function wp_head() {
    $script_tag = get_option( $this->SCRIPT_TAG );

    if ( $script_tag ) {
      $script_tag = trim( $script_tag );

      if ( defined( 'WP_DEBUG' ) && WP_DEBUG )
        $script_tag = "<!-- For lexity.com -->{$script_tag}";

      echo <<<HTML
{$script_tag}
HTML;
    }
  }

  /**
   * @return string
   */
  function get_script_tag() {
    return get_option( $this->SCRIPT_TAG );
  }

  /**
   * @param string $script_tag
   */
  function update_script_tag( $script_tag ) {
    update_option( $this->SCRIPT_TAG, $script_tag );
  }

  /**
   *
   */
//  function uninstall_plugin() {
//    echo '';
//  }

}
