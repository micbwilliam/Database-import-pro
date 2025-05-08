<?php
/**
 * Plugin Name: AEDC - Importer
 * Description: A multi-step wizard for importing CSV data into WordPress database.
 * Version: 1.0.0
 * Author: AEDC
 * Text Domain: aedc-importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AEDC_IMPORTER_VERSION', '1.0.0');
define('AEDC_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEDC_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Start session if not already started
function aedc_importer_start_session() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'aedc_importer_start_session');

// Include required files
require_once AEDC_IMPORTER_PLUGIN_DIR . 'includes/class-aedc-importer.php';
require_once AEDC_IMPORTER_PLUGIN_DIR . 'includes/class-aedc-importer-admin.php';
require_once AEDC_IMPORTER_PLUGIN_DIR . 'includes/class-aedc-importer-uploader.php';

/**
 * Initialize AJAX handlers
 */
function aedc_importer_init_ajax() {
    $uploader = new AEDC_Importer_Uploader();
    
    // Register AJAX actions
    add_action('wp_ajax_aedc_upload_file', array($uploader, 'handle_upload'));
    add_action('wp_ajax_aedc_get_headers', array($uploader, 'get_file_headers'));
}
add_action('init', 'aedc_importer_init_ajax');

/**
 * Add admin scripts
 */
function aedc_importer_admin_scripts($hook) {
    if (strpos($hook, 'aedc-importer') !== false) {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'aedcImporter', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aedc_importer_nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'aedc_importer_admin_scripts');

/**
 * Initialize the plugin
 */
function run_aedc_importer() {
    $plugin = new AEDC_Importer();
    $plugin->run();
}

// Start the plugin
run_aedc_importer();

// Clean up on deactivation
register_deactivation_hook(__FILE__, 'aedc_importer_cleanup');

function aedc_importer_cleanup() {
    // Remove temporary upload directory
    $upload_dir = wp_upload_dir();
    $temp_dir = trailingslashit($upload_dir['basedir']) . 'aedc-importer';
    if (is_dir($temp_dir)) {
        array_map('unlink', glob("$temp_dir/*.*"));
        rmdir($temp_dir);
    }
    
    // Clear session
    if (session_id()) {
        session_destroy();
    }
} 