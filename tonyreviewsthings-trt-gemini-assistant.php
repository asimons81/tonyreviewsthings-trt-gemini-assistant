<?php
/**
 * Plugin Name: TRT Gemini Assistant
 * Plugin URI: https://tonyreviewsthings.com/
 * Description: AI-assisted workflows for reviews, guides, and deals powered by Gemini.
 * Version: 0.1.0
 * Author: Tony Reviews Things
 * License: GPL2+
 * Text Domain: trtai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Plugin version. */
define( 'TRTAI_PLUGIN_VERSION', '0.1.0' );
/** Plugin file path. */
define( 'TRTAI_PLUGIN_FILE', __FILE__ );
/** Plugin directory path. */
define( 'TRTAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
/** Plugin URL. */
define( 'TRTAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the plugin bootstrap class.
 */
function trtai_load_plugin() {
    load_plugin_textdomain( 'trtai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    require_once TRTAI_PLUGIN_DIR . 'includes/class-trtai-plugin.php';
}
add_action( 'plugins_loaded', 'trtai_load_plugin', 0 );

/**
 * Instantiate the plugin after WordPress is loaded.
 */
function trtai_init_plugin() {
    if ( class_exists( 'Trtai_Plugin' ) ) {
        new Trtai_Plugin();
    }
}
add_action( 'plugins_loaded', 'trtai_init_plugin', 20 );
