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

// Include required files
require_once AEDC_IMPORTER_PLUGIN_DIR . 'includes/class-aedc-importer.php';
require_once AEDC_IMPORTER_PLUGIN_DIR . 'includes/class-aedc-importer-admin.php';

/**
 * Initialize the plugin
 */
function run_aedc_importer() {
    $plugin = new AEDC_Importer();
    $plugin->run();
}

// Start the plugin
run_aedc_importer(); 