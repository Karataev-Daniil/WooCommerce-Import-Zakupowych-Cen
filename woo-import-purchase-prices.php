<?php
/**
 * Plugin Name: WooCommerce Import Zakupowych Cen
 * Description: Plugin for importing purchase prices by SKU and recalculating prices.
 * Version: 1.0
 * Author: Daniil
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WIPC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WIPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WIPC_PLUGIN_VERSION', '1.0' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

require_once WIPC_PLUGIN_PATH . 'includes/class-import-handler.php';
require_once WIPC_PLUGIN_PATH . 'includes/class-file-parser.php';
require_once WIPC_PLUGIN_PATH . 'includes/helpers.php';
require_once WIPC_PLUGIN_PATH . 'admin/class-admin-page.php';

register_activation_hook( __FILE__, function() {
    // Create folder for imports
    $upload_dir = wp_upload_dir();
    $import_dir = $upload_dir['basedir'] . '/tmp-imports/';

    if ( ! file_exists( $import_dir ) ) {
        wp_mkdir_p( $import_dir );
    }
});

add_action( 'plugins_loaded', function() {
    if ( is_admin() ) {
        new WIPC\Admin_Page();
    }
});