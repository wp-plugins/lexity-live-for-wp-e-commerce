<?php

/**
 *
 */
class Lexity_Live_for_WPSC_Plugin extends Lexity_Plugin_Base {
  /**
   *
   */
  function initialize_plugin() {
    $this->plugin_title = __( 'Lexity Live Analytics Plugin for WP-eCommerce', 'lexity' );
    $this->plugin_label = __( 'Lexity Live', 'lexity' );
    $this->plugin_version = LEXITY_LIVE_FOR_WPSC_VER;
    $this->set_api_loader( 'Lexity_Live_WPSC_API_Client', 'classes/class-live-wpsc-api-client.php' );

    parent::initialize_plugin();
  }
  function the_settings_live_tab() {
    $c = (object)$this->get_credentials();
    $g = (object)$this->get_grant();
    $admin_embed_url =<<<URL
https://embed.lexity.com/api/account/login?app={$c->app}&platform={$c->platform}&token={$g->token}&public_id={$g->public_id}&email={$c->email}&domain={$c->domain}
URL;
    echo <<<HTML
<iframe id="lexity-admin-embed" src="{$admin_embed_url}" width="1024" height="800" scrolling="auto" frameborder="0"></iframe>
HTML;
  }
  function the_settings_about_tab() {
    $support_domain = preg_replace( '#^http://(.*)$#', '$1', $this->support_url );
    echo <<<HTML
  <p>Lexity Live allows you to track your customers in real time, get actionable insights on your business, and see historical trends for your store traffic.</p>

  <p>Visit Lexity's <a target="_blank" href="{$this->app_gallery_url}">App Gallery</a> to learn more!</p>
HTML;
  }
}
new Lexity_Live_for_WPSC_Plugin( array(
  'plugin_name' => 'lexity_live_for_wp_e_commerce',
  'plugin_slug' => 'lexity-live-for-wp-e-commerce'
));
