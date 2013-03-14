<?php
/*
 * This code is run after Imperative validates that the required libraries are available and loaded.
 */
define( 'LEXITY_LIVE_FOR_WPSC_DIR', dirname( __FILE__ ) );
define( 'LEXITY_LIVE_FOR_WPSC_VER', '1.0.9' );
define( 'LEXITY_LIVE_FOR_WPSC_MIN_PHP', '5.2.4' );
define( 'LEXITY_LIVE_FOR_WPSC_MIN_WP', '3.4' );

require( dirname( __FILE__ ) . '/classes/class-api-server.php' );
require( dirname( __FILE__ ) . '/classes/class-plugin-base.php' );
require( dirname( __FILE__ ) . '/apps/class-live-plugin.php' );

