<?php
/*
 * Plugin Name: Lexity Live for WP e-Commerce
 * Plugin URI: http://lexity.com/apps/live
 * Description: Lexity Live for WP e-Commerce enables store owners to monitor their customer activity in real-time including graphs of current store visitors, products viewed and products in shopping cart; graphs of traffic and revenue over the past 12 months; charts of traffic and revenue split out by channel (e.g. Google, Facebook, Bing, etc.) and marketing insights to improve overall revenue and traffic (e.g. top keywords, top referring sites).
 * Version: 1.0
 * Author: Lexity
 * Author URI: http://www.lexity.com/
 * Text Domain: lexity
 * License: GPLv2
 *
 * Copyright 2013 Palaran, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require( dirname( __FILE__ ) . '/libraries/imperative/imperative.php' );

require_library( 'restian', '0.3.1', __FILE__, 'libraries/restian/restian.php' );
require_library( 'sidecar', '0.4.10', __FILE__, 'libraries/sidecar/sidecar.php' );

register_plugin_loader( __FILE__ );
