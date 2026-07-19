<?php
/**
 * Plugin Name: Pull Quotes
 * Plugin URI: https://aarondcampbell.com/wordpress-plugin/pull-quotes/
 * Description: Pull quotes done right.
 * Version: 2.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Aaron D. Campbell
 * Author URI: https://aarondcampbell.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pull-quotes
 *
 * @package Pull_Quotes
 */

define( 'PULL_QUOTES_PLUGIN_FILE', __FILE__ );
define( 'PULL_QUOTES_VERSION', '2.0.0' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-pull-quotes-renderer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pull-quotes-migrator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pull-quotes.php';

register_activation_hook( __FILE__, array( 'Pull_Quotes', 'activate' ) );
Pull_Quotes::get_instance();
