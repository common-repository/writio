<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://writio.com
 * @since             1.0.0
 * @package           Writio
 *
 * @wordpress-plugin
 * Plugin Name:       Writio
 * Plugin URI:        https://writio.com/
 * Description:       Writio is a GPT-based writer that creates and manages content. This plugin integrates with your Writio account and allows Writio to publish content to your WordPress site.
 * Version:           1.3.2
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       writio
 * Domain Path:       /languages
 */

 // If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WRITIO__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRITIO_VERSION', '1.3.2' );

require_once WRITIO__PLUGIN_DIR . 'includes/rest-hooks.php';
require_once WRITIO__PLUGIN_DIR . 'includes/data-listeners.php';
require_once WRITIO__PLUGIN_DIR . 'includes/data-crons.php';
require_once WRITIO__PLUGIN_DIR . 'admin/writio-deactivate.php';
require_once WRITIO__PLUGIN_DIR . 'admin/writio-activate.php';
require_once WRITIO__PLUGIN_DIR . 'admin/writio-admin.php';
require_once WRITIO__PLUGIN_DIR . 'admin/writio-uninstall.php';

add_action( 'rest_api_init', 'writio_api_init' );
add_action( 'init', 'article_data_listener' );
add_action( 'admin_init', 'writio_redirect' );
add_action( 'admin_init', 'writio_settings_init' );
add_action( 'wp', 'schedule_pull_unpublished_articles' );
add_action( 'writio_pull_unpublished_articles', 'pull_unpublished_articles' );

function writio_add_settings_link($links) {
  $settings_link = '<a href="options-general.php?page=writio">Settings</a>';
  array_push($links, $settings_link);
  return $links;
}
add_filter( 'plugin_action_links_writio/writio-admin.php', 'writio_add_settings_link' );

register_activation_hook( __FILE__, 'writio_activate' );
register_activation_hook( __FILE__, 'create_new_author_user' );
register_deactivation_hook( __FILE__, 'writio_deactivate' );
register_uninstall_hook( __FILE__, 'writio_uninstall' );

?>